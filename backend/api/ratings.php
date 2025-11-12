<?php
/**
 * API Valoraciones
 * 
 * Endpoints:
 * - GET /api/ratings - Listar valoraciones (con filtros y paginación)
 * - POST /api/ratings - Crear valoración
 * - GET /api/ratings/{id} - Obtener valoración
 * - GET /api/ratings/worker/{worker_id} - Obtener valoraciones de un trabajador
 * - GET /api/ratings/worker/{worker_id}/average - Obtener promedio de valoraciones
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
        $action = 'ratings.view';
    } elseif ($method === 'POST') {
        $action = 'ratings.create';
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
    $ratingId = null;
    $subAction = null;
    $workerId = null;
    
    if (count($pathParts) >= 2) {
        if (is_numeric($pathParts[1])) {
            $ratingId = (int)$pathParts[1];
        } elseif ($pathParts[1] === 'worker' && isset($pathParts[2]) && is_numeric($pathParts[2])) {
            $workerId = (int)$pathParts[2];
            if (isset($pathParts[3])) {
                $subAction = $pathParts[3];
            }
        }
    }
    
    // Routing
    switch ($method) {
        case 'GET':
            if ($ratingId) {
                getRating($conn, $tenantId, $ratingId);
            } elseif ($workerId && $subAction === 'average') {
                getWorkerAverageRating($conn, $tenantId, $workerId);
            } elseif ($workerId) {
                listWorkerRatings($conn, $tenantId, $workerId);
            } else {
                listRatings($conn, $tenantId);
            }
            break;
            
        case 'POST':
            createRating($conn, $tenantId);
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
 * Listar valoraciones con filtros y paginación
 */
function listRatings($conn, $tenantId) {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
    $offset = ($page - 1) * $pageSize;
    
    $where = ["r.tenant_company_id = ?"];
    $params = [$tenantId];
    $types = 'i';
    
    if (isset($_GET['worker_id'])) {
        $where[] = "r.agency_worker_id = ?";
        $params[] = (int)$_GET['worker_id'];
        $types .= 'i';
    }
    
    if (isset($_GET['min_score'])) {
        $where[] = "r.score >= ?";
        $params[] = (int)$_GET['min_score'];
        $types .= 'i';
    }
    
    if (isset($_GET['from'])) {
        $where[] = "r.created_at >= ?";
        $params[] = $_GET['from'];
        $types .= 's';
    }
    
    if (isset($_GET['to'])) {
        $where[] = "r.created_at <= ?";
        $params[] = $_GET['to'];
        $types .= 's';
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Contar total
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM ratings r
        WHERE $whereClause
    ");
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Obtener valoraciones
    $stmt = $conn->prepare("
        SELECT 
            r.id,
            r.tenant_company_id,
            r.agency_worker_id,
            r.rated_by_user,
            r.score,
            r.tags_json,
            r.comment,
            r.created_at,
            u.first_name as rated_by_first_name,
            u.last_name as rated_by_last_name,
            w.first_name as worker_first_name,
            w.last_name as worker_last_name,
            w.worker_code
        FROM ratings r
        LEFT JOIN users u ON r.rated_by_user = u.id
        LEFT JOIN agency_workers w ON r.agency_worker_id = w.id
        WHERE $whereClause
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $pageSize;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ratings = [];
    while ($row = $result->fetch_assoc()) {
        $ratings[] = formatRating($row);
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $ratings,
        'page' => $page,
        'page_size' => $pageSize,
        'total' => $total,
        'total_pages' => ceil($total / $pageSize)
    ]);
}

/**
 * Listar valoraciones de un trabajador específico
 */
function listWorkerRatings($conn, $tenantId, $workerId) {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
    $offset = ($page - 1) * $pageSize;
    
    // Verificar que el trabajador existe y pertenece a una temporera vinculada
    $stmt = $conn->prepare("
        SELECT w.id, w.tenant_id 
        FROM agency_workers w
        INNER JOIN agency_company_links acl ON w.tenant_id = acl.agency_tenant_id
        WHERE w.id = ? AND acl.company_tenant_id = ? AND acl.status = 'active'
    ");
    $stmt->bind_param('ii', $workerId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $worker = $result->fetch_assoc();
    $stmt->close();
    
    if (!$worker) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'validation.worker_not_found',
            'error_key' => 'validation.worker_not_found',
            'message' => t('validation.worker_not_found')
        ]);
        return;
    }
    
    // Contar total
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM ratings r
        WHERE r.agency_worker_id = ? AND r.tenant_company_id = ?
    ");
    $countStmt->bind_param('ii', $workerId, $tenantId);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Obtener valoraciones
    $stmt = $conn->prepare("
        SELECT 
            r.id,
            r.tenant_company_id,
            r.agency_worker_id,
            r.rated_by_user,
            r.score,
            r.tags_json,
            r.comment,
            r.created_at,
            u.first_name as rated_by_first_name,
            u.last_name as rated_by_last_name,
            w.first_name as worker_first_name,
            w.last_name as worker_last_name,
            w.worker_code
        FROM ratings r
        LEFT JOIN users u ON r.rated_by_user = u.id
        LEFT JOIN agency_workers w ON r.agency_worker_id = w.id
        WHERE r.agency_worker_id = ? AND r.tenant_company_id = ?
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->bind_param('iiii', $workerId, $tenantId, $pageSize, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ratings = [];
    while ($row = $result->fetch_assoc()) {
        $ratings[] = formatRating($row);
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $ratings,
        'page' => $page,
        'page_size' => $pageSize,
        'total' => $total,
        'total_pages' => ceil($total / $pageSize)
    ]);
}

/**
 * Obtener promedio de valoraciones de un trabajador
 */
function getWorkerAverageRating($conn, $tenantId, $workerId) {
    // Verificar que el trabajador existe y pertenece a una temporera vinculada
    $stmt = $conn->prepare("
        SELECT w.id, w.tenant_id 
        FROM agency_workers w
        INNER JOIN agency_company_links acl ON w.tenant_id = acl.agency_tenant_id
        WHERE w.id = ? AND acl.company_tenant_id = ? AND acl.status = 'active'
    ");
    $stmt->bind_param('ii', $workerId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $worker = $result->fetch_assoc();
    $stmt->close();
    
    if (!$worker) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'validation.worker_not_found',
            'error_key' => 'validation.worker_not_found',
            'message' => t('validation.worker_not_found')
        ]);
        return;
    }
    
    // Calcular promedio
    $stmt = $conn->prepare("
        SELECT 
            AVG(r.score) as avg_score,
            COUNT(*) as total_ratings,
            SUM(CASE WHEN r.score = 5 THEN 1 ELSE 0 END) as score_5,
            SUM(CASE WHEN r.score = 4 THEN 1 ELSE 0 END) as score_4,
            SUM(CASE WHEN r.score = 3 THEN 1 ELSE 0 END) as score_3,
            SUM(CASE WHEN r.score = 2 THEN 1 ELSE 0 END) as score_2,
            SUM(CASE WHEN r.score = 1 THEN 1 ELSE 0 END) as score_1
        FROM ratings r
        WHERE r.agency_worker_id = ? AND r.tenant_company_id = ?
    ");
    $stmt->bind_param('ii', $workerId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'worker_id' => $workerId,
            'average_score' => $stats['avg_score'] ? round((float)$stats['avg_score'], 2) : null,
            'total_ratings' => (int)$stats['total_ratings'],
            'distribution' => [
                'score_5' => (int)$stats['score_5'],
                'score_4' => (int)$stats['score_4'],
                'score_3' => (int)$stats['score_3'],
                'score_2' => (int)$stats['score_2'],
                'score_1' => (int)$stats['score_1']
            ]
        ]
    ]);
}

/**
 * Obtener valoración por ID
 */
function getRating($conn, $tenantId, $ratingId) {
    $stmt = $conn->prepare("
        SELECT 
            r.id,
            r.tenant_company_id,
            r.agency_worker_id,
            r.rated_by_user,
            r.score,
            r.tags_json,
            r.comment,
            r.created_at,
            u.first_name as rated_by_first_name,
            u.last_name as rated_by_last_name,
            w.first_name as worker_first_name,
            w.last_name as worker_last_name,
            w.worker_code
        FROM ratings r
        LEFT JOIN users u ON r.rated_by_user = u.id
        LEFT JOIN agency_workers w ON r.agency_worker_id = w.id
        WHERE r.id = ? AND r.tenant_company_id = ?
    ");
    $stmt->bind_param('ii', $ratingId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rating = $result->fetch_assoc();
    $stmt->close();
    
    if (!$rating) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'ratings.not_found',
            'error_key' => 'ratings.not_found',
            'message' => t('ratings.not_found')
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => formatRating($rating)
    ]);
}

/**
 * Crear valoración
 */
function createRating($conn, $tenantId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validaciones
    if (empty($input['agency_worker_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ratings.worker_id_required',
            'error_key' => 'ratings.worker_id_required',
            'message' => t('ratings.worker_id_required')
        ]);
        return;
    }
    
    if (empty($input['score']) || !is_numeric($input['score'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ratings.score_required',
            'error_key' => 'ratings.score_required',
            'message' => t('ratings.score_required')
        ]);
        return;
    }
    
    $score = (int)$input['score'];
    if ($score < 1 || $score > 5) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ratings.score_invalid',
            'error_key' => 'ratings.score_invalid',
            'message' => t('ratings.score_invalid')
        ]);
        return;
    }
    
    $workerId = (int)$input['agency_worker_id'];
    
    // Verificar que el trabajador existe y pertenece a una temporera vinculada
    $stmt = $conn->prepare("
        SELECT w.id, w.tenant_id 
        FROM agency_workers w
        INNER JOIN agency_company_links acl ON w.tenant_id = acl.agency_tenant_id
        WHERE w.id = ? AND acl.company_tenant_id = ? AND acl.status = 'active'
    ");
    $stmt->bind_param('ii', $workerId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $worker = $result->fetch_assoc();
    $stmt->close();
    
    if (!$worker) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'validation.worker_not_found',
            'error_key' => 'validation.worker_not_found',
            'message' => t('validation.worker_not_found')
        ]);
        return;
    }
    
    // Verificar que el usuario no está valorando a un trabajador de su propia empresa
    // (solo empresas pueden valorar trabajadores de temporeras)
    $userId = $GLOBALS['USER_ID'];
    $stmt = $conn->prepare("
        SELECT t.org_type FROM tenant_users tu
        INNER JOIN tenants t ON tu.tenant_id = t.id
        WHERE tu.user_id = ? AND tu.tenant_id = ?
    ");
    $stmt->bind_param('ii', $userId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userTenant = $result->fetch_assoc();
    $stmt->close();
    
    if ($userTenant && $userTenant['org_type'] !== 'empresa') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'ratings.cannot_rate_own_worker',
            'error_key' => 'ratings.cannot_rate_own_worker',
            'message' => t('ratings.cannot_rate_own_worker')
        ]);
        return;
    }
    
    // Verificar si ya existe una valoración del mismo usuario para este trabajador
    // (opcional: permitir múltiples valoraciones o solo una)
    // Por ahora, permitir múltiples valoraciones
    
    // Preparar tags
    $tagsJson = null;
    if (!empty($input['tags']) && is_array($input['tags'])) {
        $tagsJson = json_encode($input['tags']);
    }
    
    $userId = $GLOBALS['USER_ID'];
    
    // Insertar valoración
    $stmt = $conn->prepare("
        INSERT INTO ratings (
            tenant_company_id, agency_worker_id, rated_by_user, score, tags_json, comment
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $comment = !empty($input['comment']) ? $input['comment'] : null;
    
    $stmt->bind_param('iiiiss', 
        $tenantId,
        $workerId,
        $userId,
        $score,
        $tagsJson,
        $comment
    );
    
    if (!$stmt->execute()) {
        $stmt->close();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'ratings.create_failed',
            'error_key' => 'ratings.create_failed',
            'message' => t('ratings.create_failed')
        ]);
        return;
    }
    
    $ratingId = $conn->insert_id;
    $stmt->close();
    
    // Obtener valoración creada
    getRating($conn, $tenantId, $ratingId);
}

/**
 * Formatear valoración para respuesta
 */
function formatRating($row) {
    $tags = null;
    if (!empty($row['tags_json'])) {
        $tags = json_decode($row['tags_json'], true);
    }
    
    return [
        'id' => (int)$row['id'],
        'tenant_company_id' => (int)$row['tenant_company_id'],
        'agency_worker_id' => (int)$row['agency_worker_id'],
        'worker_name' => trim(($row['worker_first_name'] ?? '') . ' ' . ($row['worker_last_name'] ?? '')),
        'worker_code' => $row['worker_code'],
        'rated_by_user' => (int)$row['rated_by_user'],
        'rated_by_name' => trim(($row['rated_by_first_name'] ?? '') . ' ' . ($row['rated_by_last_name'] ?? '')),
        'score' => (int)$row['score'],
        'tags' => $tags,
        'comment' => $row['comment'],
        'created_at' => $row['created_at']
    ];
}

