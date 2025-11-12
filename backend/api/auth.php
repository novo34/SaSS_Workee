<?php
/**
 * API de Autenticación
 * 
 * Endpoints:
 * - POST /api/auth/login - Login
 * - POST /api/auth/refresh - Refresh token
 * - POST /api/auth/logout - Logout
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../helpers/i18n.php';
require_once __DIR__ . '/../helpers/env.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '';
    
    // Detectar acción
    $action = null;
    if (preg_match('#/auth/(login|refresh|logout)#', $path, $matches)) {
        $action = $matches[1];
    }
    
    switch ($method) {
        case 'POST':
            if ($action === 'login') {
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!$input || empty($input['email']) || empty($input['password'])) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'error' => 'validation.required',
                        'error_key' => 'validation.required',
                        'error_params' => ['field' => 'email, password'],
                        'message' => t('validation.required', ['field' => 'email, password'])
                    ]);
                    exit;
                }
                
                $result = Auth::login($input['email'], $input['password']);
                
                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                } else {
                    http_response_code(401);
                    echo json_encode([
                        'success' => false,
                        'error' => 'auth.invalid_credentials',
                        'error_key' => 'auth.invalid_credentials',
                        'message' => 'Credenciales inválidas'
                    ]);
                }
                break;
            }
            
            if ($action === 'refresh') {
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (empty($input['refresh_token'])) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'error' => 'validation.required',
                        'error_key' => 'validation.required',
                        'error_params' => ['field' => 'refresh_token'],
                        'message' => t('validation.required', ['field' => 'refresh_token'])
                    ]);
                    exit;
                }
                
                // Intentar obtener tenant_id del access token actual si está disponible
                // Esto ayuda a mantener consistencia si el usuario tiene múltiples tenants
                $currentTenantId = null;
                $headers = getallheaders();
                $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
                if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                    $accessToken = $matches[1];
                    $payload = Auth::validateToken($accessToken);
                    if ($payload && isset($payload['tenant_id'])) {
                        $currentTenantId = (int)$payload['tenant_id'];
                        error_log("[Auth] Refresh: Usando tenant_id del access token actual: $currentTenantId");
                    }
                }
                
                $result = Auth::refresh($input['refresh_token'], $currentTenantId);
                
                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                } else {
                    http_response_code(401);
                    echo json_encode([
                        'success' => false,
                        'error' => 'auth.invalid_refresh_token',
                        'error_key' => 'auth.invalid_refresh_token',
                        'message' => 'Token de refresh inválido'
                    ]);
                }
                break;
            }
            
            if ($action === 'logout') {
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (empty($input['refresh_token'])) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'error' => 'validation.required',
                        'error_key' => 'validation.required',
                        'error_params' => ['field' => 'refresh_token'],
                        'message' => t('validation.required', ['field' => 'refresh_token'])
                    ]);
                    exit;
                }
                
                $result = Auth::logout($input['refresh_token']);
                
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Sesión cerrada correctamente' : 'Error al cerrar sesión'
                ]);
                break;
            }
            
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'errors.not_found',
                'error_key' => 'errors.not_found',
                'message' => t('errors.not_found')
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

