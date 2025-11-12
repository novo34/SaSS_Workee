<?php
/**
 * API Preferencias de Notificaciones
 * 
 * Endpoints:
 * - GET /api/notification-preferences - Listar preferencias del usuario/tenant
 * - POST /api/notification-preferences - Crear/actualizar preferencia
 * - PUT /api/notification-preferences/{id} - Actualizar preferencia
 * - DELETE /api/notification-preferences/{id} - Eliminar preferencia
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../core/TenantMiddleware.php';
require_once __DIR__ . '/../core/RBACMiddleware.php';
require_once __DIR__ . '/../helpers/db_helper.php';
require_once __DIR__ . '/../helpers/i18n.php';
require_once __DIR__ . '/../helpers/env.php';

// Deshabilitar display_errors para evitar HTML en respuestas JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $GLOBALS['API_PATH'] ?? $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '';
    
    // Procesar middleware
    $middlewareResult = TenantMiddleware::process();
    $tenantId = $middlewareResult['tenant_id'] ?? null;
    $GLOBALS['USER_ID'] = $middlewareResult['user_id'] ?? null;
    $userId = $GLOBALS['USER_ID'];
    
    if (!$tenantId) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'auth.tenant_required',
            'error_key' => 'auth.tenant_required',
            'message' => t('auth.tenant_required')
        ]);
        exit;
    }
    
    $conn = DBHelper::getConnection();
    
    // Parsear path
    $pathParts = explode('/', trim($path, '/'));
    $preferenceId = null;
    
    if (count($pathParts) >= 2 && is_numeric($pathParts[1])) {
        $preferenceId = (int)$pathParts[1];
    }
    
    // Routing
    switch ($method) {
        case 'GET':
            listPreferences($conn, $tenantId, $userId);
            break;
            
        case 'POST':
            createOrUpdatePreference($conn, $tenantId, $userId);
            break;
            
        case 'PUT':
            if ($preferenceId) {
                updatePreference($conn, $tenantId, $userId, $preferenceId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.id_required',
                    'error_key' => 'validation.id_required',
                    'message' => t('validation.id_required')
                ]);
            }
            break;
            
        case 'DELETE':
            if ($preferenceId) {
                deletePreference($conn, $tenantId, $userId, $preferenceId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.id_required',
                    'error_key' => 'validation.id_required',
                    'message' => t('validation.id_required')
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'method_not_allowed',
                'message' => 'Método no permitido'
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'internal_error',
        'message' => $e->getMessage()
    ]);
}

/**
 * Lista preferencias del usuario/tenant
 */
function listPreferences($conn, $tenantId, $userId) {
    $where = ["tenant_id = ?"];
    $params = [$tenantId];
    $types = 'i';
    
    // Si se especifica user_id en query, filtrar por usuario
    // Si no, mostrar preferencias del tenant (globales) y del usuario actual
    $filterUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    
    if ($filterUserId !== null) {
        $where[] = "user_id = ?";
        $params[] = $filterUserId;
        $types .= 'i';
    } else {
        // Mostrar preferencias del tenant y del usuario actual
        $where[] = "(user_id IS NULL OR user_id = ?)";
        $params[] = $userId;
        $types .= 'i';
    }
    
    $whereClause = implode(' AND ', $where);
    
    $stmt = $conn->prepare("
        SELECT 
            id,
            tenant_id,
            user_id,
            channel,
            event_type,
            enabled,
            quiet_hours_start,
            quiet_hours_end,
            created_at,
            updated_at
        FROM notification_preferences
        WHERE {$whereClause}
        ORDER BY user_id IS NULL DESC, event_type ASC, channel ASC
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $preferences = [];
    while ($row = $result->fetch_assoc()) {
        $preferences[] = [
            'id' => (int)$row['id'],
            'tenant_id' => (int)$row['tenant_id'],
            'user_id' => $row['user_id'] ? (int)$row['user_id'] : null,
            'channel' => $row['channel'],
            'event_type' => $row['event_type'],
            'enabled' => (bool)$row['enabled'],
            'quiet_hours_start' => $row['quiet_hours_start'],
            'quiet_hours_end' => $row['quiet_hours_end'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $preferences
    ]);
}

/**
 * Crea o actualiza una preferencia
 */
function createOrUpdatePreference($conn, $tenantId, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'validation.invalid_json',
            'error_key' => 'validation.invalid_json',
            'message' => t('validation.invalid_json')
        ]);
        exit;
    }
    
    // Validar campos requeridos
    if (empty($input['channel']) || empty($input['event_type'])) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'validation.required',
            'error_key' => 'validation.required',
            'error_params' => ['field' => 'channel, event_type'],
            'message' => t('validation.required', ['field' => 'channel, event_type'])
        ]);
        exit;
    }
    
    // Validar canal
    $validChannels = ['email', 'sms', 'whatsapp', 'webpush'];
    if (!in_array($input['channel'], $validChannels)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'notification_preferences.invalid_channel',
            'error_key' => 'notification_preferences.invalid_channel',
            'message' => t('notification_preferences.invalid_channel')
        ]);
        exit;
    }
    
    $channel = $input['channel'];
    $eventType = $input['event_type'];
    $enabled = isset($input['enabled']) ? (bool)$input['enabled'] : true;
    $quietHoursStart = !empty($input['quiet_hours_start']) ? $input['quiet_hours_start'] : null;
    $quietHoursEnd = !empty($input['quiet_hours_end']) ? $input['quiet_hours_end'] : null;
    
    // Determinar si es preferencia de usuario o tenant
    // Si user_id está presente y el usuario tiene permisos, crear preferencia de usuario
    // Si no, crear preferencia de tenant (solo admin puede)
    $targetUserId = isset($input['user_id']) ? (int)$input['user_id'] : null;
    
    if ($targetUserId && $targetUserId !== $userId) {
        // Verificar permisos para gestionar preferencias de otros usuarios
        if (!RBACMiddleware::checkPermission('users.update')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'auth.forbidden',
                'error_key' => 'auth.forbidden',
                'message' => t('auth.forbidden')
            ]);
            exit;
        }
    }
    
    // Verificar si ya existe
    $checkStmt = $conn->prepare("
        SELECT id FROM notification_preferences
        WHERE tenant_id = ? 
        AND (user_id = ? OR (user_id IS NULL AND ? IS NULL))
        AND channel = ? AND event_type = ?
    ");
    $checkUserId = $targetUserId ?? null;
    $checkStmt->bind_param('iisss', $tenantId, $checkUserId, $checkUserId, $channel, $eventType);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $existing = $result->fetch_assoc();
    $checkStmt->close();
    
    if ($existing) {
        // Actualizar existente
        $updateStmt = $conn->prepare("
            UPDATE notification_preferences
            SET enabled = ?,
                quiet_hours_start = ?,
                quiet_hours_end = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->bind_param('isssi', $enabled, $quietHoursStart, $quietHoursEnd, $existing['id']);
        $updateStmt->execute();
        $updateStmt->close();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => (int)$existing['id'],
                'tenant_id' => $tenantId,
                'user_id' => $targetUserId,
                'channel' => $channel,
                'event_type' => $eventType,
                'enabled' => $enabled,
                'quiet_hours_start' => $quietHoursStart,
                'quiet_hours_end' => $quietHoursEnd
            ],
            'message' => t('notification_preferences.updated_success')
        ]);
    } else {
        // Crear nuevo
        $insertStmt = $conn->prepare("
            INSERT INTO notification_preferences (tenant_id, user_id, channel, event_type, enabled, quiet_hours_start, quiet_hours_end)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->bind_param('iississ', $tenantId, $targetUserId, $channel, $eventType, $enabled, $quietHoursStart, $quietHoursEnd);
        $insertStmt->execute();
        $preferenceId = $conn->insert_id;
        $insertStmt->close();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $preferenceId,
                'tenant_id' => $tenantId,
                'user_id' => $targetUserId,
                'channel' => $channel,
                'event_type' => $eventType,
                'enabled' => $enabled,
                'quiet_hours_start' => $quietHoursStart,
                'quiet_hours_end' => $quietHoursEnd
            ],
            'message' => t('notification_preferences.created_success')
        ]);
    }
}

/**
 * Actualiza una preferencia existente
 */
function updatePreference($conn, $tenantId, $userId, $preferenceId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'validation.invalid_json',
            'error_key' => 'validation.invalid_json',
            'message' => t('validation.invalid_json')
        ]);
        exit;
    }
    
    // Verificar que existe y pertenece al tenant
    $checkStmt = $conn->prepare("
        SELECT id, user_id FROM notification_preferences
        WHERE id = ? AND tenant_id = ?
    ");
    $checkStmt->bind_param('ii', $preferenceId, $tenantId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $preference = $result->fetch_assoc();
    $checkStmt->close();
    
    if (!$preference) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'notification_preferences.not_found',
            'error_key' => 'notification_preferences.not_found',
            'message' => t('notification_preferences.not_found')
        ]);
        return;
    }
    
    // Verificar permisos: solo puede editar si es su propia preferencia o tiene permisos de admin
    if ($preference['user_id'] && $preference['user_id'] != $userId) {
        if (!RBACMiddleware::checkPermission('users.update')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'auth.forbidden',
                'error_key' => 'auth.forbidden',
                'message' => t('auth.forbidden')
            ]);
            return;
        }
    }
    
    // Construir UPDATE
    $updates = [];
    $params = [];
    $types = '';
    
    if (isset($input['enabled'])) {
        $updates[] = "enabled = ?";
        $params[] = (bool)$input['enabled'] ? 1 : 0;
        $types .= 'i';
    }
    
    if (isset($input['quiet_hours_start'])) {
        $updates[] = "quiet_hours_start = ?";
        $params[] = $input['quiet_hours_start'] ?: null;
        $types .= 's';
    }
    
    if (isset($input['quiet_hours_end'])) {
        $updates[] = "quiet_hours_end = ?";
        $params[] = $input['quiet_hours_end'] ?: null;
        $types .= 's';
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'validation.no_changes',
            'error_key' => 'validation.no_changes',
            'message' => t('validation.no_changes')
        ]);
        return;
    }
    
    $params[] = $preferenceId;
    $params[] = $tenantId;
    $types .= 'ii';
    
    $stmt = $conn->prepare("
        UPDATE notification_preferences
        SET " . implode(', ', $updates) . ", updated_at = NOW()
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->bind_param($types, ...$params);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        // Obtener preferencia actualizada
        listPreferences($conn, $tenantId, $userId);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'notification_preferences.update_failed',
            'error_key' => 'notification_preferences.update_failed',
            'message' => t('notification_preferences.update_failed')
        ]);
    }
}

/**
 * Elimina una preferencia
 */
function deletePreference($conn, $tenantId, $userId, $preferenceId) {
    // Verificar que existe y pertenece al tenant
    $checkStmt = $conn->prepare("
        SELECT id, user_id FROM notification_preferences
        WHERE id = ? AND tenant_id = ?
    ");
    $checkStmt->bind_param('ii', $preferenceId, $tenantId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $preference = $result->fetch_assoc();
    $checkStmt->close();
    
    if (!$preference) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'notification_preferences.not_found',
            'error_key' => 'notification_preferences.not_found',
            'message' => t('notification_preferences.not_found')
        ]);
        return;
    }
    
    // Verificar permisos: solo puede eliminar si es su propia preferencia o tiene permisos de admin
    if ($preference['user_id'] && $preference['user_id'] != $userId) {
        if (!RBACMiddleware::checkPermission('users.update')) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'auth.forbidden',
                'error_key' => 'auth.forbidden',
                'message' => t('auth.forbidden')
            ]);
            return;
        }
    }
    
    $deleteStmt = $conn->prepare("DELETE FROM notification_preferences WHERE id = ?");
    $deleteStmt->bind_param('i', $preferenceId);
    $success = $deleteStmt->execute();
    $deleteStmt->close();
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => t('notification_preferences.deleted_success')
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'notification_preferences.delete_failed',
            'error_key' => 'notification_preferences.delete_failed',
            'message' => t('notification_preferences.delete_failed')
        ]);
    }
}

