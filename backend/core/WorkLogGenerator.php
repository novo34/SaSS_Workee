<?php
/**
 * Generador de Work Logs desde Partes del Líder
 * 
 * Toma un parte del líder (group_shift_report) y genera work_logs individuales
 * para cada línea del parte (group_shift_report_lines)
 */

require_once __DIR__ . '/../helpers/db_helper.php';
require_once __DIR__ . '/../helpers/i18n.php';

class WorkLogGenerator {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Generar work_logs desde un parte del líder
     * 
     * @param int $reportId ID del parte
     * @param int $tenantId ID del tenant
     * @return array ['success' => bool, 'message' => string, 'work_logs' => array]
     */
    public function generateFromReport($reportId, $tenantId) {
        // Obtener el parte
        $stmt = $this->conn->prepare("
            SELECT * FROM group_shift_reports 
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->bind_param('ii', $reportId, $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $report = $result->fetch_assoc();
        $stmt->close();
        
        if (!$report) {
            return [
                'success' => false,
                'message' => t('reports.not_found')
            ];
        }
        
        // Obtener líneas del parte
        $stmt = $this->conn->prepare("
            SELECT * FROM group_shift_report_lines 
            WHERE report_id = ?
        ");
        $stmt->bind_param('i', $reportId);
        $stmt->execute();
        $result = $stmt->get_result();
        $lines = [];
        while ($row = $result->fetch_assoc()) {
            $lines[] = $row;
        }
        $stmt->close();
        
        if (empty($lines)) {
            return [
                'success' => false,
                'message' => t('reports.no_lines')
            ];
        }
        
        // Obtener información del grupo para project_id
        $projectId = null;
        if ($report['group_id']) {
            $stmt = $this->conn->prepare("SELECT project_id FROM work_groups WHERE id = ?");
            $stmt->bind_param('i', $report['group_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $group = $result->fetch_assoc();
            $stmt->close();
            $projectId = $group['project_id'] ?? null;
        }
        
        $generatedLogs = [];
        $errors = [];
        
        foreach ($lines as $line) {
            // Solo generar work_log si el miembro asistió
            if (!$line['attended']) {
                continue;
            }
            
            // Validar que tiene clock_in y clock_out
            if (!$line['clock_in'] || !$line['clock_out']) {
                $memberName = $this->getMemberName($line);
                $errorMsg = str_replace('{member}', $memberName, t('reports.missing_times'));
                $errors[] = $errorMsg;
                continue;
            }
            
            // Verificar si ya existe un work_log para este miembro en esta fecha
            $existingLog = $this->findExistingLog(
                $tenantId,
                $line['member_type'],
                $line['own_employee_id'],
                $line['agency_worker_id'],
                $report['date'],
                $report['shift_id']
            );
            
            if ($existingLog) {
                // Actualizar work_log existente
                $workLogId = $existingLog['id'];
                $stmt = $this->conn->prepare("
                    UPDATE work_logs SET
                        clock_in = ?,
                        clock_out = ?,
                        breaks_minutes = ?,
                        notes = ?,
                        shift_id = ?,
                        status = 'submitted',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $notes = $line['notes'] ?? null;
                $shiftId = $report['shift_id'] ? (int)$report['shift_id'] : null;
                
                $stmt->bind_param(
                    'ssiisii',
                    $line['clock_in'],
                    $line['clock_out'],
                    $line['breaks_minutes'],
                    $notes,
                    $shiftId,
                    $workLogId
                );
                
                $stmt->execute();
                $stmt->close();
                
                $generatedLogs[] = [
                    'id' => $workLogId,
                    'action' => 'updated'
                ];
            } else {
                // Crear nuevo work_log
                $stmt = $this->conn->prepare("
                    INSERT INTO work_logs (
                        tenant_id,
                        employee_type,
                        own_employee_id,
                        agency_worker_id,
                        project_id,
                        shift_id,
                        date,
                        clock_in,
                        clock_out,
                        breaks_minutes,
                        notes,
                        status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted')
                ");
                
                $notes = $line['notes'] ?? null;
                $shiftId = $report['shift_id'] ? (int)$report['shift_id'] : null;
                
                $stmt->bind_param(
                    'isiiissssiss',
                    $tenantId,
                    $line['member_type'],
                    $line['own_employee_id'],
                    $line['agency_worker_id'],
                    $projectId,
                    $shiftId,
                    $report['date'],
                    $line['clock_in'],
                    $line['clock_out'],
                    $line['breaks_minutes'],
                    $notes
                );
                
                $stmt->execute();
                $workLogId = $this->conn->insert_id;
                $stmt->close();
                
                $generatedLogs[] = [
                    'id' => $workLogId,
                    'action' => 'created'
                ];
            }
        }
        
        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => implode(', ', $errors),
                'work_logs' => $generatedLogs
            ];
        }
        
        $message = str_replace('{count}', count($generatedLogs), t('reports.work_logs_generated'));
        
        return [
            'success' => true,
            'message' => $message,
            'work_logs' => $generatedLogs
        ];
    }
    
    /**
     * Buscar work_log existente
     */
    private function findExistingLog($tenantId, $memberType, $ownEmployeeId, $agencyWorkerId, $date, $shiftId) {
        $where = ["tenant_id = ?", "date = ?", "employee_type = ?"];
        $params = [$tenantId, $date, $memberType];
        $types = 'iss';
        
        if ($memberType === 'own_employee' && $ownEmployeeId) {
            $where[] = "own_employee_id = ?";
            $params[] = $ownEmployeeId;
            $types .= 'i';
        } elseif ($memberType === 'agency_worker' && $agencyWorkerId) {
            $where[] = "agency_worker_id = ?";
            $params[] = $agencyWorkerId;
            $types .= 'i';
        }
        
        if ($shiftId) {
            $where[] = "shift_id = ?";
            $params[] = $shiftId;
            $types .= 'i';
        }
        
        $whereClause = implode(' AND ', $where);
        
        $stmt = $this->conn->prepare("
            SELECT id FROM work_logs 
            WHERE $whereClause
            LIMIT 1
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $log = $result->fetch_assoc();
        $stmt->close();
        
        return $log;
    }
    
    /**
     * Obtener nombre del miembro
     */
    private function getMemberName($line) {
        if ($line['member_type'] === 'own_employee' && $line['own_employee_id']) {
            $stmt = $this->conn->prepare("
                SELECT first_name, last_name FROM own_employees 
                WHERE id = ?
            ");
            $stmt->bind_param('i', $line['own_employee_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $employee = $result->fetch_assoc();
            $stmt->close();
            return trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));
        } elseif ($line['member_type'] === 'agency_worker' && $line['agency_worker_id']) {
            $stmt = $this->conn->prepare("
                SELECT first_name, last_name FROM agency_workers 
                WHERE id = ?
            ");
            $stmt->bind_param('i', $line['agency_worker_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $worker = $result->fetch_assoc();
            $stmt->close();
            return trim(($worker['first_name'] ?? '') . ' ' . ($worker['last_name'] ?? ''));
        }
        return 'Desconocido';
    }
    
    /**
     * Generar work_logs desde un parte diario de OT
     * 
     * @param int $reportId ID del parte diario
     * @param int $tenantId ID del tenant
     * @return array ['success' => bool, 'message' => string, 'work_logs' => array]
     */
    public function generateFromWorkOrderDailyReport($reportId, $tenantId) {
        // Obtener el parte diario
        $stmt = $this->conn->prepare("
            SELECT wodr.*, wo.project_id, wo.tenant_id
            FROM work_order_daily_reports wodr
            INNER JOIN work_orders wo ON wodr.work_order_id = wo.id
            WHERE wodr.id = ? AND wo.tenant_id = ?
        ");
        $stmt->bind_param('ii', $reportId, $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $report = $result->fetch_assoc();
        $stmt->close();
        
        if (!$report) {
            return [
                'success' => false,
                'message' => t('work_order_daily_reports.not_found')
            ];
        }
        
        // Obtener líneas del parte
        $stmt = $this->conn->prepare("
            SELECT * FROM work_order_daily_lines 
            WHERE daily_report_id = ?
        ");
        $stmt->bind_param('i', $reportId);
        $stmt->execute();
        $result = $stmt->get_result();
        $lines = [];
        while ($row = $result->fetch_assoc()) {
            $lines[] = $row;
        }
        $stmt->close();
        
        if (empty($lines)) {
            return [
                'success' => false,
                'message' => t('work_order_daily_reports.no_lines')
            ];
        }
        
        $projectId = $report['project_id'] ?? null;
        $generatedLogs = [];
        $errors = [];
        
        foreach ($lines as $line) {
            // Solo generar work_log si el miembro asistió
            if (!$line['attended']) {
                continue;
            }
            
            // Validar que tiene clock_in y clock_out
            if (!$line['clock_in'] || !$line['clock_out']) {
                $memberName = $this->getMemberName($line);
                $errorMsg = str_replace('{member}', $memberName, t('work_order_daily_reports.missing_times'));
                $errors[] = $errorMsg;
                continue;
            }
            
            // Verificar si ya existe un work_log para este miembro en esta fecha
            $existingLog = $this->findExistingLogForWorkOrder(
                $tenantId,
                $line['member_type'],
                $line['own_employee_id'],
                $line['agency_worker_id'],
                $report['date'],
                $report['work_order_id']
            );
            
            if ($existingLog) {
                // Actualizar work_log existente
                $workLogId = $existingLog['id'];
                $stmt = $this->conn->prepare("
                    UPDATE work_logs SET
                        clock_in = ?,
                        clock_out = ?,
                        breaks_minutes = ?,
                        notes = ?,
                        status = 'submitted',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $notes = $line['notes'] ?? null;
                
                $stmt->bind_param(
                    'ssiisi',
                    $line['clock_in'],
                    $line['clock_out'],
                    $line['breaks_minutes'],
                    $notes,
                    $workLogId
                );
                
                $stmt->execute();
                $stmt->close();
                
                $generatedLogs[] = [
                    'id' => $workLogId,
                    'action' => 'updated'
                ];
            } else {
                // Crear nuevo work_log
                $stmt = $this->conn->prepare("
                    INSERT INTO work_logs (
                        tenant_id,
                        employee_type,
                        own_employee_id,
                        agency_worker_id,
                        project_id,
                        date,
                        clock_in,
                        clock_out,
                        breaks_minutes,
                        notes,
                        status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted')
                ");
                
                $notes = $line['notes'] ?? null;
                
                $stmt->bind_param(
                    'isiiissssi',
                    $tenantId,
                    $line['member_type'],
                    $line['own_employee_id'],
                    $line['agency_worker_id'],
                    $projectId,
                    $report['date'],
                    $line['clock_in'],
                    $line['clock_out'],
                    $line['breaks_minutes'],
                    $notes
                );
                
                $stmt->execute();
                $workLogId = $this->conn->insert_id;
                $stmt->close();
                
                $generatedLogs[] = [
                    'id' => $workLogId,
                    'action' => 'created'
                ];
            }
        }
        
        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => implode(', ', $errors),
                'work_logs' => $generatedLogs
            ];
        }
        
        $message = str_replace('{count}', count($generatedLogs), t('work_order_daily_reports.work_logs_generated'));
        
        return [
            'success' => true,
            'message' => $message,
            'work_logs' => $generatedLogs
        ];
    }
    
    /**
     * Buscar work_log existente para OT (sin shift_id)
     */
    private function findExistingLogForWorkOrder($tenantId, $memberType, $ownEmployeeId, $agencyWorkerId, $date, $workOrderId) {
        $where = ["tenant_id = ?", "date = ?", "employee_type = ?"];
        $params = [$tenantId, $date, $memberType];
        $types = 'iss';
        
        if ($memberType === 'own_employee' && $ownEmployeeId) {
            $where[] = "own_employee_id = ?";
            $params[] = $ownEmployeeId;
            $types .= 'i';
        } elseif ($memberType === 'agency_worker' && $agencyWorkerId) {
            $where[] = "agency_worker_id = ?";
            $params[] = $agencyWorkerId;
            $types .= 'i';
        }
        
        // Buscar por work_order_id a través de assignments (si existe)
        // Por ahora, solo buscamos por fecha y miembro sin shift_id
        $whereClause = implode(' AND ', $where);
        
        $stmt = $this->conn->prepare("
            SELECT id FROM work_logs 
            WHERE $whereClause AND shift_id IS NULL
            LIMIT 1
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $log = $result->fetch_assoc();
        $stmt->close();
        
        return $log;
    }
}

