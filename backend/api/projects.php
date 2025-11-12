<?php
/**
 * API Proyectos
 * 
 * Endpoints:
 * - GET /api/projects - Listar proyectos
 * - GET /api/projects/{id} - Obtener proyecto
 * - POST /api/projects - Crear proyecto
 * - PUT /api/projects/{id} - Actualizar proyecto
 * - DELETE /api/projects/{id} - Eliminar proyecto
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
    RBACMiddleware::requirePermission('projects.view');
    
    $conn = DBHelper::getConnection();
    
    // Parsear path
    $pathParts = explode('/', trim($path, '/'));
    $projectId = null;
    
    if (count($pathParts) >= 2 && is_numeric($pathParts[1])) {
        $projectId = (int)$pathParts[1];
    }
    
    // Routing
    switch ($method) {
        case 'GET':
            if ($projectId) {
                getProject($conn, $tenantId, $projectId);
            } else {
                listProjects($conn, $tenantId);
            }
            break;
            
        case 'POST':
            createProject($conn, $tenantId, $userId);
            break;
            
        case 'PUT':
            if ($projectId) {
                updateProject($conn, $tenantId, $userId, $projectId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.id_required',
                    'error_key' => 'validation.id_required',
                    'message' => t('validation.id_required')
                ]);
            }
            break;
            
        case 'DELETE':
            if ($projectId) {
                deleteProject($conn, $tenantId, $userId, $projectId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.id_required',
                    'error_key' => 'validation.id_required',
                    'message' => t('validation.id_required')
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
 * Lista proyectos con paginación
 */
function listProjects($conn, $tenantId) {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
    $offset = ($page - 1) * $pageSize;
    
    $search = $_GET['q'] ?? null;
    $clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;
    
    $where = ["p.tenant_id = ?"];
    $params = [$tenantId];
    $types = 'i';
    
    if ($search) {
        $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'ss';
    }
    
    if ($clientId) {
        $where[] = "p.client_id = ?";
        $params[] = $clientId;
        $types .= 'i';
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Contar total
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM projects p
        WHERE {$whereClause}
    ");
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();
    
    // Obtener proyectos
    $stmt = $conn->prepare("
        SELECT 
            p.id,
            p.client_id,
            c.name as client_name,
            p.name,
            p.code,
            p.description,
            p.location,
            p.start_date,
            p.end_date,
            p.status,
            p.created_at,
            p.updated_at
        FROM projects p
        LEFT JOIN clients c ON p.client_id = c.id
        WHERE {$whereClause}
        ORDER BY p.name ASC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $pageSize;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $projects = [];
    while ($row = $result->fetch_assoc()) {
        $projects[] = [
            'id' => (int)$row['id'],
            'client_id' => $row['client_id'] ? (int)$row['client_id'] : null,
            'client_name' => $row['client_name'],
            'name' => $row['name'],
            'code' => $row['code'],
            'description' => $row['description'],
            'location' => $row['location'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $projects,
        'page' => $page,
        'page_size' => $pageSize,
        'total' => (int)$total,
        'total_pages' => (int)ceil($total / $pageSize)
    ]);
}

/**
 * Obtiene un proyecto específico
 */
function getProject($conn, $tenantId, $projectId) {
    $stmt = $conn->prepare("
        SELECT 
            p.id,
            p.client_id,
            c.name as client_name,
            p.name,
            p.code,
            p.description,
            p.location,
            p.start_date,
            p.end_date,
            p.status,
            p.created_at,
            p.updated_at
        FROM projects p
        LEFT JOIN clients c ON p.client_id = c.id
        WHERE p.id = ? AND p.tenant_id = ?
    ");
    $stmt->bind_param('ii', $projectId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $project = $result->fetch_assoc();
    $stmt->close();
    
    if (!$project) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'projects.not_found',
            'error_key' => 'projects.not_found',
            'message' => t('projects.not_found')
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int)$project['id'],
            'client_id' => $project['client_id'] ? (int)$project['client_id'] : null,
            'client_name' => $project['client_name'],
            'name' => $project['name'],
            'code' => $project['code'],
            'description' => $project['description'],
            'location' => $project['location'],
            'start_date' => $project['start_date'],
            'end_date' => $project['end_date'],
            'status' => $project['status'],
            'created_at' => $project['created_at'],
            'updated_at' => $project['updated_at']
        ]
    ]);
}

/**
 * Crea un nuevo proyecto
 */
function createProject($conn, $tenantId, $userId) {
    RBACMiddleware::requirePermission('projects.create');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'validation.invalid_json',
            'error_key' => 'validation.invalid_json',
            'message' => t('validation.invalid_json')
        ]);
        exit;
    }
    
    // Validar campos requeridos
    if (empty($input['name'])) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'validation.required',
            'error_key' => 'validation.required',
            'error_params' => ['field' => 'name'],
            'message' => t('validation.required', ['field' => 'name'])
        ]);
        exit;
    }
    
    $name = trim($input['name']);
    $clientId = isset($input['client_id']) ? (int)$input['client_id'] : null;
    $code = !empty($input['code']) ? trim($input['code']) : null;
    $description = !empty($input['description']) ? trim($input['description']) : null;
    $location = !empty($input['location']) ? trim($input['location']) : null;
    $startDate = !empty($input['start_date']) ? $input['start_date'] : null;
    $endDate = !empty($input['end_date']) ? $input['end_date'] : null;
    $status = isset($input['status']) ? $input['status'] : 'planning';
    
    // Validar status
    $validStatuses = ['planning', 'active', 'on_hold', 'completed', 'canceled'];
    if (!in_array($status, $validStatuses)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'validation.invalid_status',
            'error_key' => 'validation.invalid_status',
            'message' => t('validation.invalid_status')
        ]);
        exit;
    }
    
    // Validar que el cliente existe si se proporciona
    if ($clientId) {
        $clientStmt = $conn->prepare("SELECT id FROM clients WHERE id = ? AND tenant_id = ?");
        $clientStmt->bind_param('ii', $clientId, $tenantId);
        $clientStmt->execute();
        $client = $clientStmt->get_result()->fetch_assoc();
        $clientStmt->close();
        
        if (!$client) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'error' => 'projects.client_not_found',
                'error_key' => 'projects.client_not_found',
                'message' => t('projects.client_not_found')
            ]);
            exit;
        }
    }
    
    // Insertar proyecto
    $stmt = $conn->prepare("
        INSERT INTO projects (tenant_id, client_id, name, code, description, location, start_date, end_date, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('iisssssss', $tenantId, $clientId, $name, $code, $description, $location, $startDate, $endDate, $status);
    $stmt->execute();
    $projectId = $conn->insert_id;
    $stmt->close();
    
    // Registrar en auditoría
    AuditLogger::logCreate('project', $projectId, [
        'name' => $name,
        'client_id' => $clientId
    ], $tenantId, $userId);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $projectId,
            'client_id' => $clientId,
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'location' => $location,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status
        ],
        'message' => t('projects.created_success')
    ]);
}

/**
 * Actualiza un proyecto
 */
function updateProject($conn, $tenantId, $userId, $projectId) {
    RBACMiddleware::requirePermission('projects.update');
    
    // Verificar que existe
    $checkStmt = $conn->prepare("SELECT * FROM projects WHERE id = ? AND tenant_id = ?");
    $checkStmt->bind_param('ii', $projectId, $tenantId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $before = $result->fetch_assoc();
    $checkStmt->close();
    
    if (!$before) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'projects.not_found',
            'error_key' => 'projects.not_found',
            'message' => t('projects.not_found')
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'validation.invalid_json',
            'error_key' => 'validation.invalid_json',
            'message' => t('validation.invalid_json')
        ]);
        return;
    }
    
    // Construir UPDATE
    $updates = [];
    $params = [];
    $types = '';
    
    if (isset($input['name'])) {
        $updates[] = "name = ?";
        $params[] = trim($input['name']);
        $types .= 's';
    }
    
    if (isset($input['client_id'])) {
        $clientId = (int)$input['client_id'];
        // Validar que el cliente existe
        if ($clientId) {
            $clientStmt = $conn->prepare("SELECT id FROM clients WHERE id = ? AND tenant_id = ?");
            $clientStmt->bind_param('ii', $clientId, $tenantId);
            $clientStmt->execute();
            $client = $clientStmt->get_result()->fetch_assoc();
            $clientStmt->close();
            
            if (!$client) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'error' => 'projects.client_not_found',
                    'error_key' => 'projects.client_not_found',
                    'message' => t('projects.client_not_found')
                ]);
                return;
            }
        }
        $updates[] = "client_id = ?";
        $params[] = $clientId ?: null;
        $types .= 'i';
    }
    
    if (isset($input['description'])) {
        $updates[] = "description = ?";
        $params[] = !empty($input['description']) ? trim($input['description']) : null;
        $types .= 's';
    }
    
    if (isset($input['code'])) {
        $updates[] = "code = ?";
        $params[] = !empty($input['code']) ? trim($input['code']) : null;
        $types .= 's';
    }
    
    if (isset($input['location'])) {
        $updates[] = "location = ?";
        $params[] = !empty($input['location']) ? trim($input['location']) : null;
        $types .= 's';
    }
    
    if (isset($input['start_date'])) {
        $updates[] = "start_date = ?";
        $params[] = !empty($input['start_date']) ? $input['start_date'] : null;
        $types .= 's';
    }
    
    if (isset($input['end_date'])) {
        $updates[] = "end_date = ?";
        $params[] = !empty($input['end_date']) ? $input['end_date'] : null;
        $types .= 's';
    }
    
    if (isset($input['status'])) {
        $status = $input['status'];
        $validStatuses = ['planning', 'active', 'on_hold', 'completed', 'canceled'];
        if (!in_array($status, $validStatuses)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'error' => 'validation.invalid_status',
                'error_key' => 'validation.invalid_status',
                'message' => t('validation.invalid_status')
            ]);
            return;
        }
        $updates[] = "status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'validation.no_changes',
            'error_key' => 'validation.no_changes',
            'message' => t('validation.no_changes')
        ]);
        return;
    }
    
    $params[] = $projectId;
    $params[] = $tenantId;
    $types .= 'ii';
    
    $stmt = $conn->prepare("
        UPDATE projects
        SET " . implode(', ', $updates) . ", updated_at = NOW()
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
    
    // Obtener datos actualizados
    getProject($conn, $tenantId, $projectId);
    
    // Registrar en auditoría
    $afterStmt = $conn->prepare("SELECT * FROM projects WHERE id = ? AND tenant_id = ?");
    $afterStmt->bind_param('ii', $projectId, $tenantId);
    $afterStmt->execute();
    $after = $afterStmt->get_result()->fetch_assoc();
    $afterStmt->close();
    
    AuditLogger::logUpdate('project', $projectId, $before, $after, $tenantId, $userId);
}

/**
 * Elimina un proyecto
 */
function deleteProject($conn, $tenantId, $userId, $projectId) {
    RBACMiddleware::requirePermission('projects.delete');
    
    // Verificar que existe
    $checkStmt = $conn->prepare("SELECT * FROM projects WHERE id = ? AND tenant_id = ?");
    $checkStmt->bind_param('ii', $projectId, $tenantId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $project = $result->fetch_assoc();
    $checkStmt->close();
    
    if (!$project) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'projects.not_found',
            'error_key' => 'projects.not_found',
            'message' => t('projects.not_found')
        ]);
        return;
    }
    
    // Verificar que no tiene work_orders asociados
    $workOrdersStmt = $conn->prepare("SELECT COUNT(*) as count FROM work_orders WHERE project_id = ? AND tenant_id = ?");
    $workOrdersStmt->bind_param('ii', $projectId, $tenantId);
    $workOrdersStmt->execute();
    $workOrdersCount = $workOrdersStmt->get_result()->fetch_assoc()['count'];
    $workOrdersStmt->close();
    
    if ($workOrdersCount > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'projects.has_work_orders',
            'error_key' => 'projects.has_work_orders',
            'message' => t('projects.has_work_orders', ['count' => $workOrdersCount])
        ]);
        return;
    }
    
    // Eliminar
    $deleteStmt = $conn->prepare("DELETE FROM projects WHERE id = ? AND tenant_id = ?");
    $deleteStmt->bind_param('ii', $projectId, $tenantId);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    // Registrar en auditoría
    AuditLogger::logDelete('project', $projectId, $project, $tenantId, $userId);
    
    echo json_encode([
        'success' => true,
        'message' => t('projects.deleted_success')
    ]);
}

