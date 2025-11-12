<?php
/**
 * Servicio de Validación de Conflictos
 * 
 * Verifica:
 * - Solapamientos de horarios
 * - Límites diarios/semanales
 * - Skills requeridas
 * - Ratios líder:miembros
 * - Capacidad de grupos
 */

require_once __DIR__ . '/../helpers/db_helper.php';
require_once __DIR__ . '/../core/Config.php';

class ConflictValidator
{
    /**
     * Valida un turno antes de crearlo o actualizarlo
     * 
     * @param int $tenantId ID del tenant
     * @param array $shiftData Datos del turno
     * @param int|null $shiftId ID del turno (si es actualización)
     * @return array ['valid' => bool, 'conflicts' => array, 'warnings' => array]
     */
    public static function validateShift($tenantId, $shiftData, $shiftId = null)
    {
        $conflicts = [];
        $warnings = [];
        
        $startAt = $shiftData['start_at'];
        $endAt = $shiftData['end_at'];
        $employeeType = $shiftData['employee_type'];
        $ownEmployeeId = $shiftData['own_employee_id'] ?? null;
        $agencyWorkerId = $shiftData['agency_worker_id'] ?? null;
        $groupId = $shiftData['group_id'] ?? null;
        
        // 1. Validar solapamiento de horarios
        $overlap = self::checkOverlap($tenantId, $startAt, $endAt, $employeeType, $ownEmployeeId, $agencyWorkerId, $shiftId);
        if ($overlap['has_conflict']) {
            $conflicts[] = [
                'type' => 'overlap',
                'message' => 'El turno se solapa con otro turno existente',
                'details' => $overlap['conflicting_shifts']
            ];
        }
        
        // 2. Validar límites diarios/semanales
        $limits = self::checkLimits($tenantId, $startAt, $endAt, $employeeType, $ownEmployeeId, $agencyWorkerId, $shiftId);
        if (!$limits['valid']) {
            $conflicts[] = [
                'type' => 'limits_exceeded',
                'message' => $limits['message'],
                'details' => $limits['details']
            ];
        } elseif (!empty($limits['warnings'])) {
            $warnings = array_merge($warnings, $limits['warnings']);
        }
        
        // 3. Validar skills si hay grupo
        if ($groupId) {
            $skills = self::checkSkills($tenantId, $groupId, $employeeType, $ownEmployeeId, $agencyWorkerId);
            if (!$skills['valid']) {
                $conflicts[] = [
                    'type' => 'skills_missing',
                    'message' => 'El trabajador no tiene las skills requeridas para el grupo',
                    'details' => $skills['missing_skills']
                ];
            }
        }
        
        // 4. Validar capacidad del grupo
        if ($groupId) {
            $capacity = self::checkGroupCapacity($tenantId, $groupId, $startAt, $endAt, $shiftId);
            if (!$capacity['valid']) {
                $conflicts[] = [
                    'type' => 'capacity_exceeded',
                    'message' => 'El grupo ha excedido su capacidad máxima',
                    'details' => $capacity['details']
                ];
            }
        }
        
        return [
            'valid' => empty($conflicts),
            'conflicts' => $conflicts,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Verifica solapamiento de horarios
     */
    private static function checkOverlap($tenantId, $startAt, $endAt, $employeeType, $ownEmployeeId, $agencyWorkerId, $excludeShiftId = null)
    {
        $conn = DBHelper::getConnection();
        
        $where = "tenant_id = ? AND start_at < ? AND end_at > ? AND status NOT IN ('canceled', 'done')";
        $params = [$tenantId, $endAt, $startAt];
        $types = 'iss';
        
        if ($employeeType === 'own_employee' && $ownEmployeeId) {
            $where .= " AND employee_type = 'own_employee' AND own_employee_id = ?";
            $params[] = $ownEmployeeId;
            $types .= 'i';
        } elseif ($employeeType === 'agency_worker' && $agencyWorkerId) {
            $where .= " AND employee_type = 'agency_worker' AND agency_worker_id = ?";
            $params[] = $agencyWorkerId;
            $types .= 'i';
        }
        
        if ($excludeShiftId) {
            $where .= " AND id != ?";
            $params[] = $excludeShiftId;
            $types .= 'i';
        }
        
        $stmt = $conn->prepare("
            SELECT id, start_at, end_at, status, location
            FROM shifts
            WHERE $where
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $conflictingShifts = [];
        while ($row = $result->fetch_assoc()) {
            $conflictingShifts[] = $row;
        }
        $stmt->close();
        
        return [
            'has_conflict' => !empty($conflictingShifts),
            'conflicting_shifts' => $conflictingShifts
        ];
    }
    
    /**
     * Verifica límites diarios y semanales
     */
    private static function checkLimits($tenantId, $startAt, $endAt, $employeeType, $ownEmployeeId, $agencyWorkerId, $excludeShiftId = null)
    {
        $conn = DBHelper::getConnection();
        
        // Obtener configuración de límites
        $maxShiftsPerDay = Config::get($tenantId, 'calendar.max_shifts_per_day', 2);
        $maxHoursPerWeek = Config::get($tenantId, 'calendar.max_hours_per_week', 48);
        
        $startDate = date('Y-m-d', strtotime($startAt));
        $endDate = date('Y-m-d', strtotime($endAt));
        $startOfWeek = date('Y-m-d', strtotime('monday this week', strtotime($startAt)));
        $endOfWeek = date('Y-m-d', strtotime('sunday this week', strtotime($startAt)));
        
        $durationHours = (strtotime($endAt) - strtotime($startAt)) / 3600;
        
        $warnings = [];
        $conflicts = [];
        
        // Verificar límite de turnos por día
        $where = "tenant_id = ? AND DATE(start_at) = ? AND status NOT IN ('canceled', 'done')";
        $params = [$tenantId, $startDate];
        $types = 'is';
        
        if ($employeeType === 'own_employee' && $ownEmployeeId) {
            $where .= " AND employee_type = 'own_employee' AND own_employee_id = ?";
            $params[] = $ownEmployeeId;
            $types .= 'i';
        } elseif ($employeeType === 'agency_worker' && $agencyWorkerId) {
            $where .= " AND employee_type = 'agency_worker' AND agency_worker_id = ?";
            $params[] = $agencyWorkerId;
            $types .= 'i';
        }
        
        if ($excludeShiftId) {
            $where .= " AND id != ?";
            $params[] = $excludeShiftId;
            $types .= 'i';
        }
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM shifts WHERE $where");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $shiftsToday = $result->fetch_assoc()['count'];
        $stmt->close();
        
        if ($shiftsToday >= $maxShiftsPerDay) {
            $conflicts[] = "Límite de turnos por día excedido ({$maxShiftsPerDay})";
        }
        
        // Verificar límite de horas por semana
        $where = "tenant_id = ? AND DATE(start_at) >= ? AND DATE(start_at) <= ? AND status NOT IN ('canceled', 'done')";
        $params = [$tenantId, $startOfWeek, $endOfWeek];
        $types = 'iss';
        
        if ($employeeType === 'own_employee' && $ownEmployeeId) {
            $where .= " AND employee_type = 'own_employee' AND own_employee_id = ?";
            $params[] = $ownEmployeeId;
            $types .= 'i';
        } elseif ($employeeType === 'agency_worker' && $agencyWorkerId) {
            $where .= " AND employee_type = 'agency_worker' AND agency_worker_id = ?";
            $params[] = $agencyWorkerId;
            $types .= 'i';
        }
        
        if ($excludeShiftId) {
            $where .= " AND id != ?";
            $params[] = $excludeShiftId;
            $types .= 'i';
        }
        
        $stmt = $conn->prepare("
            SELECT SUM(TIMESTAMPDIFF(HOUR, start_at, end_at)) as total_hours
            FROM shifts
            WHERE $where
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $totalHoursWeek = (float)($row['total_hours'] ?? 0) + $durationHours;
        $stmt->close();
        
        if ($totalHoursWeek > $maxHoursPerWeek) {
            $conflicts[] = "Límite de horas semanales excedido ({$maxHoursPerWeek}h, actual: {$totalHoursWeek}h)";
        } elseif ($totalHoursWeek > $maxHoursPerWeek * 0.9) {
            $warnings[] = "Cerca del límite semanal ({$totalHoursWeek}h de {$maxHoursPerWeek}h)";
        }
        
        return [
            'valid' => empty($conflicts),
            'message' => !empty($conflicts) ? implode(', ', $conflicts) : null,
            'details' => [
                'shifts_today' => $shiftsToday,
                'max_shifts_per_day' => $maxShiftsPerDay,
                'total_hours_week' => $totalHoursWeek,
                'max_hours_per_week' => $maxHoursPerWeek
            ],
            'warnings' => $warnings
        ];
    }
    
    /**
     * Verifica skills requeridas del grupo
     */
    private static function checkSkills($tenantId, $groupId, $employeeType, $ownEmployeeId, $agencyWorkerId)
    {
        $conn = DBHelper::getConnection();
        
        // Obtener skills requeridas del grupo
        $stmt = $conn->prepare("
            SELECT required_skills_json
            FROM work_groups
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->bind_param('ii', $groupId, $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $group = $result->fetch_assoc();
        $stmt->close();
        
        if (!$group || empty($group['required_skills_json'])) {
            return ['valid' => true, 'missing_skills' => []];
        }
        
        $requiredSkills = json_decode($group['required_skills_json'], true);
        if (empty($requiredSkills) || !is_array($requiredSkills)) {
            return ['valid' => true, 'missing_skills' => []];
        }
        
        // Obtener skills del trabajador
        $workerSkills = [];
        if ($employeeType === 'own_employee' && $ownEmployeeId) {
            $stmt = $conn->prepare("SELECT skills_json FROM own_employees WHERE id = ? AND tenant_id = ?");
            $stmt->bind_param('ii', $ownEmployeeId, $tenantId);
            $stmt->execute();
            $result = $stmt->get_result();
            $employee = $result->fetch_assoc();
            $stmt->close();
            if ($employee && $employee['skills_json']) {
                $workerSkills = json_decode($employee['skills_json'], true) ?? [];
            }
        } elseif ($employeeType === 'agency_worker' && $agencyWorkerId) {
            // Necesitamos el tenant_id de la temporera para obtener el trabajador
            // Por ahora, asumimos que viene en el contexto
            $stmt = $conn->prepare("SELECT skills_json FROM agency_workers WHERE id = ?");
            $stmt->bind_param('i', $agencyWorkerId);
            $stmt->execute();
            $result = $stmt->get_result();
            $worker = $result->fetch_assoc();
            $stmt->close();
            if ($worker && $worker['skills_json']) {
                $workerSkills = json_decode($worker['skills_json'], true) ?? [];
            }
        }
        
        $missingSkills = array_diff($requiredSkills, $workerSkills);
        
        return [
            'valid' => empty($missingSkills),
            'missing_skills' => array_values($missingSkills)
        ];
    }
    
    /**
     * Verifica capacidad del grupo
     */
    private static function checkGroupCapacity($tenantId, $groupId, $startAt, $endAt, $excludeShiftId = null)
    {
        $conn = DBHelper::getConnection();
        
        // Obtener capacidad máxima del grupo
        $stmt = $conn->prepare("
            SELECT max_size
            FROM work_groups
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->bind_param('ii', $groupId, $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $group = $result->fetch_assoc();
        $stmt->close();
        
        if (!$group) {
            return ['valid' => false, 'details' => ['message' => 'Grupo no encontrado']];
        }
        
        $maxSize = $group['max_size'] ?? null;
        if ($maxSize === null) {
            return ['valid' => true, 'details' => []]; // Sin límite
        }
        
        // Contar miembros activos en el grupo en el rango de fechas
        $shiftWhere = "group_id = ? AND start_at <= ? AND end_at >= ? AND status NOT IN ('canceled', 'done')";
        $shiftParams = [$groupId, $endAt, $startAt];
        $shiftTypes = 'iss';
        
        if ($excludeShiftId) {
            // No contar el turno que se está actualizando
            $shiftWhere .= " AND id != ?";
            $shiftParams[] = $excludeShiftId;
            $shiftTypes .= 'i';
        }
        
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT COALESCE(own_employee_id, agency_worker_id)) as member_count
            FROM shifts
            WHERE $shiftWhere
        ");
        $stmt->bind_param($shiftTypes, ...$shiftParams);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentCount = $result->fetch_assoc()['member_count'] ?? 0;
        $stmt->close();
        
        // También contar miembros del grupo directamente
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count
            FROM group_members
            WHERE group_id = ? AND start_at <= ? AND (end_at IS NULL OR end_at >= ?)
        ");
        $stmt->bind_param('iss', $groupId, $endAt, $startAt);
        $stmt->execute();
        $result = $stmt->get_result();
        $groupMembers = $result->fetch_assoc()['count'] ?? 0;
        $stmt->close();
        
        $totalMembers = max($currentCount, $groupMembers);
        
        if ($totalMembers >= $maxSize) {
            return [
                'valid' => false,
                'details' => [
                    'message' => "Capacidad máxima del grupo alcanzada ({$maxSize} miembros)",
                    'current_count' => $totalMembers,
                    'max_size' => $maxSize
                ]
            ];
        }
        
        return [
            'valid' => true,
            'details' => [
                'current_count' => $totalMembers,
                'max_size' => $maxSize
            ]
        ];
    }
    
    /**
     * Valida ratio líder:miembros
     */
    public static function validateLeaderRatio($tenantId, $groupId)
    {
        $conn = DBHelper::getConnection();
        
        $leaderRatio = Config::get($tenantId, 'calendar.leader_ratio', 0.1);
        
        // Obtener grupo
        $stmt = $conn->prepare("
            SELECT leader_employee_id
            FROM work_groups
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->bind_param('ii', $groupId, $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $group = $result->fetch_assoc();
        $stmt->close();
        
        if (!$group) {
            return ['valid' => false, 'message' => 'Grupo no encontrado'];
        }
        
        if (!$group['leader_employee_id']) {
            return ['valid' => false, 'message' => 'El grupo no tiene líder asignado'];
        }
        
        // Contar miembros
        $stmt = $conn->prepare("
            SELECT COUNT(*) as member_count
            FROM group_members
            WHERE group_id = ?
        ");
        $stmt->bind_param('i', $groupId);
        $stmt->execute();
        $result = $stmt->get_result();
        $memberCount = $result->fetch_assoc()['member_count'] ?? 0;
        $stmt->close();
        
        $requiredLeaders = ceil($memberCount * $leaderRatio);
        
        if ($requiredLeaders > 1) {
            return [
                'valid' => false,
                'message' => "El grupo necesita al menos {$requiredLeaders} líder(es) para {$memberCount} miembros (ratio: {$leaderRatio})"
            ];
        }
        
        return ['valid' => true];
    }
}

