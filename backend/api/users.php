<?php
/**
 * API Invitación y Gestión de Usuarios
 * 
 * Endpoints:
 * - POST /api/users/invite - Invitar usuario a tenant
 * - GET /api/users - Listar usuarios del tenant
 * - PUT /api/users/{id} - Actualizar rol/usuario
 * - DELETE /api/users/{id} - Desactivar usuario
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../core/TenantMiddleware.php';
require_once __DIR__ . '/../core/RBACMiddleware.php';
require_once __DIR__ . '/../helpers/db_helper.php';
require_once __DIR__ . '/../helpers/i18n.php';
require_once __DIR__ . '/../helpers/env.php';

try {
    // Procesar tenant (OBLIGATORIO)
    $context = TenantMiddleware::process();
    $tenantId = $context['tenant_id'];
    $userId = $context['user_id'];
    
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '';
    
    // Detectar acción (invite, etc.)
    $action = null;
    if (preg_match('#/users/(invite|[\d]+)#', $path, $matches)) {
        $action = $matches[1];
    }
    
    switch ($method) {
        case 'POST':
            if ($action === 'invite') {
                // Invitar usuario a tenant
                RBACMiddleware::requirePermission('users.invite');
                
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
                if (empty($input['email'])) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'error' => 'validation.required',
                        'error_key' => 'validation.required',
                        'error_params' => ['field' => 'email'],
                        'message' => t('validation.required', ['field' => 'email'])
                    ]);
                    exit;
                }
                
                if (empty($input['role'])) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'error' => 'validation.required',
                        'error_key' => 'validation.required',
                        'error_params' => ['field' => 'role'],
                        'message' => t('validation.required', ['field' => 'role'])
                    ]);
                    exit;
                }
                
                // Validar email
                if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'error' => 'validation.email_invalid',
                        'error_key' => 'validation.email_invalid',
                        'message' => t('validation.email_invalid')
                    ]);
                    exit;
                }
                
                // Validar rol
                $validRoles = ['owner_admin', 'empresa_admin', 'planificador', 'supervisor', 'empleado', 'cliente_viewer', 'temporera_admin', 'coordinador', 'trabajador'];
                if (!in_array($input['role'], $validRoles)) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'error' => 'validation.invalid_value',
                        'error_key' => 'validation.invalid_value',
                        'error_params' => ['field' => 'role'],
                        'message' => t('validation.invalid_value', ['field' => 'role'])
                    ]);
                    exit;
                }
                
                $conn = DBHelper::getConnection();
                
                // Buscar o crear usuario
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->bind_param('s', $input['email']);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();
                
                $targetUserId = null;
                
                if ($user) {
                    // Usuario existe
                    $targetUserId = $user['id'];
                } else {
                    // Crear nuevo usuario
                    $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT); // Password temporal
                    $firstName = $input['first_name'] ?? '';
                    $lastName = $input['last_name'] ?? '';
                    
                    $stmt = $conn->prepare("INSERT INTO users (email, password_hash, first_name, last_name, is_active) VALUES (?, ?, ?, ?, 1)");
                    $stmt->bind_param('ssss', $input['email'], $passwordHash, $firstName, $lastName);
                    $stmt->execute();
                    $targetUserId = $conn->insert_id;
                    $stmt->close();
                    
                    // TODO: Enviar email de invitación con link para establecer contraseña
                }
                
                // Verificar si ya está asignado al tenant
                $stmt = $conn->prepare("SELECT id FROM tenant_users WHERE tenant_id = ? AND user_id = ?");
                $stmt->bind_param('ii', $tenantId, $targetUserId);
                $stmt->execute();
                $result = $stmt->get_result();
                $existing = $result->fetch_assoc();
                $stmt->close();
                
                if ($existing) {
                    // Actualizar rol si existe
                    $stmt = $conn->prepare("UPDATE tenant_users SET role = ?, is_active = 1 WHERE id = ?");
                    $stmt->bind_param('si', $input['role'], $existing['id']);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // Asignar usuario al tenant
                    $stmt = $conn->prepare("INSERT INTO tenant_users (tenant_id, user_id, role, is_active) VALUES (?, ?, ?, 1)");
                    $stmt->bind_param('iis', $tenantId, $targetUserId, $input['role']);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Obtener usuario completo
                $stmt = $conn->prepare("SELECT u.*, tu.role, tu.is_active as tenant_user_active FROM users u INNER JOIN tenant_users tu ON u.id = tu.user_id WHERE u.id = ? AND tu.tenant_id = ?");
                $stmt->bind_param('ii', $targetUserId, $tenantId);
                $stmt->execute();
                $result = $stmt->get_result();
                $userData = $result->fetch_assoc();
                $stmt->close();
                
                // No devolver password_hash
                unset($userData['password_hash']);
                
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'data' => $userData,
                    'message' => t('users.invite_success')
                ]);
                break;
            }
            
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'errors.not_found',
                'error_key' => 'errors.not_found',
                'message' => t('errors.not_found')
            ]);
            break;
            
        case 'GET':
            // Listar usuarios del tenant
            RBACMiddleware::requirePermission('users.view');
            
            $conn = DBHelper::getConnection();
            
            // Paginación
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
            $offset = ($page - 1) * $pageSize;
            
            // Filtros
            $where = ["tu.tenant_id = ?"];
            $params = [$tenantId];
            $types = 'i';
            
            if (!empty($_GET['role'])) {
                $where[] = "tu.role = ?";
                $params[] = $_GET['role'];
                $types .= 's';
            }
            
            if (isset($_GET['is_active'])) {
                $where[] = "tu.is_active = ?";
                $params[] = (int)$_GET['is_active'];
                $types .= 'i';
            }
            
            $whereClause = 'WHERE ' . implode(' AND ', $where);
            
            // Contar total
            $countSql = "SELECT COUNT(*) as total FROM tenant_users tu INNER JOIN users u ON tu.user_id = u.id {$whereClause}";
            $stmt = $conn->prepare($countSql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $countResult = $stmt->get_result();
            $total = $countResult->fetch_assoc()['total'];
            $stmt->close();
            
            // Obtener datos
            $sql = "SELECT u.id, u.email, u.first_name, u.last_name, u.phone, u.is_active, u.last_login_at, tu.role, tu.is_active as tenant_user_active, tu.created_at as assigned_at 
                    FROM tenant_users tu 
                    INNER JOIN users u ON tu.user_id = u.id 
                    {$whereClause} 
                    ORDER BY tu.created_at DESC 
                    LIMIT ? OFFSET ?";
            $types .= 'ii';
            $params[] = $pageSize;
            $params[] = $offset;
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $users = [];
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'data' => $users,
                'page' => $page,
                'page_size' => $pageSize,
                'total' => (int)$total,
                'total_pages' => (int)ceil($total / $pageSize)
            ]);
            break;
            
        case 'PUT':
        case 'PATCH':
            // Actualizar usuario (rol, estado)
            RBACMiddleware::requirePermission('users.update');
            
            if (!$action || !is_numeric($action)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.required',
                    'error_key' => 'validation.required',
                    'error_params' => ['field' => 'id'],
                    'message' => t('validation.required', ['field' => 'id'])
                ]);
                exit;
            }
            
            $targetUserId = (int)$action;
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
            
            $conn = DBHelper::getConnection();
            
            // Verificar que el usuario pertenece al tenant
            $stmt = $conn->prepare("SELECT id FROM tenant_users WHERE tenant_id = ? AND user_id = ?");
            $stmt->bind_param('ii', $tenantId, $targetUserId);
            $stmt->execute();
            $result = $stmt->get_result();
            $tenantUser = $result->fetch_assoc();
            $stmt->close();
            
            if (!$tenantUser) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'users.not_found',
                    'error_key' => 'users.not_found',
                    'message' => t('users.not_found')
                ]);
                exit;
            }
            
            // Actualizar campos permitidos
            $updates = [];
            $params = [];
            $types = '';
            
            if (isset($input['role'])) {
                $validRoles = ['owner_admin', 'empresa_admin', 'planificador', 'supervisor', 'empleado', 'cliente_viewer', 'temporera_admin', 'coordinador', 'trabajador'];
                if (in_array($input['role'], $validRoles)) {
                    $updates[] = "role = ?";
                    $params[] = $input['role'];
                    $types .= 's';
                }
            }
            
            if (isset($input['is_active'])) {
                $updates[] = "is_active = ?";
                $params[] = (int)$input['is_active'];
                $types .= 'i';
            }
            
            if (empty($updates)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.no_changes',
                    'error_key' => 'validation.no_changes',
                    'message' => t('validation.no_changes')
                ]);
                exit;
            }
            
            $updates[] = "updated_at = NOW()";
            $params[] = $tenantUser['id'];
            $types .= 'i';
            
            $sql = "UPDATE tenant_users SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'message' => t('users.update_success')
            ]);
            break;
            
        case 'DELETE':
            // Desactivar usuario (soft delete)
            RBACMiddleware::requirePermission('users.delete');
            
            if (!$action || !is_numeric($action)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.required',
                    'error_key' => 'validation.required',
                    'error_params' => ['field' => 'id'],
                    'message' => t('validation.required', ['field' => 'id'])
                ]);
                exit;
            }
            
            $targetUserId = (int)$action;
            $conn = DBHelper::getConnection();
            
            // Desactivar en tenant_users (no eliminar físicamente)
            $stmt = $conn->prepare("UPDATE tenant_users SET is_active = 0, updated_at = NOW() WHERE tenant_id = ? AND user_id = ?");
            $stmt->bind_param('ii', $tenantId, $targetUserId);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            
            if ($affected === 0) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'users.not_found',
                    'error_key' => 'users.not_found',
                    'message' => t('users.not_found')
                ]);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'message' => t('users.delete_success')
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'errors.method_not_allowed',
                'error_key' => 'errors.method_not_allowed',
                'message' => t('errors.method_not_allowed')
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'errors.internal_error',
        'error_key' => 'errors.internal_error',
        'message' => Env::isDebug() ? $e->getMessage() : t('errors.internal_error')
    ]);
}

