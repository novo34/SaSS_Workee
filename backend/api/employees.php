<?php
/**
 * API Empleados Propios
 * 
 * Endpoints:
 * - GET /api/employees - Listar empleados
 * - GET /api/employees/{id} - Obtener empleado
 * - POST /api/employees - Crear empleado
 * - PUT /api/employees/{id} - Actualizar empleado
 * - DELETE /api/employees/{id} - Eliminar empleado
 * - POST /api/employees/{id}/skills - Agregar skill
 * - DELETE /api/employees/{id}/skills/{skill} - Eliminar skill
 * - POST /api/employees/{id}/documents - Subir documento
 * - DELETE /api/employees/{id}/documents/{doc_id} - Eliminar documento
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
    RBACMiddleware::requirePermission('employees.view');
    
    $conn = DBHelper::getConnection();
    
    // Parsear path
    $pathParts = explode('/', trim($path, '/'));
    $employeeId = null;
    $subResource = null;
    $subResourceId = null;
    
    if (count($pathParts) >= 2 && is_numeric($pathParts[1])) {
        $employeeId = (int)$pathParts[1];
        if (count($pathParts) >= 3) {
            $subResource = $pathParts[2];
            if (count($pathParts) >= 4) {
                $subResourceId = $pathParts[3];
            }
        }
    }
    
    // Routing
    switch ($method) {
        case 'GET':
            if ($employeeId) {
                getEmployee($conn, $tenantId, $employeeId);
            } else {
                listEmployees($conn, $tenantId);
            }
            break;
            
        case 'POST':
            if ($employeeId && $subResource === 'skills') {
                addSkill($conn, $tenantId, $userId, $employeeId);
            } elseif ($employeeId && $subResource === 'documents') {
                uploadDocument($conn, $tenantId, $userId, $employeeId);
            } else {
                createEmployee($conn, $tenantId, $userId);
            }
            break;
            
        case 'PUT':
            if ($employeeId) {
                updateEmployee($conn, $tenantId, $userId, $employeeId);
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
            if ($employeeId && $subResource === 'skills' && $subResourceId) {
                removeSkill($conn, $tenantId, $userId, $employeeId, $subResourceId);
            } elseif ($employeeId && $subResource === 'documents' && $subResourceId) {
                deleteDocument($conn, $tenantId, $userId, $employeeId, (int)$subResourceId);
            } elseif ($employeeId) {
                deleteEmployee($conn, $tenantId, $userId, $employeeId);
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
 * Lista empleados con paginación
 */
function listEmployees($conn, $tenantId) {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
    $offset = ($page - 1) * $pageSize;
    
    $search = $_GET['q'] ?? null;
    $isActive = isset($_GET['is_active']) ? (int)$_GET['is_active'] : null;
    
    $where = ["e.tenant_id = ?"];
    $params = [$tenantId];
    $types = 'i';
    
    if ($search) {
        $where[] = "(e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ? OR e.employee_code LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'ssss';
    }
    
    if ($isActive !== null) {
        $where[] = "e.is_active = ?";
        $params[] = $isActive;
        $types .= 'i';
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Contar total
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM own_employees e
        WHERE {$whereClause}
    ");
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();
    
    // Obtener empleados
    $stmt = $conn->prepare("
        SELECT 
            e.id,
            e.user_id,
            e.employee_code,
            e.first_name,
            e.last_name,
            e.email,
            e.phone,
            e.address,
            e.skills_json,
            e.documents_json,
            e.is_active,
            e.created_at,
            e.updated_at
        FROM own_employees e
        WHERE {$whereClause}
        ORDER BY e.last_name ASC, e.first_name ASC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $pageSize;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = [
            'id' => (int)$row['id'],
            'user_id' => $row['user_id'] ? (int)$row['user_id'] : null,
            'employee_code' => $row['employee_code'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'address' => $row['address'],
            'skills' => $row['skills_json'] ? json_decode($row['skills_json'], true) : [],
            'documents' => $row['documents_json'] ? json_decode($row['documents_json'], true) : [],
            'is_active' => (bool)$row['is_active'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $employees,
        'page' => $page,
        'page_size' => $pageSize,
        'total' => (int)$total,
        'total_pages' => (int)ceil($total / $pageSize)
    ]);
}

/**
 * Obtiene un empleado específico
 */
function getEmployee($conn, $tenantId, $employeeId) {
    $stmt = $conn->prepare("
        SELECT 
            e.*,
            u.email as user_email
        FROM own_employees e
        LEFT JOIN users u ON e.user_id = u.id
        WHERE e.id = ? AND e.tenant_id = ?
    ");
    $stmt->bind_param('ii', $employeeId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee = $result->fetch_assoc();
    $stmt->close();
    
    if (!$employee) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'employees.not_found',
            'error_key' => 'employees.not_found',
            'message' => t('employees.not_found')
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int)$employee['id'],
            'user_id' => $employee['user_id'] ? (int)$employee['user_id'] : null,
            'user_email' => $employee['user_email'],
            'employee_code' => $employee['employee_code'],
            'first_name' => $employee['first_name'],
            'last_name' => $employee['last_name'],
            'email' => $employee['email'],
            'phone' => $employee['phone'],
            'address' => $employee['address'],
            'skills' => $employee['skills_json'] ? json_decode($employee['skills_json'], true) : [],
            'documents' => $employee['documents_json'] ? json_decode($employee['documents_json'], true) : [],
            'is_active' => (bool)$employee['is_active'],
            'created_at' => $employee['created_at'],
            'updated_at' => $employee['updated_at']
        ]
    ]);
}

/**
 * Crea un nuevo empleado
 */
function createEmployee($conn, $tenantId, $userId) {
    RBACMiddleware::requirePermission('employees.create');
    
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
    $employeeCode = !empty($input['employee_code']) ? trim($input['employee_code']) : null;
    $userIdLink = isset($input['user_id']) ? (int)$input['user_id'] : null;
    $isActive = isset($input['is_active']) ? (bool)$input['is_active'] : true;
    $skills = isset($input['skills']) && is_array($input['skills']) ? $input['skills'] : [];
    
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
                'error' => 'employees.user_not_found',
                'error_key' => 'employees.user_not_found',
                'message' => t('employees.user_not_found')
            ]);
            exit;
        }
    }
    
    // Insertar empleado
    $skillsJson = !empty($skills) ? json_encode($skills, JSON_UNESCAPED_UNICODE) : null;
    
    $stmt = $conn->prepare("
        INSERT INTO own_employees (
            tenant_id, user_id, employee_code, first_name, last_name, 
            email, phone, address, skills_json, is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('iisssssssi', 
        $tenantId, $userIdLink, $employeeCode, $firstName, $lastName,
        $email, $phone, $address, $skillsJson, $isActive
    );
    $stmt->execute();
    $employeeId = $conn->insert_id;
    $stmt->close();
    
    // Registrar en auditoría
    AuditLogger::logCreate('employee', $employeeId, [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email
    ], $tenantId, $userId);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $employeeId,
            'user_id' => $userIdLink,
            'employee_code' => $employeeCode,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'skills' => $skills,
            'is_active' => $isActive
        ],
        'message' => t('employees.created_success')
    ]);
}

/**
 * Actualiza un empleado
 */
function updateEmployee($conn, $tenantId, $userId, $employeeId) {
    RBACMiddleware::requirePermission('employees.update');
    
    // Verificar que existe
    $checkStmt = $conn->prepare("SELECT * FROM own_employees WHERE id = ? AND tenant_id = ?");
    $checkStmt->bind_param('ii', $employeeId, $tenantId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $before = $result->fetch_assoc();
    $checkStmt->close();
    
    if (!$before) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'employees.not_found',
            'error_key' => 'employees.not_found',
            'message' => t('employees.not_found')
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
    
    if (isset($input['employee_code'])) {
        $updates[] = "employee_code = ?";
        $params[] = !empty($input['employee_code']) ? trim($input['employee_code']) : null;
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
    
    $params[] = $employeeId;
    $params[] = $tenantId;
    $types .= 'ii';
    
    $stmt = $conn->prepare("
        UPDATE own_employees
        SET " . implode(', ', $updates) . ", updated_at = NOW()
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();
    
    // Obtener datos actualizados
    getEmployee($conn, $tenantId, $employeeId);
    
    // Registrar en auditoría
    $afterStmt = $conn->prepare("SELECT * FROM own_employees WHERE id = ? AND tenant_id = ?");
    $afterStmt->bind_param('ii', $employeeId, $tenantId);
    $afterStmt->execute();
    $after = $afterStmt->get_result()->fetch_assoc();
    $afterStmt->close();
    
    AuditLogger::logUpdate('employee', $employeeId, $before, $after, $tenantId, $userId);
}

/**
 * Elimina un empleado
 */
function deleteEmployee($conn, $tenantId, $userId, $employeeId) {
    RBACMiddleware::requirePermission('employees.delete');
    
    // Verificar que existe
    $checkStmt = $conn->prepare("SELECT * FROM own_employees WHERE id = ? AND tenant_id = ?");
    $checkStmt->bind_param('ii', $employeeId, $tenantId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $employee = $result->fetch_assoc();
    $checkStmt->close();
    
    if (!$employee) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'employees.not_found',
            'error_key' => 'employees.not_found',
            'message' => t('employees.not_found')
        ]);
        return;
    }
    
    // Verificar que no tiene work_logs o shifts asociados
    $workLogsStmt = $conn->prepare("SELECT COUNT(*) as count FROM work_logs WHERE own_employee_id = ? AND tenant_id = ?");
    $workLogsStmt->bind_param('ii', $employeeId, $tenantId);
    $workLogsStmt->execute();
    $workLogsCount = $workLogsStmt->get_result()->fetch_assoc()['count'];
    $workLogsStmt->close();
    
    if ($workLogsCount > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'employees.has_work_logs',
            'error_key' => 'employees.has_work_logs',
            'message' => t('employees.has_work_logs', ['count' => $workLogsCount])
        ]);
        return;
    }
    
    // Eliminar documentos asociados
    $documents = $employee['documents_json'] ? json_decode($employee['documents_json'], true) : [];
    foreach ($documents as $doc) {
        if (isset($doc['file_id'])) {
            FileStorage::delete($doc['file_id'], $tenantId);
        }
    }
    
    // Eliminar
    $deleteStmt = $conn->prepare("DELETE FROM own_employees WHERE id = ? AND tenant_id = ?");
    $deleteStmt->bind_param('ii', $employeeId, $tenantId);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    // Registrar en auditoría
    AuditLogger::logDelete('employee', $employeeId, $employee, $tenantId, $userId);
    
    echo json_encode([
        'success' => true,
        'message' => t('employees.deleted_success')
    ]);
}

/**
 * Agrega un skill a un empleado
 */
function addSkill($conn, $tenantId, $userId, $employeeId) {
    RBACMiddleware::requirePermission('employees.update');
    
    // Verificar que existe
    $checkStmt = $conn->prepare("SELECT skills_json FROM own_employees WHERE id = ? AND tenant_id = ?");
    $checkStmt->bind_param('ii', $employeeId, $tenantId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $employee = $result->fetch_assoc();
    $checkStmt->close();
    
    if (!$employee) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'employees.not_found',
            'error_key' => 'employees.not_found',
            'message' => t('employees.not_found')
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['skill']) || empty(trim($input['skill']))) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'validation.required',
            'error_key' => 'validation.required',
            'error_params' => ['field' => 'skill'],
            'message' => t('validation.required', ['field' => 'skill'])
        ]);
        return;
    }
    
    $skill = trim($input['skill']);
    $skills = $employee['skills_json'] ? json_decode($employee['skills_json'], true) : [];
    
    if (!in_array($skill, $skills)) {
        $skills[] = $skill;
        sort($skills); // Ordenar alfabéticamente
        
        $updateStmt = $conn->prepare("
            UPDATE own_employees
            SET skills_json = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        $skillsJson = json_encode($skills, JSON_UNESCAPED_UNICODE);
        $updateStmt->bind_param('sii', $skillsJson, $employeeId, $tenantId);
        $updateStmt->execute();
        $updateStmt->close();
        
        AuditLogger::log('update', 'employee_skill', $employeeId, [
            'action' => 'add',
            'skill' => $skill
        ], $tenantId, $userId);
    }
    
    getEmployee($conn, $tenantId, $employeeId);
}

/**
 * Elimina un skill de un empleado
 */
function removeSkill($conn, $tenantId, $userId, $employeeId, $skill) {
    RBACMiddleware::requirePermission('employees.update');
    
    // Verificar que existe
    $checkStmt = $conn->prepare("SELECT skills_json FROM own_employees WHERE id = ? AND tenant_id = ?");
    $checkStmt->bind_param('ii', $employeeId, $tenantId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $employee = $result->fetch_assoc();
    $checkStmt->close();
    
    if (!$employee) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'employees.not_found',
            'error_key' => 'employees.not_found',
            'message' => t('employees.not_found')
        ]);
        return;
    }
    
    $skills = $employee['skills_json'] ? json_decode($employee['skills_json'], true) : [];
    $skills = array_filter($skills, function($s) use ($skill) {
        return $s !== $skill;
    });
    $skills = array_values($skills); // Reindexar
    
    $updateStmt = $conn->prepare("
        UPDATE own_employees
        SET skills_json = ?, updated_at = NOW()
        WHERE id = ? AND tenant_id = ?
    ");
    $skillsJson = !empty($skills) ? json_encode($skills, JSON_UNESCAPED_UNICODE) : null;
    $updateStmt->bind_param('sii', $skillsJson, $employeeId, $tenantId);
    $updateStmt->execute();
    $updateStmt->close();
    
    AuditLogger::log('update', 'employee_skill', $employeeId, [
        'action' => 'remove',
        'skill' => $skill
    ], $tenantId, $userId);
    
    getEmployee($conn, $tenantId, $employeeId);
}

/**
 * Sube un documento para un empleado
 */
function uploadDocument($conn, $tenantId, $userId, $employeeId) {
    RBACMiddleware::requirePermission('employees.update');
    
    // Verificar que el empleado existe
    $checkStmt = $conn->prepare("SELECT documents_json FROM own_employees WHERE id = ? AND tenant_id = ?");
    $checkStmt->bind_param('ii', $employeeId, $tenantId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $employee = $result->fetch_assoc();
    $checkStmt->close();
    
    if (!$employee) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'employees.not_found',
            'error_key' => 'employees.not_found',
            'message' => t('employees.not_found')
        ]);
        return;
    }
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'employees.file_required',
            'error_key' => 'employees.file_required',
            'message' => t('employees.file_required')
        ]);
        return;
    }
    
    $file = $_FILES['file'];
    $documentName = $_POST['name'] ?? $file['name'];
    
    // Subir archivo
    $uploadResult = FileStorage::upload(
        $file['tmp_name'],
        $file['name'],
        'document',
        $employeeId,
        $tenantId,
        $userId
    );
    
    if (!$uploadResult['success']) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'employees.upload_failed',
            'error_key' => 'employees.upload_failed',
            'message' => $uploadResult['error'] ?? t('employees.upload_failed')
        ]);
        return;
    }
    
    // Agregar documento a la lista
    $documents = $employee['documents_json'] ? json_decode($employee['documents_json'], true) : [];
    $documents[] = [
        'file_id' => $uploadResult['file_id'],
        'name' => $documentName,
        'original_filename' => $file['name'],
        'uploaded_at' => date('c'),
        'uploaded_by' => $userId
    ];
    
    $updateStmt = $conn->prepare("
        UPDATE own_employees
        SET documents_json = ?, updated_at = NOW()
        WHERE id = ? AND tenant_id = ?
    ");
    $documentsJson = json_encode($documents, JSON_UNESCAPED_UNICODE);
    $updateStmt->bind_param('sii', $documentsJson, $employeeId, $tenantId);
    $updateStmt->execute();
    $updateStmt->close();
    
    AuditLogger::log('create', 'employee_document', $employeeId, [
        'file_id' => $uploadResult['file_id'],
        'name' => $documentName
    ], $tenantId, $userId);
    
    getEmployee($conn, $tenantId, $employeeId);
}

/**
 * Elimina un documento de un empleado
 */
function deleteDocument($conn, $tenantId, $userId, $employeeId, $docIndex) {
    RBACMiddleware::requirePermission('employees.update');
    
    // Verificar que existe
    $checkStmt = $conn->prepare("SELECT documents_json FROM own_employees WHERE id = ? AND tenant_id = ?");
    $checkStmt->bind_param('ii', $employeeId, $tenantId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $employee = $result->fetch_assoc();
    $checkStmt->close();
    
    if (!$employee) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'employees.not_found',
            'error_key' => 'employees.not_found',
            'message' => t('employees.not_found')
        ]);
        return;
    }
    
    $documents = $employee['documents_json'] ? json_decode($employee['documents_json'], true) : [];
    
    if (!isset($documents[$docIndex])) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'employees.document_not_found',
            'error_key' => 'employees.document_not_found',
            'message' => t('employees.document_not_found')
        ]);
        return;
    }
    
    $doc = $documents[$docIndex];
    
    // Eliminar archivo
    if (isset($doc['file_id'])) {
        FileStorage::delete($doc['file_id'], $tenantId);
    }
    
    // Eliminar de la lista
    unset($documents[$docIndex]);
    $documents = array_values($documents); // Reindexar
    
    $updateStmt = $conn->prepare("
        UPDATE own_employees
        SET documents_json = ?, updated_at = NOW()
        WHERE id = ? AND tenant_id = ?
    ");
    $documentsJson = !empty($documents) ? json_encode($documents, JSON_UNESCAPED_UNICODE) : null;
    $updateStmt->bind_param('sii', $documentsJson, $employeeId, $tenantId);
    $updateStmt->execute();
    $updateStmt->close();
    
    AuditLogger::log('delete', 'employee_document', $employeeId, [
        'file_id' => $doc['file_id'] ?? null,
        'name' => $doc['name'] ?? null
    ], $tenantId, $userId);
    
    getEmployee($conn, $tenantId, $employeeId);
}

