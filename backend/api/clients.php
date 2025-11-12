<?php
/**
 * API Clientes
 * 
 * Endpoints:
 * - GET /api/clients - Listar clientes
 * - GET /api/clients/{id} - Obtener cliente
 * - POST /api/clients - Crear cliente
 * - PUT /api/clients/{id} - Actualizar cliente
 * - DELETE /api/clients/{id} - Eliminar cliente
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
    RBACMiddleware::requirePermission('clients.view');
    
    $conn = DBHelper::getConnection();
    
    // Parsear path
    $pathParts = explode('/', trim($path, '/'));
    $clientId = null;
    
    if (count($pathParts) >= 2 && is_numeric($pathParts[1])) {
        $clientId = (int)$pathParts[1];
    }
    
    // Routing
    switch ($method) {
        case 'GET':
            if ($clientId) {
                getClient($conn, $tenantId, $clientId);
            } else {
                listClients($conn, $tenantId);
            }
            break;
            
        case 'POST':
            createClient($conn, $tenantId, $userId);
            break;
            
        case 'PUT':
            if ($clientId) {
                updateClient($conn, $tenantId, $userId, $clientId);
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
            if ($clientId) {
                deleteClient($conn, $tenantId, $userId, $clientId);
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
 * Lista clientes con paginación
 */
function listClients($conn, $tenantId) {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
    $offset = ($page - 1) * $pageSize;
    
    $search = $_GET['q'] ?? null;
    
    $where = ["c.tenant_id = ?"];
    $params = [$tenantId];
    $types = 'i';
    
    if ($search) {
        $where[] = "(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'sss';
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Contar total
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM clients c
        WHERE {$whereClause}
    ");
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();
    
    // Obtener clientes
    $stmt = $conn->prepare("
        SELECT 
            c.id,
            c.name,
            c.contact_name,
            c.email,
            c.phone,
            c.address,
            c.is_active,
            c.created_at,
            c.updated_at,
            COUNT(p.id) as project_count
        FROM clients c
        LEFT JOIN projects p ON c.id = p.client_id AND p.tenant_id = c.tenant_id
        WHERE {$whereClause}
        GROUP BY c.id
        ORDER BY c.name ASC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $pageSize;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $clients = [];
    while ($row = $result->fetch_assoc()) {
        $clients[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'contact_name' => $row['contact_name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'address' => $row['address'],
            'is_active' => (bool)$row['is_active'],
            'project_count' => (int)$row['project_count'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $clients,
        'page' => $page,
        'page_size' => $pageSize,
        'total' => (int)$total,
        'total_pages' => (int)ceil($total / $pageSize)
    ]);
}

/**
 * Obtiene un cliente específico
 */
function getClient($conn, $tenantId, $clientId) {
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            COUNT(p.id) as project_count
        FROM clients c
        LEFT JOIN projects p ON c.id = p.client_id AND p.tenant_id = c.tenant_id
        WHERE c.id = ? AND c.tenant_id = ?
        GROUP BY c.id
    ");
    $stmt->bind_param('ii', $clientId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $client = $result->fetch_assoc();
    $stmt->close();
    
    if (!$client) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'clients.not_found',
            'error_key' => 'clients.not_found',
            'message' => t('clients.not_found')
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int)$client['id'],
            'name' => $client['name'],
            'contact_name' => $client['contact_name'],
            'email' => $client['email'],
            'phone' => $client['phone'],
            'address' => $client['address'],
            'is_active' => (bool)$client['is_active'],
            'project_count' => (int)$client['project_count'],
            'created_at' => $client['created_at'],
            'updated_at' => $client['updated_at']
        ]
    ]);
}

/**
 * Crea un nuevo cliente
 */
function createClient($conn, $tenantId, $userId) {
    RBACMiddleware::requirePermission('clients.create');
    
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
    $contactName = !empty($input['contact_name']) ? trim($input['contact_name']) : null;
    $email = !empty($input['email']) ? trim($input['email']) : null;
    $phone = !empty($input['phone']) ? trim($input['phone']) : null;
    $address = !empty($input['address']) ? trim($input['address']) : null;
    $isActive = isset($input['is_active']) ? (bool)$input['is_active'] : true;
    
    // Validar email si se proporciona
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'validation.email_invalid',
            'error_key' => 'validation.email_invalid',
            'message' => t('validation.email_invalid')
        ]);
        exit;
    }
    
    // Insertar cliente
    $stmt = $conn->prepare("
        INSERT INTO clients (tenant_id, name, contact_name, email, phone, address, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('isssssi', $tenantId, $name, $contactName, $email, $phone, $address, $isActive);
    $stmt->execute();
    $clientId = $conn->insert_id;
    $stmt->close();
    
    // Registrar en auditoría
    AuditLogger::logCreate('client', $clientId, [
        'name' => $name,
        'email' => $email
    ], $tenantId, $userId);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $clientId,
            'name' => $name,
            'contact_name' => $contactName,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'is_active' => $isActive
        ],
        'message' => t('clients.created_success')
    ]);
}

/**
 * Actualiza un cliente
 */
function updateClient($conn, $tenantId, $userId, $clientId) {
    RBACMiddleware::requirePermission('clients.update');
    
    // Verificar que existe
    $checkStmt = $conn->prepare("SELECT * FROM clients WHERE id = ? AND tenant_id = ?");
    $checkStmt->bind_param('ii', $clientId, $tenantId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $before = $result->fetch_assoc();
    $checkStmt->close();
    
    if (!$before) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'clients.not_found',
            'error_key' => 'clients.not_found',
            'message' => t('clients.not_found')
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
    
    if (isset($input['contact_name'])) {
        $updates[] = "contact_name = ?";
        $params[] = !empty($input['contact_name']) ? trim($input['contact_name']) : null;
        $types .= 's';
    }
    
    if (isset($input['email'])) {
        $email = !empty($input['email']) ? trim($input['email']) : null;
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'error' => 'validation.email_invalid',
                'error_key' => 'validation.email_invalid',
                'message' => t('validation.email_invalid')
            ]);
            return;
        }
        $updates[] = "email = ?";
        $params[] = $email;
        $types .= 's';
    }
    
    if (isset($input['phone'])) {
        $updates[] = "phone = ?";
        $params[] = !empty($input['phone']) ? trim($input['phone']) : null;
        $types .= 's';
    }
    
    if (isset($input['is_active'])) {
        $updates[] = "is_active = ?";
        $params[] = (bool)$input['is_active'] ? 1 : 0;
        $types .= 'i';
    }
    
    if (isset($input['address'])) {
        $updates[] = "address = ?";
        $params[] = !empty($input['address']) ? trim($input['address']) : null;
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
    
    $params[] = $clientId;
    $params[] = $tenantId;
    $types .= 'ii';
    
    $stmt = $conn->prepare("
        UPDATE clients
        SET " . implode(', ', $updates) . ", updated_at = NOW()
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
    
    // Obtener datos actualizados
    getClient($conn, $tenantId, $clientId);
    
    // Registrar en auditoría
    $afterStmt = $conn->prepare("SELECT * FROM clients WHERE id = ? AND tenant_id = ?");
    $afterStmt->bind_param('ii', $clientId, $tenantId);
    $afterStmt->execute();
    $after = $afterStmt->get_result()->fetch_assoc();
    $afterStmt->close();
    
    AuditLogger::logUpdate('client', $clientId, $before, $after, $tenantId, $userId);
}

/**
 * Elimina un cliente
 */
function deleteClient($conn, $tenantId, $userId, $clientId) {
    RBACMiddleware::requirePermission('clients.delete');
    
    // Verificar que existe
    $checkStmt = $conn->prepare("SELECT * FROM clients WHERE id = ? AND tenant_id = ?");
    $checkStmt->bind_param('ii', $clientId, $tenantId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $client = $result->fetch_assoc();
    $checkStmt->close();
    
    if (!$client) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'clients.not_found',
            'error_key' => 'clients.not_found',
            'message' => t('clients.not_found')
        ]);
        return;
    }
    
    // Verificar que no tiene proyectos asociados
    $projectsStmt = $conn->prepare("SELECT COUNT(*) as count FROM projects WHERE client_id = ? AND tenant_id = ?");
    $projectsStmt->bind_param('ii', $clientId, $tenantId);
    $projectsStmt->execute();
    $projectsCount = $projectsStmt->get_result()->fetch_assoc()['count'];
    $projectsStmt->close();
    
    if ($projectsCount > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'clients.has_projects',
            'error_key' => 'clients.has_projects',
            'message' => t('clients.has_projects', ['count' => $projectsCount])
        ]);
        return;
    }
    
    // Eliminar
    $deleteStmt = $conn->prepare("DELETE FROM clients WHERE id = ? AND tenant_id = ?");
    $deleteStmt->bind_param('ii', $clientId, $tenantId);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    // Registrar en auditoría
    AuditLogger::logDelete('client', $clientId, $client, $tenantId, $userId);
    
    echo json_encode([
        'success' => true,
        'message' => t('clients.deleted_success')
    ]);
}

