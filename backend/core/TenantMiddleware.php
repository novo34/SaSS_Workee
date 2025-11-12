<?php
/**
 * TenantMiddleware
 * 
 * Extrae tenant_id de JWT/sesión/cabecera, valida e inyecta en contexto
 * OBLIGATORIO para todas las rutas de API
 */

require_once __DIR__ . '/../helpers/db_helper.php';
require_once __DIR__ . '/Auth.php';

class TenantMiddleware {
    private static $currentTenantId = null;
    private static $currentUserId = null;
    
    /**
     * Obtiene todos los headers HTTP (compatible con FastCGI/CGI)
     * 
     * @return array Headers en formato ['Header-Name' => 'value']
     */
    private static function getAllHeaders() {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }
        
        // Fallback para servidores FastCGI/CGI
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[$headerName] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'])) {
                $headerName = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower($key))));
                $headers[$headerName] = $value;
            }
        }
        
        // También verificar Authorization directamente desde $_SERVER (algunos servidores lo ponen ahí)
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        
        return $headers;
    }
    
    /**
     * Procesa el request y extrae tenant_id
     * 
     * @return array ['tenant_id' => int, 'user_id' => int|null] o lanza excepción
     */
    public static function process() {
        // Intentar obtener tenant_id de diferentes fuentes
        $tenantId = null;
        $userId = null;
        
        // 1. Desde JWT (si existe)
        $tenantId = self::getFromJWT();
        if ($tenantId !== null) {
            $userId = self::getUserIdFromJWT();
            if ($userId === null) {
                // Si no se pudo obtener user_id, intentar de nuevo
                error_log('[TenantMiddleware] No se pudo obtener user_id del JWT');
            }
            self::$currentTenantId = $tenantId;
            self::$currentUserId = $userId;
            error_log("[TenantMiddleware] Token válido - tenant_id: $tenantId, user_id: " . ($userId ?? 'null'));
            return [
                'tenant_id' => $tenantId,
                'user_id' => $userId
            ];
        } else {
            error_log('[TenantMiddleware] No se pudo obtener tenant_id del JWT');
        }
        
        // 2. Desde sesión PHP
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['tenant_id'])) {
            $tenantId = (int)$_SESSION['tenant_id'];
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        }
        
        // 3. Desde cabecera HTTP
        if ($tenantId === null) {
            $headers = self::getAllHeaders();
            if (isset($headers['X-Tenant-ID'])) {
                $tenantId = (int)$headers['X-Tenant-ID'];
            }
        }
        
        // Validar tenant_id
        if ($tenantId === null || $tenantId <= 0) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'auth.tenant_required',
                'error_key' => 'auth.tenant_required',
                'message' => 'Tenant ID requerido'
            ]);
            exit;
        }
        
        // Verificar que el tenant existe y está activo
        if (!self::validateTenant($tenantId)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'auth.tenant_invalid',
                'error_key' => 'auth.tenant_invalid',
                'message' => 'Tenant inválido o inactivo'
            ]);
            exit;
        }
        
        self::$currentTenantId = $tenantId;
        self::$currentUserId = $userId;
        
        return [
            'tenant_id' => $tenantId,
            'user_id' => $userId
        ];
    }
    
    /**
     * Obtiene tenant_id desde JWT (validando firma y expiración)
     */
    private static function getFromJWT() {
        $headers = self::getAllHeaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        
        if (!$authHeader) {
            error_log('[TenantMiddleware] No se encontró header Authorization');
            return null;
        }
        
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            error_log('[TenantMiddleware] Header Authorization no tiene formato Bearer. Header: ' . substr($authHeader, 0, 50));
            return null;
        }
        
        $token = $matches[1];
        
        if (empty($token)) {
            error_log('[TenantMiddleware] Token vacío después de extraer de Bearer');
            return null;
        }
        
        // Validar token usando Auth::validateToken() que verifica firma y expiración
        $payload = Auth::validateToken($token);
        
        if (!$payload) {
            error_log('[TenantMiddleware] Auth::validateToken() retornó null (token inválido o expirado)');
            return null;
        }
        
        if (!isset($payload['tenant_id'])) {
            error_log('[TenantMiddleware] Token válido pero no contiene tenant_id. Payload keys: ' . implode(', ', array_keys($payload)));
            return null;
        }
        
        return (int)$payload['tenant_id'];
    }
    
    /**
     * Obtiene user_id desde JWT (validando firma y expiración)
     */
    private static function getUserIdFromJWT() {
        $headers = self::getAllHeaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }
        
        $token = $matches[1];
        
        // Validar token usando Auth::validateToken() que verifica firma y expiración
        $payload = Auth::validateToken($token);
        
        if (!$payload || !isset($payload['user_id'])) {
            return null; // Token inválido o expirado
        }
        
        return (int)$payload['user_id'];
    }
    
    /**
     * Valida que el tenant existe y está activo
     */
    private static function validateTenant($tenantId) {
        $conn = DBHelper::getConnection();
        
        $stmt = $conn->prepare("SELECT id, is_active FROM tenants WHERE id = ?");
        $stmt->bind_param('i', $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $tenant = $result->fetch_assoc();
        $stmt->close();
        
        return $tenant && $tenant['is_active'] == 1;
    }
    
    /**
     * Obtiene el tenant_id actual
     */
    public static function getCurrentTenantId() {
        return self::$currentTenantId;
    }
    
    /**
     * Obtiene el user_id actual
     */
    public static function getCurrentUserId() {
        return self::$currentUserId;
    }
    
    /**
     * Establece el tenant_id (para testing o casos especiales)
     */
    public static function setCurrentTenantId($tenantId) {
        self::$currentTenantId = $tenantId;
    }
    
    /**
     * Establece el user_id (para testing o casos especiales)
     */
    public static function setCurrentUserId($userId) {
        self::$currentUserId = $userId;
    }
}

