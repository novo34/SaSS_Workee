<?php
/**
 * Sistema de Configuración
 * 
 * Herencia: Owner → Tenant → Proyecto → Grupo
 * Permite configuración dinámica con herencia
 */

require_once __DIR__ . '/../helpers/db_helper.php';

class Config {
    /**
     * Obtiene un valor de configuración con herencia
     * 
     * @param string $key Clave de configuración
     * @param int|null $tenantId ID del tenant (opcional, usa contexto actual)
     * @param int|null $projectId ID del proyecto (opcional)
     * @param int|null $groupId ID del grupo (opcional)
     * @param mixed $default Valor por defecto si no existe
     * @return mixed Valor de configuración
     */
    public static function get($key, $tenantId = null, $projectId = null, $groupId = null, $default = null) {
        // Si no se especifica tenant_id, usar el del contexto
        if ($tenantId === null) {
            require_once __DIR__ . '/TenantMiddleware.php';
            $tenantId = TenantMiddleware::getCurrentTenantId();
        }
        
        // Orden de búsqueda: Grupo → Proyecto → Tenant → Owner (global)
        $value = null;
        
        // 1. Buscar en grupo (más específico)
        if ($groupId !== null) {
            $value = self::getFromLevel('group', $groupId, $key);
            if ($value !== null) {
                return self::parseValue($value);
            }
        }
        
        // 2. Buscar en proyecto
        if ($projectId !== null) {
            $value = self::getFromLevel('project', $projectId, $key);
            if ($value !== null) {
                return self::parseValue($value);
            }
        }
        
        // 3. Buscar en tenant
        if ($tenantId !== null) {
            $value = self::getFromLevel('tenant', $tenantId, $key);
            if ($value !== null) {
                return self::parseValue($value);
            }
        }
        
        // 4. Buscar en owner (global, tenant_id = NULL)
        $value = self::getFromLevel('owner', null, $key);
        if ($value !== null) {
            return self::parseValue($value);
        }
        
        // 5. Valor por defecto del sistema
        return self::getDefault($key, $default);
    }
    
    /**
     * Obtiene configuración de un nivel específico
     */
    private static function getFromLevel($level, $id, $key) {
        $conn = DBHelper::getConnection();
        
        $sql = "SELECT config_value FROM configurations WHERE config_key = ?";
        $types = 's';
        $params = [$key];
        
        switch ($level) {
            case 'group':
                $sql .= " AND group_id = ?";
                $types .= 'i';
                $params[] = $id;
                break;
            case 'project':
                $sql .= " AND project_id = ? AND group_id IS NULL";
                $types .= 'i';
                $params[] = $id;
                break;
            case 'tenant':
                $sql .= " AND tenant_id = ? AND project_id IS NULL AND group_id IS NULL";
                $types .= 'i';
                $params[] = $id;
                break;
            case 'owner':
                $sql .= " AND tenant_id IS NULL AND project_id IS NULL AND group_id IS NULL";
                break;
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        
        if (count($params) > 1) {
            $stmt->bind_param($types, ...$params);
        } else {
            $stmt->bind_param($types, $params[0]);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row ? $row['config_value'] : null;
    }
    
    /**
     * Parsea el valor JSON almacenado
     */
    private static function parseValue($jsonValue) {
        $decoded = json_decode($jsonValue, true);
        return $decoded !== null ? $decoded : $jsonValue;
    }
    
    /**
     * Establece un valor de configuración
     * 
     * @param string $key Clave
     * @param mixed $value Valor (se serializa a JSON)
     * @param int|null $tenantId ID del tenant
     * @param int|null $projectId ID del proyecto
     * @param int|null $groupId ID del grupo
     * @return bool Éxito
     */
    public static function set($key, $value, $tenantId = null, $projectId = null, $groupId = null) {
        $conn = DBHelper::getConnection();
        
        $jsonValue = json_encode($value);
        
        // Verificar si existe
        $sql = "SELECT id FROM configurations WHERE config_key = ?";
        $types = 's';
        $params = [$key];
        
        if ($groupId !== null) {
            $sql .= " AND group_id = ?";
            $types .= 'i';
            $params[] = $groupId;
        } elseif ($projectId !== null) {
            $sql .= " AND project_id = ? AND group_id IS NULL";
            $types .= 'i';
            $params[] = $projectId;
        } elseif ($tenantId !== null) {
            $sql .= " AND tenant_id = ? AND project_id IS NULL AND group_id IS NULL";
            $types .= 'i';
            $params[] = $tenantId;
        } else {
            $sql .= " AND tenant_id IS NULL AND project_id IS NULL AND group_id IS NULL";
        }
        
        $stmt = $conn->prepare($sql);
        if (count($params) > 1) {
            $stmt->bind_param($types, ...$params);
        } else {
            $stmt->bind_param($types, $params[0]);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $existing = $result->fetch_assoc();
        $stmt->close();
        
        if ($existing) {
            // Actualizar
            $sql = "UPDATE configurations SET config_value = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $jsonValue, $existing['id']);
            $stmt->execute();
            $success = $stmt->affected_rows > 0;
            $stmt->close();
            return $success;
        } else {
            // Insertar
            $sql = "INSERT INTO configurations (tenant_id, project_id, group_id, config_key, config_value) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iiiss', $tenantId, $projectId, $groupId, $key, $jsonValue);
            $stmt->execute();
            $success = $stmt->affected_rows > 0;
            $stmt->close();
            return $success;
        }
    }
    
    /**
     * Obtiene valores por defecto del sistema
     */
    private static function getDefault($key, $customDefault = null) {
        $defaults = [
            // Fichaje
            'timeclock.required' => false,
            'timeclock.tolerance_minutes' => 5,
            'timeclock.min_break_minutes' => 30,
            'timeclock.rounding_minutes' => 15,
            
            // Aprobaciones
            'approval.stages' => 1,
            'approval.auto_approve_threshold_hours' => 8,
            'approval.require_co_approval_agency' => false,
            
            // Disputas
            'dispute.window_hours' => 72,
            'dispute.require_evidence' => true,
            
            // Matching
            'matching.max_distance_km' => 50,
            'matching.weight_availability' => 0.3,
            'matching.weight_proximity' => 0.2,
            'matching.weight_skills' => 0.3,
            'matching.weight_rating' => 0.2,
            
            // Calendario
            'calendar.max_shifts_per_day' => 2,
            'calendar.max_hours_per_week' => 48,
            'calendar.max_group_size' => 20,
            'calendar.leader_ratio' => 0.1,
            
            // Notificaciones
            'notifications.email_enabled' => true,
            'notifications.sms_enabled' => false,
            'notifications.whatsapp_enabled' => false,
            'notifications.webpush_enabled' => false,
            'notifications.quiet_hours_start' => '22:00',
            'notifications.quiet_hours_end' => '07:00',
            
            // Billing
            'billing.measure' => 'monthly_avg',
            'billing.cycle_day' => 1,
            
            // Privacidad
            'privacy.pii_visibility' => 'visible_al_confirmar',
            
            // Feature flags
            'features.webhooks' => false,
            'features.api_public' => false,
            'features.ocr_fichajes' => false
        ];
        
        return $defaults[$key] ?? $customDefault;
    }
    
    /**
     * Obtiene todas las configuraciones de un nivel (sin herencia)
     * 
     * @param int|null $tenantId
     * @param int|null $projectId
     * @param int|null $groupId
     * @return array
     */
    public static function getAll($tenantId = null, $projectId = null, $groupId = null) {
        $conn = DBHelper::getConnection();
        
        $sql = "SELECT config_key, config_value FROM configurations WHERE 1=1";
        $types = '';
        $params = [];
        
        if ($groupId !== null) {
            $sql .= " AND group_id = ?";
            $types .= 'i';
            $params[] = $groupId;
        } elseif ($projectId !== null) {
            $sql .= " AND project_id = ? AND group_id IS NULL";
            $types .= 'i';
            $params[] = $projectId;
        } elseif ($tenantId !== null) {
            $sql .= " AND tenant_id = ? AND project_id IS NULL AND group_id IS NULL";
            $types .= 'i';
            $params[] = $tenantId;
        } else {
            $sql .= " AND tenant_id IS NULL AND project_id IS NULL AND group_id IS NULL";
        }
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $configs = [];
        while ($row = $result->fetch_assoc()) {
            $configs[$row['config_key']] = self::parseValue($row['config_value']);
        }
        $stmt->close();
        
        return $configs;
    }
}

