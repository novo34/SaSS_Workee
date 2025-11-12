<?php
/**
 * Job: Snapshots de Empleados/Trabajadores Activos
 * 
 * Cron diario que cuenta empleados/trabajadores activos por tenant
 * y almacena en usage_employee_snapshots para billing
 * 
 * Uso: php backend/jobs/usage_snapshot.php [--date=YYYY-MM-DD]
 * 
 * Si no se especifica fecha, usa la fecha de ayer (para ejecutar al inicio del día)
 */

require_once __DIR__ . '/../helpers/db_helper.php';
require_once __DIR__ . '/../helpers/env.php';

// Deshabilitar time limit para jobs largos
set_time_limit(0);
ini_set('memory_limit', '256M');

// Obtener fecha (por defecto: ayer)
$targetDate = date('Y-m-d', strtotime('-1 day'));
if (isset($argv)) {
    foreach ($argv as $arg) {
        if (strpos($arg, '--date=') === 0) {
            $targetDate = substr($arg, 7);
            // Validar formato
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
                echo "Error: Formato de fecha inválido. Use YYYY-MM-DD\n";
                exit(1);
            }
        }
    }
}

echo "📊 Iniciando snapshot de activos para fecha: {$targetDate}\n\n";

$conn = DBHelper::getConnection();

try {
    // Obtener todos los tenants activos
    $stmt = $conn->prepare("
        SELECT id, name, org_type 
        FROM tenants 
        WHERE is_active = 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $tenants = [];
    while ($row = $result->fetch_assoc()) {
        $tenants[] = $row;
    }
    $stmt->close();
    
    echo "Encontrados " . count($tenants) . " tenants activos\n\n";
    
    $processed = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($tenants as $tenant) {
        $tenantId = $tenant['id'];
        $orgType = $tenant['org_type'];
        $tenantName = $tenant['name'];
        
        // Verificar si ya existe snapshot para esta fecha
        $checkStmt = $conn->prepare("
            SELECT id FROM usage_employee_snapshots 
            WHERE tenant_id = ? AND date = ?
        ");
        $checkStmt->bind_param('is', $tenantId, $targetDate);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $existing = $checkResult->fetch_assoc();
        $checkStmt->close();
        
        if ($existing) {
            echo "⏭️  Tenant #{$tenantId} ({$tenantName}): Ya existe snapshot para {$targetDate}, omitiendo...\n";
            $skipped++;
            continue;
        }
        
        // Contar activos según tipo de organización
        $activeCount = 0;
        
        if ($orgType === 'empresa') {
            // Empresa: cuenta empleados propios activos
            $countStmt = $conn->prepare("
                SELECT COUNT(*) as count
                FROM own_employees
                WHERE tenant_id = ? AND is_active = 1
            ");
            $countStmt->bind_param('i', $tenantId);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $countRow = $countResult->fetch_assoc();
            $activeCount = (int)$countRow['count'];
            $countStmt->close();
            
        } elseif ($orgType === 'temporera') {
            // Temporera: cuenta trabajadores activos (sean o no asignados)
            $countStmt = $conn->prepare("
                SELECT COUNT(*) as count
                FROM agency_workers
                WHERE tenant_id = ? AND is_active = 1
            ");
            $countStmt->bind_param('i', $tenantId);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $countRow = $countResult->fetch_assoc();
            $activeCount = (int)$countRow['count'];
            $countStmt->close();
        }
        
        // Insertar snapshot
        $insertStmt = $conn->prepare("
            INSERT INTO usage_employee_snapshots (tenant_id, date, active_count, org_type)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE active_count = VALUES(active_count)
        ");
        $insertStmt->bind_param('isis', $tenantId, $targetDate, $activeCount, $orgType);
        
        if ($insertStmt->execute()) {
            echo "✅ Tenant #{$tenantId} ({$tenantName}): {$activeCount} activos registrados\n";
            $processed++;
        } else {
            echo "❌ Tenant #{$tenantId} ({$tenantName}): Error al insertar snapshot\n";
            $errors++;
        }
        $insertStmt->close();
    }
    
    echo "\n";
    echo "═══════════════════════════════════════\n";
    echo "Resumen:\n";
    echo "  ✅ Procesados: {$processed}\n";
    echo "  ⏭️  Omitidos (ya existían): {$skipped}\n";
    echo "  ❌ Errores: {$errors}\n";
    echo "═══════════════════════════════════════\n";
    
} catch (Exception $e) {
    echo "❌ Error fatal: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

$conn->close();
echo "\n✅ Job completado exitosamente\n";

