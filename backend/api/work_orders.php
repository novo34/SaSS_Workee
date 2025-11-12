<?php
/**
 * API Órdenes de Trabajo (OT)
 * 
 * Endpoints:
 * - GET /api/work-orders - Listar órdenes de trabajo
 * - POST /api/work-orders - Crear orden de trabajo
 * - GET /api/work-orders/{id} - Obtener orden de trabajo
 * - PUT /api/work-orders/{id} - Actualizar orden de trabajo
 * - DELETE /api/work-orders/{id} - Eliminar orden de trabajo
 * - GET /api/work-orders/{id}/tasks - Obtener tareas de OT
 * - POST /api/work-orders/{id}/tasks - Agregar tarea
 * - PUT /api/work-orders/{id}/tasks/{task_id} - Actualizar tarea
 * - DELETE /api/work-orders/{id}/tasks/{task_id} - Eliminar tarea
 * - GET /api/work-orders/{id}/checklist - Obtener checklist
 * - POST /api/work-orders/{id}/checklist - Agregar item al checklist
 * - DELETE /api/work-orders/{id}/checklist/{item_id} - Eliminar item
 * - GET /api/work-orders/{id}/materials - Obtener materiales
 * - POST /api/work-orders/{id}/materials - Agregar material
 * - PUT /api/work-orders/{id}/materials/{material_id} - Actualizar material
 * - DELETE /api/work-orders/{id}/materials/{material_id} - Eliminar material
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
        $action = 'work_orders.create';
    } elseif ($method === 'PUT') {
        $action = 'work_orders.update';
    } elseif ($method === 'DELETE') {
        $action = 'work_orders.delete';
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
    $workOrderId = null;
    $subResource = null;
    $subResourceId = null;
    
    if (count($pathParts) >= 2 && is_numeric($pathParts[1])) {
        $workOrderId = (int)$pathParts[1];
        if (isset($pathParts[2])) {
            $subResource = $pathParts[2];
            if (isset($pathParts[3]) && is_numeric($pathParts[3])) {
                $subResourceId = (int)$pathParts[3];
            }
        }
    }
    
    // Routing
    switch ($method) {
        case 'GET':
            if ($workOrderId && $subResource === 'pdf') {
                generateWorkOrderPDF($conn, $tenantId, $workOrderId);
            } elseif ($workOrderId && $subResource === 'tasks') {
                listWorkOrderTasks($conn, $tenantId, $workOrderId);
            } elseif ($workOrderId && $subResource === 'checklist') {
                listWorkOrderChecklist($conn, $tenantId, $workOrderId);
            } elseif ($workOrderId && $subResource === 'materials') {
                listWorkOrderMaterials($conn, $tenantId, $workOrderId);
            } elseif ($workOrderId) {
                getWorkOrder($conn, $tenantId, $workOrderId);
            } else {
                listWorkOrders($conn, $tenantId);
            }
            break;
            
        case 'POST':
            if ($workOrderId && $subResource === 'tasks') {
                addWorkOrderTask($conn, $tenantId, $workOrderId);
            } elseif ($workOrderId && $subResource === 'checklist') {
                addWorkOrderChecklistItem($conn, $tenantId, $workOrderId);
            } elseif ($workOrderId && $subResource === 'materials') {
                addWorkOrderMaterial($conn, $tenantId, $workOrderId);
            } else {
                createWorkOrder($conn, $tenantId);
            }
            break;
            
        case 'PUT':
            if ($workOrderId && $subResource === 'tasks' && $subResourceId) {
                updateWorkOrderTask($conn, $tenantId, $workOrderId, $subResourceId);
            } elseif ($workOrderId && $subResource === 'checklist' && $subResourceId) {
                updateWorkOrderChecklistItem($conn, $tenantId, $workOrderId, $subResourceId);
            } elseif ($workOrderId && $subResource === 'materials' && $subResourceId) {
                updateWorkOrderMaterial($conn, $tenantId, $workOrderId, $subResourceId);
            } elseif ($workOrderId) {
                updateWorkOrder($conn, $tenantId, $workOrderId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.work_order_id_required',
                    'error_key' => 'validation.work_order_id_required',
                    'message' => t('validation.work_order_id_required')
                ]);
            }
            break;
            
        case 'DELETE':
            if ($workOrderId && $subResource === 'tasks' && $subResourceId) {
                deleteWorkOrderTask($conn, $tenantId, $workOrderId, $subResourceId);
            } elseif ($workOrderId && $subResource === 'checklist' && $subResourceId) {
                deleteWorkOrderChecklistItem($conn, $tenantId, $workOrderId, $subResourceId);
            } elseif ($workOrderId && $subResource === 'materials' && $subResourceId) {
                deleteWorkOrderMaterial($conn, $tenantId, $workOrderId, $subResourceId);
            } elseif ($workOrderId) {
                deleteWorkOrder($conn, $tenantId, $workOrderId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.work_order_id_required',
                    'error_key' => 'validation.work_order_id_required',
                    'message' => t('validation.work_order_id_required')
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'errors.method_not_allowed',
                'error_key' => 'errors.method_not_allowed',
                'message' => t('errors.method_not_allowed')
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'errors.server_error',
        'error_key' => 'errors.server_error',
        'message' => t('errors.server_error'),
        'debug' => Env::get('APP_ENV') === 'development' ? $e->getMessage() : null
    ]);
}

/**
 * Listar órdenes de trabajo
 */
function listWorkOrders($conn, $tenantId) {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
    $offset = ($page - 1) * $pageSize;
    
    $where = ["wo.tenant_id = ?"];
    $params = [$tenantId];
    $types = 'i';
    
    if (isset($_GET['status'])) {
        $where[] = "wo.status = ?";
        $params[] = $_GET['status'];
        $types .= 's';
    }
    
    if (isset($_GET['client_id'])) {
        $where[] = "wo.client_id = ?";
        $params[] = (int)$_GET['client_id'];
        $types .= 'i';
    }
    
    if (isset($_GET['project_id'])) {
        $where[] = "wo.project_id = ?";
        $params[] = (int)$_GET['project_id'];
        $types .= 'i';
    }
    
    if (isset($_GET['from'])) {
        $where[] = "wo.start_planned >= ?";
        $params[] = $_GET['from'];
        $types .= 's';
    }
    
    if (isset($_GET['to'])) {
        $where[] = "wo.end_planned <= ?";
        $params[] = $_GET['to'];
        $types .= 's';
    }
    
    if (isset($_GET['q']) && !empty($_GET['q'])) {
        $search = '%' . $_GET['q'] . '%';
        $where[] = "(wo.code LIKE ? OR wo.title LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $types .= 'ss';
    }
    
    $sortBy = $_GET['sort_by'] ?? 'created_at';
    $sortDir = strtoupper($_GET['sort_dir'] ?? 'DESC');
    if (!in_array($sortDir, ['ASC', 'DESC'])) {
        $sortDir = 'DESC';
    }
    $allowedSort = ['id', 'title', 'status', 'start_planned', 'end_planned', 'created_at'];
    if (!in_array($sortBy, $allowedSort)) {
        $sortBy = 'created_at';
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Contar total
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM work_orders wo
        WHERE $whereClause
    ");
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Obtener órdenes
    $stmt = $conn->prepare("
        SELECT 
            wo.id,
            wo.tenant_id,
            wo.code,
            wo.title,
            wo.client_id,
            wo.project_id,
            wo.location,
            wo.start_planned,
            wo.end_planned,
            wo.start_actual,
            wo.end_actual,
            wo.status,
            wo.scope_text,
            wo.safety_notes,
            wo.attachments_json,
            wo.created_at,
            wo.updated_at,
            c.name as client_name,
            p.name as project_name
        FROM work_orders wo
        LEFT JOIN clients c ON wo.client_id = c.id
        LEFT JOIN projects p ON wo.project_id = p.id
        WHERE $whereClause
        ORDER BY wo.$sortBy $sortDir
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $pageSize;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $workOrders = [];
    while ($row = $result->fetch_assoc()) {
        $workOrders[] = formatWorkOrder($row);
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $workOrders,
        'page' => $page,
        'page_size' => $pageSize,
        'total' => $total,
        'total_pages' => ceil($total / $pageSize),
        'sort_by' => $sortBy,
        'sort_dir' => $sortDir
    ]);
}

/**
 * Obtener una orden de trabajo
 */
function getWorkOrder($conn, $tenantId, $workOrderId) {
    $stmt = $conn->prepare("
        SELECT 
            wo.*,
            c.name as client_name,
            p.name as project_name
        FROM work_orders wo
        LEFT JOIN clients c ON wo.client_id = c.id
        LEFT JOIN projects p ON wo.project_id = p.id
        WHERE wo.id = ? AND wo.tenant_id = ?
    ");
    $stmt->bind_param('ii', $workOrderId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $workOrder = $result->fetch_assoc();
    $stmt->close();
    
    if (!$workOrder) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'work_orders.not_found',
            'error_key' => 'work_orders.not_found',
            'message' => t('work_orders.not_found')
        ]);
        return;
    }
    
    // Obtener tareas, checklist y materiales
    $workOrder['tasks'] = getWorkOrderTasksData($conn, $workOrderId);
    $workOrder['checklist'] = getWorkOrderChecklistData($conn, $workOrderId);
    $workOrder['materials'] = getWorkOrderMaterialsData($conn, $workOrderId);
    
    echo json_encode([
        'success' => true,
        'data' => formatWorkOrder($workOrder)
    ]);
}

/**
 * Crear orden de trabajo
 */
function createWorkOrder($conn, $tenantId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $errors = [];
    
    if (empty($input['title'])) {
        $errors[] = ['field' => 'title', 'error' => 'validation.required'];
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'validation.failed',
            'error_key' => 'validation.failed',
            'message' => t('validation.failed'),
            'errors' => $errors
        ]);
        return;
    }
    
    // Generar código si no se proporciona
    $code = $input['code'] ?? null;
    if (!$code) {
        $stmt = $conn->prepare("SELECT COUNT(*) + 1 as next_num FROM work_orders WHERE tenant_id = ?");
        $stmt->bind_param('i', $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $nextNum = $result->fetch_assoc()['next_num'];
        $stmt->close();
        $code = 'OT-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
    }
    
    // Insertar orden
    $stmt = $conn->prepare("
        INSERT INTO work_orders (
            tenant_id, code, title, client_id, project_id, location,
            start_planned, end_planned, status, scope_text, safety_notes, attachments_json
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $status = $input['status'] ?? 'draft';
    $scopeText = $input['scope_text'] ?? null;
    $safetyNotes = $input['safety_notes'] ?? null;
    $attachmentsJson = isset($input['attachments']) ? json_encode($input['attachments']) : null;
    
    $stmt->bind_param(
        'issississsss',
        $tenantId,
        $code,
        $input['title'],
        $input['client_id'] ?? null,
        $input['project_id'] ?? null,
        $input['location'] ?? null,
        $input['start_planned'] ?? null,
        $input['end_planned'] ?? null,
        $status,
        $scopeText,
        $safetyNotes,
        $attachmentsJson
    );
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'work_orders.create_failed',
            'error_key' => 'work_orders.create_failed',
            'message' => t('work_orders.create_failed')
        ]);
        $stmt->close();
        return;
    }
    
    $newWorkOrderId = $conn->insert_id;
    $stmt->close();
    
    // Obtener orden creada
    getWorkOrder($conn, $tenantId, $newWorkOrderId);
}

/**
 * Actualizar orden de trabajo
 */
function updateWorkOrder($conn, $tenantId, $workOrderId) {
    // Verificar que existe
    $stmt = $conn->prepare("SELECT * FROM work_orders WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $workOrderId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();
    
    if (!$existing) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'work_orders.not_found',
            'error_key' => 'work_orders.not_found',
            'message' => t('work_orders.not_found')
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Preparar datos actualizados
    $title = $input['title'] ?? $existing['title'];
    $code = $input['code'] ?? $existing['code'];
    $clientId = $input['client_id'] ?? $existing['client_id'];
    $projectId = $input['project_id'] ?? $existing['project_id'];
    $location = $input['location'] ?? $existing['location'];
    $startPlanned = $input['start_planned'] ?? $existing['start_planned'];
    $endPlanned = $input['end_planned'] ?? $existing['end_planned'];
    $startActual = $input['start_actual'] ?? $existing['start_actual'];
    $endActual = $input['end_actual'] ?? $existing['end_actual'];
    $status = $input['status'] ?? $existing['status'];
    $scopeText = $input['scope_text'] ?? $existing['scope_text'];
    $safetyNotes = $input['safety_notes'] ?? $existing['safety_notes'];
    $attachmentsJson = isset($input['attachments']) ? json_encode($input['attachments']) : $existing['attachments_json'];
    
    // Actualizar
    $stmt = $conn->prepare("
        UPDATE work_orders SET
            code = ?,
            title = ?,
            client_id = ?,
            project_id = ?,
            location = ?,
            start_planned = ?,
            end_planned = ?,
            start_actual = ?,
            end_actual = ?,
            status = ?,
            scope_text = ?,
            safety_notes = ?,
            attachments_json = ?
        WHERE id = ? AND tenant_id = ?
    ");
    
    $stmt->bind_param(
        'ssissississssii',
        $code,
        $title,
        $clientId,
        $projectId,
        $location,
        $startPlanned,
        $endPlanned,
        $startActual,
        $endActual,
        $status,
        $scopeText,
        $safetyNotes,
        $attachmentsJson,
        $workOrderId,
        $tenantId
    );
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'work_orders.update_failed',
            'error_key' => 'work_orders.update_failed',
            'message' => t('work_orders.update_failed')
        ]);
        $stmt->close();
        return;
    }
    
    $stmt->close();
    
    // Obtener orden actualizada
    getWorkOrder($conn, $tenantId, $workOrderId);
}

/**
 * Eliminar orden de trabajo
 */
function deleteWorkOrder($conn, $tenantId, $workOrderId) {
    // Verificar que existe
    $stmt = $conn->prepare("SELECT * FROM work_orders WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $workOrderId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $workOrder = $result->fetch_assoc();
    $stmt->close();
    
    if (!$workOrder) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'work_orders.not_found',
            'error_key' => 'work_orders.not_found',
            'message' => t('work_orders.not_found')
        ]);
        return;
    }
    
    // Eliminar (las tareas, checklist y materiales se eliminan por CASCADE)
    $stmt = $conn->prepare("DELETE FROM work_orders WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $workOrderId, $tenantId);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => t('work_orders.deleted_success')
    ]);
}

// Funciones para tareas, checklist y materiales (implementación similar)
// Por brevedad, incluyo solo las funciones principales

function listWorkOrderTasks($conn, $tenantId, $workOrderId) {
    $tasks = getWorkOrderTasksData($conn, $workOrderId);
    echo json_encode(['success' => true, 'data' => $tasks]);
}

function getWorkOrderTasksData($conn, $workOrderId) {
    $stmt = $conn->prepare("
        SELECT * FROM work_order_tasks
        WHERE work_order_id = ?
        ORDER BY pos ASC
    ");
    $stmt->bind_param('i', $workOrderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tasks = [];
    while ($row = $result->fetch_assoc()) {
        $tasks[] = [
            'id' => (int)$row['id'],
            'work_order_id' => (int)$row['work_order_id'],
            'pos' => (int)$row['pos'],
            'name' => $row['name'],
            'description' => $row['description'],
            'estimated_hours' => $row['estimated_hours'] ? (float)$row['estimated_hours'] : null,
            'required_skills' => $row['required_skills'] ? json_decode($row['required_skills'], true) : []
        ];
    }
    $stmt->close();
    return $tasks;
}

function addWorkOrderTask($conn, $tenantId, $workOrderId) {
    // Verificar que la OT existe y pertenece al tenant
    $stmt = $conn->prepare("SELECT id FROM work_orders WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $workOrderId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result->fetch_assoc()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'work_orders.not_found']);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Obtener siguiente posición
    $stmt = $conn->prepare("SELECT COALESCE(MAX(pos), 0) + 1 as next_pos FROM work_order_tasks WHERE work_order_id = ?");
    $stmt->bind_param('i', $workOrderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $nextPos = $result->fetch_assoc()['next_pos'];
    $stmt->close();
    
    $stmt = $conn->prepare("
        INSERT INTO work_order_tasks (work_order_id, pos, name, description, estimated_hours, required_skills)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $requiredSkills = isset($input['required_skills']) ? json_encode($input['required_skills']) : null;
    
    $stmt->bind_param(
        'iissds',
        $workOrderId,
        $nextPos,
        $input['name'],
        $input['description'] ?? null,
        $input['estimated_hours'] ?? null,
        $requiredSkills
    );
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'work_orders.task_add_failed']);
        $stmt->close();
        return;
    }
    
    $taskId = $conn->insert_id;
    $stmt->close();
    
    listWorkOrderTasks($conn, $tenantId, $workOrderId);
}

function updateWorkOrderTask($conn, $tenantId, $workOrderId, $taskId) {
    // Verificar que existe
    $stmt = $conn->prepare("
        SELECT wot.* FROM work_order_tasks wot
        INNER JOIN work_orders wo ON wot.work_order_id = wo.id
        WHERE wot.id = ? AND wot.work_order_id = ? AND wo.tenant_id = ?
    ");
    $stmt->bind_param('iii', $taskId, $workOrderId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();
    
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'work_orders.task_not_found']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $conn->prepare("
        UPDATE work_order_tasks SET
            pos = ?,
            name = ?,
            description = ?,
            estimated_hours = ?,
            required_skills = ?
        WHERE id = ? AND work_order_id = ?
    ");
    
    $requiredSkills = isset($input['required_skills']) ? json_encode($input['required_skills']) : $existing['required_skills'];
    
    $stmt->bind_param(
        'issdssi',
        $input['pos'] ?? $existing['pos'],
        $input['name'] ?? $existing['name'],
        $input['description'] ?? $existing['description'],
        $input['estimated_hours'] ?? $existing['estimated_hours'],
        $requiredSkills,
        $taskId,
        $workOrderId
    );
    
    $stmt->execute();
    $stmt->close();
    
    listWorkOrderTasks($conn, $tenantId, $workOrderId);
}

function deleteWorkOrderTask($conn, $tenantId, $workOrderId, $taskId) {
    // Verificar que existe
    $stmt = $conn->prepare("
        SELECT wot.* FROM work_order_tasks wot
        INNER JOIN work_orders wo ON wot.work_order_id = wo.id
        WHERE wot.id = ? AND wot.work_order_id = ? AND wo.tenant_id = ?
    ");
    $stmt->bind_param('iii', $taskId, $workOrderId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result->fetch_assoc()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'work_orders.task_not_found']);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    $stmt = $conn->prepare("DELETE FROM work_order_tasks WHERE id = ? AND work_order_id = ?");
    $stmt->bind_param('ii', $taskId, $workOrderId);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true, 'message' => t('work_orders.task_deleted_success')]);
}

function listWorkOrderChecklist($conn, $tenantId, $workOrderId) {
    $checklist = getWorkOrderChecklistData($conn, $workOrderId);
    echo json_encode(['success' => true, 'data' => $checklist]);
}

function getWorkOrderChecklistData($conn, $workOrderId) {
    $stmt = $conn->prepare("SELECT * FROM work_order_checklists WHERE work_order_id = ? ORDER BY id ASC");
    $stmt->bind_param('i', $workOrderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $checklist = [];
    while ($row = $result->fetch_assoc()) {
        $checklist[] = [
            'id' => (int)$row['id'],
            'work_order_id' => (int)$row['work_order_id'],
            'item' => $row['item'],
            'is_required' => (bool)$row['is_required']
        ];
    }
    $stmt->close();
    return $checklist;
}

function addWorkOrderChecklistItem($conn, $tenantId, $workOrderId) {
    // Verificar que la OT existe
    $stmt = $conn->prepare("SELECT id FROM work_orders WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $workOrderId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result->fetch_assoc()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'work_orders.not_found']);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $conn->prepare("
        INSERT INTO work_order_checklists (work_order_id, item, is_required)
        VALUES (?, ?, ?)
    ");
    
    $stmt->bind_param(
        'isi',
        $workOrderId,
        $input['item'],
        $input['is_required'] ?? 1
    );
    
    $stmt->execute();
    $stmt->close();
    
    listWorkOrderChecklist($conn, $tenantId, $workOrderId);
}

function updateWorkOrderChecklistItem($conn, $tenantId, $workOrderId, $itemId) {
    // Verificar que existe
    $stmt = $conn->prepare("
        SELECT woc.* FROM work_order_checklists woc
        INNER JOIN work_orders wo ON woc.work_order_id = wo.id
        WHERE woc.id = ? AND woc.work_order_id = ? AND wo.tenant_id = ?
    ");
    $stmt->bind_param('iii', $itemId, $workOrderId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();
    
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'work_orders.checklist_item_not_found']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $conn->prepare("
        UPDATE work_order_checklists SET
            item = ?,
            is_required = ?
        WHERE id = ? AND work_order_id = ?
    ");
    
    $stmt->bind_param(
        'siii',
        $input['item'] ?? $existing['item'],
        $input['is_required'] ?? $existing['is_required'],
        $itemId,
        $workOrderId
    );
    
    $stmt->execute();
    $stmt->close();
    
    listWorkOrderChecklist($conn, $tenantId, $workOrderId);
}

function deleteWorkOrderChecklistItem($conn, $tenantId, $workOrderId, $itemId) {
    // Verificar que existe
    $stmt = $conn->prepare("
        SELECT woc.* FROM work_order_checklists woc
        INNER JOIN work_orders wo ON woc.work_order_id = wo.id
        WHERE woc.id = ? AND woc.work_order_id = ? AND wo.tenant_id = ?
    ");
    $stmt->bind_param('iii', $itemId, $workOrderId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result->fetch_assoc()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'work_orders.checklist_item_not_found']);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    $stmt = $conn->prepare("DELETE FROM work_order_checklists WHERE id = ? AND work_order_id = ?");
    $stmt->bind_param('ii', $itemId, $workOrderId);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true, 'message' => t('work_orders.checklist_item_deleted_success')]);
}

function listWorkOrderMaterials($conn, $tenantId, $workOrderId) {
    $materials = getWorkOrderMaterialsData($conn, $workOrderId);
    echo json_encode(['success' => true, 'data' => $materials]);
}

function getWorkOrderMaterialsData($conn, $workOrderId) {
    $stmt = $conn->prepare("SELECT * FROM work_order_materials WHERE work_order_id = ? ORDER BY id ASC");
    $stmt->bind_param('i', $workOrderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $materials = [];
    while ($row = $result->fetch_assoc()) {
        $materials[] = [
            'id' => (int)$row['id'],
            'work_order_id' => (int)$row['work_order_id'],
            'name' => $row['name'],
            'unit' => $row['unit'],
            'qty_planned' => $row['qty_planned'] ? (float)$row['qty_planned'] : null,
            'qty_used' => $row['qty_used'] ? (float)$row['qty_used'] : null
        ];
    }
    $stmt->close();
    return $materials;
}

function addWorkOrderMaterial($conn, $tenantId, $workOrderId) {
    // Verificar que la OT existe
    $stmt = $conn->prepare("SELECT id FROM work_orders WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $workOrderId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result->fetch_assoc()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'work_orders.not_found']);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $conn->prepare("
        INSERT INTO work_order_materials (work_order_id, name, unit, qty_planned, qty_used)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param(
        'issdd',
        $workOrderId,
        $input['name'],
        $input['unit'] ?? null,
        $input['qty_planned'] ?? null,
        $input['qty_used'] ?? null
    );
    
    $stmt->execute();
    $stmt->close();
    
    listWorkOrderMaterials($conn, $tenantId, $workOrderId);
}

function updateWorkOrderMaterial($conn, $tenantId, $workOrderId, $materialId) {
    // Verificar que existe
    $stmt = $conn->prepare("
        SELECT wom.* FROM work_order_materials wom
        INNER JOIN work_orders wo ON wom.work_order_id = wo.id
        WHERE wom.id = ? AND wom.work_order_id = ? AND wo.tenant_id = ?
    ");
    $stmt->bind_param('iii', $materialId, $workOrderId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();
    
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'work_orders.material_not_found']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $conn->prepare("
        UPDATE work_order_materials SET
            name = ?,
            unit = ?,
            qty_planned = ?,
            qty_used = ?
        WHERE id = ? AND work_order_id = ?
    ");
    
    $stmt->bind_param(
        'ssddii',
        $input['name'] ?? $existing['name'],
        $input['unit'] ?? $existing['unit'],
        $input['qty_planned'] ?? $existing['qty_planned'],
        $input['qty_used'] ?? $existing['qty_used'],
        $materialId,
        $workOrderId
    );
    
    $stmt->execute();
    $stmt->close();
    
    listWorkOrderMaterials($conn, $tenantId, $workOrderId);
}

function deleteWorkOrderMaterial($conn, $tenantId, $workOrderId, $materialId) {
    // Verificar que existe
    $stmt = $conn->prepare("
        SELECT wom.* FROM work_order_materials wom
        INNER JOIN work_orders wo ON wom.work_order_id = wo.id
        WHERE wom.id = ? AND wom.work_order_id = ? AND wo.tenant_id = ?
    ");
    $stmt->bind_param('iii', $materialId, $workOrderId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result->fetch_assoc()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'work_orders.material_not_found']);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    $stmt = $conn->prepare("DELETE FROM work_order_materials WHERE id = ? AND work_order_id = ?");
    $stmt->bind_param('ii', $materialId, $workOrderId);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true, 'message' => t('work_orders.material_deleted_success')]);
}

/**
 * Formatear orden de trabajo para respuesta
 */
function formatWorkOrder($row) {
    $workOrder = [
        'id' => (int)$row['id'],
        'tenant_id' => (int)$row['tenant_id'],
        'code' => $row['code'],
        'title' => $row['title'],
        'client_id' => $row['client_id'] ? (int)$row['client_id'] : null,
        'client_name' => $row['client_name'] ?? null,
        'project_id' => $row['project_id'] ? (int)$row['project_id'] : null,
        'project_name' => $row['project_name'] ?? null,
        'location' => $row['location'],
        'start_planned' => $row['start_planned'],
        'end_planned' => $row['end_planned'],
        'start_actual' => $row['start_actual'],
        'end_actual' => $row['end_actual'],
        'status' => $row['status'],
        'scope_text' => $row['scope_text'],
        'safety_notes' => $row['safety_notes'],
        'attachments' => $row['attachments_json'] ? json_decode($row['attachments_json'], true) : [],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
    
    if (isset($row['tasks'])) {
        $workOrder['tasks'] = $row['tasks'];
    }
    
    if (isset($row['checklist'])) {
        $workOrder['checklist'] = $row['checklist'];
    }
    
    if (isset($row['materials'])) {
        $workOrder['materials'] = $row['materials'];
    }
    
    return $workOrder;
}

/**
 * Generar PDF de orden de trabajo
 */
function generateWorkOrderPDF($conn, $tenantId, $workOrderId) {
    require_once __DIR__ . '/../helpers/pdf_work_order.php';
    
    $result = generateWorkOrderPDFHelper($workOrderId, $conn, $tenantId);
    
    if (!$result['success']) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'work_orders.pdf_generation_failed',
            'error_key' => 'work_orders.pdf_generation_failed',
            'message' => $result['error'] ?? t('work_orders.pdf_generation_failed')
        ]);
        return;
    }
    
    // Si se generó un PDF, retornar URL
    if (isset($result['url'])) {
        echo json_encode([
            'success' => true,
            'data' => [
                'url' => $result['url'],
                'filename' => $result['filename'] ?? null,
                'file_path' => $result['file_path'] ?? null
            ]
        ]);
    } elseif (isset($result['html_path'])) {
        // Si solo se generó HTML, retornar URL del HTML
        echo json_encode([
            'success' => true,
            'data' => [
                'url' => $result['url'],
                'html_path' => $result['html_path'],
                'message' => $result['message'] ?? t('work_orders.pdf_html_generated')
            ]
        ]);
    }
}

