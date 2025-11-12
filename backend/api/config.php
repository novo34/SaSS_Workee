<?php
/**
 * API Configuración
 * 
 * Endpoints:
 * - GET /api/config - Obtener todas las configuraciones del tenant
 * - PUT /api/config - Actualizar configuraciones del tenant
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../core/TenantMiddleware.php';
require_once __DIR__ . '/../core/RBACMiddleware.php';
require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../helpers/db_helper.php';
require_once __DIR__ . '/../helpers/i18n.php';
require_once __DIR__ . '/../helpers/env.php';

// Deshabilitar display_errors para evitar HTML en respuestas JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $GLOBALS['API_PATH'] ?? $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '';
    
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
    
    // Obtener user_id del middleware
    $userId = $middlewareResult['user_id'] ?? null;
    
    // Verificar permisos
    $action = null;
    if ($method === 'GET') {
        $action = 'settings.view';
    } elseif ($method === 'PUT') {
        $action = 'settings.update';
    }
    
    if ($action && !RBACMiddleware::checkPermission($action)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'auth.forbidden',
            'error_key' => 'auth.forbidden',
            'message' => t('auth.forbidden')
        ]);
        exit;
    }
    
    switch ($method) {
        case 'GET':
            // Obtener todas las configuraciones del tenant
            $configs = Config::getAll($tenantId);
            
            // Agregar valores por defecto para claves comunes si no existen
            $defaultConfigs = [
                'timezone' => Config::get('timezone', $tenantId, null, null, 'Europe/Madrid'),
                'locale' => Config::get('locale', $tenantId, null, null, 'es'),
                'currency' => Config::get('currency', $tenantId, null, null, 'EUR'),
                'timeclock.required' => Config::get('timeclock.required', $tenantId, null, null, true),
                'timeclock.tolerance_minutes' => Config::get('timeclock.tolerance_minutes', $tenantId, null, null, 15),
                'timeclock.min_break_minutes' => Config::get('timeclock.min_break_minutes', $tenantId, null, null, 30),
                'timeclock.rounding_minutes' => Config::get('timeclock.rounding_minutes', $tenantId, null, null, 15),
                'approval.stages' => Config::get('approval.stages', $tenantId, null, null, 1),
                'approval.require_co_approval_agency' => Config::get('approval.require_co_approval_agency', $tenantId, null, null, false),
                'approval.auto_approve_threshold_hours' => Config::get('approval.auto_approve_threshold_hours', $tenantId, null, null, 0),
                'notifications.email_enabled' => Config::get('notifications.email_enabled', $tenantId, null, null, true),
                'notifications.sms_enabled' => Config::get('notifications.sms_enabled', $tenantId, null, null, false),
                'notifications.whatsapp_enabled' => Config::get('notifications.whatsapp_enabled', $tenantId, null, null, false),
                'notifications.quiet_hours_start' => Config::get('notifications.quiet_hours_start', $tenantId, null, null, null),
                'notifications.quiet_hours_end' => Config::get('notifications.quiet_hours_end', $tenantId, null, null, null)
            ];
            
            // Combinar configuraciones existentes con defaults (las existentes tienen prioridad)
            $allConfigs = array_merge($defaultConfigs, $configs);
            
            echo json_encode([
                'success' => true,
                'data' => $allConfigs
            ]);
            break;
            
        case 'PUT':
            // Actualizar configuraciones
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            if (!$data || !is_array($data)) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.invalid_data',
                    'error_key' => 'validation.invalid_data',
                    'message' => t('validation.invalid_data')
                ]);
                exit;
            }
            
            $updated = [];
            $errors = [];
            
            foreach ($data as $key => $value) {
                try {
                    if (Config::set($key, $value, $tenantId)) {
                        $updated[] = $key;
                    } else {
                        $errors[] = $key;
                    }
                } catch (Exception $e) {
                    $errors[] = $key;
                    error_log("Error setting config $key: " . $e->getMessage());
                }
            }
            
            if (count($errors) > 0 && count($updated) === 0) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'settings.update_failed',
                    'error_key' => 'settings.update_failed',
                    'message' => t('settings.update_failed')
                ]);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'message' => t('settings.updated_success'),
                'data' => [
                    'updated' => $updated,
                    'errors' => $errors
                ]
            ]);
            break;
            
        default:
            http_response_code(405);
            header('Content-Type: application/json');
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

