<?php
/**
 * API GDPR/LPD (Ley de Protección de Datos Suiza)
 * 
 * Endpoints:
 * - GET /api/gdpr/export - Exportar todos los datos personales de un usuario
 * - POST /api/gdpr/erase - Borrar todos los datos personales de un usuario
 * 
 * Cumple con GDPR (UE) y LPD (Suiza)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../core/TenantMiddleware.php';
require_once __DIR__ . '/../core/RBACMiddleware.php';
require_once __DIR__ . '/../helpers/db_helper.php';
require_once __DIR__ . '/../helpers/i18n.php';
require_once __DIR__ . '/../core/AuditLogger.php';

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
    $action = $pathParts[1] ?? null;
    
    // Routing
    switch ($method) {
        case 'GET':
            if ($action === 'export') {
                handleExport($conn, $tenantId, $userId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.invalid_action',
                    'error_key' => 'validation.invalid_action',
                    'message' => t('validation.invalid_action')
                ]);
            }
            break;
            
        case 'POST':
            if ($action === 'erase') {
                handleErase($conn, $tenantId, $userId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.invalid_action',
                    'error_key' => 'validation.invalid_action',
                    'message' => t('validation.invalid_action')
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
 * Exporta todos los datos personales de un usuario
 */
function handleExport($conn, $tenantId, $userId) {
    // Verificar permisos: solo el propio usuario o admin puede exportar
    $targetUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $userId;
    
    if ($targetUserId !== $userId && !RBACMiddleware::checkPermission('users.view')) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'auth.forbidden',
            'error_key' => 'auth.forbidden',
            'message' => t('auth.forbidden')
        ]);
        exit;
    }
    
    // Verificar que el usuario pertenece al tenant
    $userStmt = $conn->prepare("
        SELECT u.id, u.email, u.phone, u.locale
        FROM users u
        INNER JOIN tenant_users tu ON u.id = tu.user_id
        WHERE u.id = ? AND tu.tenant_id = ?
    ");
    $userStmt->bind_param('ii', $targetUserId, $tenantId);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'gdpr.user_not_found',
            'error_key' => 'gdpr.user_not_found',
            'message' => t('gdpr.user_not_found')
        ]);
        exit;
    }
    
    // Recopilar todos los datos personales
    $data = [
        'user' => $user,
        'profile' => getUserProfile($conn, $targetUserId, $tenantId),
        'employees' => getUserEmployees($conn, $targetUserId, $tenantId),
        'workers' => getUserWorkers($conn, $targetUserId, $tenantId),
        'work_logs' => getUserWorkLogs($conn, $targetUserId, $tenantId),
        'shifts' => getUserShifts($conn, $targetUserId, $tenantId),
        'disputes' => getUserDisputes($conn, $targetUserId, $tenantId),
        'ratings' => getUserRatings($conn, $targetUserId, $tenantId),
        'notifications' => getUserNotifications($conn, $targetUserId, $tenantId),
        'audit_logs' => getUserAuditLogs($conn, $targetUserId, $tenantId),
        'exported_at' => date('c'),
        'tenant_id' => $tenantId
    ];
    
    // Registrar en auditoría
    AuditLogger::log('export', 'gdpr', $targetUserId, ['exported_by' => $userId], $tenantId, $userId);
    
    // Retornar JSON
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="gdpr_export_' . $targetUserId . '_' . date('Y-m-d') . '.json"');
    
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Borra todos los datos personales de un usuario
 */
function handleErase($conn, $tenantId, $userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Verificar confirmación
    if (!isset($input['confirm']) || $input['confirm'] !== 'DELETE') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'gdpr.confirmation_required',
            'error_key' => 'gdpr.confirmation_required',
            'message' => t('gdpr.confirmation_required')
        ]);
        exit;
    }
    
    $targetUserId = isset($input['user_id']) ? (int)$input['user_id'] : $userId;
    
    // Verificar permisos: solo el propio usuario o admin puede borrar
    if ($targetUserId !== $userId && !RBACMiddleware::checkPermission('users.delete')) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'auth.forbidden',
            'error_key' => 'auth.forbidden',
            'message' => t('auth.forbidden')
        ]);
        exit;
    }
    
    // Verificar que el usuario pertenece al tenant
    $userStmt = $conn->prepare("
        SELECT u.id FROM users u
        INNER JOIN tenant_users tu ON u.id = tu.user_id
        WHERE u.id = ? AND tu.tenant_id = ?
    ");
    $userStmt->bind_param('ii', $targetUserId, $tenantId);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'gdpr.user_not_found',
            'error_key' => 'gdpr.user_not_found',
            'message' => t('gdpr.user_not_found')
        ]);
        exit;
    }
    
    // Exportar antes de borrar (por seguridad)
    $exportData = [
        'user' => getUserData($conn, $targetUserId),
        'erased_at' => date('c'),
        'erased_by' => $userId,
        'tenant_id' => $tenantId
    ];
    
    // Anonimizar datos personales (no borrar físicamente para mantener integridad referencial)
    $result = anonymizeUserData($conn, $targetUserId, $tenantId);
    
    // Registrar en auditoría
    AuditLogger::log('erase', 'gdpr', $targetUserId, [
        'erased_by' => $userId,
        'export_before_erase' => $exportData
    ], $tenantId, $userId);
    
    echo json_encode([
        'success' => true,
        'message' => t('gdpr.erase_success'),
        'anonymized' => $result['anonymized'],
        'preserved' => $result['preserved']
    ]);
}

/**
 * Obtiene perfil del usuario
 */
function getUserProfile($conn, $userId, $tenantId) {
    $stmt = $conn->prepare("
        SELECT * FROM tenant_users
        WHERE user_id = ? AND tenant_id = ?
    ");
    $stmt->bind_param('ii', $userId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();
    $stmt->close();
    
    return $profile;
}

/**
 * Obtiene empleados asociados al usuario
 */
function getUserEmployees($conn, $userId, $tenantId) {
    // Si el usuario es empleado, obtener sus datos
    $stmt = $conn->prepare("
        SELECT * FROM own_employees
        WHERE tenant_id = ? AND id IN (
            SELECT own_employee_id FROM tenant_users
            WHERE user_id = ? AND tenant_id = ?
        )
    ");
    $stmt->bind_param('iii', $tenantId, $userId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
    $stmt->close();
    
    return $employees;
}

/**
 * Obtiene trabajadores asociados al usuario
 */
function getUserWorkers($conn, $userId, $tenantId) {
    $stmt = $conn->prepare("
        SELECT * FROM agency_workers
        WHERE tenant_id = ? AND id IN (
            SELECT agency_worker_id FROM tenant_users
            WHERE user_id = ? AND tenant_id = ?
        )
    ");
    $stmt->bind_param('iii', $tenantId, $userId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $workers = [];
    while ($row = $result->fetch_assoc()) {
        $workers[] = $row;
    }
    $stmt->close();
    
    return $workers;
}

/**
 * Obtiene work logs del usuario
 */
function getUserWorkLogs($conn, $userId, $tenantId) {
    $stmt = $conn->prepare("
        SELECT wl.* FROM work_logs wl
        LEFT JOIN own_employees oe ON wl.own_employee_id = oe.id AND wl.employee_type = 'own_employee'
        LEFT JOIN agency_workers aw ON wl.agency_worker_id = aw.id AND wl.employee_type = 'agency_worker'
        LEFT JOIN tenant_users tu_oe ON oe.id = tu_oe.own_employee_id
        LEFT JOIN tenant_users tu_aw ON aw.id = tu_aw.agency_worker_id
        WHERE wl.tenant_id = ? 
        AND (tu_oe.user_id = ? OR tu_aw.user_id = ?)
        ORDER BY wl.date DESC
        LIMIT 1000
    ");
    $stmt->bind_param('iii', $tenantId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $workLogs = [];
    while ($row = $result->fetch_assoc()) {
        $workLogs[] = $row;
    }
    $stmt->close();
    
    return $workLogs;
}

/**
 * Obtiene turnos del usuario
 */
function getUserShifts($conn, $userId, $tenantId) {
    $stmt = $conn->prepare("
        SELECT s.* FROM shifts s
        LEFT JOIN own_employees oe ON s.employee_id = oe.id AND s.employee_type = 'own_employee'
        LEFT JOIN agency_workers aw ON s.employee_id = aw.id AND s.employee_type = 'agency_worker'
        LEFT JOIN tenant_users tu_oe ON oe.id = tu_oe.own_employee_id
        LEFT JOIN tenant_users tu_aw ON aw.id = tu_aw.agency_worker_id
        WHERE s.tenant_id = ?
        AND (tu_oe.user_id = ? OR tu_aw.user_id = ?)
        ORDER BY s.start_datetime DESC
        LIMIT 1000
    ");
    $stmt->bind_param('iii', $tenantId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $shifts = [];
    while ($row = $result->fetch_assoc()) {
        $shifts[] = $row;
    }
    $stmt->close();
    
    return $shifts;
}

/**
 * Obtiene disputas del usuario
 */
function getUserDisputes($conn, $userId, $tenantId) {
    $stmt = $conn->prepare("
        SELECT * FROM disputes
        WHERE tenant_id = ? AND (created_by = ? OR resolved_by = ?)
        ORDER BY created_at DESC
        LIMIT 1000
    ");
    $stmt->bind_param('iii', $tenantId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $disputes = [];
    while ($row = $result->fetch_assoc()) {
        $disputes[] = $row;
    }
    $stmt->close();
    
    return $disputes;
}

/**
 * Obtiene valoraciones del usuario
 */
function getUserRatings($conn, $userId, $tenantId) {
    $stmt = $conn->prepare("
        SELECT * FROM ratings
        WHERE tenant_id = ? AND (rated_by = ? OR rated_user_id = ?)
        ORDER BY created_at DESC
        LIMIT 1000
    ");
    $stmt->bind_param('iii', $tenantId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ratings = [];
    while ($row = $result->fetch_assoc()) {
        $ratings[] = $row;
    }
    $stmt->close();
    
    return $ratings;
}

/**
 * Obtiene notificaciones del usuario
 */
function getUserNotifications($conn, $userId, $tenantId) {
    $stmt = $conn->prepare("
        SELECT * FROM notification_preferences
        WHERE tenant_id = ? AND user_id = ?
    ");
    $stmt->bind_param('ii', $tenantId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
    
    return $notifications;
}

/**
 * Obtiene logs de auditoría del usuario
 */
function getUserAuditLogs($conn, $userId, $tenantId) {
    $stmt = $conn->prepare("
        SELECT * FROM audit_logs
        WHERE tenant_id = ? AND user_id = ?
        ORDER BY created_at DESC
        LIMIT 1000
    ");
    $stmt->bind_param('ii', $tenantId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    $stmt->close();
    
    return $logs;
}

/**
 * Obtiene datos básicos del usuario
 */
function getUserData($conn, $userId) {
    $stmt = $conn->prepare("SELECT id, email, phone, locale FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    return $user;
}

/**
 * Anonimiza datos personales del usuario
 */
function anonymizeUserData($conn, $userId, $tenantId) {
    $anonymized = [];
    $preserved = [];
    
    // Anonimizar usuario global
    $anonEmail = "deleted_{$userId}_" . time() . "@deleted.local";
    $anonPhone = null;
    
    $updateUser = $conn->prepare("
        UPDATE users
        SET email = ?, phone = NULL
        WHERE id = ?
    ");
    $updateUser->bind_param('si', $anonEmail, $userId);
    $updateUser->execute();
    $updateUser->close();
    $anonymized[] = 'users';
    
    // Anonimizar empleados
    $updateEmployees = $conn->prepare("
        UPDATE own_employees
        SET first_name = 'Deleted',
            last_name = 'User',
            email = ?,
            phone = NULL,
            address = NULL
        WHERE tenant_id = ? AND id IN (
            SELECT own_employee_id FROM tenant_users
            WHERE user_id = ? AND tenant_id = ?
        )
    ");
    $updateEmployees->bind_param('siii', $anonEmail, $tenantId, $userId, $tenantId);
    $updateEmployees->execute();
    $updateEmployees->close();
    $anonymized[] = 'own_employees';
    
    // Anonimizar trabajadores
    $updateWorkers = $conn->prepare("
        UPDATE agency_workers
        SET first_name = 'Deleted',
            last_name = 'User',
            email = ?,
            phone = NULL,
            address = NULL
        WHERE tenant_id = ? AND id IN (
            SELECT agency_worker_id FROM tenant_users
            WHERE user_id = ? AND tenant_id = ?
        )
    ");
    $updateWorkers->bind_param('siii', $anonEmail, $tenantId, $userId, $tenantId);
    $updateWorkers->execute();
    $updateWorkers->close();
    $anonymized[] = 'agency_workers';
    
    // Preservar work_logs, shifts, disputes, ratings (solo anonimizar referencias)
    // Estos datos son necesarios para integridad contable y legal
    $preserved[] = 'work_logs';
    $preserved[] = 'shifts';
    $preserved[] = 'disputes';
    $preserved[] = 'ratings';
    
    // Eliminar preferencias de notificaciones
    $deleteNotifications = $conn->prepare("
        DELETE FROM notification_preferences
        WHERE tenant_id = ? AND user_id = ?
    ");
    $deleteNotifications->bind_param('ii', $tenantId, $userId);
    $deleteNotifications->execute();
    $deleteNotifications->close();
    $anonymized[] = 'notification_preferences';
    
    return [
        'anonymized' => $anonymized,
        'preserved' => $preserved
    ];
}

