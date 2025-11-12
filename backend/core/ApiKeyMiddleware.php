<?php
/**
 * ApiKeyMiddleware
 * 
 * Valida API Keys para endpoints públicos
 * Extrae tenant_id del API key y lo inyecta en contexto
 */

require_once __DIR__ . '/../helpers/db_helper.php';

class ApiKeyMiddleware {
    /**
     * Procesa el request y valida API Key
     * 
     * @return array ['tenant_id' => int, 'api_key_id' => int] o lanza excepción
     */
    public static function process() {
        // Obtener API Key de cabecera
        $headers = getallheaders();
        $apiKey = $headers['X-API-Key'] ?? $headers['x-api-key'] ?? null;
        
        if (!$apiKey) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'auth.api_key_required',
                'error_key' => 'auth.api_key_required',
                'message' => 'API Key requerida'
            ]);
            exit;
        }
        
        $conn = DBHelper::getConnection();
        
        // Hash del API key para buscar en BD
        $keyHash = hash('sha256', $apiKey);
        
        // Buscar API key
        $stmt = $conn->prepare("
            SELECT 
                id,
                tenant_id,
                name,
                scopes_json,
                last_used_at,
                expires_at,
                is_active
            FROM api_keys
            WHERE key_hash = ? AND is_active = 1
        ");
        $stmt->bind_param('s', $keyHash);
        $stmt->execute();
        $result = $stmt->get_result();
        $apiKeyData = $result->fetch_assoc();
        $stmt->close();
        
        if (!$apiKeyData) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'auth.api_key_invalid',
                'error_key' => 'auth.api_key_invalid',
                'message' => 'API Key inválida'
            ]);
            exit;
        }
        
        // Verificar expiración
        if ($apiKeyData['expires_at'] && strtotime($apiKeyData['expires_at']) < time()) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'auth.api_key_expired',
                'error_key' => 'auth.api_key_expired',
                'message' => 'API Key expirada'
            ]);
            exit;
        }
        
        // Verificar que el tenant existe y está activo
        $tenantStmt = $conn->prepare("SELECT id, is_active FROM tenants WHERE id = ?");
        $tenantStmt->bind_param('i', $apiKeyData['tenant_id']);
        $tenantStmt->execute();
        $tenantResult = $tenantStmt->get_result();
        $tenant = $tenantResult->fetch_assoc();
        $tenantStmt->close();
        
        if (!$tenant || !$tenant['is_active']) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'auth.tenant_invalid',
                'error_key' => 'auth.tenant_invalid',
                'message' => 'Tenant inválido o inactivo'
            ]);
            exit;
        }
        
        // Actualizar last_used_at
        $updateStmt = $conn->prepare("
            UPDATE api_keys 
            SET last_used_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->bind_param('i', $apiKeyData['id']);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Inyectar tenant_id en contexto global
        $GLOBALS['API_KEY_TENANT_ID'] = (int)$apiKeyData['tenant_id'];
        $GLOBALS['API_KEY_ID'] = (int)$apiKeyData['id'];
        $GLOBALS['API_KEY_SCOPES'] = !empty($apiKeyData['scopes_json']) 
            ? json_decode($apiKeyData['scopes_json'], true) 
            : [];
        
        return [
            'tenant_id' => (int)$apiKeyData['tenant_id'],
            'api_key_id' => (int)$apiKeyData['id'],
            'scopes' => $GLOBALS['API_KEY_SCOPES']
        ];
    }
    
    /**
     * Verifica si el API key tiene un scope específico
     */
    public static function hasScope($scope) {
        $scopes = $GLOBALS['API_KEY_SCOPES'] ?? [];
        return in_array($scope, $scopes) || in_array('*', $scopes);
    }
    
    /**
     * Obtiene tenant_id del contexto
     */
    public static function getTenantId() {
        return $GLOBALS['API_KEY_TENANT_ID'] ?? null;
    }
}

