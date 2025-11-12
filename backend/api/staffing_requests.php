<?php
/**
 * API Solicitudes de Personal
 * 
 * Endpoints:
 * - GET /api/staffing-requests - Listar solicitudes
 * - POST /api/staffing-requests - Crear solicitud
 * - GET /api/staffing-requests/{id} - Obtener solicitud
 * - PUT /api/staffing-requests/{id} - Actualizar solicitud
 * - DELETE /api/staffing-requests/{id} - Cancelar solicitud
 * - POST /api/staffing-requests/{id}/send - Enviar a temporeras
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
    $path = $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '';
    
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
    
    // Verificar que el tenant es una empresa (solo empresas crean solicitudes)
    $conn = DBHelper::getConnection();
    $stmt = $conn->prepare("SELECT org_type FROM tenants WHERE id = ?");
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $tenant = $result->fetch_assoc();
    $stmt->close();
    
    if (!$tenant || $tenant['org_type'] !== 'empresa') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'requests.only_companies',
            'error_key' => 'requests.only_companies',
            'message' => t('requests.only_companies')
        ]);
        exit;
    }
    
    // Obtener user_id del middleware o JWT
    $userId = $middlewareResult['user_id'] ?? null;
    if (!$userId) {
        // Intentar obtener desde header como fallback
        $headers = getallheaders();
        $userId = $headers['X-User-ID'] ?? $_SERVER['HTTP_X_USER_ID'] ?? null;
    }
    
    // Por ahora, permitir continuar sin user_id para desarrollo
    // En producción, esto debería ser obligatorio
    
    // Parsear ruta para obtener ID y acción
    $pathParts = explode('/', trim($path, '/'));
    $requestId = null;
    $action = null;
    
    if (count($pathParts) >= 2) {
        $requestId = is_numeric($pathParts[1]) ? (int)$pathParts[1] : null;
        if (count($pathParts) >= 3) {
            $action = $pathParts[2];
        }
    }
    
    switch ($method) {
        case 'GET':
            if ($requestId) {
                // Obtener solicitud específica
                $stmt = $conn->prepare("
                    SELECT sr.*, 
                           p.name as project_name,
                           u.first_name, u.last_name, u.email as creator_email
                    FROM staffing_requests sr
                    LEFT JOIN projects p ON sr.project_id = p.id
                    LEFT JOIN users u ON sr.created_by_user = u.id
                    WHERE sr.id = ? AND sr.tenant_company_id = ?
                ");
                $stmt->bind_param('ii', $requestId, $tenantId);
                $stmt->execute();
                $result = $stmt->get_result();
                $request = $result->fetch_assoc();
                $stmt->close();
                
                if (!$request) {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'error' => 'requests.not_found',
                        'error_key' => 'requests.not_found',
                        'message' => t('requests.not_found')
                    ]);
                    exit;
                }
                
                // Obtener destinatarios
                $stmt = $conn->prepare("
                    SELECT srt.*, t.name as agency_name
                    FROM staffing_request_targets srt
                    INNER JOIN tenants t ON srt.tenant_agency_id = t.id
                    WHERE srt.request_id = ?
                ");
                $stmt->bind_param('i', $requestId);
                $stmt->execute();
                $result = $stmt->get_result();
                $targets = [];
                while ($row = $result->fetch_assoc()) {
                    $targets[] = $row;
                }
                $stmt->close();
                
                $request['targets'] = $targets;
                $request['requirements'] = json_decode($request['requirements_json'] ?? '{}', true);
                unset($request['requirements_json']);
                
                echo json_encode([
                    'success' => true,
                    'data' => $request
                ]);
            } else {
                // Listar solicitudes con paginación
                $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
                $offset = ($page - 1) * $pageSize;
                
                $status = $_GET['status'] ?? null;
                $sortBy = $_GET['sort_by'] ?? 'created_at';
                $sortDir = strtoupper($_GET['sort_dir'] ?? 'DESC');
                
                // Validar sort_by
                $allowedSort = ['created_at', 'date_from', 'date_to', 'status', 'qty_needed'];
                if (!in_array($sortBy, $allowedSort)) {
                    $sortBy = 'created_at';
                }
                if ($sortDir !== 'ASC' && $sortDir !== 'DESC') {
                    $sortDir = 'DESC';
                }
                
                // Construir query
                $where = "sr.tenant_company_id = ?";
                $params = [$tenantId];
                $types = 'i';
                
                if ($status) {
                    $where .= " AND sr.status = ?";
                    $params[] = $status;
                    $types .= 's';
                }
                
                // Contar total
                $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM staffing_requests sr WHERE $where");
                $countStmt->bind_param($types, ...$params);
                $countStmt->execute();
                $countResult = $countStmt->get_result();
                $total = $countResult->fetch_assoc()['total'];
                $countStmt->close();
                
                // Obtener solicitudes
                $query = "
                    SELECT sr.*, 
                           p.name as project_name,
                           u.first_name, u.last_name
                    FROM staffing_requests sr
                    LEFT JOIN projects p ON sr.project_id = p.id
                    LEFT JOIN users u ON sr.created_by_user = u.id
                    WHERE $where
                    ORDER BY sr.$sortBy $sortDir
                    LIMIT ? OFFSET ?
                ";
                $params[] = $pageSize;
                $params[] = $offset;
                $types .= 'ii';
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                $requests = [];
                while ($row = $result->fetch_assoc()) {
                    $row['requirements'] = json_decode($row['requirements_json'] ?? '{}', true);
                    unset($row['requirements_json']);
                    $requests[] = $row;
                }
                $stmt->close();
                
                echo json_encode([
                    'success' => true,
                    'data' => $requests,
                    'page' => $page,
                    'page_size' => $pageSize,
                    'total' => $total,
                    'total_pages' => ceil($total / $pageSize),
                    'sort_by' => $sortBy,
                    'sort_dir' => $sortDir
                ]);
            }
            break;
            
        case 'POST':
            if ($action === 'send' && $requestId) {
                // Enviar solicitud a temporeras
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!$input || empty($input['agency_ids']) || !is_array($input['agency_ids'])) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'error' => 'validation.required',
                        'error_key' => 'validation.required',
                        'error_params' => ['field' => 'agency_ids'],
                        'message' => t('validation.required', ['field' => 'agency_ids'])
                    ]);
                    exit;
                }
                
                // Verificar que la solicitud existe y pertenece al tenant
                $stmt = $conn->prepare("SELECT id FROM staffing_requests WHERE id = ? AND tenant_company_id = ?");
                $stmt->bind_param('ii', $requestId, $tenantId);
                $stmt->execute();
                $result = $stmt->get_result();
                if (!$result->fetch_assoc()) {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'error' => 'requests.not_found',
                        'error_key' => 'requests.not_found',
                        'message' => t('requests.not_found')
                    ]);
                    exit;
                }
                $stmt->close();
                
                // Verificar que las temporeras están vinculadas
                $agencyIds = array_map('intval', $input['agency_ids']);
                $placeholders = implode(',', array_fill(0, count($agencyIds), '?'));
                $stmt = $conn->prepare("
                    SELECT DISTINCT t.id 
                    FROM tenants t
                    INNER JOIN agency_company_links acl ON (
                        (acl.tenant_company_id = ? AND acl.tenant_agency_id = t.id) OR
                        (acl.tenant_agency_id = ? AND acl.tenant_company_id = t.id)
                    )
                    WHERE t.id IN ($placeholders) AND t.org_type = 'temporera' AND acl.status = 'active'
                ");
                $params = array_merge([$tenantId, $tenantId], $agencyIds);
                $types = 'ii' . str_repeat('i', count($agencyIds));
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                $validAgencies = [];
                while ($row = $result->fetch_assoc()) {
                    $validAgencies[] = $row['id'];
                }
                $stmt->close();
                
                if (empty($validAgencies)) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'error' => 'requests.invalid_agencies',
                        'error_key' => 'requests.invalid_agencies',
                        'message' => t('requests.invalid_agencies')
                    ]);
                    exit;
                }
                
                // Crear destinatarios
                $sent = [];
                $now = date('Y-m-d H:i:s');
                foreach ($validAgencies as $agencyId) {
                    // Verificar si ya existe
                    $checkStmt = $conn->prepare("SELECT id FROM staffing_request_targets WHERE request_id = ? AND tenant_agency_id = ?");
                    $checkStmt->bind_param('ii', $requestId, $agencyId);
                    $checkStmt->execute();
                    $exists = $checkStmt->get_result()->fetch_assoc();
                    $checkStmt->close();
                    
                    if (!$exists) {
                        $stmt = $conn->prepare("INSERT INTO staffing_request_targets (request_id, tenant_agency_id, status, sent_at) VALUES (?, ?, 'sent', ?)");
                        $stmt->bind_param('iis', $requestId, $agencyId, $now);
                        $stmt->execute();
                        $sent[] = $agencyId;
                        $stmt->close();
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'request_id' => $requestId,
                        'agencies_sent' => count($sent),
                        'agency_ids' => $sent
                    ],
                    'message' => t('requests.sent_success', ['count' => count($sent)])
                ]);
            } else {
                // Crear nueva solicitud
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
                $required = ['date_from', 'date_to', 'qty_needed'];
                $missing = [];
                foreach ($required as $field) {
                    if (empty($input[$field])) {
                        $missing[] = $field;
                    }
                }
                
                if (!empty($missing)) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'error' => 'validation.required',
                        'error_key' => 'validation.required',
                        'error_params' => ['field' => implode(', ', $missing)],
                        'message' => t('validation.required', ['field' => implode(', ', $missing)])
                    ]);
                    exit;
                }
                
                // Validar fechas
                $dateFrom = $input['date_from'];
                $dateTo = $input['date_to'];
                
                if (strtotime($dateFrom) >= strtotime($dateTo)) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'error' => 'validation.date_range_invalid',
                        'error_key' => 'validation.date_range_invalid',
                        'message' => t('validation.date_range_invalid')
                    ]);
                    exit;
                }
                
                // Validar cantidad
                $qtyNeeded = (int)$input['qty_needed'];
                if ($qtyNeeded <= 0) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'error' => 'validation.qty_invalid',
                        'error_key' => 'validation.qty_invalid',
                        'message' => t('validation.qty_invalid')
                    ]);
                    exit;
                }
                
                // Insertar solicitud
                $projectId = !empty($input['project_id']) ? (int)$input['project_id'] : null;
                $location = $input['location'] ?? null;
                $latitude = isset($input['latitude']) ? (float)$input['latitude'] : null;
                $longitude = isset($input['longitude']) ? (float)$input['longitude'] : null;
                $requirements = isset($input['requirements']) ? json_encode($input['requirements']) : null;
                
                $stmt = $conn->prepare("
                    INSERT INTO staffing_requests 
                    (tenant_company_id, created_by_user, project_id, date_from, date_to, location, latitude, longitude, qty_needed, requirements_json, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')
                ");
                $stmt->bind_param('iiisssddiss', $tenantId, $userId, $projectId, $dateFrom, $dateTo, $location, $latitude, $longitude, $qtyNeeded, $requirements);
                
                if (!$stmt->execute()) {
                    throw new Exception('Error creando solicitud: ' . $stmt->error);
                }
                
                $requestId = $conn->insert_id;
                $stmt->close();
                
                // Si se enviaron agency_ids, crear destinatarios
                if (!empty($input['agency_ids']) && is_array($input['agency_ids'])) {
                    $agencyIds = array_map('intval', $input['agency_ids']);
                    $now = date('Y-m-d H:i:s');
                    
                    foreach ($agencyIds as $agencyId) {
                        // Verificar vínculo
                        $checkStmt = $conn->prepare("
                            SELECT id FROM agency_company_links 
                            WHERE ((tenant_company_id = ? AND tenant_agency_id = ?) OR (tenant_company_id = ? AND tenant_agency_id = ?))
                            AND status = 'active'
                        ");
                        $checkStmt->bind_param('iiii', $tenantId, $agencyId, $agencyId, $tenantId);
                        $checkStmt->execute();
                        $link = $checkStmt->get_result()->fetch_assoc();
                        $checkStmt->close();
                        
                        if ($link) {
                            $stmt = $conn->prepare("INSERT INTO staffing_request_targets (request_id, tenant_agency_id, status, sent_at) VALUES (?, ?, 'sent', ?)");
                            $stmt->bind_param('iis', $requestId, $agencyId, $now);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                }
                
                // Obtener solicitud creada
                $stmt = $conn->prepare("
                    SELECT sr.*, p.name as project_name
                    FROM staffing_requests sr
                    LEFT JOIN projects p ON sr.project_id = p.id
                    WHERE sr.id = ?
                ");
                $stmt->bind_param('i', $requestId);
                $stmt->execute();
                $result = $stmt->get_result();
                $request = $result->fetch_assoc();
                $stmt->close();
                
                $request['requirements'] = json_decode($request['requirements_json'] ?? '{}', true);
                unset($request['requirements_json']);
                
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'data' => $request,
                    'message' => t('requests.created_success')
                ]);
            }
            break;
            
        case 'PUT':
            if (!$requestId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.request_id_required',
                    'error_key' => 'validation.request_id_required',
                    'message' => t('validation.request_id_required')
                ]);
                exit;
            }
            
            // Verificar que la solicitud existe y pertenece al tenant
            $stmt = $conn->prepare("SELECT * FROM staffing_requests WHERE id = ? AND tenant_company_id = ?");
            $stmt->bind_param('ii', $requestId, $tenantId);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing = $result->fetch_assoc();
            $stmt->close();
            
            if (!$existing) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'requests.not_found',
                    'error_key' => 'requests.not_found',
                    'message' => t('requests.not_found')
                ]);
                exit;
            }
            
            // Solo se pueden actualizar solicitudes abiertas
            if ($existing['status'] !== 'open' && $existing['status'] !== 'partially_filled') {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'error' => 'requests.cannot_update',
                    'error_key' => 'requests.cannot_update',
                    'message' => t('requests.cannot_update')
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
            
            // Construir UPDATE dinámico
            $updates = [];
            $params = [];
            $types = '';
            
            $allowedFields = ['date_from', 'date_to', 'location', 'latitude', 'longitude', 'qty_needed', 'project_id', 'requirements', 'status'];
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    if ($field === 'requirements') {
                        $updates[] = "requirements_json = ?";
                        $params[] = json_encode($input[$field]);
                        $types .= 's';
                    } elseif ($field === 'latitude' || $field === 'longitude') {
                        $updates[] = "$field = ?";
                        $params[] = (float)$input[$field];
                        $types .= 'd';
                    } elseif ($field === 'qty_needed' || $field === 'project_id') {
                        $updates[] = "$field = ?";
                        $params[] = (int)$input[$field];
                        $types .= 'i';
                    } else {
                        $updates[] = "$field = ?";
                        $params[] = $input[$field];
                        $types .= 's';
                    }
                }
            }
            
            if (empty($updates)) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.no_changes',
                    'error_key' => 'validation.no_changes',
                    'message' => t('validation.no_changes')
                ]);
                exit;
            }
            
            $params[] = $requestId;
            $params[] = $tenantId;
            $types .= 'ii';
            
            $sql = "UPDATE staffing_requests SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_company_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if (!$stmt->execute()) {
                throw new Exception('Error actualizando solicitud: ' . $stmt->error);
            }
            
            $stmt->close();
            
            // Obtener solicitud actualizada
            $stmt = $conn->prepare("
                SELECT sr.*, p.name as project_name
                FROM staffing_requests sr
                LEFT JOIN projects p ON sr.project_id = p.id
                WHERE sr.id = ?
            ");
            $stmt->bind_param('i', $requestId);
            $stmt->execute();
            $result = $stmt->get_result();
            $request = $result->fetch_assoc();
            $stmt->close();
            
            $request['requirements'] = json_decode($request['requirements_json'] ?? '{}', true);
            unset($request['requirements_json']);
            
            echo json_encode([
                'success' => true,
                'data' => $request,
                'message' => t('requests.updated_success')
            ]);
            break;
            
        case 'DELETE':
            if (!$requestId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.request_id_required',
                    'error_key' => 'validation.request_id_required',
                    'message' => t('validation.request_id_required')
                ]);
                exit;
            }
            
            // Verificar que la solicitud existe y pertenece al tenant
            $stmt = $conn->prepare("SELECT status FROM staffing_requests WHERE id = ? AND tenant_company_id = ?");
            $stmt->bind_param('ii', $requestId, $tenantId);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing = $result->fetch_assoc();
            $stmt->close();
            
            if (!$existing) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'requests.not_found',
                    'error_key' => 'requests.not_found',
                    'message' => t('requests.not_found')
                ]);
                exit;
            }
            
            // Actualizar status a 'canceled' (soft delete)
            $stmt = $conn->prepare("UPDATE staffing_requests SET status = 'canceled' WHERE id = ? AND tenant_company_id = ?");
            $stmt->bind_param('ii', $requestId, $tenantId);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'message' => t('requests.canceled_success')
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
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'errors.internal_error',
        'error_key' => 'errors.internal_error',
        'message' => Env::isDebug() ? $e->getMessage() : t('errors.internal_error')
    ]);
    exit;
} catch (Error $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'errors.internal_error',
        'error_key' => 'errors.internal_error',
        'message' => Env::isDebug() ? $e->getMessage() : t('errors.internal_error')
    ]);
    exit;
}

