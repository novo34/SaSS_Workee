<?php
/**
 * API Firmas Digitales de Órdenes de Trabajo (Work Order Signoffs)
 * 
 * Endpoints:
 * - GET /api/work-order-signoffs?work_order_id={id} - Listar firmas de una OT
 * - POST /api/work-order-signoffs - Crear firma
 * - GET /api/work-order-signoffs/{id} - Obtener firma
 * - DELETE /api/work-order-signoffs/{id} - Eliminar firma
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
    $action = null;
    if ($method === 'GET') {
        $action = 'work_orders.view';
    } elseif ($method === 'POST') {
        $action = 'work_orders.update';
    } elseif ($method === 'DELETE') {
        $action = 'work_orders.update';
    }
    
    if ($action && !RBACMiddleware::checkPermission($action)) {
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
    $signoffId = null;
    
    if (count($pathParts) >= 2 && is_numeric($pathParts[1])) {
        $signoffId = (int)$pathParts[1];
    }
    
    // Routing
    switch ($method) {
        case 'GET':
            if ($signoffId) {
                getSignoff($conn, $tenantId, $signoffId);
            } else {
                listSignoffs($conn, $tenantId);
            }
            break;
            
        case 'POST':
            createSignoff($conn, $tenantId);
            break;
            
        case 'DELETE':
            if ($signoffId) {
                deleteSignoff($conn, $tenantId, $signoffId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.signoff_id_required',
                    'error_key' => 'validation.signoff_id_required',
                    'message' => t('validation.signoff_id_required')
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
 * Listar firmas
 */
function listSignoffs($conn, $tenantId) {
    $workOrderId = isset($_GET['work_order_id']) ? (int)$_GET['work_order_id'] : null;
    
    if (!$workOrderId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'validation.work_order_id_required',
            'error_key' => 'validation.work_order_id_required',
            'message' => t('validation.work_order_id_required')
        ]);
        return;
    }
    
    // Verificar que la OT existe y pertenece al tenant
    $stmt = $conn->prepare("SELECT id FROM work_orders WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $workOrderId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result->fetch_assoc()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'work_orders.not_found',
            'error_key' => 'work_orders.not_found',
            'message' => t('work_orders.not_found')
        ]);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    $stmt = $conn->prepare("
        SELECT 
            wos.id,
            wos.work_order_id,
            wos.signer_type,
            wos.signer_user_id,
            wos.signer_name,
            wos.signature_data,
            wos.signed_at,
            wos.ip_address,
            u.first_name as signer_first_name,
            u.last_name as signer_last_name
        FROM work_order_signoffs wos
        LEFT JOIN users u ON wos.signer_user_id = u.id
        WHERE wos.work_order_id = ?
        ORDER BY wos.signed_at DESC
    ");
    $stmt->bind_param('i', $workOrderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $signoffs = [];
    while ($row = $result->fetch_assoc()) {
        $signoffs[] = formatSignoff($row);
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $signoffs
    ]);
}

/**
 * Obtener una firma
 */
function getSignoff($conn, $tenantId, $signoffId) {
    $stmt = $conn->prepare("
        SELECT 
            wos.id,
            wos.work_order_id,
            wos.signer_type,
            wos.signer_user_id,
            wos.signer_name,
            wos.signature_data,
            wos.signed_at,
            wos.ip_address,
            wo.tenant_id,
            u.first_name as signer_first_name,
            u.last_name as signer_last_name
        FROM work_order_signoffs wos
        INNER JOIN work_orders wo ON wos.work_order_id = wo.id
        LEFT JOIN users u ON wos.signer_user_id = u.id
        WHERE wos.id = ? AND wo.tenant_id = ?
    ");
    $stmt->bind_param('ii', $signoffId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $signoff = $result->fetch_assoc();
    $stmt->close();
    
    if (!$signoff) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'work_order_signoffs.not_found',
            'error_key' => 'work_order_signoffs.not_found',
            'message' => t('work_order_signoffs.not_found')
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => formatSignoff($signoff)
    ]);
}

/**
 * Crear firma
 */
function createSignoff($conn, $tenantId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $errors = [];
    
    if (empty($input['work_order_id'])) {
        $errors[] = ['field' => 'work_order_id', 'error' => 'validation.required'];
    }
    
    if (empty($input['signer_type'])) {
        $errors[] = ['field' => 'signer_type', 'error' => 'validation.required'];
    } elseif (!in_array($input['signer_type'], ['client', 'company', 'agency'])) {
        $errors[] = ['field' => 'signer_type', 'error' => 'validation.invalid'];
    }
    
    if (empty($input['signature_data'])) {
        $errors[] = ['field' => 'signature_data', 'error' => 'validation.required'];
    }
    
    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'validation.failed',
            'error_key' => 'validation.failed',
            'errors' => $errors,
            'message' => t('validation.failed')
        ]);
        return;
    }
    
    // Verificar que la OT existe y pertenece al tenant
    $stmt = $conn->prepare("SELECT id FROM work_orders WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $input['work_order_id'], $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result->fetch_assoc()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'work_orders.not_found',
            'error_key' => 'work_orders.not_found',
            'message' => t('work_orders.not_found')
        ]);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    // Obtener user_id si no se proporciona
    $signerUserId = $input['signer_user_id'] ?? $GLOBALS['USER_ID'] ?? null;
    $signerName = $input['signer_name'] ?? null;
    
    // Si no hay signer_name pero hay signer_user_id, obtener el nombre del usuario
    if (!$signerName && $signerUserId) {
        $stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt->bind_param('i', $signerUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        if ($user) {
            $signerName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        }
    }
    
    // Obtener IP del cliente
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    
    $stmt = $conn->prepare("
        INSERT INTO work_order_signoffs (
            work_order_id, signer_type, signer_user_id, signer_name,
            signature_data, signed_at, ip_address
        ) VALUES (?, ?, ?, ?, ?, NOW(), ?)
    ");
    
    $stmt->bind_param(
        'siisss',
        $input['work_order_id'],
        $input['signer_type'],
        $signerUserId,
        $signerName,
        $input['signature_data'],
        $ipAddress
    );
    
    $stmt->execute();
    $signoffId = $conn->insert_id;
    $stmt->close();
    
    getSignoff($conn, $tenantId, $signoffId);
}

/**
 * Eliminar firma
 */
function deleteSignoff($conn, $tenantId, $signoffId) {
    // Verificar que existe
    $stmt = $conn->prepare("
        SELECT wos.id 
        FROM work_order_signoffs wos
        INNER JOIN work_orders wo ON wos.work_order_id = wo.id
        WHERE wos.id = ? AND wo.tenant_id = ?
    ");
    $stmt->bind_param('ii', $signoffId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result->fetch_assoc()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'work_order_signoffs.not_found',
            'error_key' => 'work_order_signoffs.not_found',
            'message' => t('work_order_signoffs.not_found')
        ]);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    $stmt = $conn->prepare("DELETE FROM work_order_signoffs WHERE id = ?");
    $stmt->bind_param('i', $signoffId);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => t('work_order_signoffs.deleted_success')
    ]);
}

/**
 * Formatear firma para respuesta
 */
function formatSignoff($row) {
    return [
        'id' => (int)$row['id'],
        'work_order_id' => (int)$row['work_order_id'],
        'signer_type' => $row['signer_type'],
        'signer_user_id' => $row['signer_user_id'] ? (int)$row['signer_user_id'] : null,
        'signer_name' => $row['signer_name'] ?? ($row['signer_first_name'] && $row['signer_last_name'] 
            ? trim($row['signer_first_name'] . ' ' . $row['signer_last_name']) 
            : null),
        'signature_data' => $row['signature_data'],
        'signed_at' => $row['signed_at'],
        'ip_address' => $row['ip_address']
    ];
}

