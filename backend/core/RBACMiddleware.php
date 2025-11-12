<?php
/**
 * RBACMiddleware
 * 
 * Verifica permisos por rol/acción según matriz
 * OBLIGATORIO para todas las rutas de API
 */

require_once __DIR__ . '/TenantMiddleware.php';
require_once __DIR__ . '/../helpers/db_helper.php';

class RBACMiddleware {
    /**
     * Matriz de permisos por rol y acción
     * 
     * Formato: 'rol' => ['accion1', 'accion2', ...]
     */
    private static $permissions = [
        'owner_admin' => [
            'tenants.*',
            'subscriptions.*',
            'invoices.*',
            'users.*',
            'users.invite',
            'users.view',
            'users.update',
            'users.delete',
            'audit.*',
            'billing.*',
            'settings.*'
        ],
        'empresa_admin' => [
            'employees.*',
            'clients.*',
            'projects.*',
            'groups.*',
            'shifts.*',
            'requests.*',
            'proposals.view',
            'proposals.accept',
            'assignments.*',
            'work_orders.*',
            'work_logs.*',
            'approvals.*',
            'disputes.*',
            'ratings.*',
            'reports.*',
            'settings.*',
            'users.invite',
            'users.view',
            'users.update',
            'users.delete',
            'agency_links.*',
            'agency_links.create',
            'agency_links.view',
            'agency_links.update',
            'agency_links.delete'
        ],
        'planificador' => [
            'shifts.*',
            'groups.*',
            'requests.*',
            'proposals.view',
            'proposals.accept',
            'work_orders.*',
            'calendar.*',
            'reports.*'
        ],
        'supervisor' => [
            'work_logs.view',
            'work_logs.approve',
            'approvals.*',
            'disputes.view',
            'disputes.resolve',
            'reports.view'
        ],
        'empleado' => [
            'shifts.view',
            'work_logs.create',
            'work_logs.view_own',
            'disputes.create',
            'disputes.view_own'
        ],
        'temporera_admin' => [
            'workers.*',
            'proposals.*',
            'requests.view',
            'assignments.*',
            'work_logs.view',
            'disputes.view',
            'disputes.resolve',
            'ratings.view',
            'reports.*',
            'settings.*',
            'users.invite',
            'users.view',
            'users.update',
            'users.delete',
            'agency_links.*',
            'agency_links.create',
            'agency_links.view',
            'agency_links.update',
            'agency_links.delete'
        ],
        'coordinador' => [
            'workers.view',
            'proposals.*',
            'requests.view',
            'assignments.view',
            'work_logs.view',
            'disputes.view',
            'ratings.view'
        ],
        'trabajador' => [
            'shifts.view_own',
            'work_logs.create_own',
            'work_logs.view_own',
            'disputes.create',
            'disputes.view_own'
        ],
        'cliente_viewer' => [
            'reports.view',
            'work_orders.view'
        ]
    ];
    
    /**
     * Verifica si el usuario tiene permiso para una acción
     * 
     * @param string $action Acción a verificar (ej: 'shifts.create', 'work_logs.*')
     * @return bool
     */
    public static function checkPermission($action) {
        $tenantId = TenantMiddleware::getCurrentTenantId();
        $userId = TenantMiddleware::getCurrentUserId();
        
        if (!$tenantId || !$userId) {
            return false;
        }
        
        // Obtener rol del usuario en el tenant
        $role = self::getUserRole($tenantId, $userId);
        
        if (!$role) {
            return false;
        }
        
        // Verificar permisos del rol
        $rolePermissions = self::$permissions[$role] ?? [];
        
        foreach ($rolePermissions as $permission) {
            // Soporte para wildcards (ej: 'shifts.*' permite 'shifts.create')
            if ($permission === '*') {
                return true; // Permiso total
            }
            
            if ($permission === $action) {
                return true; // Permiso exacto
            }
            
            // Verificar wildcard (ej: 'shifts.*' permite 'shifts.create')
            if (strpos($permission, '.*') !== false) {
                $prefix = str_replace('.*', '', $permission);
                if (strpos($action, $prefix . '.') === 0) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Obtiene el rol del usuario en el tenant
     */
    private static function getUserRole($tenantId, $userId) {
        $conn = DBHelper::getConnection();
        
        $stmt = $conn->prepare("SELECT role FROM tenant_users WHERE tenant_id = ? AND user_id = ? AND is_active = 1");
        $stmt->bind_param('ii', $tenantId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row ? $row['role'] : null;
    }
    
    /**
     * Middleware: verifica permiso y retorna error si no tiene
     * 
     * @param string $action Acción requerida
     * @return void Lanza error si no tiene permiso
     */
    public static function requirePermission($action) {
        if (!self::checkPermission($action)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'auth.permission_denied',
                'error_key' => 'auth.permission_denied',
                'error_params' => ['action' => $action],
                'message' => 'Permiso denegado para esta acción'
            ]);
            exit;
        }
    }
    
    /**
     * Verifica múltiples permisos (OR lógico)
     * 
     * @param array $actions Array de acciones
     * @return bool
     */
    public static function checkAnyPermission($actions) {
        foreach ($actions as $action) {
            if (self::checkPermission($action)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Verifica múltiples permisos (AND lógico)
     * 
     * @param array $actions Array de acciones
     * @return bool
     */
    public static function checkAllPermissions($actions) {
        foreach ($actions as $action) {
            if (!self::checkPermission($action)) {
                return false;
            }
        }
        return true;
    }
}

