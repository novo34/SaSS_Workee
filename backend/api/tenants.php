<?php
/**
 * API Onboarding Tenants
 * 
 * Endpoint: POST /api/tenants
 * Solo Owner-Admin puede crear tenants
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../core/TenantMiddleware.php';
require_once __DIR__ . '/../core/RBACMiddleware.php';
require_once __DIR__ . '/../helpers/db_helper.php';
require_once __DIR__ . '/../helpers/i18n.php';
require_once __DIR__ . '/../helpers/env.php';

try {
    // Para crear tenants, no necesitamos tenant_id (es operación global de Owner)
    // Pero sí necesitamos autenticación y verificar que es owner_admin
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'POST':
            // Crear nuevo tenant (solo Owner-Admin)
            // Nota: En producción, esto debería tener autenticación especial para Owner
            
            // Obtener datos del body
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
            $required = ['name', 'org_type', 'plan'];
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
                    'error_params' => ['fields' => implode(', ', $missing)],
                    'message' => t('validation.required', ['field' => implode(', ', $missing)])
                ]);
                exit;
            }
            
            // Validar org_type
            if (!in_array($input['org_type'], ['empresa', 'temporera'])) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.invalid_value',
                    'error_key' => 'validation.invalid_value',
                    'error_params' => ['field' => 'org_type', 'values' => 'empresa, temporera'],
                    'message' => t('validation.invalid_value', ['field' => 'org_type'])
                ]);
                exit;
            }
            
            // Validar plan
            if (!in_array($input['plan'], ['basic', 'pro', 'enterprise'])) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.invalid_value',
                    'error_key' => 'validation.invalid_value',
                    'error_params' => ['field' => 'plan', 'values' => 'basic, pro, enterprise'],
                    'message' => t('validation.invalid_value', ['field' => 'plan'])
                ]);
                exit;
            }
            
            $conn = DBHelper::getConnection();
            
            // Generar slug único
            $slug = generateSlug($input['name']);
            $slugBase = $slug;
            $counter = 1;
            
            // Verificar que el slug sea único
            $stmt = $conn->prepare("SELECT id FROM tenants WHERE slug = ?");
            while (true) {
                $stmt->bind_param('s', $slug);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 0) {
                    break; // Slug único encontrado
                }
                $slug = $slugBase . '-' . $counter;
                $counter++;
            }
            $stmt->close();
            
            // Insertar tenant
            $stmt = $conn->prepare("INSERT INTO tenants (name, slug, org_type, plan, currency, timezone, locale, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $currency = $input['currency'] ?? 'CHF';
            $timezone = $input['timezone'] ?? 'Europe/Zurich';
            $locale = $input['locale'] ?? 'es';
            $isActive = isset($input['is_active']) ? (int)$input['is_active'] : 1;
            
            $stmt->bind_param('sssssssi', 
                $input['name'],
                $slug,
                $input['org_type'],
                $input['plan'],
                $currency,
                $timezone,
                $locale,
                $isActive
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Error creando tenant: ' . $stmt->error);
            }
            
            $tenantId = $conn->insert_id;
            $stmt->close();
            
            // Crear suscripción por defecto
            $stmt = $conn->prepare("INSERT INTO subscriptions (tenant_id, billing_cycle_anchor, fixed_price, variable_price_per_employee, measure) VALUES (?, CURDATE(), 0, 0, 'monthly_avg')");
            $stmt->bind_param('i', $tenantId);
            $stmt->execute();
            $stmt->close();
            
            // Obtener tenant creado
            $stmt = $conn->prepare("SELECT * FROM tenants WHERE id = ?");
            $stmt->bind_param('i', $tenantId);
            $stmt->execute();
            $result = $stmt->get_result();
            $tenant = $result->fetch_assoc();
            $stmt->close();
            
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'data' => $tenant,
                'message' => t('tenants.create_success')
            ]);
            break;
            
        case 'GET':
            // Obtener tenant específico o listar todos
            $apiPath = $GLOBALS['API_PATH'] ?? '';
            $pathParts = explode('/', $apiPath);
            
            // Si hay un ID en el path (ej: tenants/123)
            if (count($pathParts) > 1 && is_numeric($pathParts[1])) {
                $tenantId = (int)$pathParts[1];
                
                // Obtener tenant específico
                $conn = DBHelper::getConnection();
                $stmt = $conn->prepare("SELECT * FROM tenants WHERE id = ?");
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
                
                echo json_encode([
                    'success' => true,
                    'data' => $tenant
                ]);
                break;
            }
            
            // Listar tenants (solo Owner-Admin)
            // Nota: En producción, esto debería tener autenticación especial
            
            $conn = DBHelper::getConnection();
            
            // Paginación
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
            $offset = ($page - 1) * $pageSize;
            
            // Filtros
            $where = [];
            $params = [];
            $types = '';
            
            if (!empty($_GET['org_type'])) {
                $where[] = "org_type = ?";
                $params[] = $_GET['org_type'];
                $types .= 's';
            }
            
            if (!empty($_GET['plan'])) {
                $where[] = "plan = ?";
                $params[] = $_GET['plan'];
                $types .= 's';
            }
            
            if (isset($_GET['is_active'])) {
                $where[] = "is_active = ?";
                $params[] = (int)$_GET['is_active'];
                $types .= 'i';
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            // Contar total
            $countSql = "SELECT COUNT(*) as total FROM tenants {$whereClause}";
            $stmt = $conn->prepare($countSql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $countResult = $stmt->get_result();
            $total = $countResult->fetch_assoc()['total'];
            $stmt->close();
            
            // Obtener datos
            $sql = "SELECT * FROM tenants {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $types .= 'ii';
                $params[] = $pageSize;
                $params[] = $offset;
                $stmt->bind_param($types, ...$params);
            } else {
                $stmt->bind_param('ii', $pageSize, $offset);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $tenants = [];
            while ($row = $result->fetch_assoc()) {
                $tenants[] = $row;
            }
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'data' => $tenants,
                'page' => $page,
                'page_size' => $pageSize,
                'total' => (int)$total,
                'total_pages' => (int)ceil($total / $pageSize)
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

/**
 * Genera un slug a partir de un nombre
 */
function generateSlug($name) {
    // Convertir a minúsculas
    $slug = strtolower($name);
    
    // Reemplazar espacios y caracteres especiales
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    
    // Eliminar guiones al inicio y final
    $slug = trim($slug, '-');
    
    // Limitar longitud
    $slug = substr($slug, 0, 150);
    
    return $slug;
}

