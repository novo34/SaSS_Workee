<?php
/**
 * API Export Contable/Nómina
 * 
 * Endpoints:
 * - GET /api/exports/payroll - Export para nómina (CSV estándar)
 * - GET /api/exports/accounting - Export para contabilidad (CSV estándar)
 * 
 * Parámetros:
 * - from: fecha desde (YYYY-MM-DD)
 * - to: fecha hasta (YYYY-MM-DD)
 * - employee_id: ID de empleado (opcional)
 * - worker_id: ID de trabajador (opcional)
 * - project_id: ID de proyecto (opcional)
 * - format: csv (default) o json
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
    
    // Parsear tipo de export
    $pathParts = explode('/', trim($path, '/'));
    $exportType = isset($pathParts[1]) ? $pathParts[1] : ($_GET['type'] ?? 'payroll');
    
    // Obtener parámetros
    $from = $_GET['from'] ?? date('Y-m-01'); // Primer día del mes actual
    $to = $_GET['to'] ?? date('Y-m-t'); // Último día del mes actual
    $employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;
    $workerId = isset($_GET['worker_id']) ? (int)$_GET['worker_id'] : null;
    $projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
    $format = $_GET['format'] ?? 'csv';
    
    // Routing por tipo de export
    switch ($exportType) {
        case 'payroll':
            generatePayrollExport($conn, $tenantId, $from, $to, $employeeId, $workerId, $projectId, $format);
            break;
        case 'accounting':
            generateAccountingExport($conn, $tenantId, $from, $to, $projectId, $format);
            break;
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'exports.invalid_type',
                'error_key' => 'exports.invalid_type',
                'message' => t('exports.invalid_type')
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
 * Export para nómina (CSV estándar)
 * Formato: Empleado/Trabajador, Fecha, Horas, Proyecto, etc.
 */
function generatePayrollExport($conn, $tenantId, $from, $to, $employeeId, $workerId, $projectId, $format) {
    $where = ["wl.tenant_id = ?", "wl.date >= ?", "wl.date <= ?", "wl.status = 'approved'"];
    $params = [$tenantId, $from, $to];
    $types = 'iss';
    
    if ($employeeId) {
        $where[] = "wl.own_employee_id = ?";
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
    
    $whereClause = implode(' AND ', $where);
    
    $stmt = $conn->prepare("
        SELECT 
            wl.date,
            wl.clock_in,
            wl.clock_out,
            TIMESTAMPDIFF(MINUTE, wl.clock_in, wl.clock_out) / 60.0 as hours,
            wl.breaks_minutes,
            wl.own_employee_id,
            wl.agency_worker_id,
            wl.employee_type,
            oe.first_name as employee_first_name,
            oe.last_name as employee_last_name,
            oe.employee_code,
            aw.first_name as worker_first_name,
            aw.last_name as worker_last_name,
            aw.worker_code,
            p.name as project_name,
            p.code as project_code
        FROM work_logs wl
        LEFT JOIN own_employees oe ON wl.own_employee_id = oe.id AND wl.tenant_id = oe.tenant_id
        LEFT JOIN agency_workers aw ON wl.agency_worker_id = aw.id AND wl.tenant_id = aw.tenant_id
        LEFT JOIN projects p ON wl.project_id = p.id AND wl.tenant_id = p.tenant_id
        WHERE {$whereClause}
        ORDER BY wl.date ASC, wl.clock_in ASC
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $personName = '';
        $personCode = '';
        $personType = '';
        
        if ($row['own_employee_id']) {
            $personName = trim(($row['employee_first_name'] ?? '') . ' ' . ($row['employee_last_name'] ?? ''));
            $personCode = $row['employee_code'] ?? '';
            $personType = 'Empleado';
        } elseif ($row['agency_worker_id']) {
            $personName = trim(($row['worker_first_name'] ?? '') . ' ' . ($row['worker_last_name'] ?? ''));
            $personCode = $row['worker_code'] ?? '';
            $personType = 'Trabajador';
        }
        
        $data[] = [
            'fecha' => $row['date'],
            'tipo_persona' => $personType,
            'codigo' => $personCode,
            'nombre' => $personName,
            'entrada' => $row['clock_in'] ? date('H:i', strtotime($row['clock_in'])) : '',
            'salida' => $row['clock_out'] ? date('H:i', strtotime($row['clock_out'])) : '',
            'horas' => round((float)$row['hours'], 2),
            'pausas_minutos' => (int)$row['breaks_minutes'],
            'proyecto' => $row['project_name'] ?? '',
            'codigo_proyecto' => $row['project_code'] ?? ''
        ];
    }
    $stmt->close();
    
    if ($format === 'csv') {
        outputCSV($data, 'payroll_export', [
            'fecha' => 'Fecha',
            'tipo_persona' => 'Tipo',
            'codigo' => 'Código',
            'nombre' => 'Nombre',
            'entrada' => 'Entrada',
            'salida' => 'Salida',
            'horas' => 'Horas',
            'pausas_minutos' => 'Pausas (min)',
            'proyecto' => 'Proyecto',
            'codigo_proyecto' => 'Código Proyecto'
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'type' => 'payroll',
        'period' => [
            'from' => $from,
            'to' => $to
        ],
        'filters' => [
            'employee_id' => $employeeId,
            'worker_id' => $workerId,
            'project_id' => $projectId
        ],
        'total_records' => count($data),
        'data' => $data
    ]);
}

/**
 * Export para contabilidad (CSV estándar)
 * Formato: Proyecto, Fecha, Horas totales, Coste estimado, etc.
 */
function generateAccountingExport($conn, $tenantId, $from, $to, $projectId, $format) {
    $where = ["wl.tenant_id = ?", "wl.date >= ?", "wl.date <= ?", "wl.status = 'approved'"];
    $params = [$tenantId, $from, $to];
    $types = 'iss';
    
    if ($projectId) {
        $where[] = "wl.project_id = ?";
        $params[] = $projectId;
        $types .= 'i';
    }
    
    $whereClause = implode(' AND ', $where);
    
    $stmt = $conn->prepare("
        SELECT 
            wl.date,
            wl.project_id,
            p.name as project_name,
            p.code as project_code,
            COUNT(DISTINCT wl.id) as registros,
            COUNT(DISTINCT CASE WHEN wl.own_employee_id IS NOT NULL THEN wl.own_employee_id END) as empleados_count,
            COUNT(DISTINCT CASE WHEN wl.agency_worker_id IS NOT NULL THEN wl.agency_worker_id END) as trabajadores_count,
            SUM(TIMESTAMPDIFF(MINUTE, wl.clock_in, wl.clock_out) / 60.0) as total_hours
        FROM work_logs wl
        LEFT JOIN projects p ON wl.project_id = p.id AND wl.tenant_id = p.tenant_id
        WHERE {$whereClause}
        GROUP BY wl.date, wl.project_id, p.name, p.code
        ORDER BY wl.date ASC, p.name ASC
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'fecha' => $row['date'],
            'proyecto' => $row['project_name'] ?? 'Sin proyecto',
            'codigo_proyecto' => $row['project_code'] ?? '',
            'registros' => (int)$row['registros'],
            'empleados' => (int)$row['empleados_count'],
            'trabajadores' => (int)$row['trabajadores_count'],
            'horas_totales' => round((float)$row['total_hours'], 2)
        ];
    }
    $stmt->close();
    
    if ($format === 'csv') {
        outputCSV($data, 'accounting_export', [
            'fecha' => 'Fecha',
            'proyecto' => 'Proyecto',
            'codigo_proyecto' => 'Código',
            'registros' => 'Registros',
            'empleados' => 'Empleados',
            'trabajadores' => 'Trabajadores',
            'horas_totales' => 'Horas Totales'
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'type' => 'accounting',
        'period' => [
            'from' => $from,
            'to' => $to
        ],
        'filters' => [
            'project_id' => $projectId
        ],
        'total_records' => count($data),
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
            $value = isset($row[$key]) ? $row[$key] : '';
            $csvRow[] = $value;
        }
        fputcsv($output, $csvRow, ';');
    }
    
    fclose($output);
    exit;
}

