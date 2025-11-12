<?php
/**
 * API Grupos de Trabajo
 * 
 * Endpoints:
 * - GET /api/work-groups - Listar grupos
 * - POST /api/work-groups - Crear grupo
 * - GET /api/work-groups/{id} - Obtener grupo
 * - PUT /api/work-groups/{id} - Actualizar grupo
 * - DELETE /api/work-groups/{id} - Eliminar grupo
 * - POST /api/work-groups/{id}/members - Agregar miembro
 * - DELETE /api/work-groups/{id}/members/{member_id} - Eliminar miembro
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
    // Obtener path desde variable global (inyectada por index.php) o desde PATH_INFO/REQUEST_URI
    $path = $GLOBALS['API_PATH'] ?? $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '';
    
    // Procesar middleware para extraer tenant_id
    $middlewareResult = TenantMiddleware::process();
    $tenantId = $middlewareResult['tenant_id'] ?? null;
    
    if (!$tenantId) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'auth.tenant_required',
            'error_key' => 'auth.tenant_required',
            'message' => t('auth.tenant_required')
        ]);
        exit;
    }
    
    // Verificar que el tenant es una empresa (solo empresas crean grupos)
    $conn = DBHelper::getConnection();
    $stmt = $conn->prepare("SELECT org_type FROM tenants WHERE id = ?");
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $tenant = $result->fetch_assoc();
    $stmt->close();
    
    if (!$tenant || $tenant['org_type'] !== 'empresa') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'auth.forbidden',
            'error_key' => 'auth.forbidden',
            'message' => t('auth.forbidden')
        ]);
        exit;
    }
    
    // Verificar permisos
    $action = null;
    if ($method === 'GET') {
        $action = 'groups.read';
    } elseif ($method === 'POST') {
        $action = 'groups.create';
    } elseif ($method === 'PUT') {
        $action = 'groups.update';
    } elseif ($method === 'DELETE') {
        $action = 'groups.delete';
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
    
    // Parsear path para obtener ID si existe
    $pathParts = explode('/', trim($path, '/'));
    $groupId = null;
    $isMemberAction = false;
    $memberId = null;
    
    if (count($pathParts) >= 2 && is_numeric($pathParts[1])) {
        $groupId = (int)$pathParts[1];
        if (isset($pathParts[2]) && $pathParts[2] === 'members') {
            $isMemberAction = true;
            if (isset($pathParts[3]) && is_numeric($pathParts[3])) {
                $memberId = (int)$pathParts[3];
            }
        }
    }
    
    // Routing
    switch ($method) {
        case 'GET':
            if ($groupId && $isMemberAction) {
                // Listar miembros del grupo
                listGroupMembers($conn, $tenantId, $groupId);
            } elseif ($groupId) {
                // Obtener un grupo
                getGroup($conn, $tenantId, $groupId);
            } else {
                // Listar grupos
                listGroups($conn, $tenantId);
            }
            break;
            
        case 'POST':
            if ($groupId && $isMemberAction) {
                // Agregar miembro al grupo
                addGroupMember($conn, $tenantId, $groupId);
            } else {
                // Crear grupo
                createGroup($conn, $tenantId);
            }
            break;
            
        case 'PUT':
            if ($groupId) {
                updateGroup($conn, $tenantId, $groupId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.group_id_required',
                    'error_key' => 'validation.group_id_required',
                    'message' => t('validation.group_id_required')
                ]);
            }
            break;
            
        case 'DELETE':
            if ($groupId && $isMemberAction && $memberId) {
                // Eliminar miembro del grupo
                removeGroupMember($conn, $tenantId, $groupId, $memberId);
            } elseif ($groupId) {
                // Eliminar grupo
                deleteGroup($conn, $tenantId, $groupId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.group_id_required',
                    'error_key' => 'validation.group_id_required',
                    'message' => t('validation.group_id_required')
                ]);
            }
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
        'error' => 'errors.server_error',
        'error_key' => 'errors.server_error',
        'message' => t('errors.server_error'),
        'debug' => Env::get('APP_ENV') === 'development' ? $e->getMessage() : null
    ]);
}

/**
 * Listar grupos con filtros y paginación
 */
function listGroups($conn, $tenantId) {
    // Parámetros de paginación
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
    $offset = ($page - 1) * $pageSize;
    
    // Filtros
    $where = ["wg.tenant_company_id = ?"];
    $params = [$tenantId];
    $types = 'i';
    
    // Filtro por proyecto
    if (isset($_GET['project_id'])) {
        $where[] = "wg.project_id = ?";
        $params[] = (int)$_GET['project_id'];
        $types .= 'i';
    }
    
    // Ordenamiento
    $sortBy = $_GET['sort_by'] ?? 'created_at';
    $sortDir = strtoupper($_GET['sort_dir'] ?? 'DESC');
    if (!in_array($sortDir, ['ASC', 'DESC'])) {
        $sortDir = 'DESC';
    }
    $allowedSort = ['id', 'name', 'created_at', 'updated_at'];
    if (!in_array($sortBy, $allowedSort)) {
        $sortBy = 'created_at';
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Contar total
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM work_groups wg
        WHERE $whereClause
    ");
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Obtener grupos
    $stmt = $conn->prepare("
        SELECT 
            wg.id,
            wg.tenant_company_id,
            wg.name,
            wg.leader_employee_id,
            wg.project_id,
            wg.notes,
            wg.created_at,
            wg.updated_at,
            e.first_name as leader_first_name,
            e.last_name as leader_last_name,
            p.name as project_name,
            (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = wg.id) as member_count
        FROM work_groups wg
        LEFT JOIN own_employees e ON wg.leader_employee_id = e.id
        LEFT JOIN projects p ON wg.project_id = p.id
        WHERE $whereClause
        ORDER BY wg.$sortBy $sortDir
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $pageSize;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $groups = [];
    while ($row = $result->fetch_assoc()) {
        $groups[] = formatGroup($row);
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $groups,
        'page' => $page,
        'page_size' => $pageSize,
        'total' => $total,
        'total_pages' => ceil($total / $pageSize),
        'sort_by' => $sortBy,
        'sort_dir' => $sortDir
    ]);
}

/**
 * Obtener un grupo por ID
 */
function getGroup($conn, $tenantId, $groupId) {
    $stmt = $conn->prepare("
        SELECT 
            wg.id,
            wg.tenant_company_id,
            wg.name,
            wg.leader_employee_id,
            wg.project_id,
            wg.notes,
            wg.created_at,
            wg.updated_at,
            e.first_name as leader_first_name,
            e.last_name as leader_last_name,
            p.name as project_name
        FROM work_groups wg
        LEFT JOIN own_employees e ON wg.leader_employee_id = e.id
        LEFT JOIN projects p ON wg.project_id = p.id
        WHERE wg.id = ? AND wg.tenant_company_id = ?
    ");
    $stmt->bind_param('ii', $groupId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $group = $result->fetch_assoc();
    $stmt->close();
    
    if (!$group) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'groups.not_found',
            'error_key' => 'groups.not_found',
            'message' => t('groups.not_found')
        ]);
        return;
    }
    
    // Obtener miembros del grupo
    $members = getGroupMembersData($conn, $groupId);
    $group['members'] = $members;
    
    echo json_encode([
        'success' => true,
        'data' => formatGroup($group)
    ]);
}

/**
 * Crear un grupo
 */
function createGroup($conn, $tenantId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validaciones
    $errors = [];
    
    if (empty($input['name'])) {
        $errors[] = ['field' => 'name', 'error' => 'validation.required'];
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'validation.failed',
            'error_key' => 'validation.failed',
            'message' => t('validation.failed'),
            'errors' => $errors
        ]);
        return;
    }
    
    // Validar que el líder existe (si se proporciona)
    if (!empty($input['leader_employee_id'])) {
        $stmt = $conn->prepare("SELECT id FROM own_employees WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param('ii', $input['leader_employee_id'], $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $leader = $result->fetch_assoc();
        $stmt->close();
        
        if (!$leader) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'groups.leader_not_found',
                'error_key' => 'groups.leader_not_found',
                'message' => t('groups.leader_not_found')
            ]);
            return;
        }
    }
    
    // Insertar grupo
    $stmt = $conn->prepare("
        INSERT INTO work_groups (
            tenant_company_id, name, leader_employee_id, project_id, notes
        ) VALUES (?, ?, ?, ?, ?)
    ");
    
    $leaderId = $input['leader_employee_id'] ?? null;
    $projectId = $input['project_id'] ?? null;
    $notes = $input['notes'] ?? null;
    
    $stmt->bind_param(
        'isiss',
        $tenantId,
        $input['name'],
        $leaderId,
        $projectId,
        $notes
    );
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'groups.create_failed',
            'error_key' => 'groups.create_failed',
            'message' => t('groups.create_failed')
        ]);
        $stmt->close();
        return;
    }
    
    $newGroupId = $conn->insert_id;
    $stmt->close();
    
    // Obtener grupo creado
    getGroup($conn, $tenantId, $newGroupId);
}

/**
 * Actualizar un grupo
 */
function updateGroup($conn, $tenantId, $groupId) {
    // Verificar que existe
    $stmt = $conn->prepare("SELECT * FROM work_groups WHERE id = ? AND tenant_company_id = ?");
    $stmt->bind_param('ii', $groupId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();
    
    if (!$existing) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'groups.not_found',
            'error_key' => 'groups.not_found',
            'message' => t('groups.not_found')
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validar líder si se proporciona
    if (isset($input['leader_employee_id']) && !empty($input['leader_employee_id'])) {
        $stmt = $conn->prepare("SELECT id FROM own_employees WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param('ii', $input['leader_employee_id'], $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $leader = $result->fetch_assoc();
        $stmt->close();
        
        if (!$leader) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'groups.leader_not_found',
                'error_key' => 'groups.leader_not_found',
                'message' => t('groups.leader_not_found')
            ]);
            return;
        }
    }
    
    // Preparar datos actualizados
    $name = $input['name'] ?? $existing['name'];
    $leaderId = $input['leader_employee_id'] ?? $existing['leader_employee_id'];
    $projectId = $input['project_id'] ?? $existing['project_id'];
    $notes = $input['notes'] ?? $existing['notes'];
    
    // Actualizar
    $stmt = $conn->prepare("
        UPDATE work_groups SET
            name = ?,
            leader_employee_id = ?,
            project_id = ?,
            notes = ?
        WHERE id = ? AND tenant_company_id = ?
    ");
    
    $stmt->bind_param(
        'sissii',
        $name,
        $leaderId,
        $projectId,
        $notes,
        $groupId,
        $tenantId
    );
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'groups.update_failed',
            'error_key' => 'groups.update_failed',
            'message' => t('groups.update_failed')
        ]);
        $stmt->close();
        return;
    }
    
    $stmt->close();
    
    // Obtener grupo actualizado
    getGroup($conn, $tenantId, $groupId);
}

/**
 * Eliminar un grupo
 */
function deleteGroup($conn, $tenantId, $groupId) {
    // Verificar que existe
    $stmt = $conn->prepare("SELECT * FROM work_groups WHERE id = ? AND tenant_company_id = ?");
    $stmt->bind_param('ii', $groupId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $group = $result->fetch_assoc();
    $stmt->close();
    
    if (!$group) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'groups.not_found',
            'error_key' => 'groups.not_found',
            'message' => t('groups.not_found')
        ]);
        return;
    }
    
    // Eliminar grupo (los miembros se eliminan por CASCADE)
    $stmt = $conn->prepare("DELETE FROM work_groups WHERE id = ? AND tenant_company_id = ?");
    $stmt->bind_param('ii', $groupId, $tenantId);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => t('groups.deleted_success')
    ]);
}

/**
 * Agregar miembro a un grupo
 */
function addGroupMember($conn, $tenantId, $groupId) {
    // Verificar que el grupo existe y pertenece al tenant
    $stmt = $conn->prepare("SELECT * FROM work_groups WHERE id = ? AND tenant_company_id = ?");
    $stmt->bind_param('ii', $groupId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $group = $result->fetch_assoc();
    $stmt->close();
    
    if (!$group) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'groups.not_found',
            'error_key' => 'groups.not_found',
            'message' => t('groups.not_found')
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validaciones
    $errors = [];
    
    if (empty($input['member_type']) || !in_array($input['member_type'], ['own_employee', 'agency_worker'])) {
        $errors[] = ['field' => 'member_type', 'error' => 'validation.invalid'];
    }
    
    if (empty($input['start_at'])) {
        $errors[] = ['field' => 'start_at', 'error' => 'validation.required'];
    }
    
    if ($input['member_type'] === 'own_employee' && empty($input['own_employee_id'])) {
        $errors[] = ['field' => 'own_employee_id', 'error' => 'validation.required'];
    }
    
    if ($input['member_type'] === 'agency_worker' && empty($input['agency_worker_id'])) {
        $errors[] = ['field' => 'agency_worker_id', 'error' => 'validation.required'];
    }
    
    if ($input['member_type'] === 'agency_worker' && empty($input['tenant_agency_id'])) {
        $errors[] = ['field' => 'tenant_agency_id', 'error' => 'validation.required'];
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'validation.failed',
            'error_key' => 'validation.failed',
            'message' => t('validation.failed'),
            'errors' => $errors
        ]);
        return;
    }
    
    // Validar que el empleado/trabajador existe
    if ($input['member_type'] === 'own_employee') {
        $stmt = $conn->prepare("SELECT id FROM own_employees WHERE id = ? AND tenant_id = ?");
        $stmt->bind_param('ii', $input['own_employee_id'], $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $employee = $result->fetch_assoc();
        $stmt->close();
        
        if (!$employee) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'groups.employee_not_found',
                'error_key' => 'groups.employee_not_found',
                'message' => t('groups.employee_not_found')
            ]);
            return;
        }
    }
    
    // Insertar miembro
    $stmt = $conn->prepare("
        INSERT INTO group_members (
            group_id, member_type, own_employee_id, agency_worker_id, tenant_agency_id, start_at, end_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $ownEmployeeId = $input['member_type'] === 'own_employee' ? $input['own_employee_id'] : null;
    $agencyWorkerId = $input['member_type'] === 'agency_worker' ? $input['agency_worker_id'] : null;
    $tenantAgencyId = $input['member_type'] === 'agency_worker' ? $input['tenant_agency_id'] : null;
    $endAt = $input['end_at'] ?? null;
    
    $stmt->bind_param(
        'issiiss',
        $groupId,
        $input['member_type'],
        $ownEmployeeId,
        $agencyWorkerId,
        $tenantAgencyId,
        $input['start_at'],
        $endAt
    );
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'groups.member_add_failed',
            'error_key' => 'groups.member_add_failed',
            'message' => t('groups.member_add_failed')
        ]);
        $stmt->close();
        return;
    }
    
    $memberId = $conn->insert_id;
    $stmt->close();
    
    // Obtener miembro creado
    listGroupMembers($conn, $tenantId, $groupId);
}

/**
 * Eliminar miembro de un grupo
 */
function removeGroupMember($conn, $tenantId, $groupId, $memberId) {
    // Verificar que el grupo existe y pertenece al tenant
    $stmt = $conn->prepare("SELECT * FROM work_groups WHERE id = ? AND tenant_company_id = ?");
    $stmt->bind_param('ii', $groupId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $group = $result->fetch_assoc();
    $stmt->close();
    
    if (!$group) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'groups.not_found',
            'error_key' => 'groups.not_found',
            'message' => t('groups.not_found')
        ]);
        return;
    }
    
    // Verificar que el miembro existe y pertenece al grupo
    $stmt = $conn->prepare("SELECT * FROM group_members WHERE id = ? AND group_id = ?");
    $stmt->bind_param('ii', $memberId, $groupId);
    $stmt->execute();
    $result = $stmt->get_result();
    $member = $result->fetch_assoc();
    $stmt->close();
    
    if (!$member) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'groups.member_not_found',
            'error_key' => 'groups.member_not_found',
            'message' => t('groups.member_not_found')
        ]);
        return;
    }
    
    // Eliminar miembro
    $stmt = $conn->prepare("DELETE FROM group_members WHERE id = ? AND group_id = ?");
    $stmt->bind_param('ii', $memberId, $groupId);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => t('groups.member_removed_success')
    ]);
}

/**
 * Listar miembros de un grupo
 */
function listGroupMembers($conn, $tenantId, $groupId) {
    // Verificar que el grupo existe y pertenece al tenant
    $stmt = $conn->prepare("SELECT * FROM work_groups WHERE id = ? AND tenant_company_id = ?");
    $stmt->bind_param('ii', $groupId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $group = $result->fetch_assoc();
    $stmt->close();
    
    if (!$group) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'groups.not_found',
            'error_key' => 'groups.not_found',
            'message' => t('groups.not_found')
        ]);
        return;
    }
    
    $members = getGroupMembersData($conn, $groupId);
    
    echo json_encode([
        'success' => true,
        'data' => $members
    ]);
}

/**
 * Obtener datos de miembros de un grupo
 */
function getGroupMembersData($conn, $groupId) {
    $stmt = $conn->prepare("
        SELECT 
            gm.id,
            gm.group_id,
            gm.member_type,
            gm.own_employee_id,
            gm.agency_worker_id,
            gm.tenant_agency_id,
            gm.start_at,
            gm.end_at,
            CASE 
                WHEN gm.member_type = 'own_employee' THEN e.first_name
                WHEN gm.member_type = 'agency_worker' THEN w.first_name
            END as first_name,
            CASE 
                WHEN gm.member_type = 'own_employee' THEN e.last_name
                WHEN gm.member_type = 'agency_worker' THEN w.last_name
            END as last_name,
            CASE 
                WHEN gm.member_type = 'own_employee' THEN e.employee_code
                WHEN gm.member_type = 'agency_worker' THEN w.worker_code
            END as code,
            t.name as agency_name
        FROM group_members gm
        LEFT JOIN own_employees e ON gm.member_type = 'own_employee' AND gm.own_employee_id = e.id
        LEFT JOIN agency_workers w ON gm.member_type = 'agency_worker' AND gm.agency_worker_id = w.id
        LEFT JOIN tenants t ON gm.tenant_agency_id = t.id
        WHERE gm.group_id = ?
        ORDER BY gm.start_at ASC
    ");
    $stmt->bind_param('i', $groupId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = [
            'id' => (int)$row['id'],
            'group_id' => (int)$row['group_id'],
            'member_type' => $row['member_type'],
            'own_employee_id' => $row['own_employee_id'] ? (int)$row['own_employee_id'] : null,
            'agency_worker_id' => $row['agency_worker_id'] ? (int)$row['agency_worker_id'] : null,
            'tenant_agency_id' => $row['tenant_agency_id'] ? (int)$row['tenant_agency_id'] : null,
            'start_at' => $row['start_at'],
            'end_at' => $row['end_at'],
            'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
            'code' => $row['code'],
            'agency_name' => $row['agency_name']
        ];
    }
    $stmt->close();
    
    return $members;
}

/**
 * Formatear grupo para respuesta
 */
function formatGroup($row) {
    $group = [
        'id' => (int)$row['id'],
        'tenant_company_id' => (int)$row['tenant_company_id'],
        'name' => $row['name'],
        'leader_employee_id' => $row['leader_employee_id'] ? (int)$row['leader_employee_id'] : null,
        'leader_name' => trim(($row['leader_first_name'] ?? '') . ' ' . ($row['leader_last_name'] ?? '')),
        'project_id' => $row['project_id'] ? (int)$row['project_id'] : null,
        'project_name' => $row['project_name'] ?? null,
        'notes' => $row['notes'],
        'member_count' => isset($row['member_count']) ? (int)$row['member_count'] : (isset($row['members']) ? count($row['members']) : 0),
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
    
    if (isset($row['members'])) {
        $group['members'] = $row['members'];
    }
    
    return $group;
}

