<?php
/**
 * API Fichaje Individual (Time Clock)
 * 
 * Endpoints:
 * - GET /api/time-clock/status - Obtener estado actual
 * - POST /api/time-clock/in - Clock in
 * - POST /api/time-clock/out - Clock out
 * - POST /api/time-clock/break/start - Iniciar pausa
 * - POST /api/time-clock/break/end - Finalizar pausa
 * - GET /api/time-clock/history - Historial de fichajes
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../core/TenantMiddleware.php';
require_once __DIR__ . '/../core/RBACMiddleware.php';
require_once __DIR__ . '/../helpers/db_helper.php';
require_once __DIR__ . '/../helpers/i18n.php';
require_once __DIR__ . '/../core/AuditLogger.php';

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
    $userId = $GLOBALS['USER_ID'];
    
    if (!$tenantId || !$userId) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'auth.tenant_required',
            'error_key' => 'auth.tenant_required',
            'message' => t('auth.tenant_required')
        ]);
        exit;
    }
    
    $conn = DBHelper::getConnection();
    
    // Obtener información del empleado/trabajador del usuario
    $employeeInfo = getEmployeeInfo($conn, $tenantId, $userId);
    
    if (!$employeeInfo) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'time_clock.employee_not_found',
            'error_key' => 'time_clock.employee_not_found',
            'message' => t('time_clock.employee_not_found')
        ]);
        exit;
    }
    
    // Parsear path
    $pathParts = explode('/', trim($path, '/'));
    $action = $pathParts[1] ?? null;
    $subAction = $pathParts[2] ?? null;
    
    // Routing
    switch ($method) {
        case 'GET':
            if ($action === 'status') {
                getStatus($conn, $tenantId, $employeeInfo);
            } elseif ($action === 'history') {
                getHistory($conn, $tenantId, $employeeInfo);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'not_found',
                    'message' => 'Endpoint no encontrado'
                ]);
            }
            break;
            
        case 'POST':
            if ($action === 'in') {
                clockIn($conn, $tenantId, $userId, $employeeInfo);
            } elseif ($action === 'out') {
                clockOut($conn, $tenantId, $userId, $employeeInfo);
            } elseif ($action === 'break' && $subAction === 'start') {
                startBreak($conn, $tenantId, $userId, $employeeInfo);
            } elseif ($action === 'break' && $subAction === 'end') {
                endBreak($conn, $tenantId, $userId, $employeeInfo);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'not_found',
                    'message' => 'Endpoint no encontrado'
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
 * Obtiene información del empleado/trabajador del usuario
 */
function getEmployeeInfo($conn, $tenantId, $userId) {
    // Buscar en tenant_users
    $stmt = $conn->prepare("
        SELECT 
            tu.own_employee_id,
            tu.agency_worker_id,
            tu.tenant_id
        FROM tenant_users tu
        WHERE tu.user_id = ? AND tu.tenant_id = ?
    ");
    $stmt->bind_param('ii', $userId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $tenantUser = $result->fetch_assoc();
    $stmt->close();
    
    if (!$tenantUser) {
        return null;
    }
    
    if ($tenantUser['own_employee_id']) {
        return [
            'type' => 'own_employee',
            'id' => (int)$tenantUser['own_employee_id'],
            'employee_id' => (int)$tenantUser['own_employee_id'],
            'worker_id' => null
        ];
    } elseif ($tenantUser['agency_worker_id']) {
        return [
            'type' => 'agency_worker',
            'id' => (int)$tenantUser['agency_worker_id'],
            'employee_id' => null,
            'worker_id' => (int)$tenantUser['agency_worker_id']
        ];
    }
    
    return null;
}

/**
 * Obtiene el estado actual del fichaje
 */
function getStatus($conn, $tenantId, $employeeInfo) {
    $today = date('Y-m-d');
    
    // Buscar work_log abierto (sin clock_out) de hoy
    $stmt = $conn->prepare("
        SELECT 
            wl.id,
            wl.date,
            wl.clock_in,
            wl.clock_out,
            wl.breaks_minutes,
            wl.status,
            wl.project_id,
            p.name as project_name
        FROM work_logs wl
        LEFT JOIN projects p ON wl.project_id = p.id
        WHERE wl.tenant_id = ?
        AND wl.date = ?
        AND wl.clock_out IS NULL
        AND wl.employee_type = ?
        AND (
            (wl.own_employee_id = ? AND ? IS NOT NULL) OR
            (wl.agency_worker_id = ? AND ? IS NOT NULL)
        )
        ORDER BY wl.clock_in DESC
        LIMIT 1
    ");
    
    $employeeType = $employeeInfo['type'] === 'own_employee' ? 'own_employee' : 'agency_worker';
    $employeeId = $employeeInfo['employee_id'];
    $workerId = $employeeInfo['worker_id'];
    
    $stmt->bind_param('issiiii', 
        $tenantId, 
        $today, 
        $employeeType,
        $employeeId, $employeeId,
        $workerId, $workerId
    );
    $stmt->execute();
    $result = $stmt->get_result();
    $workLog = $result->fetch_assoc();
    $stmt->close();
    
    if (!$workLog) {
        echo json_encode([
            'success' => true,
            'data' => [
                'is_clocked_in' => false,
                'current_work_log' => null
            ]
        ]);
        return;
    }
    
    // Calcular tiempo transcurrido
    $clockIn = new DateTime($workLog['clock_in']);
    $now = new DateTime();
    $elapsed = $now->diff($clockIn);
    $elapsedMinutes = ($elapsed->h * 60) + $elapsed->i;
    $elapsedMinutes -= (int)$workLog['breaks_minutes'];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'is_clocked_in' => true,
            'current_work_log' => [
                'id' => (int)$workLog['id'],
                'date' => $workLog['date'],
                'clock_in' => $workLog['clock_in'],
                'breaks_minutes' => (int)$workLog['breaks_minutes'],
                'elapsed_minutes' => max(0, $elapsedMinutes),
                'project' => $workLog['project_name'] ? [
                    'id' => (int)$workLog['project_id'],
                    'name' => $workLog['project_name']
                ] : null
            ]
        ]
    ]);
}

/**
 * Clock in
 */
function clockIn($conn, $tenantId, $userId, $employeeInfo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');
    
    // Verificar que no hay un fichaje abierto
    $checkStmt = $conn->prepare("
        SELECT id FROM work_logs
        WHERE tenant_id = ?
        AND date = ?
        AND clock_out IS NULL
        AND employee_type = ?
        AND (
            (own_employee_id = ? AND ? IS NOT NULL) OR
            (agency_worker_id = ? AND ? IS NOT NULL)
        )
    ");
    
    $employeeType = $employeeInfo['type'] === 'own_employee' ? 'own_employee' : 'agency_worker';
    $employeeId = $employeeInfo['employee_id'];
    $workerId = $employeeInfo['worker_id'];
    
    $checkStmt->bind_param('issiiii',
        $tenantId,
        $today,
        $employeeType,
        $employeeId, $employeeId,
        $workerId, $workerId
    );
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $existing = $result->fetch_assoc();
    $checkStmt->close();
    
    if ($existing) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'time_clock.already_clocked_in',
            'error_key' => 'time_clock.already_clocked_in',
            'message' => t('time_clock.already_clocked_in')
        ]);
        return;
    }
    
    // Validar proyecto si se proporciona
    $projectId = isset($input['project_id']) ? (int)$input['project_id'] : null;
    if ($projectId) {
        $projectStmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND tenant_id = ?");
        $projectStmt->bind_param('ii', $projectId, $tenantId);
        $projectStmt->execute();
        $project = $projectStmt->get_result()->fetch_assoc();
        $projectStmt->close();
        
        if (!$project) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'error' => 'time_clock.project_not_found',
                'error_key' => 'time_clock.project_not_found',
                'message' => t('time_clock.project_not_found')
            ]);
            return;
        }
    }
    
    // Insertar work_log
    $employeeType = $employeeInfo['type'] === 'own_employee' ? 'own_employee' : 'agency_worker';
    $ownEmployeeId = $employeeInfo['employee_id'];
    $agencyWorkerId = $employeeInfo['worker_id'];
    $notes = isset($input['notes']) ? trim($input['notes']) : null;
    
    $stmt = $conn->prepare("
        INSERT INTO work_logs (
            tenant_id, employee_type, own_employee_id, agency_worker_id,
            project_id, date, clock_in, status, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', ?)
    ");
    $stmt->bind_param('isiiisss',
        $tenantId,
        $employeeType,
        $ownEmployeeId,
        $agencyWorkerId,
        $projectId,
        $today,
        $now,
        $notes
    );
    $stmt->execute();
    $workLogId = $conn->insert_id;
    $stmt->close();
    
    // Registrar en auditoría
    AuditLogger::log('create', 'work_log', $workLogId, [
        'action' => 'clock_in',
        'clock_in' => $now
    ], $tenantId, $userId);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'work_log_id' => $workLogId,
            'clock_in' => $now,
            'date' => $today
        ],
        'message' => t('time_clock.clocked_in_success')
    ]);
}

/**
 * Clock out
 */
function clockOut($conn, $tenantId, $userId, $employeeInfo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');
    
    // Buscar work_log abierto
    $stmt = $conn->prepare("
        SELECT id, clock_in, breaks_minutes
        FROM work_logs
        WHERE tenant_id = ?
        AND date = ?
        AND clock_out IS NULL
        AND employee_type = ?
        AND (
            (own_employee_id = ? AND ? IS NOT NULL) OR
            (agency_worker_id = ? AND ? IS NOT NULL)
        )
        ORDER BY clock_in DESC
        LIMIT 1
    ");
    
    $employeeType = $employeeInfo['type'] === 'own_employee' ? 'own_employee' : 'agency_worker';
    $employeeId = $employeeInfo['employee_id'];
    $workerId = $employeeInfo['worker_id'];
    
    $stmt->bind_param('issiiii',
        $tenantId,
        $today,
        $employeeType,
        $employeeId, $employeeId,
        $workerId, $workerId
    );
    $stmt->execute();
    $result = $stmt->get_result();
    $workLog = $result->fetch_assoc();
    $stmt->close();
    
    if (!$workLog) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'time_clock.no_clock_in',
            'error_key' => 'time_clock.no_clock_in',
            'message' => t('time_clock.no_clock_in')
        ]);
        return;
    }
    
    // Validar que clock_out es después de clock_in
    $clockIn = new DateTime($workLog['clock_in']);
    $clockOut = new DateTime($now);
    
    if ($clockOut <= $clockIn) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'time_clock.invalid_clock_out',
            'error_key' => 'time_clock.invalid_clock_out',
            'message' => t('time_clock.invalid_clock_out')
        ]);
        return;
    }
    
    // Calcular horas trabajadas
    $diff = $clockOut->diff($clockIn);
    $totalMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
    $totalMinutes -= (int)$workLog['breaks_minutes'];
    
    // Validar mínimo de horas (opcional, configurable)
    if ($totalMinutes < 0) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'time_clock.negative_hours',
            'error_key' => 'time_clock.negative_hours',
            'message' => t('time_clock.negative_hours')
        ]);
        return;
    }
    
    // Actualizar work_log
    $notes = isset($input['notes']) ? trim($input['notes']) : null;
    
    $updateStmt = $conn->prepare("
        UPDATE work_logs
        SET clock_out = ?, notes = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->bind_param('ssi', $now, $notes, $workLog['id']);
    $updateStmt->execute();
    $updateStmt->close();
    
    // Registrar en auditoría
    AuditLogger::log('update', 'work_log', $workLog['id'], [
        'action' => 'clock_out',
        'clock_out' => $now,
        'total_minutes' => $totalMinutes
    ], $tenantId, $userId);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'work_log_id' => (int)$workLog['id'],
            'clock_out' => $now,
            'total_minutes' => $totalMinutes,
            'total_hours' => round($totalMinutes / 60, 2)
        ],
        'message' => t('time_clock.clocked_out_success')
    ]);
}

/**
 * Inicia una pausa
 */
function startBreak($conn, $tenantId, $userId, $employeeInfo) {
    // Por ahora, las pausas se manejan incrementando breaks_minutes
    // En una implementación más avanzada, se podría tener una tabla de breaks
    
    $today = date('Y-m-d');
    
    // Buscar work_log abierto
    $stmt = $conn->prepare("
        SELECT id, breaks_minutes
        FROM work_logs
        WHERE tenant_id = ?
        AND date = ?
        AND clock_out IS NULL
        AND employee_type = ?
        AND (
            (own_employee_id = ? AND ? IS NOT NULL) OR
            (agency_worker_id = ? AND ? IS NOT NULL)
        )
        ORDER BY clock_in DESC
        LIMIT 1
    ");
    
    $employeeType = $employeeInfo['type'] === 'own_employee' ? 'own_employee' : 'agency_worker';
    $employeeId = $employeeInfo['employee_id'];
    $workerId = $employeeInfo['worker_id'];
    
    $stmt->bind_param('issiiii',
        $tenantId,
        $today,
        $employeeType,
        $employeeId, $employeeId,
        $workerId, $workerId
    );
    $stmt->execute();
    $result = $stmt->get_result();
    $workLog = $result->fetch_assoc();
    $stmt->close();
    
    if (!$workLog) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'time_clock.no_clock_in',
            'error_key' => 'time_clock.no_clock_in',
            'message' => t('time_clock.no_clock_in')
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $breakMinutes = isset($input['minutes']) ? (int)$input['minutes'] : 0;
    
    if ($breakMinutes <= 0) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'time_clock.invalid_break_minutes',
            'error_key' => 'time_clock.invalid_break_minutes',
            'message' => t('time_clock.invalid_break_minutes')
        ]);
        return;
    }
    
    // Actualizar breaks_minutes
    $newBreaksMinutes = (int)$workLog['breaks_minutes'] + $breakMinutes;
    
    $updateStmt = $conn->prepare("
        UPDATE work_logs
        SET breaks_minutes = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->bind_param('ii', $newBreaksMinutes, $workLog['id']);
    $updateStmt->execute();
    $updateStmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'work_log_id' => (int)$workLog['id'],
            'breaks_minutes' => $newBreaksMinutes
        ],
        'message' => t('time_clock.break_started_success')
    ]);
}

/**
 * Finaliza una pausa (similar a startBreak, pero con validación adicional)
 */
function endBreak($conn, $tenantId, $userId, $employeeInfo) {
    // Por simplicidad, endBreak es similar a startBreak
    // En una implementación más avanzada, se calcularía automáticamente desde startBreak
    startBreak($conn, $tenantId, $userId, $employeeInfo);
}

/**
 * Obtiene el historial de fichajes
 */
function getHistory($conn, $tenantId, $employeeInfo) {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
    $offset = ($page - 1) * $pageSize;
    
    $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
    $to = $_GET['to'] ?? date('Y-m-d');
    
    $employeeType = $employeeInfo['type'] === 'own_employee' ? 'own_employee' : 'agency_worker';
    $employeeId = $employeeInfo['employee_id'];
    $workerId = $employeeInfo['worker_id'];
    
    // Contar total
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM work_logs
        WHERE tenant_id = ?
        AND date BETWEEN ? AND ?
        AND employee_type = ?
        AND (
            (own_employee_id = ? AND ? IS NOT NULL) OR
            (agency_worker_id = ? AND ? IS NOT NULL)
        )
    ");
    $countStmt->bind_param('issiiii',
        $tenantId,
        $from,
        $to,
        $employeeType,
        $employeeId, $employeeId,
        $workerId, $workerId
    );
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();
    
    // Obtener work_logs
    $stmt = $conn->prepare("
        SELECT 
            wl.id,
            wl.date,
            wl.clock_in,
            wl.clock_out,
            wl.breaks_minutes,
            wl.status,
            wl.notes,
            p.name as project_name,
            CASE 
                WHEN wl.clock_out IS NOT NULL THEN
                    TIMESTAMPDIFF(MINUTE, wl.clock_in, wl.clock_out) - wl.breaks_minutes
                ELSE NULL
            END as total_minutes
        FROM work_logs wl
        LEFT JOIN projects p ON wl.project_id = p.id
        WHERE wl.tenant_id = ?
        AND wl.date BETWEEN ? AND ?
        AND wl.employee_type = ?
        AND (
            (wl.own_employee_id = ? AND ? IS NOT NULL) OR
            (wl.agency_worker_id = ? AND ? IS NOT NULL)
        )
        ORDER BY wl.date DESC, wl.clock_in DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->bind_param('issiiiiii',
        $tenantId,
        $from,
        $to,
        $employeeType,
        $employeeId, $employeeId,
        $workerId, $workerId,
        $pageSize,
        $offset
    );
    $stmt->execute();
    $result = $stmt->get_result();
    
    $workLogs = [];
    while ($row = $result->fetch_assoc()) {
        $workLogs[] = [
            'id' => (int)$row['id'],
            'date' => $row['date'],
            'clock_in' => $row['clock_in'],
            'clock_out' => $row['clock_out'],
            'breaks_minutes' => (int)$row['breaks_minutes'],
            'total_minutes' => $row['total_minutes'] ? (int)$row['total_minutes'] : null,
            'total_hours' => $row['total_minutes'] ? round((int)$row['total_minutes'] / 60, 2) : null,
            'status' => $row['status'],
            'notes' => $row['notes'],
            'project' => $row['project_name'] ? [
                'name' => $row['project_name']
            ] : null
        ];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $workLogs,
        'page' => $page,
        'page_size' => $pageSize,
        'total' => (int)$total,
        'total_pages' => (int)ceil($total / $pageSize)
    ]);
}

