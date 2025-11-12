<?php
/**
 * API Asignaciones
 * 
 * Endpoints:
 * - GET /api/assignments - Listar asignaciones
 * - POST /api/assignments - Crear asignación (desde propuesta aceptada)
 * - GET /api/assignments/{id} - Obtener asignación
 * - DELETE /api/assignments/{id} - Eliminar asignación
 * 
 * Nota: Las asignaciones se generan automáticamente al aceptar una propuesta,
 * pero también se pueden crear manualmente.
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
    // Obtener path desde variable global (inyectada por index.php) o desde PATH_INFO/REQUEST_URI
    $path = $GLOBALS['API_PATH'] ?? $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '';
    
    // Procesar middleware para extraer tenant_id
    $middlewareResult = TenantMiddleware::process();
    $tenantId = $middlewareResult['tenant_id'] ?? null;
    
    if (!$tenantId) {
        http_response_code(401);
        header('Content-Type: application/json');
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
        $action = 'assignments.view';
    } elseif ($method === 'POST') {
        $action = 'assignments.create';
    } elseif ($method === 'DELETE') {
        $action = 'assignments.delete';
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
    
    // Parsear path para obtener ID si existe
    $pathParts = explode('/', trim($path, '/'));
    $assignmentId = null;
    
    if (count($pathParts) >= 2 && is_numeric($pathParts[1])) {
        $assignmentId = (int)$pathParts[1];
    }
    
    // Routing
    switch ($method) {
        case 'GET':
            if ($assignmentId) {
                // Obtener una asignación
                getAssignment($conn, $tenantId, $assignmentId);
            } else {
                // Listar asignaciones
                listAssignments($conn, $tenantId);
            }
            break;
            
        case 'POST':
            // Crear asignación
            createAssignment($conn, $tenantId);
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
 * Generar asignaciones desde una propuesta aceptada
 * Esta función se llama automáticamente cuando se acepta una propuesta
 */
function generateAssignmentsFromProposal($conn, $proposalId, $tenantCompanyId) {
    // Obtener propuesta
    $stmt = $conn->prepare("
        SELECT sp.*, sr.date_from, sr.date_to, sr.location, sr.project_id
        FROM staffing_proposals sp
        INNER JOIN staffing_requests sr ON sp.request_id = sr.id
        WHERE sp.id = ? AND sr.tenant_company_id = ?
    ");
    $stmt->bind_param('ii', $proposalId, $tenantCompanyId);
    $stmt->execute();
    $result = $stmt->get_result();
    $proposal = $result->fetch_assoc();
    $stmt->close();
    
    if (!$proposal || $proposal['status'] !== 'accepted') {
        return ['success' => false, 'message' => 'Propuesta no encontrada o no aceptada'];
    }
    
    $proposalData = json_decode($proposal['proposal_json'], true);
    $candidates = $proposalData['candidates'] ?? [];
    
    $created = 0;
    $errors = [];
    
    // Crear shift (asignación) para cada candidato aceptado
    foreach ($candidates as $candidate) {
        if (empty($candidate['worker_id'])) {
            continue;
        }
        
        // Obtener información del trabajador
        $stmt = $conn->prepare("
            SELECT id, tenant_id 
            FROM agency_workers 
            WHERE id = ?
        ");
        $stmt->bind_param('i', $candidate['worker_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $worker = $result->fetch_assoc();
        $stmt->close();
        
        if (!$worker) {
            $errors[] = "Trabajador ID {$candidate['worker_id']} no encontrado";
            continue;
        }
        
        // Crear shift (asignación)
        $stmt = $conn->prepare("
            INSERT INTO shifts (
                tenant_id, 
                employee_type, 
                agency_worker_id,
                start_at, 
                end_at, 
                location,
                status
            ) VALUES (?, 'agency_worker', ?, ?, ?, ?, 'assigned')
        ");
        
        $stmt->bind_param(
            'iisss',
            $tenantCompanyId,
            $candidate['worker_id'],
            $proposal['date_from'],
            $proposal['date_to'],
            $proposal['location']
        );
        
        if ($stmt->execute()) {
            $created++;
        } else {
            $errors[] = "Error creando shift para trabajador ID {$candidate['worker_id']}: " . $stmt->error;
        }
        $stmt->close();
    }
    
    return [
        'success' => $created > 0,
        'created' => $created,
        'errors' => $errors
    ];
}

/**
 * Listar asignaciones
 */
function listAssignments($conn, $tenantId) {
    // Parámetros de paginación
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
    $offset = ($page - 1) * $pageSize;
    
    // Filtros
    $where = ["s.tenant_id = ?"];
    $params = [$tenantId];
    $types = 'i';
    
    // Filtro por fecha desde
    if (isset($_GET['from'])) {
        $where[] = "DATE(s.start_at) >= ?";
        $params[] = $_GET['from'];
        $types .= 's';
    }
    
    // Filtro por fecha hasta
    if (isset($_GET['to'])) {
        $where[] = "DATE(s.end_at) <= ?";
        $params[] = $_GET['to'];
        $types .= 's';
    }
    
    // Filtro por estado
    if (isset($_GET['status'])) {
        $where[] = "s.status = ?";
        $params[] = $_GET['status'];
        $types .= 's';
    }
    
    // Solo asignaciones (shifts con status assigned o in_progress)
    $where[] = "s.status IN ('assigned', 'in_progress')";
    
    $whereClause = implode(' AND ', $where);
    
    // Contar total
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM shifts s
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
            s.id,
            s.tenant_id,
            s.group_id,
            s.employee_type,
            s.own_employee_id,
            s.agency_worker_id,
            s.start_at,
            s.end_at,
            s.location,
            s.status,
            s.created_at,
            s.updated_at,
            g.name as group_name,
            CASE 
                WHEN s.employee_type = 'own_employee' THEN e.first_name
                WHEN s.employee_type = 'agency_worker' THEN w.first_name
            END as employee_first_name,
            CASE 
                WHEN s.employee_type = 'own_employee' THEN e.last_name
                WHEN s.employee_type = 'agency_worker' THEN w.last_name
            END as employee_last_name,
            t.name as agency_name
        FROM shifts s
        LEFT JOIN work_groups g ON s.group_id = g.id
        LEFT JOIN own_employees e ON s.employee_type = 'own_employee' AND s.own_employee_id = e.id
        LEFT JOIN agency_workers w ON s.employee_type = 'agency_worker' AND s.agency_worker_id = w.id
        LEFT JOIN tenants t ON w.tenant_id = t.id
        WHERE $whereClause
        ORDER BY s.start_at DESC
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
 * Obtener una asignación por ID
 */
function getAssignment($conn, $tenantId, $assignmentId) {
    $stmt = $conn->prepare("
        SELECT 
            s.id,
            s.tenant_id,
            s.group_id,
            s.employee_type,
            s.own_employee_id,
            s.agency_worker_id,
            s.start_at,
            s.end_at,
            s.location,
            s.status,
            s.created_at,
            s.updated_at,
            g.name as group_name,
            CASE 
                WHEN s.employee_type = 'own_employee' THEN e.first_name
                WHEN s.employee_type = 'agency_worker' THEN w.first_name
            END as employee_first_name,
            CASE 
                WHEN s.employee_type = 'own_employee' THEN e.last_name
                WHEN s.employee_type = 'agency_worker' THEN w.last_name
            END as employee_last_name,
            t.name as agency_name
        FROM shifts s
        LEFT JOIN work_groups g ON s.group_id = g.id
        LEFT JOIN own_employees e ON s.employee_type = 'own_employee' AND s.own_employee_id = e.id
        LEFT JOIN agency_workers w ON s.employee_type = 'agency_worker' AND s.agency_worker_id = w.id
        LEFT JOIN tenants t ON w.tenant_id = t.id
        WHERE s.id = ? AND s.tenant_id = ?
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
            'error' => 'assignments.not_found',
            'error_key' => 'assignments.not_found',
            'message' => t('assignments.not_found')
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => formatAssignment($assignment)
    ]);
}

/**
 * Crear asignación manualmente
 */
function createAssignment($conn, $tenantId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Si viene proposal_id, generar desde propuesta
    if (!empty($input['proposal_id'])) {
        $result = generateAssignmentsFromProposal($conn, $input['proposal_id'], $tenantId);
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => t('assignments.created_from_proposal', ['count' => $result['created']]),
                'created' => $result['created'],
                'errors' => $result['errors']
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'assignments.create_failed',
                'error_key' => 'assignments.create_failed',
                'message' => t('assignments.create_failed'),
                'errors' => $result['errors']
            ]);
        }
        return;
    }
    
    // Crear asignación manual (similar a crear shift)
    // Validaciones
    $errors = [];
    
    if (empty($input['start_at'])) {
        $errors[] = ['field' => 'start_at', 'error' => 'validation.required'];
    }
    
    if (empty($input['end_at'])) {
        $errors[] = ['field' => 'end_at', 'error' => 'validation.required'];
    }
    
    if (empty($input['employee_type']) || !in_array($input['employee_type'], ['own_employee', 'agency_worker'])) {
        $errors[] = ['field' => 'employee_type', 'error' => 'validation.invalid'];
    }
    
    if ($input['employee_type'] === 'own_employee' && empty($input['own_employee_id'])) {
        $errors[] = ['field' => 'own_employee_id', 'error' => 'validation.required'];
    }
    
    if ($input['employee_type'] === 'agency_worker' && empty($input['agency_worker_id'])) {
        $errors[] = ['field' => 'agency_worker_id', 'error' => 'validation.required'];
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
    
    // Insertar shift (asignación)
    $stmt = $conn->prepare("
        INSERT INTO shifts (
            tenant_id, group_id, employee_type, own_employee_id, agency_worker_id,
            start_at, end_at, location, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'assigned')
    ");
    
    $stmt->bind_param(
        'iissiisss',
        $tenantId,
        $input['group_id'] ?? null,
        $input['employee_type'],
        $input['own_employee_id'] ?? null,
        $input['agency_worker_id'] ?? null,
        $input['start_at'],
        $input['end_at'],
        $input['location'] ?? null
    );
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'assignments.create_failed',
            'error_key' => 'assignments.create_failed',
            'message' => t('assignments.create_failed')
        ]);
        $stmt->close();
        return;
    }
    
    $newAssignmentId = $conn->insert_id;
    $stmt->close();
    
    // Obtener asignación creada
    getAssignment($conn, $tenantId, $newAssignmentId);
}

/**
 * Eliminar asignación
 */
function deleteAssignment($conn, $tenantId, $assignmentId) {
    // Verificar que existe
    $stmt = $conn->prepare("SELECT * FROM shifts WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $assignmentId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignment = $result->fetch_assoc();
    $stmt->close();
    
    if (!$assignment) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'assignments.not_found',
            'error_key' => 'assignments.not_found',
            'message' => t('assignments.not_found')
        ]);
        return;
    }
    
    // Cambiar estado a canceled
    $stmt = $conn->prepare("UPDATE shifts SET status = 'canceled' WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $assignmentId, $tenantId);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => t('assignments.canceled_success')
    ]);
}

/**
 * Formatear asignación para respuesta
 */
function formatAssignment($row) {
    return [
        'id' => (int)$row['id'],
        'tenant_id' => (int)$row['tenant_id'],
        'group_id' => $row['group_id'] ? (int)$row['group_id'] : null,
        'group_name' => $row['group_name'] ?? null,
        'employee_type' => $row['employee_type'],
        'own_employee_id' => $row['own_employee_id'] ? (int)$row['own_employee_id'] : null,
        'agency_worker_id' => $row['agency_worker_id'] ? (int)$row['agency_worker_id'] : null,
        'employee_name' => trim(($row['employee_first_name'] ?? '') . ' ' . ($row['employee_last_name'] ?? '')),
        'agency_name' => $row['agency_name'] ?? null,
        'start_at' => $row['start_at'],
        'end_at' => $row['end_at'],
        'location' => $row['location'],
        'status' => $row['status'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
}

