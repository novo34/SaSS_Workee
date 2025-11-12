<?php
/**
 * API Gestión de API Keys
 * 
 * Endpoints:
 * - GET /api/api-keys - Listar API keys del tenant
 * - POST /api/api-keys - Crear nueva API key
 * - DELETE /api/api-keys/{id} - Revocar API key
 * 
 * Solo Owner-Admin y Tenant-Admin pueden gestionar API keys
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
    $path = $GLOBALS['API_PATH'] ?? $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '';
    
    // Procesar middleware
    $middlewareResult = TenantMiddleware::process();
    $tenantId = $middlewareResult['tenant_id'] ?? null;
    $GLOBALS['USER_ID'] = $middlewareResult['user_id'] ?? null;
    
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
    
    // Verificar permisos (solo admin puede gestionar API keys)
    if (!RBACMiddleware::checkPermission('settings.*') && !RBACMiddleware::checkPermission('tenants.*')) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'auth.forbidden',
            'error_key' => 'auth.forbidden',
            'message' => t('auth.forbidden')
        ]);
        exit;
    }
    
    $conn = DBHelper::getConnection();
    
    // Parsear path
    $pathParts = explode('/', trim($path, '/'));
    $apiKeyId = null;
    
    if (count($pathParts) >= 2 && is_numeric($pathParts[1])) {
        $apiKeyId = (int)$pathParts[1];
    }
    
    // Routing
    switch ($method) {
        case 'GET':
            listApiKeys($conn, $tenantId);
            break;
            
        case 'POST':
            createApiKey($conn, $tenantId);
            break;
            
        case 'DELETE':
            if ($apiKeyId) {
                revokeApiKey($conn, $tenantId, $apiKeyId);
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
 * Listar API keys del tenant
 */
function listApiKeys($conn, $tenantId) {
    $stmt = $conn->prepare("
        SELECT 
            id,
            name,
            scopes_json,
            last_used_at,
            expires_at,
            is_active,
            created_at
        FROM api_keys
        WHERE tenant_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $apiKeys = [];
    while ($row = $result->fetch_assoc()) {
        $scopes = !empty($row['scopes_json']) ? json_decode($row['scopes_json'], true) : [];
        
        $apiKeys[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'scopes' => $scopes,
            'last_used_at' => $row['last_used_at'],
            'expires_at' => $row['expires_at'],
            'is_active' => (bool)$row['is_active'],
            'created_at' => $row['created_at']
        ];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $apiKeys
    ]);
}

/**
 * Crear nueva API key
 */
function createApiKey($conn, $tenantId) {
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
    $scopes = isset($input['scopes']) && is_array($input['scopes']) ? $input['scopes'] : ['*'];
    $expiresAt = isset($input['expires_at']) ? $input['expires_at'] : null;
    
    // Generar API key (formato: wk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx)
    $apiKey = 'wk_' . bin2hex(random_bytes(32));
    $keyHash = hash('sha256', $apiKey);
    
    // Insertar en BD
    $stmt = $conn->prepare("
        INSERT INTO api_keys (tenant_id, key_hash, name, scopes_json, expires_at)
        VALUES (?, ?, ?, ?, ?)
    ");
    $scopesJson = json_encode($scopes);
    $stmt->bind_param('issss', $tenantId, $keyHash, $name, $scopesJson, $expiresAt);
    
    if ($stmt->execute()) {
        $apiKeyId = $conn->insert_id;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $apiKeyId,
                'api_key' => $apiKey, // Solo se muestra una vez al crear
                'name' => $name,
                'scopes' => $scopes,
                'expires_at' => $expiresAt,
                'created_at' => date('Y-m-d H:i:s')
            ],
            'message' => t('api_keys.created_success')
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'api_keys.create_failed',
            'error_key' => 'api_keys.create_failed',
            'message' => t('api_keys.create_failed')
        ]);
    }
    $stmt->close();
}

/**
 * Revocar API key
 */
function revokeApiKey($conn, $tenantId, $apiKeyId) {
    // Verificar que existe y pertenece al tenant
    $stmt = $conn->prepare("
        SELECT id FROM api_keys 
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->bind_param('ii', $apiKeyId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $apiKey = $result->fetch_assoc();
    $stmt->close();
    
    if (!$apiKey) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'api_keys.not_found',
            'error_key' => 'api_keys.not_found',
            'message' => t('api_keys.not_found')
        ]);
        return;
    }
    
    // Desactivar API key
    $updateStmt = $conn->prepare("
        UPDATE api_keys 
        SET is_active = 0
        WHERE id = ?
    ");
    $updateStmt->bind_param('i', $apiKeyId);
    $updateStmt->execute();
    $updateStmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => t('api_keys.revoked_success')
    ]);
}

