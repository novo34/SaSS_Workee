<?php
/**
 * API Partes Diarios de Órdenes de Trabajo (Work Order Daily Reports)
 * 
 * Endpoints:
 * - GET /api/work-order-daily-reports - Listar partes diarios
 * - POST /api/work-order-daily-reports - Crear parte diario
 * - GET /api/work-order-daily-reports/{id} - Obtener parte con líneas
 * - PUT /api/work-order-daily-reports/{id} - Actualizar parte
 * - PUT /api/work-order-daily-reports/{id}/submit - Enviar parte (genera work_logs)
 * - DELETE /api/work-order-daily-reports/{id} - Eliminar parte
 * - GET /api/work-order-daily-reports/{id}/lines - Obtener líneas del parte
 * - POST /api/work-order-daily-reports/{id}/lines - Agregar línea
 * - PUT /api/work-order-daily-reports/{id}/lines/{line_id} - Actualizar línea
 * - DELETE /api/work-order-daily-reports/{id}/lines/{line_id} - Eliminar línea
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
 * Listar partes diarios
 */
function listReports($conn, $tenantId) {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
    $offset = ($page - 1) * $pageSize;
    
    $where = ["wo.tenant_id = ?"];
    $params = [$tenantId];
    $types = 'i';
    
    if (isset($_GET['work_order_id'])) {
        $where[] = "wodr.work_order_id = ?";
        $params[] = (int)$_GET['work_order_id'];
        $types .= 'i';
    }
    
    if (isset($_GET['group_id'])) {
        $where[] = "wodr.group_id = ?";
        $params[] = (int)$_GET['group_id'];
        $types .= 'i';
    }
    
    if (isset($_GET['date'])) {
        $where[] = "wodr.date = ?";
        $params[] = $_GET['date'];
        $types .= 's';
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Contar total
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM work_order_daily_reports wodr
        INNER JOIN work_orders wo ON wodr.work_order_id = wo.id
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
            wodr.id,
            wodr.work_order_id,
            wodr.date,
            wodr.group_id,
            wodr.leader_user_id,
            wodr.notes,
            wodr.progress_pct,
            wodr.attachments_json,
            wodr.created_at,
            wo.code as work_order_code,
            wo.title as work_order_title,
            wg.name as group_name,
            u.first_name as leader_first_name,
            u.last_name as leader_last_name
        FROM work_order_daily_reports wodr
        INNER JOIN work_orders wo ON wodr.work_order_id = wo.id
        LEFT JOIN work_groups wg ON wodr.group_id = wg.id
        LEFT JOIN users u ON wodr.leader_user_id = u.id
        WHERE $whereClause
        ORDER BY wodr.date DESC, wodr.created_at DESC
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
            wodr.id,
            wodr.work_order_id,
            wodr.date,
            wodr.group_id,
            wodr.leader_user_id,
            wodr.notes,
            wodr.progress_pct,
            wodr.attachments_json,
            wodr.created_at,
            wo.code as work_order_code,
            wo.title as work_order_title,
            wo.tenant_id,
            wg.name as group_name,
            u.first_name as leader_first_name,
            u.last_name as leader_last_name
        FROM work_order_daily_reports wodr
        INNER JOIN work_orders wo ON wodr.work_order_id = wo.id
        LEFT JOIN work_groups wg ON wodr.group_id = wg.id
        LEFT JOIN users u ON wodr.leader_user_id = u.id
        WHERE wodr.id = ? AND wo.tenant_id = ?
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
            'error' => 'work_order_daily_reports.not_found',
            'error_key' => 'work_order_daily_reports.not_found',
            'message' => t('work_order_daily_reports.not_found')
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
 * Crear parte diario
 */
function createReport($conn, $tenantId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $errors = [];
    
    if (empty($input['work_order_id'])) {
        $errors[] = ['field' => 'work_order_id', 'error' => 'validation.required'];
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
    
    // Verificar que la OT existe y pertenece al tenant
    $stmt = $conn->prepare("SELECT id FROM work_orders WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param('ii', $input['work_order_id'], $tenantId);
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
    
    // Verificar grupo si se proporciona
    $groupId = isset($input['group_id']) ? (int)$input['group_id'] : null;
    if ($groupId) {
        $stmt = $conn->prepare("SELECT id FROM work_groups WHERE id = ? AND tenant_company_id = ?");
        $stmt->bind_param('ii', $groupId, $tenantId);
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
    
    // Obtener user_id del líder
    $leaderUserId = $GLOBALS['USER_ID'] ?? null;
    
    if (!$leaderUserId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'work_order_daily_reports.leader_required',
            'error_key' => 'work_order_daily_reports.leader_required',
            'message' => t('work_order_daily_reports.leader_required')
        ]);
        return;
    }
    
    // Verificar que no existe ya un parte para esta OT, fecha y grupo
    $stmt = $conn->prepare("
        SELECT id FROM work_order_daily_reports 
        WHERE work_order_id = ? AND date = ? AND (group_id = ? OR (group_id IS NULL AND ? IS NULL))
    ");
    $stmt->bind_param('isii', $input['work_order_id'], $input['date'], $groupId, $groupId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->fetch_assoc()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'work_order_daily_reports.already_exists',
            'error_key' => 'work_order_daily_reports.already_exists',
            'message' => t('work_order_daily_reports.already_exists')
        ]);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    $stmt = $conn->prepare("
        INSERT INTO work_order_daily_reports (
            work_order_id, date, group_id, leader_user_id, notes, progress_pct, attachments_json
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $notes = $input['notes'] ?? null;
    $progressPct = isset($input['progress_pct']) ? (float)$input['progress_pct'] : null;
    $attachmentsJson = isset($input['attachments_json']) ? json_encode($input['attachments_json']) : null;
    
    $stmt->bind_param(
        'isissds',
        $input['work_order_id'],
        $input['date'],
        $groupId,
        $leaderUserId,
        $notes,
        $progressPct,
        $attachmentsJson
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
    $stmt = $conn->prepare("
        SELECT wodr.*, wo.tenant_id 
        FROM work_order_daily_reports wodr
        INNER JOIN work_orders wo ON wodr.work_order_id = wo.id
        WHERE wodr.id = ? AND wo.tenant_id = ?
    ");
    $stmt->bind_param('ii', $reportId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();
    
    if (!$existing) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'work_order_daily_reports.not_found',
            'error_key' => 'work_order_daily_reports.not_found',
            'message' => t('work_order_daily_reports.not_found')
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $conn->prepare("
        UPDATE work_order_daily_reports SET
            notes = ?,
            progress_pct = ?,
            attachments_json = ?
        WHERE id = ?
    ");
    
    $notes = $input['notes'] ?? $existing['notes'];
    $progressPct = isset($input['progress_pct']) ? (float)$input['progress_pct'] : $existing['progress_pct'];
    $attachmentsJson = isset($input['attachments_json']) ? json_encode($input['attachments_json']) : $existing['attachments_json'];
    
    $stmt->bind_param('sdsi', $notes, $progressPct, $attachmentsJson, $reportId);
    $stmt->execute();
    $stmt->close();
    
    getReport($conn, $tenantId, $reportId);
}

/**
 * Enviar parte (genera work_logs)
 */
function submitReport($conn, $tenantId, $reportId) {
    // Verificar que existe
    $stmt = $conn->prepare("
        SELECT wodr.*, wo.tenant_id 
        FROM work_order_daily_reports wodr
        INNER JOIN work_orders wo ON wodr.work_order_id = wo.id
        WHERE wodr.id = ? AND wo.tenant_id = ?
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
            'error' => 'work_order_daily_reports.not_found',
            'error_key' => 'work_order_daily_reports.not_found',
            'message' => t('work_order_daily_reports.not_found')
        ]);
        return;
    }
    
    // Obtener líneas
    $lines = getReportLinesData($conn, $reportId);
    
    if (empty($lines)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'work_order_daily_reports.no_lines',
            'error_key' => 'work_order_daily_reports.no_lines',
            'message' => t('work_order_daily_reports.no_lines')
        ]);
        return;
    }
    
    // Generar work_logs usando WorkLogGenerator adaptado para OT
    require_once __DIR__ . '/../core/WorkLogGenerator.php';
    
    $generator = new WorkLogGenerator($conn);
    $result = $generator->generateFromWorkOrderDailyReport($reportId, $tenantId);
    
    if (!$result['success']) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'work_order_daily_reports.generation_failed',
            'error_key' => 'work_order_daily_reports.generation_failed',
            'message' => $result['message'] ?? t('work_order_daily_reports.generation_failed')
        ]);
        return;
    }
    
    getReport($conn, $tenantId, $reportId);
}

/**
 * Eliminar parte
 */
function deleteReport($conn, $tenantId, $reportId) {
    // Verificar que existe
    $stmt = $conn->prepare("
        SELECT wodr.id 
        FROM work_order_daily_reports wodr
        INNER JOIN work_orders wo ON wodr.work_order_id = wo.id
        WHERE wodr.id = ? AND wo.tenant_id = ?
    ");
    $stmt->bind_param('ii', $reportId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result->fetch_assoc()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'work_order_daily_reports.not_found',
            'error_key' => 'work_order_daily_reports.not_found',
            'message' => t('work_order_daily_reports.not_found')
        ]);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    // Eliminar (las líneas se eliminan en cascada)
    $stmt = $conn->prepare("DELETE FROM work_order_daily_reports WHERE id = ?");
    $stmt->bind_param('i', $reportId);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => t('work_order_daily_reports.deleted_success')
    ]);
}

/**
 * Obtener líneas del parte
 */
function listReportLines($conn, $tenantId, $reportId) {
    // Verificar que el parte existe y pertenece al tenant
    $stmt = $conn->prepare("
        SELECT wodr.id 
        FROM work_order_daily_reports wodr
        INNER JOIN work_orders wo ON wodr.work_order_id = wo.id
        WHERE wodr.id = ? AND wo.tenant_id = ?
    ");
    $stmt->bind_param('ii', $reportId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result->fetch_assoc()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'work_order_daily_reports.not_found',
            'error_key' => 'work_order_daily_reports.not_found',
            'message' => t('work_order_daily_reports.not_found')
        ]);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    $lines = getReportLinesData($conn, $reportId);
    echo json_encode(['success' => true, 'data' => $lines]);
}

function getReportLinesData($conn, $reportId) {
    $stmt = $conn->prepare("
        SELECT 
            wodl.id,
            wodl.daily_report_id,
            wodl.member_type,
            wodl.own_employee_id,
            wodl.agency_worker_id,
            wodl.attended,
            wodl.clock_in,
            wodl.clock_out,
            wodl.breaks_minutes,
            wodl.notes,
            oe.first_name as employee_first_name,
            oe.last_name as employee_last_name,
            aw.first_name as worker_first_name,
            aw.last_name as worker_last_name
        FROM work_order_daily_lines wodl
        LEFT JOIN own_employees oe ON wodl.own_employee_id = oe.id
        LEFT JOIN agency_workers aw ON wodl.agency_worker_id = aw.id
        WHERE wodl.daily_report_id = ?
        ORDER BY wodl.id ASC
    ");
    $stmt->bind_param('i', $reportId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $lines = [];
    while ($row = $result->fetch_assoc()) {
        $lines[] = [
            'id' => (int)$row['id'],
            'daily_report_id' => (int)$row['daily_report_id'],
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
    $stmt = $conn->prepare("
        SELECT wodr.* 
        FROM work_order_daily_reports wodr
        INNER JOIN work_orders wo ON wodr.work_order_id = wo.id
        WHERE wodr.id = ? AND wo.tenant_id = ?
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
            'error' => 'work_order_daily_reports.not_found',
            'error_key' => 'work_order_daily_reports.not_found',
            'message' => t('work_order_daily_reports.not_found')
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
        INSERT INTO work_order_daily_lines (
            daily_report_id, member_type, own_employee_id, agency_worker_id,
            attended, clock_in, clock_out, breaks_minutes, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $attended = isset($input['attended']) ? (int)$input['attended'] : 1;
    $clockIn = $input['clock_in'] ?? null;
    $clockOut = $input['clock_out'] ?? null;
    $breaksMinutes = isset($input['breaks_minutes']) ? (int)$input['breaks_minutes'] : 0;
    $notes = $input['notes'] ?? null;
    $ownEmployeeId = $input['member_type'] === 'own_employee' ? (int)$input['own_employee_id'] : null;
    $agencyWorkerId = $input['member_type'] === 'agency_worker' ? (int)$input['agency_worker_id'] : null;
    
    $stmt->bind_param(
        'isiiisssi',
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
    $lineId = $conn->insert_id;
    $stmt->close();
    
    $lines = getReportLinesData($conn, $reportId);
    echo json_encode([
        'success' => true,
        'data' => $lines
    ]);
}

/**
 * Actualizar línea del parte
 */
function updateReportLine($conn, $tenantId, $reportId, $lineId) {
    // Verificar que el parte existe
    $stmt = $conn->prepare("
        SELECT wodr.id 
        FROM work_order_daily_reports wodr
        INNER JOIN work_orders wo ON wodr.work_order_id = wo.id
        WHERE wodr.id = ? AND wo.tenant_id = ?
    ");
    $stmt->bind_param('ii', $reportId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result->fetch_assoc()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'work_order_daily_reports.not_found',
            'error_key' => 'work_order_daily_reports.not_found',
            'message' => t('work_order_daily_reports.not_found')
        ]);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    // Verificar que la línea existe
    $stmt = $conn->prepare("SELECT * FROM work_order_daily_lines WHERE id = ? AND daily_report_id = ?");
    $stmt->bind_param('ii', $lineId, $reportId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();
    
    if (!$existing) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'work_order_daily_lines.not_found',
            'error_key' => 'work_order_daily_lines.not_found',
            'message' => t('work_order_daily_lines.not_found')
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $conn->prepare("
        UPDATE work_order_daily_lines SET
            attended = ?,
            clock_in = ?,
            clock_out = ?,
            breaks_minutes = ?,
            notes = ?
        WHERE id = ? AND daily_report_id = ?
    ");
    
    $attended = isset($input['attended']) ? (int)$input['attended'] : $existing['attended'];
    $clockIn = $input['clock_in'] ?? $existing['clock_in'];
    $clockOut = $input['clock_out'] ?? $existing['clock_out'];
    $breaksMinutes = isset($input['breaks_minutes']) ? (int)$input['breaks_minutes'] : $existing['breaks_minutes'];
    $notes = $input['notes'] ?? $existing['notes'];
    
    $stmt->bind_param('isssisi', $attended, $clockIn, $clockOut, $breaksMinutes, $notes, $lineId, $reportId);
    $stmt->execute();
    $stmt->close();
    
    $lines = getReportLinesData($conn, $reportId);
    echo json_encode([
        'success' => true,
        'data' => $lines
    ]);
}

/**
 * Eliminar línea del parte
 */
function deleteReportLine($conn, $tenantId, $reportId, $lineId) {
    // Verificar que el parte existe
    $stmt = $conn->prepare("
        SELECT wodr.id 
        FROM work_order_daily_reports wodr
        INNER JOIN work_orders wo ON wodr.work_order_id = wo.id
        WHERE wodr.id = ? AND wo.tenant_id = ?
    ");
    $stmt->bind_param('ii', $reportId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result->fetch_assoc()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'work_order_daily_reports.not_found',
            'error_key' => 'work_order_daily_reports.not_found',
            'message' => t('work_order_daily_reports.not_found')
        ]);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    $stmt = $conn->prepare("DELETE FROM work_order_daily_lines WHERE id = ? AND daily_report_id = ?");
    $stmt->bind_param('ii', $lineId, $reportId);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => t('work_order_daily_lines.deleted_success')
    ]);
}

/**
 * Formatear parte para respuesta
 */
function formatReport($row) {
    $attachments = null;
    if ($row['attachments_json']) {
        $attachments = json_decode($row['attachments_json'], true);
    }
    
    return [
        'id' => (int)$row['id'],
        'work_order_id' => (int)$row['work_order_id'],
        'work_order_code' => $row['work_order_code'],
        'work_order_title' => $row['work_order_title'],
        'date' => $row['date'],
        'group_id' => $row['group_id'] ? (int)$row['group_id'] : null,
        'group_name' => $row['group_name'],
        'leader_user_id' => (int)$row['leader_user_id'],
        'leader_name' => trim(($row['leader_first_name'] ?? '') . ' ' . ($row['leader_last_name'] ?? '')),
        'notes' => $row['notes'],
        'progress_pct' => $row['progress_pct'] ? (float)$row['progress_pct'] : null,
        'attachments' => $attachments,
        'created_at' => $row['created_at']
    ];
}

