<?php
/**
 * API Relaciones Empresa ↔ Temporera
 * 
 * Endpoints:
 * - POST /api/agency-company-links - Crear vínculo
 * - GET /api/agency-company-links - Listar vínculos
 * - PUT /api/agency-company-links/{id} - Actualizar vínculo
 * - DELETE /api/agency-company-links/{id} - Eliminar vínculo
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
    
    // Extraer ID de la URL si existe
    $id = null;
    if (preg_match('#/agency-company-links/(\d+)#', $path, $matches)) {
        $id = (int)$matches[1];
    }
    
    switch ($method) {
        case 'POST':
            // Crear vínculo Empresa ↔ Temporera
            RBACMiddleware::requirePermission('agency_links.create');
            
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
            if (empty($input['tenant_company_id']) || empty($input['tenant_agency_id'])) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.required',
                    'error_key' => 'validation.required',
                    'error_params' => ['field' => 'tenant_company_id, tenant_agency_id'],
                    'message' => t('validation.required', ['field' => 'tenant_company_id, tenant_agency_id'])
                ]);
                exit;
            }
            
            $companyId = (int)$input['tenant_company_id'];
            $agencyId = (int)$input['tenant_agency_id'];
            
            // Validar que no sea el mismo tenant
            if ($companyId === $agencyId) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'error' => 'agency_links.same_tenant',
                    'error_key' => 'agency_links.same_tenant',
                    'message' => t('agency_links.same_tenant')
                ]);
                exit;
            }
            
            $conn = DBHelper::getConnection();
            
            // Verificar que los tenants existen y tienen los tipos correctos
            $stmt = $conn->prepare("SELECT id, org_type FROM tenants WHERE id IN (?, ?)");
            $stmt->bind_param('ii', $companyId, $agencyId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $tenants = [];
            while ($row = $result->fetch_assoc()) {
                $tenants[$row['id']] = $row['org_type'];
            }
            $stmt->close();
            
            if (count($tenants) !== 2) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'tenants.not_found',
                    'error_key' => 'tenants.not_found',
                    'message' => t('tenants.not_found')
                ]);
                exit;
            }
            
            // Verificar tipos: uno debe ser empresa y otro temporera
            if (!(($tenants[$companyId] === 'empresa' && $tenants[$agencyId] === 'temporera') ||
                  ($tenants[$companyId] === 'temporera' && $tenants[$agencyId] === 'empresa'))) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'error' => 'agency_links.invalid_types',
                    'error_key' => 'agency_links.invalid_types',
                    'message' => t('agency_links.invalid_types')
                ]);
                exit;
            }
            
            // Asegurar que company_id es empresa y agency_id es temporera
            if ($tenants[$companyId] === 'temporera') {
                // Intercambiar si están al revés
                $temp = $companyId;
                $companyId = $agencyId;
                $agencyId = $temp;
            }
            
            // Verificar que no existe ya el vínculo
            $stmt = $conn->prepare("SELECT id FROM agency_company_links WHERE tenant_company_id = ? AND tenant_agency_id = ?");
            $stmt->bind_param('ii', $companyId, $agencyId);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing = $result->fetch_assoc();
            $stmt->close();
            
            if ($existing) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'error' => 'agency_links.already_exists',
                    'error_key' => 'agency_links.already_exists',
                    'message' => t('agency_links.already_exists')
                ]);
                exit;
            }
            
            // Crear vínculo
            $status = $input['status'] ?? 'active';
            $termsJson = isset($input['terms_json']) ? json_encode($input['terms_json']) : null;
            
            $stmt = $conn->prepare("INSERT INTO agency_company_links (tenant_company_id, tenant_agency_id, status, terms_json) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('iiss', $companyId, $agencyId, $status, $termsJson);
            
            if (!$stmt->execute()) {
                throw new Exception('Error creando vínculo: ' . $stmt->error);
            }
            
            $linkId = $conn->insert_id;
            $stmt->close();
            
            // Obtener vínculo creado
            $stmt = $conn->prepare("SELECT acl.*, 
                                           tc.name as company_name, 
                                           ta.name as agency_name 
                                    FROM agency_company_links acl
                                    INNER JOIN tenants tc ON acl.tenant_company_id = tc.id
                                    INNER JOIN tenants ta ON acl.tenant_agency_id = ta.id
                                    WHERE acl.id = ?");
            $stmt->bind_param('i', $linkId);
            $stmt->execute();
            $result = $stmt->get_result();
            $link = $result->fetch_assoc();
            $stmt->close();
            
            // Parsear JSON
            if ($link['terms_json']) {
                $link['terms'] = json_decode($link['terms_json'], true);
            }
            unset($link['terms_json']);
            
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'data' => $link,
                'message' => t('agency_links.create_success')
            ]);
            break;
            
        case 'GET':
            // Listar vínculos del tenant
            RBACMiddleware::requirePermission('agency_links.view');
            
            $conn = DBHelper::getConnection();
            
            // Determinar si el tenant es empresa o temporera
            $stmt = $conn->prepare("SELECT org_type FROM tenants WHERE id = ?");
            $stmt->bind_param('i', $tenantId);
            $stmt->execute();
            $result = $stmt->get_result();
            $tenant = $result->fetch_assoc();
            $stmt->close();
            
            if (!$tenant) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'tenants.not_found',
                    'error_key' => 'tenants.not_found',
                    'message' => t('tenants.not_found')
                ]);
                exit;
            }
            
            // Paginación
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
            $offset = ($page - 1) * $pageSize;
            
            // Construir query según tipo de tenant
            if ($tenant['org_type'] === 'empresa') {
                // Si es empresa, buscar vínculos donde es company
                $where = "acl.tenant_company_id = ?";
                $params = [$tenantId];
            } else {
                // Si es temporera, buscar vínculos donde es agency
                $where = "acl.tenant_agency_id = ?";
                $params = [$tenantId];
            }
            
            // Filtros adicionales
            if (!empty($_GET['status'])) {
                $where .= " AND acl.status = ?";
                $params[] = $_GET['status'];
            }
            
            // Contar total
            $countSql = "SELECT COUNT(*) as total 
                         FROM agency_company_links acl 
                         WHERE {$where}";
            $types = str_repeat('s', count($params));
            $stmt = $conn->prepare($countSql);
            if (count($params) > 0) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $countResult = $stmt->get_result();
            $total = $countResult->fetch_assoc()['total'];
            $stmt->close();
            
            // Obtener datos
            $sql = "SELECT acl.*, 
                           tc.name as company_name, 
                           ta.name as agency_name 
                    FROM agency_company_links acl
                    INNER JOIN tenants tc ON acl.tenant_company_id = tc.id
                    INNER JOIN tenants ta ON acl.tenant_agency_id = ta.id
                    WHERE {$where}
                    ORDER BY acl.created_at DESC
                    LIMIT ? OFFSET ?";
            
            $types .= 'ii';
            $params[] = $pageSize;
            $params[] = $offset;
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $links = [];
            while ($row = $result->fetch_assoc()) {
                if ($row['terms_json']) {
                    $row['terms'] = json_decode($row['terms_json'], true);
                }
                unset($row['terms_json']);
                $links[] = $row;
            }
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'data' => $links,
                'page' => $page,
                'page_size' => $pageSize,
                'total' => (int)$total,
                'total_pages' => (int)ceil($total / $pageSize)
            ]);
            break;
            
        case 'PUT':
        case 'PATCH':
            // Actualizar vínculo
            RBACMiddleware::requirePermission('agency_links.update');
            
            if (!$id) {
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
            
            // Verificar que el vínculo existe y pertenece al tenant
            $stmt = $conn->prepare("SELECT * FROM agency_company_links WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $link = $result->fetch_assoc();
            $stmt->close();
            
            if (!$link) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'agency_links.not_found',
                    'error_key' => 'agency_links.not_found',
                    'message' => t('agency_links.not_found')
                ]);
                exit;
            }
            
            // Verificar que el tenant tiene acceso a este vínculo
            if ($link['tenant_company_id'] != $tenantId && $link['tenant_agency_id'] != $tenantId) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'errors.forbidden',
                    'error_key' => 'errors.forbidden',
                    'message' => t('errors.forbidden')
                ]);
                exit;
            }
            
            // Actualizar campos permitidos
            $updates = [];
            $params = [];
            $types = '';
            
            if (isset($input['status'])) {
                if (in_array($input['status'], ['active', 'paused', 'blocked'])) {
                    $updates[] = "status = ?";
                    $params[] = $input['status'];
                    $types .= 's';
                }
            }
            
            if (isset($input['terms_json'])) {
                $updates[] = "terms_json = ?";
                $params[] = json_encode($input['terms_json']);
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
                exit;
            }
            
            $updates[] = "updated_at = NOW()";
            $params[] = $id;
            $types .= 'i';
            
            $sql = "UPDATE agency_company_links SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'message' => t('agency_links.update_success')
            ]);
            break;
            
        case 'DELETE':
            // Eliminar vínculo
            RBACMiddleware::requirePermission('agency_links.delete');
            
            if (!$id) {
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
            
            $conn = DBHelper::getConnection();
            
            // Verificar que el vínculo existe y pertenece al tenant
            $stmt = $conn->prepare("SELECT * FROM agency_company_links WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $link = $result->fetch_assoc();
            $stmt->close();
            
            if (!$link) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'agency_links.not_found',
                    'error_key' => 'agency_links.not_found',
                    'message' => t('agency_links.not_found')
                ]);
                exit;
            }
            
            // Verificar que el tenant tiene acceso
            if ($link['tenant_company_id'] != $tenantId && $link['tenant_agency_id'] != $tenantId) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'errors.forbidden',
                    'error_key' => 'errors.forbidden',
                    'message' => t('errors.forbidden')
                ]);
                exit;
            }
            
            // Eliminar vínculo
            $stmt = $conn->prepare("DELETE FROM agency_company_links WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'message' => t('agency_links.delete_success')
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

