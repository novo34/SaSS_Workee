<?php
/**
 * API Asignaciones de Órdenes de Trabajo (Work Order Assignments)
 * 
 * Endpoints:
 * - GET /api/work-order-assignments - Listar asignaciones
 * - POST /api/work-order-assignments - Crear asignación
 * - GET /api/work-order-assignments/{id} - Obtener asignación
 * - PUT /api/work-order-assignments/{id} - Actualizar asignación
 * - DELETE /api/work-order-assignments/{id} - Eliminar asignación
 * - GET /api/work-order-assignments/work-order/{work_order_id} - Obtener asignaciones de una OT
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
    } elseif ($method === 'PUT') {
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
    $assignmentId = null;
    $subResource = null;
    $subResourceId = null;
    
    if (count($pathParts) >= 2) {
        if ($pathParts[1] === 'work-order' && isset($pathParts[2])) {
            $subResource = 'work-order';
            $subResourceId = (int)$pathParts[2];
        } elseif (is_numeric($pathParts[1])) {
            $assignmentId = (int)$pathParts[1];
        }
    }
    
    // Routing
    switch ($method) {
        case 'GET':
            if ($subResource === 'work-order' && $subResourceId) {
                listByWorkOrder($conn, $tenantId, $subResourceId);
            } elseif ($assignmentId) {
                getAssignment($conn, $tenantId, $assignmentId);
            } else {
                listAssignments($conn, $tenantId);
            }
            break;
            
        case 'POST':
            createAssignment($conn, $tenantId);
            break;
            
        case 'PUT':
            if ($assignmentId) {
                updateAssignment($conn, $tenantId, $assignmentId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.assignment_id_required',
                    'error_key' => 'validation.assignment_id_required',
                    'message' => t('validation.assignment_id_required')
                ]);
            }
            break;
            
        case 'DELETE':
            if ($assignmentId) {
                deleteAssignment($conn, $tenantId, $assignmentId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.assignment_id_required',
                    'error_key' => 'validation.assignment_id_required',
                    'message' => t('validation.assignment_id_required')
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
 * Listar asignaciones
 */
function listAssignments($conn, $tenantId) {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
    $offset = ($page - 1) * $pageSize;
    
    $where = ["wo.tenant_id = ?"];
    $params = [$tenantId];
    $types = 'i';
    
    if (isset($_GET['work_order_id'])) {
        $where[] = "woa.work_order_id = ?";
        $params[] = (int)$_GET['work_order_id'];
        $types .= 'i';
    }
    
    if (isset($_GET['group_id'])) {
        $where[] = "woa.group_id = ?";
        $params[] = (int)$_GET['group_id'];
        $types .= 'i';
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Contar total
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM work_order_assignments woa
        INNER JOIN work_orders wo ON woa.work_order_id = wo.id
        WHERE $whereClause
    ");
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Obtener asignaciones
    $stmt = $conn->prepare("
        SELECT 
            woa.id,
            woa.work_order_id,
            woa.group_id,
            woa.employee_type,
            woa.own_employee_id,
            woa.agency_worker_id,
            woa.tenant_agency_id,
            woa.start_at,
            woa.end_at,
            wo.code as work_order_code,
            wo.title as work_order_title,
            wg.name as group_name,
            CASE 
                WHEN woa.employee_type = 'own_employee' THEN e.first_name
                WHEN woa.employee_type = 'agency_worker' THEN w.first_name
            END as employee_first_name,
            CASE 
                WHEN woa.employee_type = 'own_employee' THEN e.last_name
                WHEN woa.employee_type = 'agency_worker' THEN w.last_name
            END as employee_last_name,
            t.name as agency_name
        FROM work_order_assignments woa
        INNER JOIN work_orders wo ON woa.work_order_id = wo.id
        LEFT JOIN work_groups wg ON woa.group_id = wg.id
        LEFT JOIN own_employees e ON woa.employee_type = 'own_employee' AND woa.own_employee_id = e.id
        LEFT JOIN agency_workers w ON woa.employee_type = 'agency_worker' AND woa.agency_worker_id = w.id
        LEFT JOIN tenants t ON woa.tenant_agency_id = t.id
        WHERE $whereClause
        ORDER BY woa.start_at DESC, woa.id DESC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $pageSize;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $assignments = [];
    while ($row = $result->fetch_assoc()) {
        $assignments[] = formatAssignment($row);
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $assignments,
        'page' => $page,
        'page_size' => $pageSize,
        'total' => $total,
        'total_pages' => ceil($total / $pageSize)
    ]);
}

/**
 * Listar asignaciones por orden de trabajo
 */
function listByWorkOrder($conn, $tenantId, $workOrderId) {
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
            woa.id,
            woa.work_order_id,
            woa.group_id,
            woa.employee_type,
            woa.own_employee_id,
            woa.agency_worker_id,
            woa.tenant_agency_id,
            woa.start_at,
            woa.end_at,
            wo.code as work_order_code,
            wo.title as work_order_title,
            wg.name as group_name,
            CASE 
                WHEN woa.employee_type = 'own_employee' THEN e.first_name
                WHEN woa.employee_type = 'agency_worker' THEN w.first_name
            END as employee_first_name,
            CASE 
                WHEN woa.employee_type = 'own_employee' THEN e.last_name
                WHEN woa.employee_type = 'agency_worker' THEN w.last_name
            END as employee_last_name,
            t.name as agency_name
        FROM work_order_assignments woa
        INNER JOIN work_orders wo ON woa.work_order_id = wo.id
        LEFT JOIN work_groups wg ON woa.group_id = wg.id
        LEFT JOIN own_employees e ON woa.employee_type = 'own_employee' AND woa.own_employee_id = e.id
        LEFT JOIN agency_workers w ON woa.employee_type = 'agency_worker' AND woa.agency_worker_id = w.id
        LEFT JOIN tenants t ON woa.tenant_agency_id = t.id
        WHERE woa.work_order_id = ? AND wo.tenant_id = ?
        ORDER BY woa.start_at ASC, woa.id ASC
    ");
    $stmt->bind_param('ii', $workOrderId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $assignments = [];
    while ($row = $result->fetch_assoc()) {
        $assignments[] = formatAssignment($row);
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $assignments
    ]);
}

/**
 * Obtener una asignación
 */
function getAssignment($conn, $tenantId, $assignmentId) {
    $stmt = $conn->prepare("
        SELECT 
            woa.id,
            woa.work_order_id,
            woa.group_id,
            woa.employee_type,
            woa.own_employee_id,
            woa.agency_worker_id,
            woa.tenant_agency_id,
            woa.start_at,
            woa.end_at,
            wo.code as work_order_code,
            wo.title as work_order_title,
            wg.name as group_name,
            CASE 
                WHEN woa.employee_type = 'own_employee' THEN e.first_name
                WHEN woa.employee_type = 'agency_worker' THEN w.first_name
            END as employee_first_name,
            CASE 
                WHEN woa.employee_type = 'own_employee' THEN e.last_name
                WHEN woa.employee_type = 'agency_worker' THEN w.last_name
            END as employee_last_name,
            t.name as agency_name
        FROM work_order_assignments woa
        INNER JOIN work_orders wo ON woa.work_order_id = wo.id
        LEFT JOIN work_groups wg ON woa.group_id = wg.id
        LEFT JOIN own_employees e ON woa.employee_type = 'own_employee' AND woa.own_employee_id = e.id
        LEFT JOIN agency_workers w ON woa.employee_type = 'agency_worker' AND woa.agency_worker_id = w.id
        LEFT JOIN tenants t ON woa.tenant_agency_id = t.id
        WHERE woa.id = ? AND wo.tenant_id = ?
    ");
    $stmt->bind_param('ii', $assignmentId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignment = $result->fetch_assoc();
    $stmt->close();
    
    if (!$assignment) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'work_order_assignments.not_found',
            'error_key' => 'work_order_assignments.not_found',
            'message' => t('work_order_assignments.not_found')
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => formatAssignment($assignment)
    ]);
}

/**
 * Crear asignación
 */
function createAssignment($conn, $tenantId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $errors = [];
    
    if (empty($input['work_order_id'])) {
        $errors[] = ['field' => 'work_order_id', 'error' => 'validation.required'];
    }
    
    if (empty($input['employee_type'])) {
        $errors[] = ['field' => 'employee_type', 'error' => 'validation.required'];
    } elseif (!in_array($input['employee_type'], ['own_employee', 'agency_worker'])) {
        $errors[] = ['field' => 'employee_type', 'error' => 'validation.invalid'];
    }
    
    if ($input['employee_type'] === 'own_employee' && empty($input['own_employee_id']) && empty($input['group_id'])) {
        $errors[] = ['field' => 'own_employee_id', 'error' => 'validation.required'];
    }
    
    if ($input['employee_type'] === 'agency_worker' && empty($input['agency_worker_id'])) {
        $errors[] = ['field' => 'agency_worker_id', 'error' => 'validation.required'];
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
    
    // Si se asigna un grupo, verificar que existe
    if (!empty($input['group_id'])) {
        $stmt = $conn->prepare("SELECT id FROM work_groups WHERE id = ? AND tenant_company_id = ?");
        $stmt->bind_param('ii', $input['group_id'], $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$result->fetch_assoc()) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'groups.not_found',
                'error_key' => 'groups.not_found',
                'message' => t('groups.not_found')
            ]);
            $stmt->close();
            return;
        }
        $stmt->close();
    }
    
    $stmt = $conn->prepare("
        INSERT INTO work_order_assignments (
            work_order_id, group_id, employee_type, own_employee_id, 
            agency_worker_id, tenant_agency_id, start_at, end_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $groupId = isset($input['group_id']) ? (int)$input['group_id'] : null;
    $ownEmployeeId = $input['employee_type'] === 'own_employee' && isset($input['own_employee_id']) ? (int)$input['own_employee_id'] : null;
    $agencyWorkerId = $input['employee_type'] === 'agency_worker' ? (int)$input['agency_worker_id'] : null;
    $tenantAgencyId = isset($input['tenant_agency_id']) ? (int)$input['tenant_agency_id'] : null;
    $startAt = $input['start_at'] ?? null;
    $endAt = $input['end_at'] ?? null;
    
    $stmt->bind_param(
        'iisiiiss',
        $input['work_order_id'],
        $groupId,
        $input['employee_type'],
        $ownEmployeeId,
        $agencyWorkerId,
        $tenantAgencyId,
        $startAt,
        $endAt
    );
    
    $stmt->execute();
    $assignmentId = $conn->insert_id;
    $stmt->close();
    
    getAssignment($conn, $tenantId, $assignmentId);
}

/**
 * Actualizar asignación
 */
function updateAssignment($conn, $tenantId, $assignmentId) {
    // Verificar que existe
    $stmt = $conn->prepare("
        SELECT woa.* FROM work_order_assignments woa
        INNER JOIN work_orders wo ON woa.work_order_id = wo.id
        WHERE woa.id = ? AND wo.tenant_id = ?
    ");
    $stmt->bind_param('ii', $assignmentId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();
    
    if (!$existing) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'work_order_assignments.not_found',
            'error_key' => 'work_order_assignments.not_found',
            'message' => t('work_order_assignments.not_found')
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $conn->prepare("
        UPDATE work_order_assignments SET
            group_id = ?,
            start_at = ?,
            end_at = ?
        WHERE id = ?
    ");
    
    $groupId = isset($input['group_id']) ? (int)$input['group_id'] : $existing['group_id'];
    $startAt = $input['start_at'] ?? $existing['start_at'];
    $endAt = $input['end_at'] ?? $existing['end_at'];
    
    $stmt->bind_param('issi', $groupId, $startAt, $endAt, $assignmentId);
    $stmt->execute();
    $stmt->close();
    
    getAssignment($conn, $tenantId, $assignmentId);
}

/**
 * Eliminar asignación
 */
function deleteAssignment($conn, $tenantId, $assignmentId) {
    // Verificar que existe
    $stmt = $conn->prepare("
        SELECT woa.* FROM work_order_assignments woa
        INNER JOIN work_orders wo ON woa.work_order_id = wo.id
        WHERE woa.id = ? AND wo.tenant_id = ?
    ");
    $stmt->bind_param('ii', $assignmentId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result->fetch_assoc()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'work_order_assignments.not_found',
            'error_key' => 'work_order_assignments.not_found',
            'message' => t('work_order_assignments.not_found')
        ]);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    $stmt = $conn->prepare("DELETE FROM work_order_assignments WHERE id = ?");
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => t('work_order_assignments.deleted_success')
    ]);
}

/**
 * Formatear asignación para respuesta
 */
function formatAssignment($row) {
    return [
        'id' => (int)$row['id'],
        'work_order_id' => (int)$row['work_order_id'],
        'group_id' => $row['group_id'] ? (int)$row['group_id'] : null,
        'employee_type' => $row['employee_type'],
        'own_employee_id' => $row['own_employee_id'] ? (int)$row['own_employee_id'] : null,
        'agency_worker_id' => $row['agency_worker_id'] ? (int)$row['agency_worker_id'] : null,
        'tenant_agency_id' => $row['tenant_agency_id'] ? (int)$row['tenant_agency_id'] : null,
        'start_at' => $row['start_at'],
        'end_at' => $row['end_at'],
        'work_order_code' => $row['work_order_code'],
        'work_order_title' => $row['work_order_title'],
        'group_name' => $row['group_name'],
        'employee_name' => trim(($row['employee_first_name'] ?? '') . ' ' . ($row['employee_last_name'] ?? '')),
        'agency_name' => $row['agency_name']
    ];
}

