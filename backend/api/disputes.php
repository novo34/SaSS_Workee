<?php
/**
 * API Disputas
 * 
 * Endpoints:
 * - GET /api/disputes - Listar disputas (con filtros y paginación)
 * - POST /api/disputes - Crear disputa
 * - GET /api/disputes/{id} - Obtener disputa
 * - PUT /api/disputes/{id}/resolve - Resolver disputa
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
        $action = 'disputes.view';
    } elseif ($method === 'POST') {
        $action = 'disputes.create';
    } elseif ($method === 'PUT') {
        $action = 'disputes.resolve';
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
    $disputeId = null;
    $subAction = null;
    
    if (count($pathParts) >= 2) {
        if (is_numeric($pathParts[1])) {
            $disputeId = (int)$pathParts[1];
            if (isset($pathParts[2])) {
                $subAction = $pathParts[2];
            }
        }
    }
    
    // Routing
    switch ($method) {
        case 'GET':
            if ($disputeId) {
                getDispute($conn, $tenantId, $disputeId);
            } else {
                listDisputes($conn, $tenantId);
            }
            break;
            
        case 'POST':
            createDispute($conn, $tenantId);
            break;
            
        case 'PUT':
            if ($disputeId && $subAction === 'resolve') {
                resolveDispute($conn, $tenantId, $disputeId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.action_required',
                    'error_key' => 'validation.action_required',
                    'message' => t('validation.action_required')
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
 * Listar disputas con filtros y paginación
 */
function listDisputes($conn, $tenantId) {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
    $offset = ($page - 1) * $pageSize;
    
    $where = ["d.tenant_id = ?"];
    $params = [$tenantId];
    $types = 'i';
    
    if (isset($_GET['status'])) {
        $where[] = "d.status = ?";
        $params[] = $_GET['status'];
        $types .= 's';
    }
    
    if (isset($_GET['work_log_id'])) {
        $where[] = "d.work_log_id = ?";
        $params[] = (int)$_GET['work_log_id'];
        $types .= 'i';
    }
    
    if (isset($_GET['from'])) {
        $where[] = "d.created_at >= ?";
        $params[] = $_GET['from'];
        $types .= 's';
    }
    
    if (isset($_GET['to'])) {
        $where[] = "d.created_at <= ?";
        $params[] = $_GET['to'];
        $types .= 's';
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Contar total
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM disputes d
        WHERE $whereClause
    ");
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Obtener disputas
    $stmt = $conn->prepare("
        SELECT 
            d.id,
            d.tenant_id,
            d.work_log_id,
            d.report_line_id,
            d.raised_by_user,
            d.raised_by_tenant_id,
            d.reason,
            d.evidence_json,
            d.status,
            d.resolution,
            d.resolved_by,
            d.resolved_at,
            d.created_at,
            d.updated_at,
            u.first_name as raised_by_first_name,
            u.last_name as raised_by_last_name,
            ru.first_name as resolved_by_first_name,
            ru.last_name as resolved_by_last_name,
            wl.date as work_log_date,
            wl.clock_in,
            wl.clock_out
        FROM disputes d
        LEFT JOIN users u ON d.raised_by_user = u.id
        LEFT JOIN users ru ON d.resolved_by = ru.id
        LEFT JOIN work_logs wl ON d.work_log_id = wl.id
        WHERE $whereClause
        ORDER BY d.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $pageSize;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $disputes = [];
    while ($row = $result->fetch_assoc()) {
        $disputes[] = formatDispute($row);
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $disputes,
        'page' => $page,
        'page_size' => $pageSize,
        'total' => $total,
        'total_pages' => ceil($total / $pageSize)
    ]);
}

/**
 * Obtener disputa por ID
 */
function getDispute($conn, $tenantId, $disputeId) {
    $stmt = $conn->prepare("
        SELECT 
            d.id,
            d.tenant_id,
            d.work_log_id,
            d.report_line_id,
            d.raised_by_user,
            d.raised_by_tenant_id,
            d.reason,
            d.evidence_json,
            d.status,
            d.resolution,
            d.resolved_by,
            d.resolved_at,
            d.created_at,
            d.updated_at,
            u.first_name as raised_by_first_name,
            u.last_name as raised_by_last_name,
            ru.first_name as resolved_by_first_name,
            ru.last_name as resolved_by_last_name,
            wl.date as work_log_date,
            wl.clock_in,
            wl.clock_out
        FROM disputes d
        LEFT JOIN users u ON d.raised_by_user = u.id
        LEFT JOIN users ru ON d.resolved_by = ru.id
        LEFT JOIN work_logs wl ON d.work_log_id = wl.id
        WHERE d.id = ? AND d.tenant_id = ?
    ");
    $stmt->bind_param('ii', $disputeId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $dispute = $result->fetch_assoc();
    $stmt->close();
    
    if (!$dispute) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'disputes.not_found',
            'error_key' => 'disputes.not_found',
            'message' => t('disputes.not_found')
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => formatDispute($dispute)
    ]);
}

/**
 * Crear disputa
 */
function createDispute($conn, $tenantId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validaciones
    if (empty($input['work_log_id']) && empty($input['report_line_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'disputes.work_log_id_required',
            'error_key' => 'disputes.work_log_id_required',
            'message' => t('disputes.work_log_id_required')
        ]);
        return;
    }
    
    if (empty($input['reason'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'disputes.reason_required',
            'error_key' => 'disputes.reason_required',
            'message' => t('disputes.reason_required')
        ]);
        return;
    }
    
    // Validar que existe el work_log o report_line y pertenece al tenant
    if (!empty($input['work_log_id'])) {
        $stmt = $conn->prepare("SELECT id FROM work_logs WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param('ii', $input['work_log_id'], $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $workLog = $result->fetch_assoc();
        $stmt->close();
        
        if (!$workLog) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'validation.work_log_not_found',
                'error_key' => 'validation.work_log_not_found',
                'message' => t('validation.work_log_not_found')
            ]);
            return;
        }
    }
    
    if (!empty($input['report_line_id'])) {
        $stmt = $conn->prepare("
            SELECT id FROM group_shift_report_lines 
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->bind_param('ii', $input['report_line_id'], $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $reportLine = $result->fetch_assoc();
        $stmt->close();
        
        if (!$reportLine) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'validation.report_line_not_found',
                'error_key' => 'validation.report_line_not_found',
                'message' => t('validation.report_line_not_found')
            ]);
            return;
        }
    }
    
    // Validar ventana de reclamación (configurable, default 72 horas)
    // Por ahora, permitir siempre (se puede configurar después)
    
    // Preparar evidencias
    $evidenceJson = null;
    if (!empty($input['evidence'])) {
        $evidence = [
            'photos' => $input['evidence']['photos'] ?? [],
            'comments' => $input['evidence']['comments'] ?? []
        ];
        $evidenceJson = json_encode($evidence);
    }
    
    // Validar que hay al menos una evidencia
    if (empty($evidenceJson) || (empty($input['evidence']['photos']) && empty($input['evidence']['comments']))) {
        if (empty($input['reason']) || strlen(trim($input['reason'])) < 10) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'disputes.evidence_required',
                'error_key' => 'disputes.evidence_required',
                'message' => t('disputes.evidence_required')
            ]);
            return;
        }
    }
    
    $userId = $GLOBALS['USER_ID'];
    
    // Obtener tenant_id del usuario que crea la disputa
    $stmt = $conn->prepare("
        SELECT tenant_id FROM tenant_users WHERE user_id = ? AND tenant_id = ?
    ");
    $stmt->bind_param('ii', $userId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userTenant = $result->fetch_assoc();
    $stmt->close();
    
    $raisedByTenantId = $userTenant ? $userTenant['tenant_id'] : $tenantId;
    
    // Insertar disputa
    $stmt = $conn->prepare("
        INSERT INTO disputes (
            tenant_id, work_log_id, report_line_id, raised_by_user, raised_by_tenant_id,
            reason, evidence_json, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'disputado')
    ");
    
    $workLogId = !empty($input['work_log_id']) ? (int)$input['work_log_id'] : null;
    $reportLineId = !empty($input['report_line_id']) ? (int)$input['report_line_id'] : null;
    
    $stmt->bind_param('iiiiiss', 
        $tenantId,
        $workLogId,
        $reportLineId,
        $userId,
        $raisedByTenantId,
        $input['reason'],
        $evidenceJson
    );
    
    if (!$stmt->execute()) {
        $stmt->close();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'disputes.create_failed',
            'error_key' => 'disputes.create_failed',
            'message' => t('disputes.create_failed')
        ]);
        return;
    }
    
    $disputeId = $conn->insert_id;
    $stmt->close();
    
    // Obtener disputa creada
    getDispute($conn, $tenantId, $disputeId);
}

/**
 * Resolver disputa
 */
function resolveDispute($conn, $tenantId, $disputeId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['resolution'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'validation.resolution_required',
            'error_key' => 'validation.resolution_required',
            'message' => t('validation.resolution_required')
        ]);
        return;
    }
    
    // Verificar que existe y está en estado disputado
    $stmt = $conn->prepare("
        SELECT * FROM disputes 
        WHERE id = ? AND tenant_id = ? AND status = 'disputado'
    ");
    $stmt->bind_param('ii', $disputeId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $dispute = $result->fetch_assoc();
    $stmt->close();
    
    if (!$dispute) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'disputes.cannot_resolve',
            'error_key' => 'disputes.cannot_resolve',
            'message' => t('disputes.cannot_resolve')
        ]);
        return;
    }
    
    // Actualizar disputa
    $userId = $GLOBALS['USER_ID'];
    $stmt = $conn->prepare("
        UPDATE disputes 
        SET status = 'resuelto',
            resolution = ?,
            resolved_by = ?,
            resolved_at = NOW()
        WHERE id = ? AND tenant_id = ?
    ");
    
    $stmt->bind_param('siii', 
        $input['resolution'],
        $userId,
        $disputeId,
        $tenantId
    );
    
    if (!$stmt->execute()) {
        $stmt->close();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'disputes.resolve_failed',
            'error_key' => 'disputes.resolve_failed',
            'message' => t('disputes.resolve_failed')
        ]);
        return;
    }
    
    $stmt->close();
    
    // Obtener disputa actualizada
    getDispute($conn, $tenantId, $disputeId);
}

/**
 * Formatear disputa para respuesta
 */
function formatDispute($row) {
    $evidence = null;
    if (!empty($row['evidence_json'])) {
        $evidence = json_decode($row['evidence_json'], true);
    }
    
    return [
        'id' => (int)$row['id'],
        'tenant_id' => (int)$row['tenant_id'],
        'work_log_id' => $row['work_log_id'] ? (int)$row['work_log_id'] : null,
        'report_line_id' => $row['report_line_id'] ? (int)$row['report_line_id'] : null,
        'raised_by_user' => (int)$row['raised_by_user'],
        'raised_by_tenant_id' => (int)$row['raised_by_tenant_id'],
        'raised_by_name' => trim(($row['raised_by_first_name'] ?? '') . ' ' . ($row['raised_by_last_name'] ?? '')),
        'reason' => $row['reason'],
        'evidence' => $evidence,
        'status' => $row['status'],
        'resolution' => $row['resolution'],
        'resolved_by' => $row['resolved_by'] ? (int)$row['resolved_by'] : null,
        'resolved_by_name' => $row['resolved_by'] ? trim(($row['resolved_by_first_name'] ?? '') . ' ' . ($row['resolved_by_last_name'] ?? '')) : null,
        'resolved_at' => $row['resolved_at'],
        'work_log_date' => $row['work_log_date'],
        'work_log_clock_in' => $row['clock_in'],
        'work_log_clock_out' => $row['clock_out'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
}

