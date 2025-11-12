<?php
/**
 * API Partes del Líder (Group Shift Reports)
 * 
 * Endpoints:
 * - GET /api/group-shift-reports - Listar partes
 * - POST /api/group-shift-reports - Crear parte
 * - GET /api/group-shift-reports/{id} - Obtener parte con líneas
 * - PUT /api/group-shift-reports/{id} - Actualizar parte
 * - PUT /api/group-shift-reports/{id}/submit - Enviar parte (genera work_logs)
 * - DELETE /api/group-shift-reports/{id} - Eliminar parte
 * - GET /api/group-shift-reports/{id}/lines - Obtener líneas del parte
 * - POST /api/group-shift-reports/{id}/lines - Agregar línea
 * - PUT /api/group-shift-reports/{id}/lines/{line_id} - Actualizar línea
 * - DELETE /api/group-shift-reports/{id}/lines/{line_id} - Eliminar línea
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
        $action = 'reports.view';
    } elseif ($method === 'POST') {
        $action = 'reports.create';
    } elseif ($method === 'PUT') {
        $action = 'reports.update';
    } elseif ($method === 'DELETE') {
        $action = 'reports.delete';
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
    $reportId = null;
    $subResource = null;
    $subResourceId = null;
    
    if (count($pathParts) >= 2 && is_numeric($pathParts[1])) {
        $reportId = (int)$pathParts[1];
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
            if ($reportId && $subResource === 'lines') {
                listReportLines($conn, $tenantId, $reportId);
            } elseif ($reportId) {
                getReport($conn, $tenantId, $reportId);
            } else {
                listReports($conn, $tenantId);
            }
            break;
            
        case 'POST':
            if ($reportId && $subResource === 'lines') {
                addReportLine($conn, $tenantId, $reportId);
            } else {
                createReport($conn, $tenantId);
            }
            break;
            
        case 'PUT':
            if ($reportId && $subResource === 'submit') {
                submitReport($conn, $tenantId, $reportId);
            } elseif ($reportId && $subResource === 'lines' && $subResourceId) {
                updateReportLine($conn, $tenantId, $reportId, $subResourceId);
            } elseif ($reportId) {
                updateReport($conn, $tenantId, $reportId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.report_id_required',
                    'error_key' => 'validation.report_id_required',
                    'message' => t('validation.report_id_required')
                ]);
            }
            break;
            
        case 'DELETE':
            if ($reportId && $subResource === 'lines' && $subResourceId) {
                deleteReportLine($conn, $tenantId, $reportId, $subResourceId);
            } elseif ($reportId) {
                deleteReport($conn, $tenantId, $reportId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.report_id_required',
                    'error_key' => 'validation.report_id_required',
                    'message' => t('validation.report_id_required')
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
 * Listar partes del líder
 */
function listReports($conn, $tenantId) {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
    $offset = ($page - 1) * $pageSize;
    
    $where = ["gsr.tenant_id = ?"];
    $params = [$tenantId];
    $types = 'i';
    
    if (isset($_GET['group_id'])) {
        $where[] = "gsr.group_id = ?";
        $params[] = (int)$_GET['group_id'];
        $types .= 'i';
    }
    
    if (isset($_GET['date'])) {
        $where[] = "gsr.date = ?";
        $params[] = $_GET['date'];
        $types .= 's';
    }
    
    if (isset($_GET['status'])) {
        $where[] = "gsr.status = ?";
        $params[] = $_GET['status'];
        $types .= 's';
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Contar total
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM group_shift_reports gsr
        WHERE $whereClause
    ");
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Obtener partes
    $stmt = $conn->prepare("
        SELECT 
            gsr.id,
            gsr.tenant_id,
            gsr.group_id,
            gsr.shift_id,
            gsr.date,
            gsr.leader_user_id,
            gsr.notes,
            gsr.status,
            gsr.created_at,
            gsr.updated_at,
            wg.name as group_name,
            u.first_name as leader_first_name,
            u.last_name as leader_last_name
        FROM group_shift_reports gsr
        LEFT JOIN work_groups wg ON gsr.group_id = wg.id
        LEFT JOIN users u ON gsr.leader_user_id = u.id
        WHERE $whereClause
        ORDER BY gsr.date DESC, gsr.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $pageSize;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reports = [];
    while ($row = $result->fetch_assoc()) {
        $reports[] = formatReport($row);
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $reports,
        'page' => $page,
        'page_size' => $pageSize,
        'total' => $total,
        'total_pages' => ceil($total / $pageSize)
    ]);
}

/**
 * Obtener un parte con sus líneas
 */
function getReport($conn, $tenantId, $reportId) {
    $stmt = $conn->prepare("
        SELECT 
            gsr.id,
            gsr.tenant_id,
            gsr.group_id,
            gsr.shift_id,
            gsr.date,
            gsr.leader_user_id,
            gsr.notes,
            gsr.status,
            gsr.created_at,
            gsr.updated_at,
            wg.name as group_name,
            u.first_name as leader_first_name,
            u.last_name as leader_last_name
        FROM group_shift_reports gsr
        LEFT JOIN work_groups wg ON gsr.group_id = wg.id
        LEFT JOIN users u ON gsr.leader_user_id = u.id
        WHERE gsr.id = ? AND gsr.tenant_id = ?
    ");
    $stmt->bind_param('ii', $reportId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $report = $result->fetch_assoc();
    $stmt->close();
    
    if (!$report) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'reports.not_found',
            'error_key' => 'reports.not_found',
            'message' => t('reports.not_found')
        ]);
        return;
    }
    
    // Obtener líneas
    $lines = getReportLinesData($conn, $reportId);
    
    $reportData = formatReport($report);
    $reportData['lines'] = $lines;
    
    echo json_encode([
        'success' => true,
        'data' => $reportData
    ]);
}

/**
 * Crear parte del líder
 */
function createReport($conn, $tenantId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $errors = [];
    
    if (empty($input['group_id'])) {
        $errors[] = ['field' => 'group_id', 'error' => 'validation.required'];
    }
    
    if (empty($input['date'])) {
        $errors[] = ['field' => 'date', 'error' => 'validation.required'];
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
    
    // Verificar que el grupo existe y pertenece al tenant
    $stmt = $conn->prepare("SELECT id, leader_employee_id FROM work_groups WHERE id = ? AND tenant_company_id = ?");
    $stmt->bind_param('ii', $input['group_id'], $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $group = $result->fetch_assoc();
    $stmt->close();
    
    if (!$group) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'groups.not_found',
            'error_key' => 'groups.not_found',
            'message' => t('groups.not_found')
        ]);
        return;
    }
    
    // Obtener user_id del líder desde el employee_id
    $leaderUserId = null;
    if ($group['leader_employee_id']) {
        $stmt = $conn->prepare("SELECT user_id FROM own_employees WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param('ii', $group['leader_employee_id'], $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $employee = $result->fetch_assoc();
        $stmt->close();
        $leaderUserId = $employee['user_id'] ?? null;
    }
    
    // Si no hay líder asignado, usar el usuario actual
    if (!$leaderUserId) {
        $leaderUserId = $GLOBALS['USER_ID'] ?? null;
    }
    
    if (!$leaderUserId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'reports.leader_required',
            'error_key' => 'reports.leader_required',
            'message' => t('reports.leader_required')
        ]);
        return;
    }
    
    // Verificar que no existe ya un parte para este grupo y fecha
    $stmt = $conn->prepare("
        SELECT id FROM group_shift_reports 
        WHERE group_id = ? AND date = ? AND tenant_id = ?
    ");
    $stmt->bind_param('isi', $input['group_id'], $input['date'], $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->fetch_assoc()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'reports.already_exists',
            'error_key' => 'reports.already_exists',
            'message' => t('reports.already_exists')
        ]);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    $stmt = $conn->prepare("
        INSERT INTO group_shift_reports (
            tenant_id, group_id, shift_id, date, leader_user_id, notes, status
        ) VALUES (?, ?, ?, ?, ?, ?, 'draft')
    ");
    
    $shiftId = isset($input['shift_id']) ? (int)$input['shift_id'] : null;
    $notes = $input['notes'] ?? null;
    
    $stmt->bind_param(
        'iiisisi',
        $tenantId,
        $input['group_id'],
        $shiftId,
        $input['date'],
        $leaderUserId,
        $notes
    );
    
    $stmt->execute();
    $reportId = $conn->insert_id;
    $stmt->close();
    
    getReport($conn, $tenantId, $reportId);
}

/**
 * Actualizar parte
 */
function updateReport($conn, $tenantId, $reportId) {
    // Verificar que existe
    $stmt = $conn->prepare("SELECT * FROM group_shift_reports WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $reportId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();
    
    if (!$existing) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'reports.not_found',
            'error_key' => 'reports.not_found',
            'message' => t('reports.not_found')
        ]);
        return;
    }
    
    // No permitir editar si ya está enviado
    if ($existing['status'] === 'submitted') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'reports.cannot_edit_submitted',
            'error_key' => 'reports.cannot_edit_submitted',
            'message' => t('reports.cannot_edit_submitted')
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $conn->prepare("
        UPDATE group_shift_reports SET
            notes = ?,
            shift_id = ?
        WHERE id = ? AND tenant_id = ?
    ");
    
    $notes = $input['notes'] ?? $existing['notes'];
    $shiftId = isset($input['shift_id']) ? (int)$input['shift_id'] : null;
    
    $stmt->bind_param('siii', $notes, $shiftId, $reportId, $tenantId);
    $stmt->execute();
    $stmt->close();
    
    getReport($conn, $tenantId, $reportId);
}

/**
 * Enviar parte (genera work_logs)
 */
function submitReport($conn, $tenantId, $reportId) {
    // Verificar que existe
    $stmt = $conn->prepare("SELECT * FROM group_shift_reports WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $reportId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $report = $result->fetch_assoc();
    $stmt->close();
    
    if (!$report) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'reports.not_found',
            'error_key' => 'reports.not_found',
            'message' => t('reports.not_found')
        ]);
        return;
    }
    
    if ($report['status'] === 'submitted') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'reports.already_submitted',
            'error_key' => 'reports.already_submitted',
            'message' => t('reports.already_submitted')
        ]);
        return;
    }
    
    // Obtener líneas
    $lines = getReportLinesData($conn, $reportId);
    
    if (empty($lines)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'reports.no_lines',
            'error_key' => 'reports.no_lines',
            'message' => t('reports.no_lines')
        ]);
        return;
    }
    
    // Generar work_logs usando WorkLogGenerator
    require_once __DIR__ . '/../core/WorkLogGenerator.php';
    
    $generator = new WorkLogGenerator($conn);
    $result = $generator->generateFromReport($reportId, $tenantId);
    
    if (!$result['success']) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'reports.generation_failed',
            'error_key' => 'reports.generation_failed',
            'message' => $result['message'] ?? t('reports.generation_failed')
        ]);
        return;
    }
    
    // Actualizar status del parte
    $stmt = $conn->prepare("
        UPDATE group_shift_reports SET status = 'submitted'
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->bind_param('ii', $reportId, $tenantId);
    $stmt->execute();
    $stmt->close();
    
    getReport($conn, $tenantId, $reportId);
}

/**
 * Eliminar parte
 */
function deleteReport($conn, $tenantId, $reportId) {
    // Verificar que existe
    $stmt = $conn->prepare("SELECT * FROM group_shift_reports WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $reportId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result->fetch_assoc()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'reports.not_found',
            'error_key' => 'reports.not_found',
            'message' => t('reports.not_found')
        ]);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    // Eliminar (las líneas se eliminan en cascada)
    $stmt = $conn->prepare("DELETE FROM group_shift_reports WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $reportId, $tenantId);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => t('reports.deleted_success')
    ]);
}

/**
 * Obtener líneas del parte
 */
function listReportLines($conn, $tenantId, $reportId) {
    $lines = getReportLinesData($conn, $reportId);
    echo json_encode(['success' => true, 'data' => $lines]);
}

function getReportLinesData($conn, $reportId) {
    $stmt = $conn->prepare("
        SELECT 
            gsrl.id,
            gsrl.report_id,
            gsrl.member_type,
            gsrl.own_employee_id,
            gsrl.agency_worker_id,
            gsrl.attended,
            gsrl.clock_in,
            gsrl.clock_out,
            gsrl.breaks_minutes,
            gsrl.notes,
            oe.first_name as employee_first_name,
            oe.last_name as employee_last_name,
            aw.first_name as worker_first_name,
            aw.last_name as worker_last_name
        FROM group_shift_report_lines gsrl
        LEFT JOIN own_employees oe ON gsrl.own_employee_id = oe.id
        LEFT JOIN agency_workers aw ON gsrl.agency_worker_id = aw.id
        WHERE gsrl.report_id = ?
        ORDER BY gsrl.id ASC
    ");
    $stmt->bind_param('i', $reportId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $lines = [];
    while ($row = $result->fetch_assoc()) {
        $lines[] = [
            'id' => (int)$row['id'],
            'report_id' => (int)$row['report_id'],
            'member_type' => $row['member_type'],
            'own_employee_id' => $row['own_employee_id'] ? (int)$row['own_employee_id'] : null,
            'agency_worker_id' => $row['agency_worker_id'] ? (int)$row['agency_worker_id'] : null,
            'attended' => (bool)$row['attended'],
            'clock_in' => $row['clock_in'],
            'clock_out' => $row['clock_out'],
            'breaks_minutes' => (int)$row['breaks_minutes'],
            'notes' => $row['notes'],
            'member_name' => $row['member_type'] === 'own_employee' 
                ? trim(($row['employee_first_name'] ?? '') . ' ' . ($row['employee_last_name'] ?? ''))
                : trim(($row['worker_first_name'] ?? '') . ' ' . ($row['worker_last_name'] ?? ''))
        ];
    }
    $stmt->close();
    
    return $lines;
}

/**
 * Agregar línea al parte
 */
function addReportLine($conn, $tenantId, $reportId) {
    // Verificar que el parte existe
    $stmt = $conn->prepare("SELECT * FROM group_shift_reports WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $reportId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $report = $result->fetch_assoc();
    $stmt->close();
    
    if (!$report) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'reports.not_found',
            'error_key' => 'reports.not_found',
            'message' => t('reports.not_found')
        ]);
        return;
    }
    
    if ($report['status'] === 'submitted') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'reports.cannot_edit_submitted',
            'error_key' => 'reports.cannot_edit_submitted',
            'message' => t('reports.cannot_edit_submitted')
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $errors = [];
    
    if (empty($input['member_type'])) {
        $errors[] = ['field' => 'member_type', 'error' => 'validation.required'];
    } elseif (!in_array($input['member_type'], ['own_employee', 'agency_worker'])) {
        $errors[] = ['field' => 'member_type', 'error' => 'validation.invalid'];
    }
    
    if ($input['member_type'] === 'own_employee' && empty($input['own_employee_id'])) {
        $errors[] = ['field' => 'own_employee_id', 'error' => 'validation.required'];
    }
    
    if ($input['member_type'] === 'agency_worker' && empty($input['agency_worker_id'])) {
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
    
    $stmt = $conn->prepare("
        INSERT INTO group_shift_report_lines (
            report_id, member_type, own_employee_id, agency_worker_id,
            attended, clock_in, clock_out, breaks_minutes, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $ownEmployeeId = $input['member_type'] === 'own_employee' ? (int)$input['own_employee_id'] : null;
    $agencyWorkerId = $input['member_type'] === 'agency_worker' ? (int)$input['agency_worker_id'] : null;
    $attended = isset($input['attended']) ? (int)$input['attended'] : 1;
    $clockIn = $input['clock_in'] ?? null;
    $clockOut = $input['clock_out'] ?? null;
    $breaksMinutes = isset($input['breaks_minutes']) ? (int)$input['breaks_minutes'] : 0;
    $notes = $input['notes'] ?? null;
    
    $stmt->bind_param(
        'isiiissis',
        $reportId,
        $input['member_type'],
        $ownEmployeeId,
        $agencyWorkerId,
        $attended,
        $clockIn,
        $clockOut,
        $breaksMinutes,
        $notes
    );
    
    $stmt->execute();
    $stmt->close();
    
    listReportLines($conn, $tenantId, $reportId);
}

/**
 * Actualizar línea del parte
 */
function updateReportLine($conn, $tenantId, $reportId, $lineId) {
    // Verificar que el parte existe
    $stmt = $conn->prepare("SELECT * FROM group_shift_reports WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $reportId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $report = $result->fetch_assoc();
    $stmt->close();
    
    if (!$report) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'reports.not_found',
            'error_key' => 'reports.not_found',
            'message' => t('reports.not_found')
        ]);
        return;
    }
    
    if ($report['status'] === 'submitted') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'reports.cannot_edit_submitted',
            'error_key' => 'reports.cannot_edit_submitted',
            'message' => t('reports.cannot_edit_submitted')
        ]);
        return;
    }
    
    // Verificar que la línea existe
    $stmt = $conn->prepare("SELECT * FROM group_shift_report_lines WHERE id = ? AND report_id = ?");
    $stmt->bind_param('ii', $lineId, $reportId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();
    
    if (!$existing) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'reports.line_not_found',
            'error_key' => 'reports.line_not_found',
            'message' => t('reports.line_not_found')
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $conn->prepare("
        UPDATE group_shift_report_lines SET
            attended = ?,
            clock_in = ?,
            clock_out = ?,
            breaks_minutes = ?,
            notes = ?
        WHERE id = ? AND report_id = ?
    ");
    
    $attended = isset($input['attended']) ? (int)$input['attended'] : $existing['attended'];
    $clockIn = $input['clock_in'] ?? $existing['clock_in'];
    $clockOut = $input['clock_out'] ?? $existing['clock_out'];
    $breaksMinutes = isset($input['breaks_minutes']) ? (int)$input['breaks_minutes'] : $existing['breaks_minutes'];
    $notes = $input['notes'] ?? $existing['notes'];
    
    $stmt->bind_param(
        'issisii',
        $attended,
        $clockIn,
        $clockOut,
        $breaksMinutes,
        $notes,
        $lineId,
        $reportId
    );
    
    $stmt->execute();
    $stmt->close();
    
    listReportLines($conn, $tenantId, $reportId);
}

/**
 * Eliminar línea del parte
 */
function deleteReportLine($conn, $tenantId, $reportId, $lineId) {
    // Verificar que el parte existe
    $stmt = $conn->prepare("SELECT * FROM group_shift_reports WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $reportId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $report = $result->fetch_assoc();
    $stmt->close();
    
    if (!$report) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'reports.not_found',
            'error_key' => 'reports.not_found',
            'message' => t('reports.not_found')
        ]);
        return;
    }
    
    if ($report['status'] === 'submitted') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'reports.cannot_edit_submitted',
            'error_key' => 'reports.cannot_edit_submitted',
            'message' => t('reports.cannot_edit_submitted')
        ]);
        return;
    }
    
    // Verificar que la línea existe
    $stmt = $conn->prepare("SELECT * FROM group_shift_report_lines WHERE id = ? AND report_id = ?");
    $stmt->bind_param('ii', $lineId, $reportId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result->fetch_assoc()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'reports.line_not_found',
            'error_key' => 'reports.line_not_found',
            'message' => t('reports.line_not_found')
        ]);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    $stmt = $conn->prepare("DELETE FROM group_shift_report_lines WHERE id = ? AND report_id = ?");
    $stmt->bind_param('ii', $lineId, $reportId);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => t('reports.line_deleted_success')
    ]);
}

/**
 * Formatear parte para respuesta
 */
function formatReport($row) {
    return [
        'id' => (int)$row['id'],
        'tenant_id' => (int)$row['tenant_id'],
        'group_id' => (int)$row['group_id'],
        'shift_id' => $row['shift_id'] ? (int)$row['shift_id'] : null,
        'date' => $row['date'],
        'leader_user_id' => (int)$row['leader_user_id'],
        'notes' => $row['notes'],
        'status' => $row['status'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
        'group_name' => $row['group_name'],
        'leader_name' => trim(($row['leader_first_name'] ?? '') . ' ' . ($row['leader_last_name'] ?? ''))
    ];
}

