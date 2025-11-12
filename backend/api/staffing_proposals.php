<?php
/**
 * API Propuestas de Candidatos
 * 
 * Endpoints:
 * - GET /api/staffing-proposals - Listar propuestas
 * - POST /api/staffing-proposals - Crear propuesta (con auto-matching opcional)
 * - GET /api/staffing-proposals/{id} - Obtener propuesta
 * - PUT /api/staffing-proposals/{id}/accept - Aceptar propuesta
 * - PUT /api/staffing-proposals/{id}/reject - Rechazar propuesta
 * - PUT /api/staffing-proposals/{id}/change - Solicitar cambios
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../core/TenantMiddleware.php';
require_once __DIR__ . '/../core/RBACMiddleware.php';
require_once __DIR__ . '/../core/MatchingService.php';
require_once __DIR__ . '/../helpers/db_helper.php';
require_once __DIR__ . '/../helpers/i18n.php';
require_once __DIR__ . '/../helpers/env.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '';
    
    // Extraer tenant_id del middleware
    $tenantId = TenantMiddleware::getTenantId();
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
    
    // Verificar tipo de tenant
    $conn = DBHelper::getConnection();
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
            'error' => 'errors.tenant_not_found',
            'error_key' => 'errors.tenant_not_found',
            'message' => t('errors.tenant_not_found')
        ]);
        exit;
    }
    
    $orgType = $tenant['org_type'];
    
    // Obtener user_id del JWT
    $userId = $_SERVER['HTTP_X_USER_ID'] ?? null;
    if (!$userId) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'auth.user_required',
            'error_key' => 'auth.user_required',
            'message' => t('auth.user_required')
        ]);
        exit;
    }
    
    // Parsear ruta
    $pathParts = explode('/', trim($path, '/'));
    $proposalId = null;
    $action = null;
    
    if (count($pathParts) >= 2) {
        $proposalId = is_numeric($pathParts[1]) ? (int)$pathParts[1] : null;
        if (count($pathParts) >= 3) {
            $action = $pathParts[2];
        }
    }
    
    switch ($method) {
        case 'GET':
            if ($proposalId) {
                // Obtener propuesta específica
                if ($orgType === 'temporera') {
                    $stmt = $conn->prepare("
                        SELECT sp.*, 
                               sr.date_from, sr.date_to, sr.location, sr.qty_needed,
                               sr.tenant_company_id, t.name as company_name
                        FROM staffing_proposals sp
                        INNER JOIN staffing_requests sr ON sp.request_id = sr.id
                        INNER JOIN tenants t ON sr.tenant_company_id = t.id
                        WHERE sp.id = ? AND sp.tenant_agency_id = ?
                    ");
                    $stmt->bind_param('ii', $proposalId, $tenantId);
                } else {
                    // Empresa ve propuestas de sus solicitudes
                    $stmt = $conn->prepare("
                        SELECT sp.*, 
                               sr.date_from, sr.date_to, sr.location, sr.qty_needed,
                               sp.tenant_agency_id, t.name as agency_name
                        FROM staffing_proposals sp
                        INNER JOIN staffing_requests sr ON sp.request_id = sr.id
                        INNER JOIN tenants t ON sp.tenant_agency_id = t.id
                        WHERE sp.id = ? AND sr.tenant_company_id = ?
                    ");
                    $stmt->bind_param('ii', $proposalId, $tenantId);
                }
                
                $stmt->execute();
                $result = $stmt->get_result();
                $proposal = $result->fetch_assoc();
                $stmt->close();
                
                if (!$proposal) {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'error' => 'proposals.not_found',
                        'error_key' => 'proposals.not_found',
                        'message' => t('proposals.not_found')
                    ]);
                    exit;
                }
                
                $proposal['proposal'] = json_decode($proposal['proposal_json'] ?? '{}', true);
                unset($proposal['proposal_json']);
                
                echo json_encode([
                    'success' => true,
                    'data' => $proposal
                ]);
            } else {
                // Listar propuestas con paginación
                $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
                $offset = ($page - 1) * $pageSize;
                
                $status = $_GET['status'] ?? null;
                $requestId = isset($_GET['request_id']) ? (int)$_GET['request_id'] : null;
                $sortBy = $_GET['sort_by'] ?? 'created_at';
                $sortDir = strtoupper($_GET['sort_dir'] ?? 'DESC');
                
                $allowedSort = ['created_at', 'updated_at', 'status'];
                if (!in_array($sortBy, $allowedSort)) {
                    $sortBy = 'created_at';
                }
                if ($sortDir !== 'ASC' && $sortDir !== 'DESC') {
                    $sortDir = 'DESC';
                }
                
                if ($orgType === 'temporera') {
                    // Temporera ve sus propuestas
                    $where = "sp.tenant_agency_id = ?";
                    $params = [$tenantId];
                    $types = 'i';
                    
                    if ($status) {
                        $where .= " AND sp.status = ?";
                        $params[] = $status;
                        $types .= 's';
                    }
                    
                    if ($requestId) {
                        $where .= " AND sp.request_id = ?";
                        $params[] = $requestId;
                        $types .= 'i';
                    }
                    
                    $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM staffing_proposals sp WHERE $where");
                    $countStmt->bind_param($types, ...$params);
                    $countStmt->execute();
                    $countResult = $countStmt->get_result();
                    $total = $countResult->fetch_assoc()['total'];
                    $countStmt->close();
                    
                    $query = "
                        SELECT sp.*, 
                               sr.date_from, sr.date_to, sr.location, sr.qty_needed,
                               sr.tenant_company_id, t.name as company_name
                        FROM staffing_proposals sp
                        INNER JOIN staffing_requests sr ON sp.request_id = sr.id
                        INNER JOIN tenants t ON sr.tenant_company_id = t.id
                        WHERE $where
                        ORDER BY sp.$sortBy $sortDir
                        LIMIT ? OFFSET ?
                    ";
                } else {
                    // Empresa ve propuestas de sus solicitudes
                    $where = "sr.tenant_company_id = ?";
                    $params = [$tenantId];
                    $types = 'i';
                    
                    if ($status) {
                        $where .= " AND sp.status = ?";
                        $params[] = $status;
                        $types .= 's';
                    }
                    
                    if ($requestId) {
                        $where .= " AND sp.request_id = ?";
                        $params[] = $requestId;
                        $types .= 'i';
                    }
                    
                    $countStmt = $conn->prepare("
                        SELECT COUNT(*) as total 
                        FROM staffing_proposals sp
                        INNER JOIN staffing_requests sr ON sp.request_id = sr.id
                        WHERE $where
                    ");
                    $countStmt->bind_param($types, ...$params);
                    $countStmt->execute();
                    $countResult = $countStmt->get_result();
                    $total = $countResult->fetch_assoc()['total'];
                    $countStmt->close();
                    
                    $query = "
                        SELECT sp.*, 
                               sr.date_from, sr.date_to, sr.location, sr.qty_needed,
                               sp.tenant_agency_id, t.name as agency_name
                        FROM staffing_proposals sp
                        INNER JOIN staffing_requests sr ON sp.request_id = sr.id
                        INNER JOIN tenants t ON sp.tenant_agency_id = t.id
                        WHERE $where
                        ORDER BY sp.$sortBy $sortDir
                        LIMIT ? OFFSET ?
                    ";
                }
                
                $params[] = $pageSize;
                $params[] = $offset;
                $types .= 'ii';
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                $proposals = [];
                while ($row = $result->fetch_assoc()) {
                    $row['proposal'] = json_decode($row['proposal_json'] ?? '{}', true);
                    unset($row['proposal_json']);
                    $proposals[] = $row;
                }
                $stmt->close();
                
                echo json_encode([
                    'success' => true,
                    'data' => $proposals,
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
            // Solo temporeras pueden crear propuestas
            if ($orgType !== 'temporera') {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'proposals.only_agencies',
                    'error_key' => 'proposals.only_agencies',
                    'message' => t('proposals.only_agencies')
                ]);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || empty($input['request_id'])) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.required',
                    'error_key' => 'validation.required',
                    'error_params' => ['field' => 'request_id'],
                    'message' => t('validation.required', ['field' => 'request_id'])
                ]);
                exit;
            }
            
            $requestId = (int)$input['request_id'];
            
            // Verificar que la solicitud existe y fue enviada a esta temporera
            $stmt = $conn->prepare("
                SELECT sr.*
                FROM staffing_requests sr
                INNER JOIN staffing_request_targets srt ON sr.id = srt.request_id
                WHERE sr.id = ? AND srt.tenant_agency_id = ? AND srt.status = 'sent'
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
                    'error' => 'proposals.request_not_found',
                    'error_key' => 'proposals.request_not_found',
                    'message' => t('proposals.request_not_found')
                ]);
                exit;
            }
            
            // Verificar que no existe ya una propuesta para esta solicitud
            $stmt = $conn->prepare("SELECT id FROM staffing_proposals WHERE request_id = ? AND tenant_agency_id = ?");
            $stmt->bind_param('ii', $requestId, $tenantId);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($existing) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'error' => 'proposals.already_exists',
                    'error_key' => 'proposals.already_exists',
                    'message' => t('proposals.already_exists')
                ]);
                exit;
            }
            
            // Si se solicita auto-matching, usar MatchingService
            $proposalData = null;
            if (!empty($input['use_auto_matching']) && $input['use_auto_matching']) {
                $matches = MatchingService::findMatches($requestId, $tenantId, $input['matching_options'] ?? []);
                
                // Construir propuesta desde matches
                $candidates = [];
                foreach ($matches as $match) {
                    if ($match['score'] > 0.3 && !$match['details']['has_conflict']) {
                        $candidates[] = [
                            'worker_id' => $match['worker_id'],
                            'worker_code' => $match['worker_code'],
                            'first_name' => $match['first_name'],
                            'last_name' => $match['last_name'],
                            'score' => $match['score'],
                            'details' => $match['details']
                        ];
                    }
                }
                
                $proposalData = [
                    'candidates' => $candidates,
                    'auto_matched' => true
                ];
            } else {
                // Propuesta manual
                if (empty($input['proposal']) || !is_array($input['proposal'])) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'error' => 'validation.required',
                        'error_key' => 'validation.required',
                        'error_params' => ['field' => 'proposal'],
                        'message' => t('validation.required', ['field' => 'proposal'])
                    ]);
                    exit;
                }
                
                $proposalData = $input['proposal'];
                $proposalData['auto_matched'] = false;
            }
            
            // Insertar propuesta
            $proposalJson = json_encode($proposalData);
            $stmt = $conn->prepare("
                INSERT INTO staffing_proposals 
                (request_id, tenant_agency_id, proposed_by_user, proposal_json, status)
                VALUES (?, ?, ?, ?, 'pending_company')
            ");
            $stmt->bind_param('iiis', $requestId, $tenantId, $userId, $proposalJson);
            
            if (!$stmt->execute()) {
                throw new Exception('Error creando propuesta: ' . $stmt->error);
            }
            
            $proposalId = $conn->insert_id;
            $stmt->close();
            
            // Actualizar status del target a 'responded'
            $stmt = $conn->prepare("
                UPDATE staffing_request_targets 
                SET status = 'responded', responded_at = NOW()
                WHERE request_id = ? AND tenant_agency_id = ?
            ");
            $stmt->bind_param('ii', $requestId, $tenantId);
            $stmt->execute();
            $stmt->close();
            
            // Obtener propuesta creada
            $stmt = $conn->prepare("
                SELECT sp.*, sr.date_from, sr.date_to, sr.location, sr.qty_needed
                FROM staffing_proposals sp
                INNER JOIN staffing_requests sr ON sp.request_id = sr.id
                WHERE sp.id = ?
            ");
            $stmt->bind_param('i', $proposalId);
            $stmt->execute();
            $result = $stmt->get_result();
            $proposal = $result->fetch_assoc();
            $stmt->close();
            
            $proposal['proposal'] = json_decode($proposal['proposal_json'] ?? '{}', true);
            unset($proposal['proposal_json']);
            
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'data' => $proposal,
                'message' => t('proposals.created_success')
            ]);
            break;
            
        case 'PUT':
            if (!$proposalId || !$action) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.action_required',
                    'error_key' => 'validation.action_required',
                    'message' => t('validation.action_required')
                ]);
                exit;
            }
            
            // Solo empresas pueden aceptar/rechazar/cambiar propuestas
            if ($orgType !== 'empresa') {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'proposals.only_companies',
                    'error_key' => 'proposals.only_companies',
                    'message' => t('proposals.only_companies')
                ]);
                exit;
            }
            
            // Verificar que la propuesta existe y pertenece a una solicitud de esta empresa
            $stmt = $conn->prepare("
                SELECT sp.*
                FROM staffing_proposals sp
                INNER JOIN staffing_requests sr ON sp.request_id = sr.id
                WHERE sp.id = ? AND sr.tenant_company_id = ?
            ");
            $stmt->bind_param('ii', $proposalId, $tenantId);
            $stmt->execute();
            $result = $stmt->get_result();
            $proposal = $result->fetch_assoc();
            $stmt->close();
            
            if (!$proposal) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'proposals.not_found',
                    'error_key' => 'proposals.not_found',
                    'message' => t('proposals.not_found')
                ]);
                exit;
            }
            
            // Solo se pueden modificar propuestas pendientes
            if ($proposal['status'] !== 'pending_company') {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'error' => 'proposals.cannot_modify',
                    'error_key' => 'proposals.cannot_modify',
                    'message' => t('proposals.cannot_modify')
                ]);
                exit;
            }
            
            $newStatus = null;
            $messageKey = '';
            
            switch ($action) {
                case 'accept':
                    $newStatus = 'accepted';
                    $messageKey = 'proposals.accepted_success';
                    break;
                    
                case 'reject':
                    $newStatus = 'rejected';
                    $messageKey = 'proposals.rejected_success';
                    break;
                    
                case 'change':
                    $newStatus = 'changed';
                    $messageKey = 'proposals.changed_success';
                    break;
                    
                default:
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => 'validation.invalid_action',
                        'error_key' => 'validation.invalid_action',
                        'message' => t('validation.invalid_action')
                    ]);
                    exit;
            }
            
            // Actualizar status
            $stmt = $conn->prepare("UPDATE staffing_proposals SET status = ? WHERE id = ?");
            $stmt->bind_param('si', $newStatus, $proposalId);
            $stmt->execute();
            $stmt->close();
            
            // Si se acepta, generar asignaciones y actualizar status de la solicitud
            if ($newStatus === 'accepted') {
                // Generar asignaciones desde la propuesta
                // Nota: La función generateAssignmentsFromProposal está en assignments.php
                // pero necesita acceso directo, así que la llamamos después de incluir el archivo
                require_once __DIR__ . '/assignments.php';
                
                // Obtener tenant_company_id de la solicitud
                $stmt = $conn->prepare("SELECT tenant_company_id FROM staffing_requests WHERE id = ?");
                $stmt->bind_param('i', $proposal['request_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $request = $result->fetch_assoc();
                $stmt->close();
                
                if ($request) {
                    $assignmentResult = generateAssignmentsFromProposal($conn, $proposalId, $request['tenant_company_id']);
                }
                
                // Verificar cuántas propuestas aceptadas tiene la solicitud
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as accepted_count
                    FROM staffing_proposals
                    WHERE request_id = ? AND status = 'accepted'
                ");
                $stmt->bind_param('i', $proposal['request_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $acceptedCount = $result->fetch_assoc()['accepted_count'];
                $stmt->close();
                
                // Obtener solicitud para verificar qty_needed
                $stmt = $conn->prepare("SELECT qty_needed, tenant_company_id FROM staffing_requests WHERE id = ?");
                $stmt->bind_param('i', $proposal['request_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $request = $result->fetch_assoc();
                $stmt->close();
                
                // Actualizar status de solicitud
                $newRequestStatus = 'filled';
                if ($acceptedCount < $request['qty_needed']) {
                    $newRequestStatus = 'partially_filled';
                }
                
                $stmt = $conn->prepare("UPDATE staffing_requests SET status = ? WHERE id = ?");
                $stmt->bind_param('si', $newRequestStatus, $proposal['request_id']);
                $stmt->execute();
                $stmt->close();
            }
            
            // Obtener propuesta actualizada
            $stmt = $conn->prepare("
                SELECT sp.*, sr.date_from, sr.date_to, sr.location, sr.qty_needed
                FROM staffing_proposals sp
                INNER JOIN staffing_requests sr ON sp.request_id = sr.id
                WHERE sp.id = ?
            ");
            $stmt->bind_param('i', $proposalId);
            $stmt->execute();
            $result = $stmt->get_result();
            $updatedProposal = $result->fetch_assoc();
            $stmt->close();
            
            $updatedProposal['proposal'] = json_decode($updatedProposal['proposal_json'] ?? '{}', true);
            unset($updatedProposal['proposal_json']);
            
            echo json_encode([
                'success' => true,
                'data' => $updatedProposal,
                'message' => t($messageKey)
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

