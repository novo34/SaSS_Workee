<?php
/**
 * API Webhooks
 * 
 * Endpoints:
 * - GET /api/webhooks - Listar webhooks del tenant
 * - POST /api/webhooks - Crear webhook
 * - PUT /api/webhooks/{id} - Actualizar webhook
 * - DELETE /api/webhooks/{id} - Eliminar webhook
 * - GET /api/webhooks/{id}/deliveries - Listar entregas de un webhook
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../core/TenantMiddleware.php';
require_once __DIR__ . '/../core/RBACMiddleware.php';
require_once __DIR__ . '/../core/WebhookService.php';
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
    
    // Verificar permisos
    if (!RBACMiddleware::checkPermission('settings.*')) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'auth.forbidden',
            'error_key' => 'auth.forbidden',
            'message' => t('auth.forbidden')
        ]);
        exit;
    }
    
    $conn = DBHelper::getConnection();
    
    // Parsear path
    $pathParts = explode('/', trim($path, '/'));
    $webhookId = null;
    $subAction = null;
    
    if (count($pathParts) >= 2) {
        if (is_numeric($pathParts[1])) {
            $webhookId = (int)$pathParts[1];
            if (isset($pathParts[2])) {
                $subAction = $pathParts[2];
            }
        }
    }
    
    // Routing
    switch ($method) {
        case 'GET':
            if ($webhookId && $subAction === 'deliveries') {
                listWebhookDeliveries($conn, $tenantId, $webhookId);
            } elseif ($webhookId) {
                getWebhook($conn, $tenantId, $webhookId);
            } else {
                listWebhooks($tenantId);
            }
            break;
            
        case 'POST':
            createWebhook($conn, $tenantId);
            break;
            
        case 'PUT':
            if ($webhookId) {
                updateWebhook($conn, $tenantId, $webhookId);
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
            if ($webhookId) {
                deleteWebhook($conn, $tenantId, $webhookId);
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
 * Listar webhooks
 */
function listWebhooks($tenantId) {
    $webhooks = WebhookService::list($tenantId);
    
    echo json_encode([
        'success' => true,
        'data' => $webhooks
    ]);
}

/**
 * Obtener webhook por ID
 */
function getWebhook($conn, $tenantId, $webhookId) {
    $stmt = $conn->prepare("
        SELECT 
            id,
            url,
            events_json,
            is_active,
            created_at,
            updated_at
        FROM webhooks
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->bind_param('ii', $webhookId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $webhook = $result->fetch_assoc();
    $stmt->close();
    
    if (!$webhook) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'webhooks.not_found',
            'error_key' => 'webhooks.not_found',
            'message' => t('webhooks.not_found')
        ]);
        return;
    }
    
    $events = !empty($webhook['events_json']) ? json_decode($webhook['events_json'], true) : [];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int)$webhook['id'],
            'url' => $webhook['url'],
            'events' => $events,
            'is_active' => (bool)$webhook['is_active'],
            'created_at' => $webhook['created_at'],
            'updated_at' => $webhook['updated_at']
        ]
    ]);
}

/**
 * Crear webhook
 */
function createWebhook($conn, $tenantId) {
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
    if (empty($input['url'])) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'validation.required',
            'error_key' => 'validation.required',
            'error_params' => ['field' => 'url'],
            'message' => t('validation.required', ['field' => 'url'])
        ]);
        exit;
    }
    
    if (empty($input['events']) || !is_array($input['events'])) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'validation.required',
            'error_key' => 'validation.required',
            'error_params' => ['field' => 'events'],
            'message' => t('validation.required', ['field' => 'events'])
        ]);
        exit;
    }
    
    $result = WebhookService::create($tenantId, $input['url'], $input['events']);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $result['id'],
                'url' => $input['url'],
                'events' => $input['events'],
                'secret' => $result['secret'], // Solo se muestra una vez
                'created_at' => date('Y-m-d H:i:s')
            ],
            'message' => t('webhooks.created_success')
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'webhooks.create_failed',
            'error_key' => $result['error'] ?? 'webhooks.create_failed',
            'message' => t($result['error'] ?? 'webhooks.create_failed')
        ]);
    }
}

/**
 * Actualizar webhook
 */
function updateWebhook($conn, $tenantId, $webhookId) {
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
    
    $data = [];
    if (isset($input['url'])) {
        $data['url'] = $input['url'];
    }
    if (isset($input['events'])) {
        $data['events'] = $input['events'];
    }
    if (isset($input['is_active'])) {
        $data['is_active'] = (bool)$input['is_active'];
    }
    
    $success = WebhookService::update($webhookId, $tenantId, $data);
    
    if ($success) {
        getWebhook($conn, $tenantId, $webhookId);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'webhooks.not_found',
            'error_key' => 'webhooks.not_found',
            'message' => t('webhooks.not_found')
        ]);
    }
}

/**
 * Eliminar webhook
 */
function deleteWebhook($conn, $tenantId, $webhookId) {
    $success = WebhookService::delete($webhookId, $tenantId);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => t('webhooks.deleted_success')
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'webhooks.not_found',
            'error_key' => 'webhooks.not_found',
            'message' => t('webhooks.not_found')
        ]);
    }
}

/**
 * Listar entregas de un webhook
 */
function listWebhookDeliveries($conn, $tenantId, $webhookId) {
    // Verificar que el webhook pertenece al tenant
    $checkStmt = $conn->prepare("SELECT id FROM webhooks WHERE id = ? AND tenant_id = ?");
    $checkStmt->bind_param('ii', $webhookId, $tenantId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $webhook = $result->fetch_assoc();
    $checkStmt->close();
    
    if (!$webhook) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'webhooks.not_found',
            'error_key' => 'webhooks.not_found',
            'message' => t('webhooks.not_found')
        ]);
        return;
    }
    
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
    $offset = ($page - 1) * $pageSize;
    
    // Contar total
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM webhook_deliveries
        WHERE webhook_id = ?
    ");
    $countStmt->bind_param('i', $webhookId);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Obtener entregas
    $stmt = $conn->prepare("
        SELECT 
            id,
            event_type,
            attempt,
            status,
            response_code,
            response_body,
            attempted_at,
            created_at
        FROM webhook_deliveries
        WHERE webhook_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('iii', $webhookId, $pageSize, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $deliveries = [];
    while ($row = $result->fetch_assoc()) {
        $deliveries[] = [
            'id' => (int)$row['id'],
            'event_type' => $row['event_type'],
            'attempt' => (int)$row['attempt'],
            'status' => $row['status'],
            'response_code' => $row['response_code'],
            'response_body' => $row['response_body'],
            'attempted_at' => $row['attempted_at'],
            'created_at' => $row['created_at']
        ];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $deliveries,
        'page' => $page,
        'page_size' => $pageSize,
        'total' => $total,
        'total_pages' => ceil($total / $pageSize)
    ]);
}

