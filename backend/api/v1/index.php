<?php

/**
 * API Pública REST v1
 * 
 * Endpoints públicos con autenticación por API Keys
 * Base path: /api/v1/
 * 
 * Autenticación: Header X-API-Key con API Key del tenant
 * 
 * Endpoints disponibles:
 * - GET /api/v1/shifts - Listar turnos (read-only)
 * - GET /api/v1/shifts/{id} - Obtener turno
 * - GET /api/v1/work-logs - Listar work logs (read-only)
 * - GET /api/v1/work-logs/{id} - Obtener work log
 * - GET /api/v1/work-orders - Listar órdenes de trabajo (read-only)
 * - GET /api/v1/work-orders/{id} - Obtener orden de trabajo
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../core/ApiKeyMiddleware.php';
require_once __DIR__ . '/../../helpers/db_helper.php';
require_once __DIR__ . '/../../helpers/i18n.php';
require_once __DIR__ . '/../../helpers/env.php';

// Deshabilitar display_errors para evitar HTML en respuestas JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    // Validar API Key
    $apiKeyResult = ApiKeyMiddleware::process();
    $tenantId = $apiKeyResult['tenant_id'];
    $scopes = $apiKeyResult['scopes'] ?? [];

    // Parsear path
    $path = $GLOBALS['API_PATH'] ?? $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '';

    // Remover /api/v1 del path
    $path = preg_replace('#^/api/v1/?#', '', $path);
    $pathParts = explode('/', trim($path, '/'));

    $resource = $pathParts[0] ?? null;
    $resourceId = isset($pathParts[1]) && is_numeric($pathParts[1]) ? (int)$pathParts[1] : null;

    $method = $_SERVER['REQUEST_METHOD'];

    // Routing
    switch ($resource) {
        case 'shifts':
            if ($method === 'GET' && $resourceId) {
                getPublicShift($tenantId, $resourceId);
            } elseif ($method === 'GET') {
                listPublicShifts($tenantId);
            } else {
                methodNotAllowed();
            }
            break;

        case 'work-logs':
        case 'work_logs':
            if ($method === 'GET' && $resourceId) {
                getPublicWorkLog($tenantId, $resourceId);
            } elseif ($method === 'GET') {
                listPublicWorkLogs($tenantId);
            } else {
                methodNotAllowed();
            }
            break;

        case 'work-orders':
        case 'work_orders':
            if ($method === 'GET' && $resourceId) {
                getPublicWorkOrder($tenantId, $resourceId);
            } elseif ($method === 'GET') {
                listPublicWorkOrders($tenantId);
            } else {
                methodNotAllowed();
            }
            break;

        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'api.resource_not_found',
                'error_key' => 'api.resource_not_found',
                'message' => 'Recurso no encontrado'
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

function methodNotAllowed()
{
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'method_not_allowed',
        'message' => 'Método no permitido'
    ]);
    exit;
}

/**
 * Listar turnos (read-only)
 */
function listPublicShifts($tenantId)
{
    $conn = DBHelper::getConnection();

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
    $offset = ($page - 1) * $pageSize;

    $where = ["s.tenant_id = ?"];
    $params = [$tenantId];
    $types = 'i';

    if (isset($_GET['from'])) {
        $where[] = "DATE(s.start_at) >= ?";
        $params[] = $_GET['from'];
        $types .= 's';
    }

    if (isset($_GET['to'])) {
        $where[] = "DATE(s.start_at) <= ?";
        $params[] = $_GET['to'];
        $types .= 's';
    }

    if (isset($_GET['status'])) {
        $where[] = "s.status = ?";
        $params[] = $_GET['status'];
        $types .= 's';
    }

    $whereClause = implode(' AND ', $where);

    // Contar total
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM shifts s
        WHERE {$whereClause}
    ");
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    $countStmt->close();

    // Obtener shifts
    $stmt = $conn->prepare("
        SELECT 
            s.id,
            s.start_at,
            s.end_at,
            s.location,
            s.status,
            s.group_id,
            wg.name as group_name,
            p.name as project_name,
            p.code as project_code
        FROM shifts s
        LEFT JOIN work_groups wg ON s.group_id = wg.id AND s.tenant_id = wg.tenant_company_id
        LEFT JOIN projects p ON s.project_id = p.id AND s.tenant_id = p.tenant_id
        WHERE {$whereClause}
        ORDER BY s.start_at DESC
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
        $shifts[] = [
            'id' => (int)$row['id'],
            'start_at' => $row['start_at'],
            'end_at' => $row['end_at'],
            'location' => $row['location'],
            'status' => $row['status'],
            'group' => $row['group_id'] ? [
                'id' => (int)$row['group_id'],
                'name' => $row['group_name']
            ] : null,
            'project' => $row['project_name'] ? [
                'name' => $row['project_name'],
                'code' => $row['project_code']
            ] : null
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'data' => $shifts,
        'page' => $page,
        'page_size' => $pageSize,
        'total' => $total,
        'total_pages' => ceil($total / $pageSize)
    ]);
}

/**
 * Obtener turno por ID
 */
function getPublicShift($tenantId, $shiftId)
{
    $conn = DBHelper::getConnection();

    $stmt = $conn->prepare("
        SELECT 
            s.id,
            s.start_at,
            s.end_at,
            s.location,
            s.status,
            s.group_id,
            wg.name as group_name,
            p.name as project_name,
            p.code as project_code
        FROM shifts s
        LEFT JOIN work_groups wg ON s.group_id = wg.id AND s.tenant_id = wg.tenant_company_id
        LEFT JOIN projects p ON s.project_id = p.id AND s.tenant_id = p.tenant_id
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
            'error' => 'api.resource_not_found',
            'error_key' => 'api.resource_not_found',
            'message' => 'Turno no encontrado'
        ]);
        return;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int)$shift['id'],
            'start_at' => $shift['start_at'],
            'end_at' => $shift['end_at'],
            'location' => $shift['location'],
            'status' => $shift['status'],
            'group' => $shift['group_id'] ? [
                'id' => (int)$shift['group_id'],
                'name' => $shift['group_name']
            ] : null,
            'project' => $shift['project_name'] ? [
                'name' => $shift['project_name'],
                'code' => $shift['project_code']
            ] : null
        ]
    ]);
}

/**
 * Listar work logs (read-only)
 */
function listPublicWorkLogs($tenantId)
{
    $conn = DBHelper::getConnection();

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
    $offset = ($page - 1) * $pageSize;

    $where = ["wl.tenant_id = ?", "wl.status = 'approved'"];
    $params = [$tenantId];
    $types = 'i';

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

    $whereClause = implode(' AND ', $where);

    // Contar total
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM work_logs wl
        WHERE {$whereClause}
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
            wl.date,
            wl.clock_in,
            wl.clock_out,
            TIMESTAMPDIFF(MINUTE, wl.clock_in, wl.clock_out) / 60.0 as hours,
            wl.breaks_minutes,
            wl.status,
            p.name as project_name,
            p.code as project_code
        FROM work_logs wl
        LEFT JOIN projects p ON wl.project_id = p.id AND wl.tenant_id = p.tenant_id
        WHERE {$whereClause}
        ORDER BY wl.date DESC, wl.clock_in DESC
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
        $workLogs[] = [
            'id' => (int)$row['id'],
            'date' => $row['date'],
            'clock_in' => $row['clock_in'],
            'clock_out' => $row['clock_out'],
            'hours' => round((float)$row['hours'], 2),
            'breaks_minutes' => (int)$row['breaks_minutes'],
            'status' => $row['status'],
            'project' => $row['project_name'] ? [
                'name' => $row['project_name'],
                'code' => $row['project_code']
            ] : null
        ];
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
 * Obtener work log por ID
 */
function getPublicWorkLog($tenantId, $workLogId)
{
    $conn = DBHelper::getConnection();

    $stmt = $conn->prepare("
        SELECT 
            wl.id,
            wl.date,
            wl.clock_in,
            wl.clock_out,
            TIMESTAMPDIFF(MINUTE, wl.clock_in, wl.clock_out) / 60.0 as hours,
            wl.breaks_minutes,
            wl.status,
            p.name as project_name,
            p.code as project_code
        FROM work_logs wl
        LEFT JOIN projects p ON wl.project_id = p.id AND wl.tenant_id = p.tenant_id
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
            'error' => 'api.resource_not_found',
            'error_key' => 'api.resource_not_found',
            'message' => 'Work log no encontrado'
        ]);
        return;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int)$workLog['id'],
            'date' => $workLog['date'],
            'clock_in' => $workLog['clock_in'],
            'clock_out' => $workLog['clock_out'],
            'hours' => round((float)$workLog['hours'], 2),
            'breaks_minutes' => (int)$workLog['breaks_minutes'],
            'status' => $workLog['status'],
            'project' => $workLog['project_name'] ? [
                'name' => $workLog['project_name'],
                'code' => $workLog['project_code']
            ] : null
        ]
    ]);
}

/**
 * Listar work orders (read-only)
 */
function listPublicWorkOrders($tenantId)
{
    $conn = DBHelper::getConnection();

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

    $whereClause = implode(' AND ', $where);

    // Contar total
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM work_orders wo
        WHERE {$whereClause}
    ");
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    $countStmt->close();

    // Obtener work orders
    $stmt = $conn->prepare("
        SELECT 
            wo.id,
            wo.code,
            wo.title,
            wo.status,
            wo.start_planned,
            wo.end_planned,
            wo.start_actual,
            wo.end_actual,
            c.name as client_name,
            p.name as project_name
        FROM work_orders wo
        LEFT JOIN clients c ON wo.client_id = c.id AND wo.tenant_id = c.tenant_id
        LEFT JOIN projects p ON wo.project_id = p.id AND wo.tenant_id = p.tenant_id
        WHERE {$whereClause}
        ORDER BY wo.created_at DESC
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
        $workOrders[] = [
            'id' => (int)$row['id'],
            'code' => $row['code'],
            'title' => $row['title'],
            'status' => $row['status'],
            'start_planned' => $row['start_planned'],
            'end_planned' => $row['end_planned'],
            'start_actual' => $row['start_actual'],
            'end_actual' => $row['end_actual'],
            'client' => $row['client_name'] ? ['name' => $row['client_name']] : null,
            'project' => $row['project_name'] ? ['name' => $row['project_name']] : null
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'data' => $workOrders,
        'page' => $page,
        'page_size' => $pageSize,
        'total' => $total,
        'total_pages' => ceil($total / $pageSize)
    ]);
}

/**
 * Obtener work order por ID
 */
function getPublicWorkOrder($tenantId, $workOrderId)
{
    $conn = DBHelper::getConnection();

    $stmt = $conn->prepare("
        SELECT 
            wo.id,
            wo.code,
            wo.title,
            wo.status,
            wo.start_planned,
            wo.end_planned,
            wo.start_actual,
            wo.end_actual,
            wo.location,
            wo.scope,
            c.name as client_name,
            p.name as project_name
        FROM work_orders wo
        LEFT JOIN clients c ON wo.client_id = c.id AND wo.tenant_id = c.tenant_id
        LEFT JOIN projects p ON wo.project_id = p.id AND wo.tenant_id = p.tenant_id
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
            'error' => 'api.resource_not_found',
            'error_key' => 'api.resource_not_found',
            'message' => 'Orden de trabajo no encontrada'
        ]);
        return;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int)$workOrder['id'],
            'code' => $workOrder['code'],
            'title' => $workOrder['title'],
            'status' => $workOrder['status'],
            'start_planned' => $workOrder['start_planned'],
            'end_planned' => $workOrder['end_planned'],
            'start_actual' => $workOrder['start_actual'],
            'end_actual' => $workOrder['end_actual'],
            'location' => $workOrder['location'],
            'scope' => $workOrder['scope'],
            'client' => $workOrder['client_name'] ? ['name' => $workOrder['client_name']] : null,
            'project' => $workOrder['project_name'] ? ['name' => $workOrder['project_name']] : null
        ]
    ]);
}
