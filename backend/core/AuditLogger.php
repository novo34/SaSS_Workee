<?php
/**
 * AuditLogger
 * 
 * Sistema de auditoría avanzada para registrar operaciones sensibles
 * 
 * Registra:
 * - Quién (user_id, tenant_id)
 * - Cuándo (timestamp)
 * - Qué (tabla/acción, resource_type, resource_id)
 * - Diffs de cambios (changes_json)
 * - IP y User-Agent para trazabilidad
 * 
 * Operaciones sensibles:
 * - Billing (facturas, suscripciones, pagos)
 * - Sharing (compartir datos entre tenants)
 * - Cambios de permisos (RBAC)
 * - Eliminaciones (soft/hard delete)
 */

require_once __DIR__ . '/../helpers/db_helper.php';

class AuditLogger {
    /**
     * Acciones comunes
     */
    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';
    const ACTION_APPROVE = 'approve';
    const ACTION_REJECT = 'reject';
    const ACTION_SHARE = 'share';
    const ACTION_UNSHARE = 'unshare';
    const ACTION_PERMISSION_CHANGE = 'permission_change';
    const ACTION_LOGIN = 'login';
    const ACTION_LOGOUT = 'logout';
    const ACTION_EXPORT = 'export';
    const ACTION_IMPORT = 'import';
    
    /**
     * Tipos de recursos sensibles
     */
    const RESOURCE_BILLING = 'billing';
    const RESOURCE_INVOICE = 'invoice';
    const RESOURCE_SUBSCRIPTION = 'subscription';
    const RESOURCE_USER = 'user';
    const RESOURCE_PERMISSION = 'permission';
    const RESOURCE_SHARING = 'sharing';
    const RESOURCE_TENANT = 'tenant';
    const RESOURCE_API_KEY = 'api_key';
    const RESOURCE_WEBHOOK = 'webhook';
    
    /**
     * Registra una operación de auditoría
     * 
     * @param string $action Acción realizada (create, update, delete, etc.)
     * @param string $resourceType Tipo de recurso (invoice, user, etc.)
     * @param int|null $resourceId ID del recurso afectado
     * @param array|null $changes Cambios realizados (antes/después)
     * @param int|null $tenantId ID del tenant (si no se proporciona, se obtiene del contexto)
     * @param int|null $userId ID del usuario (si no se proporciona, se obtiene del contexto)
     * @return bool Éxito de la operación
     */
    public static function log($action, $resourceType, $resourceId = null, $changes = null, $tenantId = null, $userId = null) {
        $conn = DBHelper::getConnection();
        
        // Obtener tenant_id y user_id del contexto si no se proporcionan
        if ($tenantId === null) {
            $tenantId = $GLOBALS['TENANT_ID'] ?? null;
        }
        
        if ($userId === null) {
            $userId = $GLOBALS['USER_ID'] ?? null;
        }
        
        // Obtener IP y User-Agent
        $ipAddress = self::getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Limitar longitud de user_agent
        if ($userAgent && strlen($userAgent) > 255) {
            $userAgent = substr($userAgent, 0, 252) . '...';
        }
        
        // Convertir changes a JSON
        $changesJson = null;
        if ($changes !== null) {
            $changesJson = json_encode($changes, JSON_UNESCAPED_UNICODE);
        }
        
        // Insertar log
        $stmt = $conn->prepare("
            INSERT INTO audit_logs (
                tenant_id,
                user_id,
                action,
                resource_type,
                resource_id,
                changes_json,
                ip_address,
                user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            'iissssss',
            $tenantId,
            $userId,
            $action,
            $resourceType,
            $resourceId,
            $changesJson,
            $ipAddress,
            $userAgent
        );
        
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }
    
    /**
     * Registra una creación
     */
    public static function logCreate($resourceType, $resourceId, $data = null, $tenantId = null, $userId = null) {
        $changes = $data ? ['created' => $data] : null;
        return self::log(self::ACTION_CREATE, $resourceType, $resourceId, $changes, $tenantId, $userId);
    }
    
    /**
     * Registra una actualización con diff
     */
    public static function logUpdate($resourceType, $resourceId, $before, $after, $tenantId = null, $userId = null) {
        $changes = [
            'before' => $before,
            'after' => $after,
            'diff' => self::calculateDiff($before, $after)
        ];
        return self::log(self::ACTION_UPDATE, $resourceType, $resourceId, $changes, $tenantId, $userId);
    }
    
    /**
     * Registra una eliminación
     */
    public static function logDelete($resourceType, $resourceId, $data = null, $tenantId = null, $userId = null) {
        $changes = $data ? ['deleted' => $data] : null;
        return self::log(self::ACTION_DELETE, $resourceType, $resourceId, $changes, $tenantId, $userId);
    }
    
    /**
     * Registra un cambio de permisos
     */
    public static function logPermissionChange($userId, $before, $after, $tenantId = null, $actorUserId = null) {
        $changes = [
            'user_id' => $userId,
            'before' => $before,
            'after' => $after
        ];
        return self::log(self::ACTION_PERMISSION_CHANGE, self::RESOURCE_PERMISSION, $userId, $changes, $tenantId, $actorUserId);
    }
    
    /**
     * Registra una operación de billing
     */
    public static function logBilling($action, $resourceType, $resourceId, $data = null, $tenantId = null, $userId = null) {
        return self::log($action, $resourceType, $resourceId, $data, $tenantId, $userId);
    }
    
    /**
     * Registra un login
     */
    public static function logLogin($userId, $tenantId = null, $success = true) {
        $changes = ['success' => $success];
        return self::log(self::ACTION_LOGIN, self::RESOURCE_USER, $userId, $changes, $tenantId, $userId);
    }
    
    /**
     * Registra un logout
     */
    public static function logLogout($userId, $tenantId = null) {
        return self::log(self::ACTION_LOGOUT, self::RESOURCE_USER, $userId, null, $tenantId, $userId);
    }
    
    /**
     * Calcula el diff entre dos arrays
     */
    private static function calculateDiff($before, $after) {
        $diff = [];
        
        // Campos que cambiaron
        foreach ($after as $key => $value) {
            if (!isset($before[$key]) || $before[$key] !== $value) {
                $diff[$key] = [
                    'old' => $before[$key] ?? null,
                    'new' => $value
                ];
            }
        }
        
        // Campos que se eliminaron
        foreach ($before as $key => $value) {
            if (!isset($after[$key])) {
                $diff[$key] = [
                    'old' => $value,
                    'new' => null
                ];
            }
        }
        
        return $diff;
    }
    
    /**
     * Obtiene la IP del cliente
     */
    private static function getClientIp() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Si es una lista (X-Forwarded-For), tomar la primera
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validar IPv4 o IPv6
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Obtiene logs de auditoría con filtros
     * 
     * @param int|null $tenantId Filtrar por tenant
     * @param int|null $userId Filtrar por usuario
     * @param string|null $resourceType Filtrar por tipo de recurso
     * @param string|null $action Filtrar por acción
     * @param string|null $dateFrom Fecha desde (Y-m-d)
     * @param string|null $dateTo Fecha hasta (Y-m-d)
     * @param int $page Página (default: 1)
     * @param int $pageSize Tamaño de página (default: 50, max: 200)
     * @return array Logs y metadatos de paginación
     */
    public static function getLogs($tenantId = null, $userId = null, $resourceType = null, $action = null, $dateFrom = null, $dateTo = null, $page = 1, $pageSize = 50) {
        $conn = DBHelper::getConnection();
        
        // Validar pageSize
        $pageSize = min(max(1, $pageSize), 200);
        $offset = ($page - 1) * $pageSize;
        
        // Construir WHERE
        $where = [];
        $params = [];
        $types = '';
        
        if ($tenantId !== null) {
            $where[] = "tenant_id = ?";
            $params[] = $tenantId;
            $types .= 'i';
        }
        
        if ($userId !== null) {
            $where[] = "user_id = ?";
            $params[] = $userId;
            $types .= 'i';
        }
        
        if ($resourceType !== null) {
            $where[] = "resource_type = ?";
            $params[] = $resourceType;
            $types .= 's';
        }
        
        if ($action !== null) {
            $where[] = "action = ?";
            $params[] = $action;
            $types .= 's';
        }
        
        if ($dateFrom !== null) {
            $where[] = "DATE(created_at) >= ?";
            $params[] = $dateFrom;
            $types .= 's';
        }
        
        if ($dateTo !== null) {
            $where[] = "DATE(created_at) <= ?";
            $params[] = $dateTo;
            $types .= 's';
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Contar total
        $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM audit_logs {$whereClause}");
        if (!empty($params)) {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();
        
        // Obtener logs
        $stmt = $conn->prepare("
            SELECT 
                id,
                tenant_id,
                user_id,
                action,
                resource_type,
                resource_id,
                changes_json,
                ip_address,
                user_agent,
                created_at
            FROM audit_logs
            {$whereClause}
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $pageSize;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = [
                'id' => (int)$row['id'],
                'tenant_id' => $row['tenant_id'] ? (int)$row['tenant_id'] : null,
                'user_id' => $row['user_id'] ? (int)$row['user_id'] : null,
                'action' => $row['action'],
                'resource_type' => $row['resource_type'],
                'resource_id' => $row['resource_id'] ? (int)$row['resource_id'] : null,
                'changes' => $row['changes_json'] ? json_decode($row['changes_json'], true) : null,
                'ip_address' => $row['ip_address'],
                'user_agent' => $row['user_agent'],
                'created_at' => $row['created_at']
            ];
        }
        $stmt->close();
        
        return [
            'logs' => $logs,
            'total' => (int)$total,
            'page' => $page,
            'page_size' => $pageSize,
            'total_pages' => (int)ceil($total / $pageSize)
        ];
    }
    
    /**
     * Purga logs antiguos según retención configurada
     * 
     * @param int $retentionDays Días de retención (default: 365)
     * @return int Número de logs eliminados
     */
    public static function purgeOldLogs($retentionDays = 365) {
        $conn = DBHelper::getConnection();
        
        $stmt = $conn->prepare("
            DELETE FROM audit_logs
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->bind_param('i', $retentionDays);
        $stmt->execute();
        $deleted = $conn->affected_rows;
        $stmt->close();
        
        return $deleted;
    }
}

