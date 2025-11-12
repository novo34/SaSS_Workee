<?php
/**
 * Security
 * 
 * Hardening de seguridad:
 * - CSRF tokens
 * - Rate limiting
 * - Headers de seguridad
 * - Validación de entrada
 */

require_once __DIR__ . '/../helpers/db_helper.php';
require_once __DIR__ . '/../helpers/env.php';

class Security {
    /**
     * Genera un token CSRF
     * 
     * @return string Token CSRF
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }
    
    /**
     * Valida un token CSRF
     * 
     * @param string $token Token a validar
     * @param int $maxAge Segundos de validez (default: 3600 = 1 hora)
     * @return bool Válido o no
     */
    public static function validateCSRFToken($token, $maxAge = 3600) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        
        // Verificar expiración
        if (time() - $_SESSION['csrf_token_time'] > $maxAge) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }
        
        // Verificar token
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Aplica headers de seguridad
     */
    public static function applySecurityHeaders() {
        // Content Security Policy
        $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net; connect-src 'self';";
        header("Content-Security-Policy: {$csp}");
        
        // X-Frame-Options
        header("X-Frame-Options: DENY");
        
        // X-Content-Type-Options
        header("X-Content-Type-Options: nosniff");
        
        // Referrer-Policy
        header("Referrer-Policy: strict-origin-when-cross-origin");
        
        // Strict-Transport-Security (solo si es HTTPS)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
        }
        
        // Permissions-Policy
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    }
    
    /**
     * Verifica rate limiting
     * 
     * @param string $key Clave única para el rate limit (ej: "login:tenant:123" o "api:user:456")
     * @param int $maxRequests Máximo de requests permitidos
     * @param int $window Segundos de ventana (default: 60)
     * @return array ['allowed' => bool, 'remaining' => int, 'reset' => int]
     */
    public static function checkRateLimit($key, $maxRequests, $window = 60) {
        $conn = DBHelper::getConnection();
        
        // Crear tabla de rate limits si no existe
        self::ensureRateLimitTable($conn);
        
        $now = time();
        $windowStart = $now - $window;
        
        // Limpiar entradas expiradas
        $cleanupStmt = $conn->prepare("
            DELETE FROM rate_limits
            WHERE window_start < ?
        ");
        $cleanupStmt->bind_param('i', $windowStart);
        $cleanupStmt->execute();
        $cleanupStmt->close();
        
        // Obtener o crear entrada
        $stmt = $conn->prepare("
            SELECT request_count, window_start
            FROM rate_limits
            WHERE rate_key = ? AND window_start >= ?
        ");
        $stmt->bind_param('si', $key, $windowStart);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row) {
            // Entrada existente
            $requestCount = $row['request_count'] + 1;
            $windowStart = $row['window_start'];
            
            // Actualizar contador
            $updateStmt = $conn->prepare("
                UPDATE rate_limits
                SET request_count = ?, last_request_at = ?
                WHERE rate_key = ? AND window_start = ?
            ");
            $updateStmt->bind_param('iisi', $requestCount, $now, $key, $windowStart);
            $updateStmt->execute();
            $updateStmt->close();
            
            $allowed = $requestCount <= $maxRequests;
            $remaining = max(0, $maxRequests - $requestCount);
            $reset = $windowStart + $window;
        } else {
            // Nueva entrada
            $insertStmt = $conn->prepare("
                INSERT INTO rate_limits (rate_key, request_count, window_start, last_request_at)
                VALUES (?, 1, ?, ?)
            ");
            $insertStmt->bind_param('sii', $key, $now, $now);
            $insertStmt->execute();
            $insertStmt->close();
            
            $allowed = true;
            $remaining = $maxRequests - 1;
            $reset = $now + $window;
        }
        
        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'reset' => $reset
        ];
    }
    
    /**
     * Aplica rate limit y retorna respuesta HTTP si se excede
     * 
     * @param string $key Clave única
     * @param int $maxRequests Máximo de requests
     * @param int $window Segundos de ventana
     * @return array|null null si está permitido, array con respuesta HTTP si se excede
     */
    public static function enforceRateLimit($key, $maxRequests, $window = 60) {
        $result = self::checkRateLimit($key, $maxRequests, $window);
        
        if (!$result['allowed']) {
            $retryAfter = max(1, $result['reset'] - time());
            
            http_response_code(429);
            header("X-RateLimit-Limit: {$maxRequests}");
            header("X-RateLimit-Remaining: 0");
            header("X-RateLimit-Reset: {$result['reset']}");
            header("Retry-After: {$retryAfter}");
            
            return [
                'success' => false,
                'error' => 'rate_limit_exceeded',
                'error_key' => 'rate_limit_exceeded',
                'message' => 'Límite de requests excedido. Intenta de nuevo más tarde.',
                'retry_after' => $retryAfter
            ];
        }
        
        // Agregar headers de rate limit
        header("X-RateLimit-Limit: {$maxRequests}");
        header("X-RateLimit-Remaining: {$result['remaining']}");
        header("X-RateLimit-Reset: {$result['reset']}");
        
        return null;
    }
    
    /**
     * Asegura que la tabla rate_limits existe
     */
    private static function ensureRateLimitTable($conn) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS rate_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                rate_key VARCHAR(255) NOT NULL,
                request_count INT NOT NULL DEFAULT 1,
                window_start INT NOT NULL,
                last_request_at INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_key_window (rate_key, window_start),
                INDEX idx_cleanup (window_start)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci
        ");
    }
    
    /**
     * Sanitiza entrada de usuario
     * 
     * @param mixed $input Entrada a sanitizar
     * @param string $type Tipo de sanitización (string, int, float, email, url)
     * @return mixed Entrada sanitizada
     */
    public static function sanitize($input, $type = 'string') {
        switch ($type) {
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
                
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
                
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
                
            case 'string':
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Valida entrada según reglas
     * 
     * @param mixed $value Valor a validar
     * @param string $type Tipo de validación
     * @param array $options Opciones adicionales (min, max, pattern, etc.)
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validate($value, $type, $options = []) {
        switch ($type) {
            case 'required':
                return [
                    'valid' => !empty($value) || $value === 0 || $value === '0',
                    'error' => empty($value) && $value !== 0 && $value !== '0' ? 'Campo requerido' : null
                ];
                
            case 'email':
                $valid = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                return [
                    'valid' => $valid,
                    'error' => $valid ? null : 'Email inválido'
                ];
                
            case 'url':
                $valid = filter_var($value, FILTER_VALIDATE_URL) !== false;
                return [
                    'valid' => $valid,
                    'error' => $valid ? null : 'URL inválida'
                ];
                
            case 'int':
                $valid = filter_var($value, FILTER_VALIDATE_INT) !== false;
                if ($valid && isset($options['min']) && $value < $options['min']) {
                    return ['valid' => false, 'error' => "Valor mínimo: {$options['min']}"];
                }
                if ($valid && isset($options['max']) && $value > $options['max']) {
                    return ['valid' => false, 'error' => "Valor máximo: {$options['max']}"];
                }
                return [
                    'valid' => $valid,
                    'error' => $valid ? null : 'Entero inválido'
                ];
                
            case 'string':
                $length = strlen($value);
                if (isset($options['min']) && $length < $options['min']) {
                    return ['valid' => false, 'error' => "Longitud mínima: {$options['min']}"];
                }
                if (isset($options['max']) && $length > $options['max']) {
                    return ['valid' => false, 'error' => "Longitud máxima: {$options['max']}"];
                }
                if (isset($options['pattern']) && !preg_match($options['pattern'], $value)) {
                    return ['valid' => false, 'error' => 'Formato inválido'];
                }
                return ['valid' => true, 'error' => null];
                
            default:
                return ['valid' => true, 'error' => null];
        }
    }
}

