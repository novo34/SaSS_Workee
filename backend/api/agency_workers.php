<?php
/**
 * API Trabajadores Temporeros
 * 
 * Endpoints:
 * - GET /api/agency-workers - Listar trabajadores
 * - GET /api/agency-workers/{id} - Obtener trabajador
 * - POST /api/agency-workers - Crear trabajador
 * - PUT /api/agency-workers/{id} - Actualizar trabajador
 * - DELETE /api/agency-workers/{id} - Eliminar trabajador
 * - GET /api/agency-workers/{id}/availability - Obtener disponibilidad
 * - POST /api/agency-workers/{id}/availability - Actualizar disponibilidad
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../core/TenantMiddleware.php';
require_once __DIR__ . '/../core/RBACMiddleware.php';
require_once __DIR__ . '/../helpers/db_helper.php';
require_once __DIR__ . '/../helpers/i18n.php';
require_once __DIR__ . '/../core/AuditLogger.php';
require_once __DIR__ . '/../core/FileStorage.php';

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
    RBACMiddleware::requirePermission('agency_workers.view');
    
    $conn = DBHelper::getConnection();
    
    // Parsear path
    $pathParts = explode('/', trim($path, '/'));
    $workerId = null;
    $subResource = null;
    
    if (count($pathParts) >= 2 && is_numeric($pathParts[1])) {
        $workerId = (int)$pathParts[1];
        if (count($pathParts) >= 3) {
            $subResource = $pathParts[2];
        }
    }
    
    // Routing
    switch ($method) {
        case 'GET':
            if ($workerId && $subResource === 'availability') {
                getAvailability($conn, $tenantId, $workerId);
            } elseif ($workerId) {
                getWorker($conn, $tenantId, $workerId);
            } else {
                listWorkers($conn, $tenantId);
            }
            break;
            
        case 'POST':
            if ($workerId && $subResource === 'availability') {
                updateAvailability($conn, $tenantId, $userId, $workerId);
            } else {
                createWorker($conn, $tenantId, $userId);
            }
            break;
            
        case 'PUT':
            if ($workerId) {
                updateWorker($conn, $tenantId, $userId, $workerId);
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
            if ($workerId) {
                deleteWorker($conn, $tenantId, $userId, $workerId);
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
 * Lista trabajadores con paginación
 */
function listWorkers($conn, $tenantId) {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
    $offset = ($page - 1) * $pageSize;
    
    $search = $_GET['q'] ?? null;
    $isActive = isset($_GET['is_active']) ? (int)$_GET['is_active'] : null;
    
    $where = ["w.tenant_id = ?"];
    $params = [$tenantId];
    $types = 'i';
    
    if ($search) {
        $where[] = "(w.first_name LIKE ? OR w.last_name LIKE ? OR w.email LIKE ? OR w.worker_code LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'ssss';
    }
    
    if ($isActive !== null) {
        $where[] = "w.is_active = ?";
        $params[] = $isActive;
        $types .= 'i';
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Contar total
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM agency_workers w
        WHERE {$whereClause}
    ");
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();
    
    // Obtener trabajadores
    $stmt = $conn->prepare("
        SELECT 
            w.id,
            w.user_id,
            w.worker_code,
            w.first_name,
            w.last_name,
            w.email,
            w.phone,
            w.address,
            w.skills_json,
            w.availability_json,
            w.rating_avg,
            w.is_active,
            w.created_at,
            w.updated_at
        FROM agency_workers w
        WHERE {$whereClause}
        ORDER BY w.last_name ASC, w.first_name ASC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $pageSize;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $workers = [];
    while ($row = $result->fetch_assoc()) {
        $workers[] = [
            'id' => (int)$row['id'],
            'user_id' => $row['user_id'] ? (int)$row['user_id'] : null,
            'worker_code' => $row['worker_code'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'address' => $row['address'],
            'skills' => $row['skills_json'] ? json_decode($row['skills_json'], true) : [],
            'availability' => $row['availability_json'] ? json_decode($row['availability_json'], true) : null,
            'rating_avg' => $row['rating_avg'] ? (float)$row['rating_avg'] : null,
            'is_active' => (bool)$row['is_active'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $workers,
        'page' => $page,
        'page_size' => $pageSize,
        'total' => (int)$total,
        'total_pages' => (int)ceil($total / $pageSize)
    ]);
}

/**
 * Obtiene un trabajador específico
 */
function getWorker($conn, $tenantId, $workerId) {
    $stmt = $conn->prepare("
        SELECT 
            w.*,
            u.email as user_email
        FROM agency_workers w
        LEFT JOIN users u ON w.user_id = u.id
        WHERE w.id = ? AND w.tenant_id = ?
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
            'error' => 'agency_workers.not_found',
            'error_key' => 'agency_workers.not_found',
            'message' => t('agency_workers.not_found')
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int)$worker['id'],
            'user_id' => $worker['user_id'] ? (int)$worker['user_id'] : null,
            'user_email' => $worker['user_email'],
            'worker_code' => $worker['worker_code'],
            'first_name' => $worker['first_name'],
            'last_name' => $worker['last_name'],
            'email' => $worker['email'],
            'phone' => $worker['phone'],
            'address' => $worker['address'],
            'skills' => $worker['skills_json'] ? json_decode($worker['skills_json'], true) : [],
            'availability' => $worker['availability_json'] ? json_decode($worker['availability_json'], true) : null,
            'rating_avg' => $worker['rating_avg'] ? (float)$worker['rating_avg'] : null,
            'is_active' => (bool)$worker['is_active'],
            'created_at' => $worker['created_at'],
            'updated_at' => $worker['updated_at']
        ]
    ]);
}

/**
 * Crea un nuevo trabajador
 */
function createWorker($conn, $tenantId, $userId) {
    RBACMiddleware::requirePermission('agency_workers.create');
    
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
    if (empty($input['first_name']) || empty($input['last_name'])) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'validation.required',
            'error_key' => 'validation.required',
            'error_params' => ['field' => 'first_name, last_name'],
            'message' => t('validation.required', ['field' => 'first_name, last_name'])
        ]);
        exit;
    }
    
    $firstName = trim($input['first_name']);
    $lastName = trim($input['last_name']);
    $email = !empty($input['email']) ? trim($input['email']) : null;
    $phone = !empty($input['phone']) ? trim($input['phone']) : null;
    $address = !empty($input['address']) ? trim($input['address']) : null;
    $workerCode = !empty($input['worker_code']) ? trim($input['worker_code']) : null;
    $userIdLink = isset($input['user_id']) ? (int)$input['user_id'] : null;
    $isActive = isset($input['is_active']) ? (bool)$input['is_active'] : true;
    $skills = isset($input['skills']) && is_array($input['skills']) ? $input['skills'] : [];
    $availability = isset($input['availability']) ? $input['availability'] : null;
    
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
    
    // Validar que el user_id existe si se proporciona
    if ($userIdLink) {
        $userStmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
        $userStmt->bind_param('i', $userIdLink);
        $userStmt->execute();
        $user = $userStmt->get_result()->fetch_assoc();
        $userStmt->close();
        
        if (!$user) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'error' => 'agency_workers.user_not_found',
                'error_key' => 'agency_workers.user_not_found',
                'message' => t('agency_workers.user_not_found')
            ]);
            exit;
        }
    }
    
    // Insertar trabajador
    $skillsJson = !empty($skills) ? json_encode($skills, JSON_UNESCAPED_UNICODE) : null;
    $availabilityJson = $availability ? json_encode($availability, JSON_UNESCAPED_UNICODE) : null;
    
    $stmt = $conn->prepare("
        INSERT INTO agency_workers (
            tenant_id, user_id, worker_code, first_name, last_name, 
            email, phone, address, skills_json, availability_json, is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('iissssssssi', 
        $tenantId, $userIdLink, $workerCode, $firstName, $lastName,
        $email, $phone, $address, $skillsJson, $availabilityJson, $isActive
    );
    $stmt->execute();
    $workerId = $conn->insert_id;
    $stmt->close();
    
    // Registrar en auditoría
    AuditLogger::logCreate('agency_worker', $workerId, [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email
    ], $tenantId, $userId);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $workerId,
            'user_id' => $userIdLink,
            'worker_code' => $workerCode,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'skills' => $skills,
            'availability' => $availability,
            'is_active' => $isActive
        ],
        'message' => t('agency_workers.created_success')
    ]);
}

/**
 * Actualiza un trabajador
 */
function updateWorker($conn, $tenantId, $userId, $workerId) {
    RBACMiddleware::requirePermission('agency_workers.update');
    
    // Verificar que existe
    $checkStmt = $conn->prepare("SELECT * FROM agency_workers WHERE id = ? AND tenant_id = ?");
    $checkStmt->bind_param('ii', $workerId, $tenantId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $before = $result->fetch_assoc();
    $checkStmt->close();
    
    if (!$before) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'agency_workers.not_found',
            'error_key' => 'agency_workers.not_found',
            'message' => t('agency_workers.not_found')
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
    
    if (isset($input['first_name'])) {
        $updates[] = "first_name = ?";
        $params[] = trim($input['first_name']);
        $types .= 's';
    }
    
    if (isset($input['last_name'])) {
        $updates[] = "last_name = ?";
        $params[] = trim($input['last_name']);
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
    
    if (isset($input['address'])) {
        $updates[] = "address = ?";
        $params[] = !empty($input['address']) ? trim($input['address']) : null;
        $types .= 's';
    }
    
    if (isset($input['worker_code'])) {
        $updates[] = "worker_code = ?";
        $params[] = !empty($input['worker_code']) ? trim($input['worker_code']) : null;
        $types .= 's';
    }
    
    if (isset($input['is_active'])) {
        $updates[] = "is_active = ?";
        $params[] = (bool)$input['is_active'] ? 1 : 0;
        $types .= 'i';
    }
    
    if (isset($input['skills']) && is_array($input['skills'])) {
        $updates[] = "skills_json = ?";
        $params[] = json_encode($input['skills'], JSON_UNESCAPED_UNICODE);
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
    
    $params[] = $workerId;
    $params[] = $tenantId;
    $types .= 'ii';
    
    $stmt = $conn->prepare("
        UPDATE agency_workers
        SET " . implode(', ', $updates) . ", updated_at = NOW()
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
    
    // Obtener datos actualizados
    getWorker($conn, $tenantId, $workerId);
    
    // Registrar en auditoría
    $afterStmt = $conn->prepare("SELECT * FROM agency_workers WHERE id = ? AND tenant_id = ?");
    $afterStmt->bind_param('ii', $workerId, $tenantId);
    $afterStmt->execute();
    $after = $afterStmt->get_result()->fetch_assoc();
    $afterStmt->close();
    
    AuditLogger::logUpdate('agency_worker', $workerId, $before, $after, $tenantId, $userId);
}

/**
 * Elimina un trabajador
 */
function deleteWorker($conn, $tenantId, $userId, $workerId) {
    RBACMiddleware::requirePermission('agency_workers.delete');
    
    // Verificar que existe
    $checkStmt = $conn->prepare("SELECT * FROM agency_workers WHERE id = ? AND tenant_id = ?");
    $checkStmt->bind_param('ii', $workerId, $tenantId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $worker = $result->fetch_assoc();
    $checkStmt->close();
    
    if (!$worker) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'agency_workers.not_found',
            'error_key' => 'agency_workers.not_found',
            'message' => t('agency_workers.not_found')
        ]);
        return;
    }
    
    // Verificar que no tiene work_logs o shifts asociados
    $workLogsStmt = $conn->prepare("SELECT COUNT(*) as count FROM work_logs WHERE agency_worker_id = ? AND tenant_id = ?");
    $workLogsStmt->bind_param('ii', $workerId, $tenantId);
    $workLogsStmt->execute();
    $workLogsCount = $workLogsStmt->get_result()->fetch_assoc()['count'];
    $workLogsStmt->close();
    
    if ($workLogsCount > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'agency_workers.has_work_logs',
            'error_key' => 'agency_workers.has_work_logs',
            'message' => t('agency_workers.has_work_logs', ['count' => $workLogsCount])
        ]);
        return;
    }
    
    // Eliminar
    $deleteStmt = $conn->prepare("DELETE FROM agency_workers WHERE id = ? AND tenant_id = ?");
    $deleteStmt->bind_param('ii', $workerId, $tenantId);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    // Registrar en auditoría
    AuditLogger::logDelete('agency_worker', $workerId, $worker, $tenantId, $userId);
    
    echo json_encode([
        'success' => true,
        'message' => t('agency_workers.deleted_success')
    ]);
}

/**
 * Obtiene disponibilidad de un trabajador
 */
function getAvailability($conn, $tenantId, $workerId) {
    $stmt = $conn->prepare("
        SELECT availability_json
        FROM agency_workers
        WHERE id = ? AND tenant_id = ?
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
            'error' => 'agency_workers.not_found',
            'error_key' => 'agency_workers.not_found',
            'message' => t('agency_workers.not_found')
        ]);
        return;
    }
    
    $availability = $worker['availability_json'] ? json_decode($worker['availability_json'], true) : null;
    
    echo json_encode([
        'success' => true,
        'data' => $availability
    ]);
}

/**
 * Actualiza disponibilidad de un trabajador
 */
function updateAvailability($conn, $tenantId, $userId, $workerId) {
    RBACMiddleware::requirePermission('agency_workers.update');
    
    // Verificar que existe
    $checkStmt = $conn->prepare("SELECT id FROM agency_workers WHERE id = ? AND tenant_id = ?");
    $checkStmt->bind_param('ii', $workerId, $tenantId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $worker = $result->fetch_assoc();
    $checkStmt->close();
    
    if (!$worker) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'agency_workers.not_found',
            'error_key' => 'agency_workers.not_found',
            'message' => t('agency_workers.not_found')
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['availability'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'validation.required',
            'error_key' => 'validation.required',
            'error_params' => ['field' => 'availability'],
            'message' => t('validation.required', ['field' => 'availability'])
        ]);
        return;
    }
    
    $availability = $input['availability'];
    $availabilityJson = json_encode($availability, JSON_UNESCAPED_UNICODE);
    
    $updateStmt = $conn->prepare("
        UPDATE agency_workers
        SET availability_json = ?, updated_at = NOW()
        WHERE id = ? AND tenant_id = ?
    ");
    $updateStmt->bind_param('sii', $availabilityJson, $workerId, $tenantId);
    $updateStmt->execute();
    $updateStmt->close();
    
    AuditLogger::log('update', 'agency_worker_availability', $workerId, [
        'availability' => $availability
    ], $tenantId, $userId);
    
    getAvailability($conn, $tenantId, $workerId);
}

