<?php

/**
 * Sistema de Autenticación JWT
 * 
 * Login/logout, JWT + refresh tokens, rate limiting
 */

require_once __DIR__ . '/../helpers/env.php';
require_once __DIR__ . '/../helpers/db_helper.php';

class Auth
{
    private static $jwtSecret = null;
    private static $jwtExpiration = 900; // 15 minutos
    private static $refreshExpiration = 604800; // 7 días

    /**
     * Obtiene el secreto JWT desde .env
     */
    private static function getSecret()
    {
        if (self::$jwtSecret === null) {
            self::$jwtSecret = Env::get('JWT_SECRET', 'change-me-in-production');

            // En producción, el secreto debe ser fuerte
            if (Env::isProduction() && self::$jwtSecret === 'change-me-in-production') {
                throw new Exception('JWT_SECRET debe configurarse en producción');
            }
        }
        return self::$jwtSecret;
    }

    /**
     * Genera un JWT token
     * 
     * @param int $userId ID del usuario
     * @param int $tenantId ID del tenant
     * @param array $additionalClaims Claims adicionales
     * @return string Token JWT
     */
    public static function generateToken($userId, $tenantId, $additionalClaims = [])
    {
        $secret = self::getSecret();
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];

        $payload = [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'iat' => time(),
            'exp' => time() + self::$jwtExpiration
        ];

        $payload = array_merge($payload, $additionalClaims);

        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($header)));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));

        $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, $secret, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64Header . '.' . $base64Payload . '.' . $base64Signature;
    }

    /**
     * Valida y decodifica un JWT token
     * 
     * @param string $token Token JWT
     * @return array|null Payload o null si inválido
     */
    public static function validateToken($token)
    {
        if (empty($token)) {
            error_log('[Auth] validateToken: Token vacío');
            return null;
        }
        
        $secret = self::getSecret();
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            error_log('[Auth] validateToken: Token no tiene 3 partes');
            return null;
        }

        list($base64Header, $base64Payload, $base64Signature) = $parts;

        // Verificar firma
        $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, $secret, true);
        $expectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        if ($base64Signature !== $expectedSignature) {
            error_log('[Auth] validateToken: Firma inválida');
            return null; // Firma inválida
        }

        // Decodificar payload (agregar padding si es necesario para base64_decode)
        $base64PayloadDecoded = str_replace(['-', '_'], ['+', '/'], $base64Payload);
        // Agregar padding si es necesario (base64_decode requiere padding)
        $padding = strlen($base64PayloadDecoded) % 4;
        if ($padding > 0) {
            $base64PayloadDecoded .= str_repeat('=', 4 - $padding);
        }
        $decodedPayload = base64_decode($base64PayloadDecoded, true);
        
        if ($decodedPayload === false) {
            error_log('[Auth] validateToken: Error al decodificar base64 del payload');
            return null;
        }
        
        $payload = json_decode($decodedPayload, true);

        if (!$payload) {
            error_log('[Auth] validateToken: No se pudo decodificar JSON del payload. Payload decodificado: ' . substr($decodedPayload, 0, 100));
            return null;
        }

        // Verificar expiración
        if (isset($payload['exp'])) {
            $now = time();
            $exp = $payload['exp'];
            $timeUntilExpiry = $exp - $now;
            
            if ($exp < $now) {
                error_log("[Auth] validateToken: Token expirado. Exp: $exp, Now: $now, Diff: $timeUntilExpiry segundos");
                return null; // Token expirado
            }
            
            // Log si está próximo a expirar (menos de 2 minutos)
            if ($timeUntilExpiry < 120) {
                error_log("[Auth] validateToken: Token próximo a expirar. Quedan $timeUntilExpiry segundos");
            }
        }

        return $payload;
    }

    /**
     * Genera un refresh token y lo guarda en BD
     * 
     * @param int $userId ID del usuario
     * @return string Token de refresh
     */
    public static function generateRefreshToken($userId)
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $conn = DBHelper::getConnection();
        $expiresAt = date('Y-m-d H:i:s', time() + self::$refreshExpiration);

        $stmt = $conn->prepare("INSERT INTO refresh_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $userId, $tokenHash, $expiresAt);
        $stmt->execute();
        $stmt->close();

        return $token;
    }

    /**
     * Valida un refresh token
     * 
     * @param string $token Token de refresh
     * @return array|null ['user_id' => int] o null si inválido
     */
    public static function validateRefreshToken($token)
    {
        $tokenHash = hash('sha256', $token);

        $conn = DBHelper::getConnection();
        $stmt = $conn->prepare("SELECT user_id FROM refresh_tokens WHERE token_hash = ? AND expires_at > NOW() AND revoked_at IS NULL");
        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return null;
        }

        return ['user_id' => (int)$row['user_id']];
    }

    /**
     * Revoca un refresh token
     * 
     * @param string $token Token de refresh
     * @return bool Éxito
     */
    public static function revokeRefreshToken($token)
    {
        $tokenHash = hash('sha256', $token);

        $conn = DBHelper::getConnection();
        $stmt = $conn->prepare("UPDATE refresh_tokens SET revoked_at = NOW() WHERE token_hash = ?");
        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected > 0;
    }

    /**
     * Login: autentica usuario y retorna tokens
     * 
     * @param string $email Email del usuario
     * @param string $password Contraseña
     * @return array|null ['access_token', 'refresh_token', 'user', 'tenant'] o null si falla
     */
    public static function login($email, $password)
    {
        // Rate limiting básico (mejorar con Redis/Memcached en producción)
        self::checkRateLimit($email);

        $conn = DBHelper::getConnection();

        // Buscar usuario
        $stmt = $conn->prepare("SELECT id, email, password_hash, first_name, last_name FROM users WHERE email = ? AND is_active = 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            return null; // Usuario no encontrado
        }

        // Verificar contraseña
        if (!password_verify($password, $user['password_hash'])) {
            return null; // Contraseña incorrecta
        }

        // Obtener tenant del usuario (primer tenant activo)
        $stmt = $conn->prepare("SELECT tenant_id, role FROM tenant_users WHERE user_id = ? AND is_active = 1 LIMIT 1");
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $tenantUser = $result->fetch_assoc();
        $stmt->close();

        if (!$tenantUser) {
            return null; // Usuario sin tenant
        }

        $tenantId = (int)$tenantUser['tenant_id'];
        $userId = (int)$user['id'];

        // Generar tokens
        $accessToken = self::generateToken($userId, $tenantId, [
            'role' => $tenantUser['role']
        ]);
        $refreshToken = self::generateRefreshToken($userId);

        // Actualizar último login
        $stmt = $conn->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => self::$jwtExpiration,
            'user' => [
                'id' => $userId,
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name']
            ],
            'tenant_id' => $tenantId,
            'role' => $tenantUser['role']
        ];
    }

    /**
     * Rate limiting básico (5 intentos por minuto por IP/email)
     */
    private static function checkRateLimit($email)
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'login_attempts_' . md5($ip . $email);

        // En producción usar Redis/Memcached
        // Por ahora, solo registro básico
        $file = sys_get_temp_dir() . '/' . $key . '.tmp';

        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            $attempts = $data['attempts'] ?? 0;
            $firstAttempt = $data['first_attempt'] ?? time();

            if (time() - $firstAttempt < 60) {
                if ($attempts >= 5) {
                    http_response_code(429);
                    echo json_encode([
                        'success' => false,
                        'error' => 'auth.rate_limit_exceeded',
                        'error_key' => 'auth.rate_limit_exceeded',
                        'message' => 'Demasiados intentos. Intente más tarde.'
                    ]);
                    exit;
                }
                $attempts++;
            } else {
                $attempts = 1;
                $firstAttempt = time();
            }
        } else {
            $attempts = 1;
            $firstAttempt = time();
        }

        file_put_contents($file, json_encode([
            'attempts' => $attempts,
            'first_attempt' => $firstAttempt
        ]));
    }

    /**
     * Logout: revoca refresh token
     * 
     * @param string $refreshToken Token de refresh
     * @return bool Éxito
     */
    public static function logout($refreshToken)
    {
        return self::revokeRefreshToken($refreshToken);
    }

    /**
     * Refresh: genera nuevo access token desde refresh token
     * 
     * IMPORTANTE: Usa el tenant_id del access token actual (si está disponible) para mantener consistencia.
     * Si no está disponible, usa el primer tenant activo del usuario.
     * 
     * @param string $refreshToken Token de refresh
     * @param int|null $currentTenantId Tenant ID actual del access token (opcional, para mantener consistencia)
     * @return array|null ['access_token', 'refresh_token', 'tenant_id'] o null si inválido
     */
    public static function refresh($refreshToken, $currentTenantId = null)
    {
        $data = self::validateRefreshToken($refreshToken);

        if (!$data) {
            return null;
        }

        $userId = $data['user_id'];

        // Obtener tenant del usuario
        // IMPORTANTE: Si se proporciona currentTenantId, verificar que el usuario pertenece a ese tenant
        // Esto previene que el refresh cambie el tenant si el usuario tiene múltiples tenants activos
        $conn = DBHelper::getConnection();
        
        if ($currentTenantId !== null) {
            // Verificar que el usuario pertenece al tenant actual
            $stmt = $conn->prepare("SELECT tenant_id, role FROM tenant_users WHERE user_id = ? AND tenant_id = ? AND is_active = 1 LIMIT 1");
            $stmt->bind_param('ii', $userId, $currentTenantId);
        } else {
            // Si no se proporciona tenant_id, usar el primer tenant activo (comportamiento anterior)
            $stmt = $conn->prepare("SELECT tenant_id, role FROM tenant_users WHERE user_id = ? AND is_active = 1 ORDER BY id ASC LIMIT 1");
            $stmt->bind_param('i', $userId);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $tenantUser = $result->fetch_assoc();
        $stmt->close();

        if (!$tenantUser) {
            // Si se proporcionó currentTenantId pero no se encontró, el usuario ya no pertenece a ese tenant
            // En este caso, intentar con cualquier tenant activo
            if ($currentTenantId !== null) {
                error_log("[Auth] Usuario $userId ya no pertenece al tenant $currentTenantId, buscando otro tenant activo...");
                $stmt = $conn->prepare("SELECT tenant_id, role FROM tenant_users WHERE user_id = ? AND is_active = 1 ORDER BY id ASC LIMIT 1");
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $tenantUser = $result->fetch_assoc();
                $stmt->close();
                
                if (!$tenantUser) {
                    return null;
                }
            } else {
                return null;
            }
        }

        $tenantId = (int)$tenantUser['tenant_id'];

        // Generar nuevos tokens
        $accessToken = self::generateToken($userId, $tenantId, [
            'role' => $tenantUser['role']
        ]);
        $newRefreshToken = self::generateRefreshToken($userId);

        // Revocar el refresh token anterior
        self::revokeRefreshToken($refreshToken);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => self::$jwtExpiration,
            'tenant_id' => $tenantId, // IMPORTANTE: Incluir tenant_id para que el frontend no lo pierda
            'user_id' => $userId // Incluir user_id también para consistencia
        ];
    }
}
