<?php

/**
 * WebhookService
 * 
 * Servicio para gestionar y enviar webhooks
 * - Registro de webhooks por tenant
 * - Disparo de eventos
 * - Retry con backoff exponencial
 * - Firma HMAC para seguridad
 * - Logging de intentos
 */

require_once __DIR__ . '/../helpers/db_helper.php';

class WebhookService
{
    /**
     * Eventos disponibles
     */
    const EVENT_REQUEST_CREATED = 'request.created';
    const EVENT_PROPOSAL_SUBMITTED = 'proposal.submitted';
    const EVENT_PROPOSAL_ACCEPTED = 'proposal.accepted';
    const EVENT_SHIFT_ASSIGNED = 'shift.assigned';
    const EVENT_WORKLOG_APPROVED = 'worklog.approved';
    const EVENT_INVOICE_ISSUED = 'invoice.issued';
    const EVENT_DISPUTE_CREATED = 'dispute.created';
    const EVENT_DISPUTE_RESOLVED = 'dispute.resolved';

    /**
     * Dispara un evento webhook
     * 
     * @param int $tenantId ID del tenant
     * @param string $eventType Tipo de evento
     * @param array $payload Datos del evento
     * @return void
     */
    public static function trigger($tenantId, $eventType, $payload)
    {
        $conn = DBHelper::getConnection();

        // Obtener webhooks activos del tenant que están suscritos a este evento
        $stmt = $conn->prepare("
            SELECT id, url, secret, events_json
            FROM webhooks
            WHERE tenant_id = ? AND is_active = 1
        ");
        $stmt->bind_param('i', $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();

        $webhooks = [];
        while ($row = $result->fetch_assoc()) {
            $events = !empty($row['events_json']) ? json_decode($row['events_json'], true) : [];

            // Verificar si el webhook está suscrito a este evento
            if (in_array($eventType, $events) || in_array('*', $events)) {
                $webhooks[] = [
                    'id' => (int)$row['id'],
                    'url' => $row['url'],
                    'secret' => $row['secret']
                ];
            }
        }
        $stmt->close();

        // Enviar webhook a cada URL suscrita
        foreach ($webhooks as $webhook) {
            self::deliver($conn, $webhook['id'], $eventType, $payload, $webhook['url'], $webhook['secret']);
        }
    }

    /**
     * Entrega un webhook (con retry automático)
     * 
     * @param mysqli $conn Conexión a BD
     * @param int $webhookId ID del webhook
     * @param string $eventType Tipo de evento
     * @param array $payload Datos del evento
     * @param string $url URL destino
     * @param string $secret Secreto para firma HMAC
     * @return void
     */
    private static function deliver($conn, $webhookId, $eventType, $payload, $url, $secret)
    {
        // Crear payload completo
        $fullPayload = [
            'event' => $eventType,
            'timestamp' => date('c'), // ISO 8601
            'data' => $payload
        ];

        $payloadJson = json_encode($fullPayload);

        // Generar firma HMAC
        $signature = hash_hmac('sha256', $payloadJson, $secret);

        // Generar ID único para deduplicación
        $webhookIdValue = uniqid('wh_', true);

        // Crear registro de entrega
        $stmt = $conn->prepare("
            INSERT INTO webhook_deliveries (webhook_id, event_type, payload_json, status)
            VALUES (?, ?, ?, 'pending')
        ");
        $stmt->bind_param('iss', $webhookId, $eventType, $payloadJson);
        $stmt->execute();
        $deliveryId = $conn->insert_id;
        $stmt->close();

        // Intentar enviar (con retry automático si falla)
        self::sendWebhook($conn, $deliveryId, $url, $payloadJson, $signature, $webhookIdValue);
    }

    /**
     * Envía un webhook HTTP
     * 
     * @param mysqli $conn Conexión a BD
     * @param int $deliveryId ID del registro de entrega
     * @param string $url URL destino
     * @param string $payloadJson Payload JSON
     * @param string $signature Firma HMAC
     * @param string $webhookId ID único para deduplicación
     * @param int $attempt Número de intento (para retry)
     * @return void
     */
    private static function sendWebhook($conn, $deliveryId, $url, $payloadJson, $signature, $webhookId, $attempt = 1)
    {
        // Calcular backoff exponencial: 1s, 2s, 4s, 8s, 16s, 32s, 64s, 128s, 256s, 512s
        $backoffSeconds = min(pow(2, $attempt - 1), 512);

        // Si no es el primer intento, esperar antes de reintentar
        if ($attempt > 1) {
            sleep($backoffSeconds);
        }

        // Preparar request HTTP
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payloadJson,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Signature: ' . $signature,
                'X-Webhook-Id: ' . $webhookId,
                'X-Webhook-Event: ' . json_decode($payloadJson, true)['event']
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $duration = round((microtime(true) - $startTime) * 1000); // ms
        curl_close($ch);

        // Determinar si fue exitoso
        $success = ($httpCode >= 200 && $httpCode < 300) && empty($error);

        // Actualizar registro de entrega
        $status = $success ? 'success' : 'failed';
        $responseBody = $success ? substr($response, 0, 1000) : ($error ?: substr($response, 0, 1000));

        $updateStmt = $conn->prepare("
            UPDATE webhook_deliveries
            SET attempt = ?,
                status = ?,
                response_code = ?,
                response_body = ?,
                attempted_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->bind_param('isisi', $attempt, $status, $httpCode, $responseBody, $deliveryId);
        $updateStmt->execute();
        $updateStmt->close();

        // Si falló y no hemos alcanzado el máximo de intentos, reintentar
        if (!$success && $attempt < 10) {
            // Calcular tiempo hasta el siguiente intento (máximo 24 horas desde creación)
            $checkStmt = $conn->prepare("
                SELECT created_at FROM webhook_deliveries WHERE id = ?
            ");
            $checkStmt->bind_param('i', $deliveryId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $delivery = $result->fetch_assoc();
            $checkStmt->close();

            $createdAt = strtotime($delivery['created_at']);
            $hoursSinceCreation = (time() - $createdAt) / 3600;

            // Solo reintentar si han pasado menos de 24 horas
            if ($hoursSinceCreation < 24) {
                // Programar siguiente intento (usar job o ejecutar en background)
                // Por ahora, ejecutar inmediatamente con backoff
                self::sendWebhook($conn, $deliveryId, $url, $payloadJson, $signature, $webhookId, $attempt + 1);
            }
        }
    }

    /**
     * Crea un webhook para un tenant
     * 
     * @param int $tenantId ID del tenant
     * @param string $url URL destino
     * @param array $events Eventos a los que está suscrito
     * @return array ['success' => bool, 'id' => int|null, 'secret' => string|null]
     */
    public static function create($tenantId, $url, $events)
    {
        $conn = DBHelper::getConnection();

        // Validar URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'error' => 'webhooks.invalid_url'
            ];
        }

        // Generar secreto
        $secret = bin2hex(random_bytes(32));

        // Insertar webhook
        $stmt = $conn->prepare("
            INSERT INTO webhooks (tenant_id, url, events_json, secret)
            VALUES (?, ?, ?, ?)
        ");
        $eventsJson = json_encode($events);
        $stmt->bind_param('isss', $tenantId, $url, $eventsJson, $secret);

        if ($stmt->execute()) {
            $webhookId = $conn->insert_id;
            $stmt->close();

            return [
                'success' => true,
                'id' => $webhookId,
                'secret' => $secret // Solo se muestra una vez al crear
            ];
        } else {
            $stmt->close();
            return [
                'success' => false,
                'error' => 'webhooks.create_failed'
            ];
        }
    }

    /**
     * Lista webhooks de un tenant
     * 
     * @param int $tenantId ID del tenant
     * @return array Lista de webhooks
     */
    public static function list($tenantId)
    {
        $conn = DBHelper::getConnection();

        $stmt = $conn->prepare("
            SELECT 
                id,
                url,
                events_json,
                is_active,
                created_at,
                updated_at
            FROM webhooks
            WHERE tenant_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->bind_param('i', $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();

        $webhooks = [];
        while ($row = $result->fetch_assoc()) {
            $events = !empty($row['events_json']) ? json_decode($row['events_json'], true) : [];

            $webhooks[] = [
                'id' => (int)$row['id'],
                'url' => $row['url'],
                'events' => $events,
                'is_active' => (bool)$row['is_active'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }
        $stmt->close();

        return $webhooks;
    }

    /**
     * Actualiza un webhook
     * 
     * @param int $webhookId ID del webhook
     * @param int $tenantId ID del tenant (para validación)
     * @param array $data Datos a actualizar (url, events, is_active)
     * @return bool
     */
    public static function update($webhookId, $tenantId, $data)
    {
        $conn = DBHelper::getConnection();

        // Verificar que el webhook pertenece al tenant
        $checkStmt = $conn->prepare("SELECT id FROM webhooks WHERE id = ? AND tenant_id = ?");
        $checkStmt->bind_param('ii', $webhookId, $tenantId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $webhook = $result->fetch_assoc();
        $checkStmt->close();

        if (!$webhook) {
            return false;
        }

        // Construir UPDATE
        $updates = [];
        $params = [];
        $types = '';

        if (isset($data['url'])) {
            $updates[] = "url = ?";
            $params[] = $data['url'];
            $types .= 's';
        }

        if (isset($data['events'])) {
            $updates[] = "events_json = ?";
            $params[] = json_encode($data['events']);
            $types .= 's';
        }

        if (isset($data['is_active'])) {
            $updates[] = "is_active = ?";
            $params[] = $data['is_active'] ? 1 : 0;
            $types .= 'i';
        }

        if (empty($updates)) {
            return true; // No hay nada que actualizar
        }

        $params[] = $webhookId;
        $params[] = $tenantId;
        $types .= 'ii';

        $stmt = $conn->prepare("
            UPDATE webhooks 
            SET " . implode(', ', $updates) . "
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->bind_param($types, ...$params);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    /**
     * Elimina un webhook
     * 
     * @param int $webhookId ID del webhook
     * @param int $tenantId ID del tenant (para validación)
     * @return bool
     */
    public static function delete($webhookId, $tenantId)
    {
        $conn = DBHelper::getConnection();

        $stmt = $conn->prepare("DELETE FROM webhooks WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param('ii', $webhookId, $tenantId);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }
}
