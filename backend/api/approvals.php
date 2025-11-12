<?php
/**
 * API Aprobaciones de Work Logs
 * 
 * Endpoints:
 * - GET /api/approvals - Listar work logs pendientes de aprobación
 * - PUT /api/approvals/{work_log_id}/approve - Aprobar work log
 * - PUT /api/approvals/{work_log_id}/reject - Rechazar work log
 * - GET /api/approvals/pending - Obtener conteo de pendientes
 * - POST /api/approvals/batch - Aprobar/rechazar múltiples work logs
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
        $action = 'approvals.view';
    } elseif ($method === 'PUT' || $method === 'POST') {
        $action = 'approvals.approve';
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
    $workLogId = null;
    $subAction = null;
    
    if (count($pathParts) >= 2) {
        if (is_numeric($pathParts[1])) {
            $workLogId = (int)$pathParts[1];
            if (isset($pathParts[2])) {
                $subAction = $pathParts[2];
            }
        } elseif ($pathParts[1] === 'pending') {
            $subAction = 'pending';
        } elseif ($pathParts[1] === 'batch') {
            $subAction = 'batch';
        }
    }
    
    // Routing
    switch ($method) {
        case 'GET':
            if ($workLogId) {
                getWorkLog($conn, $tenantId, $workLogId);
            } elseif ($subAction === 'pending') {
                getPendingCount($conn, $tenantId);
            } else {
                listPendingApprovals($conn, $tenantId);
            }
            break;
            
        case 'PUT':
            if ($workLogId && $subAction === 'approve') {
                approveWorkLog($conn, $tenantId, $workLogId);
            } elseif ($workLogId && $subAction === 'reject') {
                rejectWorkLog($conn, $tenantId, $workLogId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.work_log_id_required',
                    'error_key' => 'validation.work_log_id_required',
                    'message' => t('validation.work_log_id_required')
                ]);
            }
            break;
            
        case 'POST':
            if ($subAction === 'batch') {
                batchApprove($conn, $tenantId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.action_required',
                    'error_key' => 'validation.action_required',
                    'message' => t('validation.action_required')
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
 * Listar work logs pendientes de aprobación
 */
function listPendingApprovals($conn, $tenantId) {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
    $offset = ($page - 1) * $pageSize;
    
    $where = ["wl.tenant_id = ?", "wl.status = 'submitted'"];
    $params = [$tenantId];
    $types = 'i';
    
    if (isset($_GET['employee_type'])) {
        $where[] = "wl.employee_type = ?";
        $params[] = $_GET['employee_type'];
        $types .= 's';
    }
    
    if (isset($_GET['from'])) {
        $where[] = "wl.date >= ?";
        $params[] = $_GET['from'];
        $types .= 's';
    }
    
    if (isset($_GET['to'])) {
        $where[] = "wl.date <= ?";
        $params[] = $_GET['to'];
        $types .= 's';
    }
    
    if (isset($_GET['project_id'])) {
        $where[] = "wl.project_id = ?";
        $params[] = (int)$_GET['project_id'];
        $types .= 'i';
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Contar total
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM work_logs wl
        WHERE $whereClause
    ");
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Obtener work logs
    $stmt = $conn->prepare("
        SELECT 
            wl.id,
            wl.tenant_id,
            wl.employee_type,
            wl.own_employee_id,
            wl.agency_worker_id,
            wl.project_id,
            wl.shift_id,
            wl.date,
            wl.clock_in,
            wl.clock_out,
            wl.breaks_minutes,
            wl.status,
            wl.notes,
            wl.created_at,
            wl.updated_at,
            CASE 
                WHEN wl.employee_type = 'own_employee' THEN e.first_name
                WHEN wl.employee_type = 'agency_worker' THEN w.first_name
            END as employee_first_name,
            CASE 
                WHEN wl.employee_type = 'own_employee' THEN e.last_name
                WHEN wl.employee_type = 'agency_worker' THEN w.last_name
            END as employee_last_name,
            CASE 
                WHEN wl.employee_type = 'own_employee' THEN e.employee_code
                WHEN wl.employee_type = 'agency_worker' THEN w.worker_code
            END as employee_code,
            p.name as project_name,
            t.name as agency_name,
            wl.agency_worker_id IS NOT NULL as requires_co_approval
        FROM work_logs wl
        LEFT JOIN own_employees e ON wl.employee_type = 'own_employee' AND wl.own_employee_id = e.id
        LEFT JOIN agency_workers w ON wl.employee_type = 'agency_worker' AND wl.agency_worker_id = w.id
        LEFT JOIN tenants t ON w.tenant_id = t.id
        LEFT JOIN projects p ON wl.project_id = p.id
        WHERE $whereClause
        ORDER BY wl.date DESC, wl.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $pageSize;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $workLogs = [];
    while ($row = $result->fetch_assoc()) {
        $workLogs[] = formatWorkLog($row);
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $workLogs,
        'page' => $page,
        'page_size' => $pageSize,
        'total' => $total,
        'total_pages' => ceil($total / $pageSize)
    ]);
}

/**
 * Obtener conteo de work logs pendientes
 */
function getPendingCount($conn, $tenantId) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM work_logs
        WHERE tenant_id = ? AND status = 'submitted'
    ");
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'count' => (int)$row['count']
        ]
    ]);
}

/**
 * Aprobar work log
 */
function approveWorkLog($conn, $tenantId, $workLogId) {
    // Verificar que existe y está pendiente
    $stmt = $conn->prepare("
        SELECT * FROM work_logs 
        WHERE id = ? AND tenant_id = ? AND status = 'submitted'
    ");
    $stmt->bind_param('ii', $workLogId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $workLog = $result->fetch_assoc();
    $stmt->close();
    
    if (!$workLog) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'approvals.work_log_not_found',
            'error_key' => 'approvals.work_log_not_found',
            'message' => t('approvals.work_log_not_found')
        ]);
        return;
    }
    
    // Verificar co-aprobación si es trabajador de agencia
    $requiresCoApproval = $workLog['employee_type'] === 'agency_worker';
    
    if ($requiresCoApproval) {
        // Verificar si ya fue aprobado por la empresa
        // Si requiere co-aprobación, verificar que el usuario actual es de la temporera
        $input = json_decode(file_get_contents('php://input'), true);
        $isCoApproval = isset($input['is_co_approval']) && $input['is_co_approval'];
        
        // Obtener tenant del usuario actual
        $userId = $GLOBALS['USER_ID'];
        if ($userId) {
            $stmt = $conn->prepare("
                SELECT t.org_type FROM tenant_users tu
                INNER JOIN tenants t ON tu.tenant_id = t.id
                WHERE tu.user_id = ? AND tu.tenant_id = ?
            ");
            $stmt->bind_param('ii', $userId, $tenantId);
            $stmt->execute();
            $result = $stmt->get_result();
            $userTenant = $result->fetch_assoc();
            $stmt->close();
            
            // Si el usuario es de temporera, es co-aprobación
            if ($userTenant && $userTenant['org_type'] === 'temporera') {
                $isCoApproval = true;
            }
        }
        
        // Si requiere co-aprobación y no es co-aprobación, solo marcar como aprobado por empresa
        // (la co-aprobación se hace desde la temporera)
        if (!$isCoApproval) {
            // Aprobación de empresa - solo actualizar status si no requiere co-aprobación
            // Por ahora, aprobar directamente (en producción se implementaría lógica de co-aprobación)
        }
    }
    
    // Actualizar work log
    $userId = $GLOBALS['USER_ID'];
    $stmt = $conn->prepare("
        UPDATE work_logs SET
            status = 'approved',
            approved_by = ?,
            approved_at = NOW()
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->bind_param('iii', $userId, $workLogId, $tenantId);
    $stmt->execute();
    $stmt->close();
    
    // Obtener work log actualizado
    getWorkLog($conn, $tenantId, $workLogId);
}

/**
 * Rechazar work log
 */
function rejectWorkLog($conn, $tenantId, $workLogId) {
    // Verificar que existe y está pendiente
    $stmt = $conn->prepare("
        SELECT * FROM work_logs 
        WHERE id = ? AND tenant_id = ? AND status = 'submitted'
    ");
    $stmt->bind_param('ii', $workLogId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $workLog = $result->fetch_assoc();
    $stmt->close();
    
    if (!$workLog) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'approvals.work_log_not_found',
            'error_key' => 'approvals.work_log_not_found',
            'message' => t('approvals.work_log_not_found')
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $rejectionReason = $input['reason'] ?? null;
    
    // Actualizar work log
    $userId = $GLOBALS['USER_ID'];
    $stmt = $conn->prepare("
        UPDATE work_logs SET
            status = 'rejected',
            approved_by = ?,
            approved_at = NOW(),
            notes = CONCAT(COALESCE(notes, ''), IF(notes IS NOT NULL, ' | ', ''), ?)
        WHERE id = ? AND tenant_id = ?
    ");
    
    $rejectionNote = $rejectionReason ? "Rechazado: " . $rejectionReason : "Rechazado";
    $stmt->bind_param('issi', $userId, $rejectionNote, $workLogId, $tenantId);
    $stmt->execute();
    $stmt->close();
    
    // Obtener work log actualizado
    getWorkLog($conn, $tenantId, $workLogId);
}

/**
 * Aprobar/rechazar múltiples work logs
 */
function batchApprove($conn, $tenantId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['work_log_ids']) || !is_array($input['work_log_ids'])) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'validation.work_log_ids_required',
            'error_key' => 'validation.work_log_ids_required',
            'message' => t('validation.work_log_ids_required')
        ]);
        return;
    }
    
    if (empty($input['action']) || !in_array($input['action'], ['approve', 'reject'])) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'validation.action_required',
            'error_key' => 'validation.action_required',
            'message' => t('validation.action_required')
        ]);
        return;
    }
    
    $workLogIds = array_map('intval', $input['work_log_ids']);
    $action = $input['action'];
    $rejectionReason = $input['reason'] ?? null;
    $userId = $GLOBALS['USER_ID'];
    
    $placeholders = implode(',', array_fill(0, count($workLogIds), '?'));
    $types = str_repeat('i', count($workLogIds));
    
    if ($action === 'approve') {
        $stmt = $conn->prepare("
            UPDATE work_logs SET
                status = 'approved',
                approved_by = ?,
                approved_at = NOW()
            WHERE id IN ($placeholders) AND tenant_id = ? AND status = 'submitted'
        ");
        $params = array_merge([$userId], $workLogIds, [$tenantId]);
        $types = 'i' . $types . 'i';
    } else {
        $rejectionNote = $rejectionReason ? "Rechazado: " . $rejectionReason : "Rechazado";
        $stmt = $conn->prepare("
            UPDATE work_logs SET
                status = 'rejected',
                approved_by = ?,
                approved_at = NOW(),
                notes = CONCAT(COALESCE(notes, ''), IF(notes IS NOT NULL, ' | ', ''), ?)
            WHERE id IN ($placeholders) AND tenant_id = ? AND status = 'submitted'
        ");
        $params = array_merge([$userId, $rejectionNote], $workLogIds, [$tenantId]);
        $types = 'iss' . $types . 'i';
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => t('approvals.batch_success', ['count' => $affected, 'action' => $action === 'approve' ? 'aprobados' : 'rechazados']),
        'affected' => $affected
    ]);
}

/**
 * Obtener un work log
 */
function getWorkLog($conn, $tenantId, $workLogId) {
    $stmt = $conn->prepare("
        SELECT 
            wl.id,
            wl.tenant_id,
            wl.employee_type,
            wl.own_employee_id,
            wl.agency_worker_id,
            wl.project_id,
            wl.shift_id,
            wl.date,
            wl.clock_in,
            wl.clock_out,
            wl.breaks_minutes,
            wl.status,
            wl.notes,
            wl.approved_by,
            wl.approved_at,
            wl.created_at,
            wl.updated_at,
            CASE 
                WHEN wl.employee_type = 'own_employee' THEN e.first_name
                WHEN wl.employee_type = 'agency_worker' THEN w.first_name
            END as employee_first_name,
            CASE 
                WHEN wl.employee_type = 'own_employee' THEN e.last_name
                WHEN wl.employee_type = 'agency_worker' THEN w.last_name
            END as employee_last_name,
            CASE 
                WHEN wl.employee_type = 'own_employee' THEN e.employee_code
                WHEN wl.employee_type = 'agency_worker' THEN w.worker_code
            END as employee_code,
            p.name as project_name,
            t.name as agency_name,
            u.first_name as approver_first_name,
            u.last_name as approver_last_name
        FROM work_logs wl
        LEFT JOIN own_employees e ON wl.employee_type = 'own_employee' AND wl.own_employee_id = e.id
        LEFT JOIN agency_workers w ON wl.employee_type = 'agency_worker' AND wl.agency_worker_id = w.id
        LEFT JOIN tenants t ON w.tenant_id = t.id
        LEFT JOIN projects p ON wl.project_id = p.id
        LEFT JOIN users u ON wl.approved_by = u.id
        WHERE wl.id = ? AND wl.tenant_id = ?
    ");
    $stmt->bind_param('ii', $workLogId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $workLog = $result->fetch_assoc();
    $stmt->close();
    
    if (!$workLog) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'approvals.work_log_not_found',
            'error_key' => 'approvals.work_log_not_found',
            'message' => t('approvals.work_log_not_found')
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => formatWorkLog($workLog)
    ]);
}

/**
 * Formatear work log para respuesta
 */
function formatWorkLog($row) {
    // Calcular horas trabajadas
    $hours = 0;
    if ($row['clock_in'] && $row['clock_out']) {
        $start = new DateTime($row['clock_in']);
        $end = new DateTime($row['clock_out']);
        $diff = $start->diff($end);
        $hours = $diff->h + ($diff->i / 60) - ($row['breaks_minutes'] / 60);
        $hours = max(0, $hours);
    }
    
    return [
        'id' => (int)$row['id'],
        'tenant_id' => (int)$row['tenant_id'],
        'employee_type' => $row['employee_type'],
        'own_employee_id' => $row['own_employee_id'] ? (int)$row['own_employee_id'] : null,
        'agency_worker_id' => $row['agency_worker_id'] ? (int)$row['agency_worker_id'] : null,
        'project_id' => $row['project_id'] ? (int)$row['project_id'] : null,
        'shift_id' => $row['shift_id'] ? (int)$row['shift_id'] : null,
        'date' => $row['date'],
        'clock_in' => $row['clock_in'],
        'clock_out' => $row['clock_out'],
        'breaks_minutes' => (int)$row['breaks_minutes'],
        'hours' => round($hours, 2),
        'status' => $row['status'],
        'notes' => $row['notes'],
        'approved_by' => $row['approved_by'] ? (int)$row['approved_by'] : null,
        'approved_at' => $row['approved_at'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
        'employee_name' => trim(($row['employee_first_name'] ?? '') . ' ' . ($row['employee_last_name'] ?? '')),
        'employee_code' => $row['employee_code'],
        'project_name' => $row['project_name'],
        'agency_name' => $row['agency_name'],
        'approver_name' => $row['approver_first_name'] ? trim(($row['approver_first_name'] ?? '') . ' ' . ($row['approver_last_name'] ?? '')) : null,
        'requires_co_approval' => isset($row['requires_co_approval']) ? (bool)$row['requires_co_approval'] : ($row['employee_type'] === 'agency_worker')
    ];
}

