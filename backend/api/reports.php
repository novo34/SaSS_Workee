<?php
/**
 * API Reportes Avanzados
 * 
 * Endpoints:
 * - GET /api/reports/hours - Reporte de horas trabajadas
 * - GET /api/reports/planning - Reporte de planificación
 * - GET /api/reports/company-agency - Reporte relación Empresa↔Temporera
 * 
 * Parámetros comunes:
 * - type: tipo de reporte (hours, planning, company_agency)
 * - from: fecha desde (YYYY-MM-DD)
 * - to: fecha hasta (YYYY-MM-DD)
 * - employee_id: ID de empleado (opcional)
 * - worker_id: ID de trabajador (opcional)
 * - project_id: ID de proyecto (opcional)
 * - group_id: ID de grupo (opcional)
 * - format: json (default) o csv
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
    
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'method_not_allowed',
            'message' => 'Método no permitido'
        ]);
        exit;
    }
    
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
    if (!RBACMiddleware::checkPermission('reports.view')) {
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
    
    // Parsear tipo de reporte
    $pathParts = explode('/', trim($path, '/'));
    $reportType = isset($pathParts[1]) ? $pathParts[1] : ($_GET['type'] ?? 'hours');
    
    // Obtener parámetros
    $from = $_GET['from'] ?? date('Y-m-01'); // Primer día del mes actual
    $to = $_GET['to'] ?? date('Y-m-t'); // Último día del mes actual
    $employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;
    $workerId = isset($_GET['worker_id']) ? (int)$_GET['worker_id'] : null;
    $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
    $groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : null;
    $format = $_GET['format'] ?? 'json';
    
    // Routing por tipo de reporte
    switch ($reportType) {
        case 'hours':
            generateHoursReport($conn, $tenantId, $from, $to, $employeeId, $workerId, $projectId, $groupId, $format);
            break;
        case 'planning':
            generatePlanningReport($conn, $tenantId, $from, $to, $projectId, $groupId, $format);
            break;
        case 'company-agency':
        case 'company_agency':
            generateCompanyAgencyReport($conn, $tenantId, $from, $to, $format);
            break;
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'reports.invalid_type',
                'error_key' => 'reports.invalid_type',
                'message' => t('reports.invalid_type')
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
 * Reporte de horas trabajadas
 */
function generateHoursReport($conn, $tenantId, $from, $to, $employeeId, $workerId, $projectId, $groupId, $format) {
    $where = ["wl.tenant_id = ?", "wl.date >= ?", "wl.date <= ?", "wl.status = 'approved'"];
    $params = [$tenantId, $from, $to];
    $types = 'iss';
    
    if ($employeeId) {
        $where[] = "wl.employee_id = ?";
        $params[] = $employeeId;
        $types .= 'i';
    }
    
    if ($workerId) {
        $where[] = "wl.agency_worker_id = ?";
        $params[] = $workerId;
        $types .= 'i';
    }
    
    if ($projectId) {
        $where[] = "wl.project_id = ?";
        $params[] = $projectId;
        $types .= 'i';
    }
    
    if ($groupId) {
        // work_logs no tiene work_group_id directamente, pero shifts sí
        // Necesitamos hacer join con shifts
        $where[] = "s.group_id = ?";
        $params[] = $groupId;
        $types .= 'i';
    }
    
    $whereClause = implode(' AND ', $where);
    $joinGroup = $groupId ? "INNER JOIN shifts s ON wl.shift_id = s.id" : "LEFT JOIN shifts s ON wl.shift_id = s.id";
    
    $stmt = $conn->prepare("
        SELECT 
            wl.id,
            wl.date,
            wl.clock_in,
            wl.clock_out,
            TIMESTAMPDIFF(MINUTE, wl.clock_in, wl.clock_out) / 60.0 as hours,
            wl.breaks_minutes,
            wl.own_employee_id,
            wl.agency_worker_id,
            wl.project_id,
            wl.shift_id,
            s.group_id as work_group_id,
            oe.first_name as employee_first_name,
            oe.last_name as employee_last_name,
            oe.employee_code,
            aw.first_name as worker_first_name,
            aw.last_name as worker_last_name,
            aw.worker_code,
            p.name as project_name,
            p.code as project_code,
            wg.name as group_name
        FROM work_logs wl
        {$joinGroup}
        LEFT JOIN own_employees oe ON wl.own_employee_id = oe.id AND wl.tenant_id = oe.tenant_id
        LEFT JOIN agency_workers aw ON wl.agency_worker_id = aw.id AND wl.tenant_id = aw.tenant_id
        LEFT JOIN projects p ON wl.project_id = p.id AND wl.tenant_id = p.tenant_id
        LEFT JOIN work_groups wg ON s.work_group_id = wg.id AND s.tenant_id = wg.tenant_company_id
        WHERE {$whereClause}
        ORDER BY wl.date DESC, wl.clock_in ASC
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    $totalHours = 0;
    while ($row = $result->fetch_assoc()) {
        $hours = (float)$row['hours'];
        $totalHours += $hours;
        
        $data[] = [
            'id' => (int)$row['id'],
            'date' => $row['date'],
            'clock_in' => $row['clock_in'],
            'clock_out' => $row['clock_out'],
            'hours' => $hours,
            'breaks_minutes' => (int)$row['breaks_minutes'],
            'employee' => $row['own_employee_id'] ? [
                'id' => (int)$row['own_employee_id'],
                'name' => trim(($row['employee_first_name'] ?? '') . ' ' . ($row['employee_last_name'] ?? '')),
                'code' => $row['employee_code']
            ] : null,
            'worker' => $row['agency_worker_id'] ? [
                'id' => (int)$row['agency_worker_id'],
                'name' => trim(($row['worker_first_name'] ?? '') . ' ' . ($row['worker_last_name'] ?? '')),
                'code' => $row['worker_code']
            ] : null,
            'project' => $row['project_id'] ? [
                'id' => (int)$row['project_id'],
                'name' => $row['project_name'],
                'code' => $row['project_code']
            ] : null,
            'group' => $row['work_group_id'] ? [
                'id' => (int)$row['work_group_id'],
                'name' => $row['group_name']
            ] : null
        ];
    }
    $stmt->close();
    
    if ($format === 'csv') {
        outputCSV($data, 'hours_report', [
            'date' => 'Fecha',
            'employee_name' => 'Empleado',
            'worker_name' => 'Trabajador',
            'clock_in' => 'Entrada',
            'clock_out' => 'Salida',
            'hours' => 'Horas',
            'breaks_minutes' => 'Pausas (min)',
            'project_name' => 'Proyecto',
            'group_name' => 'Grupo'
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'type' => 'hours',
        'period' => [
            'from' => $from,
            'to' => $to
        ],
        'filters' => [
            'employee_id' => $employeeId,
            'worker_id' => $workerId,
            'project_id' => $projectId,
            'group_id' => $groupId
        ],
        'summary' => [
            'total_hours' => round($totalHours, 2),
            'total_records' => count($data)
        ],
        'data' => $data
    ]);
}

/**
 * Reporte de planificación
 */
function generatePlanningReport($conn, $tenantId, $from, $to, $projectId, $groupId, $format) {
    $where = ["s.tenant_id = ?", "DATE(s.start_at) >= ?", "DATE(s.start_at) <= ?"];
    $params = [$tenantId, $from, $to];
    $types = 'iss';
    
    if ($projectId) {
        $where[] = "s.project_id = ?";
        $params[] = $projectId;
        $types .= 'i';
    }
    
    if ($groupId) {
        $where[] = "s.group_id = ?";
        $params[] = $groupId;
        $types .= 'i';
    }
    
    $whereClause = implode(' AND ', $where);
    
    $stmt = $conn->prepare("
        SELECT 
            s.id,
            DATE(s.start_at) as date,
            TIME(s.start_at) as start_time,
            TIME(s.end_at) as end_time,
            s.project_id,
            s.group_id as work_group_id,
            p.name as project_name,
            p.code as project_code,
            wg.name as group_name,
            COUNT(DISTINCT CASE WHEN s.own_employee_id IS NOT NULL THEN s.own_employee_id END) + 
            COUNT(DISTINCT CASE WHEN s.agency_worker_id IS NOT NULL THEN s.agency_worker_id END) as assigned_count,
            COUNT(DISTINCT CASE WHEN s.own_employee_id IS NOT NULL THEN s.own_employee_id END) as employees_count,
            COUNT(DISTINCT CASE WHEN s.agency_worker_id IS NOT NULL THEN s.agency_worker_id END) as workers_count
        FROM shifts s
        LEFT JOIN projects p ON s.project_id = p.id AND s.tenant_id = p.tenant_id
        LEFT JOIN work_groups wg ON s.group_id = wg.id AND s.tenant_id = wg.tenant_company_id
        WHERE {$whereClause}
        GROUP BY s.id, DATE(s.start_at), TIME(s.start_at), TIME(s.end_at), s.project_id, s.group_id, p.name, p.code, wg.name
        ORDER BY DATE(s.start_at) ASC, TIME(s.start_at) ASC
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    $totalShifts = 0;
    while ($row = $result->fetch_assoc()) {
        $totalShifts++;
        $data[] = [
            'id' => (int)$row['id'],
            'date' => $row['date'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'project' => $row['project_id'] ? [
                'id' => (int)$row['project_id'],
                'name' => $row['project_name'],
                'code' => $row['project_code']
            ] : null,
            'group' => $row['work_group_id'] ? [
                'id' => (int)$row['work_group_id'],
                'name' => $row['group_name']
            ] : null,
            'assigned_count' => (int)$row['assigned_count'],
            'employees_count' => (int)$row['employees_count'],
            'workers_count' => (int)$row['workers_count']
        ];
    }
    $stmt->close();
    
    if ($format === 'csv') {
        outputCSV($data, 'planning_report', [
            'date' => 'Fecha',
            'start_time' => 'Hora Inicio',
            'end_time' => 'Hora Fin',
            'project_name' => 'Proyecto',
            'group_name' => 'Grupo',
            'assigned_count' => 'Asignados',
            'employees_count' => 'Empleados',
            'workers_count' => 'Trabajadores'
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'type' => 'planning',
        'period' => [
            'from' => $from,
            'to' => $to
        ],
        'filters' => [
            'project_id' => $projectId,
            'group_id' => $groupId
        ],
        'summary' => [
            'total_shifts' => $totalShifts,
            'total_records' => count($data)
        ],
        'data' => $data
    ]);
}

/**
 * Reporte relación Empresa↔Temporera
 */
function generateCompanyAgencyReport($conn, $tenantId, $from, $to, $format) {
    // Obtener tipo de tenant
    $tenantStmt = $conn->prepare("SELECT org_type FROM tenants WHERE id = ?");
    $tenantStmt->bind_param('i', $tenantId);
    $tenantStmt->execute();
    $tenantResult = $tenantStmt->get_result();
    $tenant = $tenantResult->fetch_assoc();
    $tenantStmt->close();
    
    if (!$tenant) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'reports.tenant_not_found',
            'error_key' => 'reports.tenant_not_found',
            'message' => t('reports.tenant_not_found')
        ]);
        return;
    }
    
    $orgType = $tenant['org_type'];
    
    if ($orgType === 'empresa') {
        // Reporte desde perspectiva de Empresa
        // Contar shifts con trabajadores de la temporera vinculada
        $stmt = $conn->prepare("
            SELECT 
                acl.id as link_id,
                acl.tenant_agency_id as agency_tenant_id,
                acl.status as link_status,
                t.name as agency_name,
                COUNT(DISTINCT sr.id) as requests_count,
                COUNT(DISTINCT sp.id) as proposals_count,
                COUNT(DISTINCT s.id) as shifts_count,
                COUNT(DISTINCT wl.id) as work_logs_count,
                COALESCE(SUM(TIMESTAMPDIFF(MINUTE, wl.clock_in, wl.clock_out) / 60.0), 0) as total_hours
            FROM agency_company_links acl
            INNER JOIN tenants t ON acl.tenant_agency_id = t.id
            LEFT JOIN staffing_requests sr ON acl.tenant_company_id = sr.tenant_company_id 
                AND acl.tenant_agency_id = sr.tenant_agency_id
                AND sr.created_at >= ? AND sr.created_at <= ?
            LEFT JOIN staffing_proposals sp ON sr.id = sp.staffing_request_id
                AND sp.created_at >= ? AND sp.created_at <= ?
            LEFT JOIN shifts s ON s.tenant_id = acl.tenant_company_id
                AND s.agency_worker_id IN (
                    SELECT id FROM agency_workers WHERE tenant_id = acl.tenant_agency_id
                )
                AND DATE(s.start_at) >= ? AND DATE(s.start_at) <= ?
            LEFT JOIN work_logs wl ON wl.shift_id = s.id
                AND wl.date >= ? AND wl.date <= ?
                AND wl.status = 'approved'
            WHERE acl.tenant_company_id = ?
            GROUP BY acl.id, acl.tenant_agency_id, acl.status, t.name
            ORDER BY t.name ASC
        ");
        $stmt->bind_param('sssssssi', $from, $to, $from, $to, $from, $to, $from, $to, $tenantId);
    } else {
        // Reporte desde perspectiva de Temporera
        $stmt = $conn->prepare("
            SELECT 
                acl.id as link_id,
                acl.tenant_company_id as company_tenant_id,
                acl.status as link_status,
                t.name as company_name,
                COUNT(DISTINCT sr.id) as requests_count,
                COUNT(DISTINCT sp.id) as proposals_count,
                COUNT(DISTINCT s.id) as shifts_count,
                COUNT(DISTINCT wl.id) as work_logs_count,
                COALESCE(SUM(TIMESTAMPDIFF(MINUTE, wl.clock_in, wl.clock_out) / 60.0), 0) as total_hours
            FROM agency_company_links acl
            INNER JOIN tenants t ON acl.tenant_company_id = t.id
            LEFT JOIN staffing_requests sr ON acl.tenant_company_id = sr.tenant_company_id 
                AND acl.tenant_agency_id = sr.tenant_agency_id
                AND sr.created_at >= ? AND sr.created_at <= ?
            LEFT JOIN staffing_proposals sp ON sr.id = sp.staffing_request_id
                AND sp.created_at >= ? AND sp.created_at <= ?
            LEFT JOIN shifts s ON s.tenant_id = acl.tenant_company_id
                AND s.agency_worker_id IN (
                    SELECT id FROM agency_workers WHERE tenant_id = acl.tenant_agency_id
                )
                AND DATE(s.start_at) >= ? AND DATE(s.start_at) <= ?
            LEFT JOIN work_logs wl ON wl.shift_id = s.id
                AND wl.date >= ? AND wl.date <= ?
                AND wl.status = 'approved'
            WHERE acl.tenant_agency_id = ?
            GROUP BY acl.id, acl.tenant_company_id, acl.status, t.name
            ORDER BY t.name ASC
        ");
        $stmt->bind_param('sssssssi', $from, $to, $from, $to, $from, $to, $from, $to, $tenantId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'link_id' => (int)$row['link_id'],
            'partner_name' => $orgType === 'empresa' ? $row['agency_name'] : $row['company_name'],
            'partner_id' => $orgType === 'empresa' ? (int)$row['agency_tenant_id'] : (int)$row['company_tenant_id'],
            'link_status' => $row['link_status'],
            'requests_count' => (int)$row['requests_count'],
            'proposals_count' => (int)$row['proposals_count'],
            'shifts_count' => (int)$row['shifts_count'],
            'work_logs_count' => (int)$row['work_logs_count'],
            'total_hours' => round((float)$row['total_hours'], 2)
        ];
    }
    $stmt->close();
    
    if ($format === 'csv') {
        outputCSV($data, 'company_agency_report', [
            'partner_name' => $orgType === 'empresa' ? 'Temporera' : 'Empresa',
            'link_status' => 'Estado',
            'requests_count' => 'Solicitudes',
            'proposals_count' => 'Propuestas',
            'shifts_count' => 'Turnos',
            'work_logs_count' => 'Work Logs',
            'total_hours' => 'Total Horas'
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'type' => 'company_agency',
        'period' => [
            'from' => $from,
            'to' => $to
        ],
        'org_type' => $orgType,
        'summary' => [
            'total_partners' => count($data),
            'total_requests' => array_sum(array_column($data, 'requests_count')),
            'total_proposals' => array_sum(array_column($data, 'proposals_count')),
            'total_shifts' => array_sum(array_column($data, 'shifts_count')),
            'total_hours' => round(array_sum(array_column($data, 'total_hours')), 2)
        ],
        'data' => $data
    ]);
}

/**
 * Genera salida CSV
 */
function outputCSV($data, $filename, $columnMapping) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('YmdHis') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM para Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    $headers = array_values($columnMapping);
    fputcsv($output, $headers, ';');
    
    // Data
    foreach ($data as $row) {
        $csvRow = [];
        foreach (array_keys($columnMapping) as $key) {
            $value = '';
            if (isset($row[$key])) {
                if (is_array($row[$key])) {
                    $value = isset($row[$key]['name']) ? $row[$key]['name'] : '';
                } else {
                    $value = $row[$key];
                }
            }
            $csvRow[] = $value;
        }
        fputcsv($output, $csvRow, ';');
    }
    
    fclose($output);
    exit;
}

