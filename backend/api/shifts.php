<?php
/**
 * API Calendario/Shifts
 * 
 * Endpoints:
 * - GET /api/shifts - Listar turnos (con filtros y paginación)
 * - POST /api/shifts - Crear turno
 * - GET /api/shifts/{id} - Obtener turno
 * - PUT /api/shifts/{id} - Actualizar turno
 * - DELETE /api/shifts/{id} - Cancelar turno
 * - POST /api/shifts/{id}/validate - Validar conflictos
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../core/TenantMiddleware.php';
require_once __DIR__ . '/../core/RBACMiddleware.php';
require_once __DIR__ . '/../core/ConflictValidator.php';
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
    if ($method === 'GET' && strpos($path, '/validate') === false) {
        $action = 'shifts.read';
    } elseif ($method === 'POST' && strpos($path, '/validate') === false) {
        $action = 'shifts.create';
    } elseif ($method === 'PUT') {
        $action = 'shifts.update';
    } elseif ($method === 'DELETE') {
        $action = 'shifts.delete';
    } elseif ($method === 'POST' && strpos($path, '/validate') !== false) {
        $action = 'shifts.read'; // Validar es lectura
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
    $shiftId = null;
    $isValidate = false;
    
    if (count($pathParts) >= 2 && is_numeric($pathParts[1])) {
        $shiftId = (int)$pathParts[1];
        if (isset($pathParts[2]) && $pathParts[2] === 'validate') {
            $isValidate = true;
        }
    }
    
    // Routing
    switch ($method) {
        case 'GET':
            if ($isValidate && $shiftId) {
                // Validar turno existente
                validateShift($conn, $tenantId, $shiftId);
            } elseif ($shiftId) {
                // Obtener un turno
                getShift($conn, $tenantId, $shiftId);
            } else {
                // Listar turnos
                listShifts($conn, $tenantId);
            }
            break;
            
        case 'POST':
            if ($isValidate && $shiftId) {
                // Validar turno existente
                validateShift($conn, $tenantId, $shiftId);
            } else {
                // Crear turno
                createShift($conn, $tenantId);
            }
            break;
            
        case 'PUT':
            if ($shiftId) {
                updateShift($conn, $tenantId, $shiftId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.shift_id_required',
                    'error_key' => 'validation.shift_id_required',
                    'message' => t('validation.shift_id_required')
                ]);
            }
            break;
            
        case 'DELETE':
            if ($shiftId) {
                deleteShift($conn, $tenantId, $shiftId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.shift_id_required',
                    'error_key' => 'validation.shift_id_required',
                    'message' => t('validation.shift_id_required')
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
 * Listar turnos con filtros y paginación
 */
function listShifts($conn, $tenantId) {
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
    
    // Filtro por grupo
    if (isset($_GET['group_id'])) {
        $where[] = "s.group_id = ?";
        $params[] = (int)$_GET['group_id'];
        $types .= 'i';
    }
    
    // Filtro por tipo de empleado
    if (isset($_GET['employee_type'])) {
        $where[] = "s.employee_type = ?";
        $params[] = $_GET['employee_type'];
        $types .= 's';
    }
    
    // Ordenamiento
    $sortBy = $_GET['sort_by'] ?? 'start_at';
    $sortDir = strtoupper($_GET['sort_dir'] ?? 'ASC');
    if (!in_array($sortDir, ['ASC', 'DESC'])) {
        $sortDir = 'ASC';
    }
    $allowedSort = ['id', 'start_at', 'end_at', 'status', 'created_at'];
    if (!in_array($sortBy, $allowedSort)) {
        $sortBy = 'start_at';
    }
    
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
    
    // Obtener turnos
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
            END as employee_last_name
        FROM shifts s
        LEFT JOIN work_groups g ON s.group_id = g.id
        LEFT JOIN own_employees e ON s.employee_type = 'own_employee' AND s.own_employee_id = e.id
        LEFT JOIN agency_workers w ON s.employee_type = 'agency_worker' AND s.agency_worker_id = w.id
        WHERE $whereClause
        ORDER BY s.$sortBy $sortDir
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $pageSize;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $shifts = [];
    while ($row = $result->fetch_assoc()) {
        $shifts[] = formatShift($row);
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $shifts,
        'page' => $page,
        'page_size' => $pageSize,
        'total' => $total,
        'total_pages' => ceil($total / $pageSize),
        'sort_by' => $sortBy,
        'sort_dir' => $sortDir
    ]);
}

/**
 * Obtener un turno por ID
 */
function getShift($conn, $tenantId, $shiftId) {
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
            END as employee_last_name
        FROM shifts s
        LEFT JOIN work_groups g ON s.group_id = g.id
        LEFT JOIN own_employees e ON s.employee_type = 'own_employee' AND s.own_employee_id = e.id
        LEFT JOIN agency_workers w ON s.employee_type = 'agency_worker' AND s.agency_worker_id = w.id
        WHERE s.id = ? AND s.tenant_id = ?
    ");
    $stmt->bind_param('ii', $shiftId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $shift = $result->fetch_assoc();
    $stmt->close();
    
    if (!$shift) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'shifts.not_found',
            'error_key' => 'shifts.not_found',
            'message' => t('shifts.not_found')
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => formatShift($shift)
    ]);
}

/**
 * Crear un turno
 */
function createShift($conn, $tenantId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
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
    
    if (!empty($input['start_at']) && !empty($input['end_at'])) {
        if (strtotime($input['start_at']) >= strtotime($input['end_at'])) {
            $errors[] = ['field' => 'end_at', 'error' => 'validation.date_range_invalid'];
        }
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
    
    // Preparar datos
    $shiftData = [
        'start_at' => $input['start_at'],
        'end_at' => $input['end_at'],
        'employee_type' => $input['employee_type'],
        'own_employee_id' => $input['own_employee_id'] ?? null,
        'agency_worker_id' => $input['agency_worker_id'] ?? null,
        'group_id' => $input['group_id'] ?? null,
        'location' => $input['location'] ?? null,
        'status' => $input['status'] ?? 'planned'
    ];
    
    // Validar conflictos
    $validation = ConflictValidator::validateShift($tenantId, $shiftData);
    
    if (!$validation['valid']) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'shifts.conflicts_detected',
            'error_key' => 'shifts.conflicts_detected',
            'message' => t('shifts.conflicts_detected'),
            'conflicts' => $validation['conflicts'],
            'warnings' => $validation['warnings']
        ]);
        return;
    }
    
    // Insertar turno
    $stmt = $conn->prepare("
        INSERT INTO shifts (
            tenant_id, group_id, employee_type, own_employee_id, agency_worker_id,
            start_at, end_at, location, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param(
        'iissiisss',
        $tenantId,
        $shiftData['group_id'],
        $shiftData['employee_type'],
        $shiftData['own_employee_id'],
        $shiftData['agency_worker_id'],
        $shiftData['start_at'],
        $shiftData['end_at'],
        $shiftData['location'],
        $shiftData['status']
    );
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'shifts.create_failed',
            'error_key' => 'shifts.create_failed',
            'message' => t('shifts.create_failed')
        ]);
        $stmt->close();
        return;
    }
    
    $newShiftId = $conn->insert_id;
    $stmt->close();
    
    // Obtener turno creado
    getShift($conn, $tenantId, $newShiftId);
}

/**
 * Actualizar un turno
 */
function updateShift($conn, $tenantId, $shiftId) {
    // Verificar que existe
    $stmt = $conn->prepare("SELECT * FROM shifts WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $shiftId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();
    
    if (!$existing) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'shifts.not_found',
            'error_key' => 'shifts.not_found',
            'message' => t('shifts.not_found')
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Preparar datos actualizados (merge con existente)
    $shiftData = [
        'start_at' => $input['start_at'] ?? $existing['start_at'],
        'end_at' => $input['end_at'] ?? $existing['end_at'],
        'employee_type' => $input['employee_type'] ?? $existing['employee_type'],
        'own_employee_id' => $input['own_employee_id'] ?? $existing['own_employee_id'],
        'agency_worker_id' => $input['agency_worker_id'] ?? $existing['agency_worker_id'],
        'group_id' => $input['group_id'] ?? $existing['group_id'],
        'location' => $input['location'] ?? $existing['location'],
        'status' => $input['status'] ?? $existing['status']
    ];
    
    // Validar conflictos (excluyendo el turno actual)
    $validation = ConflictValidator::validateShift($tenantId, $shiftData, $shiftId);
    
    if (!$validation['valid']) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'shifts.conflicts_detected',
            'error_key' => 'shifts.conflicts_detected',
            'message' => t('shifts.conflicts_detected'),
            'conflicts' => $validation['conflicts'],
            'warnings' => $validation['warnings']
        ]);
        return;
    }
    
    // Actualizar
    $stmt = $conn->prepare("
        UPDATE shifts SET
            group_id = ?,
            employee_type = ?,
            own_employee_id = ?,
            agency_worker_id = ?,
            start_at = ?,
            end_at = ?,
            location = ?,
            status = ?
        WHERE id = ? AND tenant_id = ?
    ");
    
    $stmt->bind_param(
        'issiisssii',
        $shiftData['group_id'],
        $shiftData['employee_type'],
        $shiftData['own_employee_id'],
        $shiftData['agency_worker_id'],
        $shiftData['start_at'],
        $shiftData['end_at'],
        $shiftData['location'],
        $shiftData['status'],
        $shiftId,
        $tenantId
    );
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'shifts.update_failed',
            'error_key' => 'shifts.update_failed',
            'message' => t('shifts.update_failed')
        ]);
        $stmt->close();
        return;
    }
    
    $stmt->close();
    
    // Obtener turno actualizado
    getShift($conn, $tenantId, $shiftId);
}

/**
 * Cancelar un turno (soft delete)
 */
function deleteShift($conn, $tenantId, $shiftId) {
    // Verificar que existe
    $stmt = $conn->prepare("SELECT * FROM shifts WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $shiftId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $shift = $result->fetch_assoc();
    $stmt->close();
    
    if (!$shift) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'shifts.not_found',
            'error_key' => 'shifts.not_found',
            'message' => t('shifts.not_found')
        ]);
        return;
    }
    
    // Cambiar estado a canceled
    $stmt = $conn->prepare("UPDATE shifts SET status = 'canceled' WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $shiftId, $tenantId);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => t('shifts.canceled_success')
    ]);
}

/**
 * Validar conflictos de un turno
 */
function validateShift($conn, $tenantId, $shiftId) {
    // Obtener turno
    $stmt = $conn->prepare("SELECT * FROM shifts WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $shiftId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $shift = $result->fetch_assoc();
    $stmt->close();
    
    if (!$shift) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'shifts.not_found',
            'error_key' => 'shifts.not_found',
            'message' => t('shifts.not_found')
        ]);
        return;
    }
    
    $shiftData = [
        'start_at' => $shift['start_at'],
        'end_at' => $shift['end_at'],
        'employee_type' => $shift['employee_type'],
        'own_employee_id' => $shift['own_employee_id'],
        'agency_worker_id' => $shift['agency_worker_id'],
        'group_id' => $shift['group_id']
    ];
    
    $validation = ConflictValidator::validateShift($tenantId, $shiftData, $shiftId);
    
    echo json_encode([
        'success' => true,
        'valid' => $validation['valid'],
        'conflicts' => $validation['conflicts'],
        'warnings' => $validation['warnings']
    ]);
}

/**
 * Formatear turno para respuesta
 */
function formatShift($row) {
    return [
        'id' => (int)$row['id'],
        'tenant_id' => (int)$row['tenant_id'],
        'group_id' => $row['group_id'] ? (int)$row['group_id'] : null,
        'group_name' => $row['group_name'] ?? null,
        'employee_type' => $row['employee_type'],
        'own_employee_id' => $row['own_employee_id'] ? (int)$row['own_employee_id'] : null,
        'agency_worker_id' => $row['agency_worker_id'] ? (int)$row['agency_worker_id'] : null,
        'employee_name' => trim(($row['employee_first_name'] ?? '') . ' ' . ($row['employee_last_name'] ?? '')),
        'start_at' => $row['start_at'],
        'end_at' => $row['end_at'],
        'location' => $row['location'],
        'status' => $row['status'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
}

