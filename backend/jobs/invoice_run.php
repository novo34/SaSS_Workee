<?php
/**
 * Job: Facturación Mensual
 * 
 * Cron mensual que calcula fijo+variable y genera facturas
 * 
 * Uso: php backend/jobs/invoice_run.php [--month=YYYY-MM] [--dry-run]
 * 
 * Si no se especifica mes, usa el mes anterior
 * --dry-run: solo simula, no genera facturas
 */

require_once __DIR__ . '/../helpers/db_helper.php';
require_once __DIR__ . '/../helpers/env.php';

// Deshabilitar time limit para jobs largos
set_time_limit(0);
ini_set('memory_limit', '512M');

// Obtener parámetros
$dryRun = false;
$targetMonth = date('Y-m', strtotime('-1 month')); // Mes anterior por defecto

if (isset($argv)) {
    foreach ($argv as $arg) {
        if ($arg === '--dry-run') {
            $dryRun = true;
        } elseif (strpos($arg, '--month=') === 0) {
            $targetMonth = substr($arg, 8);
            // Validar formato
            if (!preg_match('/^\d{4}-\d{2}$/', $targetMonth)) {
                echo "Error: Formato de mes inválido. Use YYYY-MM\n";
                exit(1);
            }
        }
    }
}

// Calcular período de facturación (mes completo)
$periodFrom = $targetMonth . '-01';
$periodTo = date('Y-m-t', strtotime($periodFrom)); // Último día del mes

echo "💰 Iniciando facturación para período: {$periodFrom} a {$periodTo}\n";
if ($dryRun) {
    echo "⚠️  MODO DRY-RUN: No se generarán facturas reales\n";
}
echo "\n";

$conn = DBHelper::getConnection();

try {
    // Obtener todas las suscripciones activas
    $stmt = $conn->prepare("
        SELECT 
            s.id,
            s.tenant_id,
            s.billing_cycle_anchor,
            s.fixed_price,
            s.variable_price_per_employee,
            s.measure,
            t.name as tenant_name,
            t.org_type,
            t.currency
        FROM subscriptions s
        INNER JOIN tenants t ON s.tenant_id = t.id
        WHERE s.status = 'active' AND t.is_active = 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $subscriptions = [];
    while ($row = $result->fetch_assoc()) {
        $subscriptions[] = $row;
    }
    $stmt->close();
    
    echo "Encontradas " . count($subscriptions) . " suscripciones activas\n\n";
    
    $processed = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($subscriptions as $subscription) {
        $subscriptionId = $subscription['id'];
        $tenantId = $subscription['tenant_id'];
        $tenantName = $subscription['tenant_name'];
        $billingCycleAnchor = $subscription['billing_cycle_anchor'];
        
        // Verificar si el período está dentro del ciclo de facturación
        // (por ahora, facturar todos los meses, se puede refinar después)
        
        // Verificar si ya existe factura para este período
        $checkStmt = $conn->prepare("
            SELECT id FROM invoices 
            WHERE tenant_id = ? AND period_from = ? AND period_to = ?
        ");
        $checkStmt->bind_param('iss', $tenantId, $periodFrom, $periodTo);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $existing = $checkResult->fetch_assoc();
        $checkStmt->close();
        
        if ($existing) {
            echo "⏭️  Tenant #{$tenantId} ({$tenantName}): Ya existe factura para {$periodFrom} - {$periodTo}, omitiendo...\n";
            $skipped++;
            continue;
        }
        
        // Calcular monto fijo
        $fixedAmount = (float)$subscription['fixed_price'];
        
        // Calcular monto variable según medida
        $variableUnits = 0;
        $measure = $subscription['measure'];
        $variableRate = (float)$subscription['variable_price_per_employee'];
        
        if ($measure === 'monthly_avg') {
            // Promedio mensual de snapshots
            $avgStmt = $conn->prepare("
                SELECT AVG(active_count) as avg_count
                FROM usage_employee_snapshots
                WHERE tenant_id = ? 
                AND date >= ? 
                AND date <= ?
            ");
            $avgStmt->bind_param('iss', $tenantId, $periodFrom, $periodTo);
            $avgStmt->execute();
            $avgResult = $avgStmt->get_result();
            $avgRow = $avgResult->fetch_assoc();
            $variableUnits = (int)round($avgRow['avg_count'] ?? 0);
            $avgStmt->close();
            
        } elseif ($measure === 'monthly_peak') {
            // Pico mensual de snapshots
            $peakStmt = $conn->prepare("
                SELECT MAX(active_count) as peak_count
                FROM usage_employee_snapshots
                WHERE tenant_id = ? 
                AND date >= ? 
                AND date <= ?
            ");
            $peakStmt->bind_param('iss', $tenantId, $periodFrom, $periodTo);
            $peakStmt->execute();
            $peakResult = $peakStmt->get_result();
            $peakRow = $peakResult->fetch_assoc();
            $variableUnits = (int)($peakRow['peak_count'] ?? 0);
            $peakStmt->close();
            
        } elseif ($measure === 'daily') {
            // Suma diaria (para facturación diaria, no mensual)
            // Por ahora, usar promedio como fallback
            $dailyStmt = $conn->prepare("
                SELECT AVG(active_count) as avg_count
                FROM usage_employee_snapshots
                WHERE tenant_id = ? 
                AND date >= ? 
                AND date <= ?
            ");
            $dailyStmt->bind_param('iss', $tenantId, $periodFrom, $periodTo);
            $dailyStmt->execute();
            $dailyResult = $dailyStmt->get_result();
            $dailyRow = $dailyResult->fetch_assoc();
            $variableUnits = (int)round($dailyRow['avg_count'] ?? 0);
            $dailyStmt->close();
        }
        
        $variableAmount = $variableUnits * $variableRate;
        $totalAmount = $fixedAmount + $variableAmount;
        
        // Generar número de factura
        $invoiceNumber = generateInvoiceNumber($conn, $tenantId, $periodFrom);
        
        if ($dryRun) {
            echo "🔍 [DRY-RUN] Tenant #{$tenantId} ({$tenantName}):\n";
            echo "   Factura: {$invoiceNumber}\n";
            echo "   Fijo: {$fixedAmount} {$subscription['currency']}\n";
            echo "   Variable: {$variableUnits} unidades × {$variableRate} = {$variableAmount} {$subscription['currency']}\n";
            echo "   Total: {$totalAmount} {$subscription['currency']}\n";
            echo "   Medida: {$measure}\n\n";
            $processed++;
            continue;
        }
        
        // Insertar factura
        $insertStmt = $conn->prepare("
            INSERT INTO invoices (
                tenant_id, invoice_number, period_from, period_to,
                fixed_amount, variable_units, variable_rate, total_amount,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft')
        ");
        $insertStmt->bind_param('isssiidd', 
            $tenantId,
            $invoiceNumber,
            $periodFrom,
            $periodTo,
            $fixedAmount,
            $variableUnits,
            $variableRate,
            $totalAmount
        );
        
        if ($insertStmt->execute()) {
            $invoiceId = $conn->insert_id;
            echo "✅ Tenant #{$tenantId} ({$tenantName}): Factura #{$invoiceNumber} creada (ID: {$invoiceId})\n";
            echo "   Total: {$totalAmount} {$subscription['currency']} (Fijo: {$fixedAmount}, Variable: {$variableAmount})\n";
            $processed++;
        } else {
            echo "❌ Tenant #{$tenantId} ({$tenantName}): Error al crear factura\n";
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

/**
 * Genera número de factura único
 * Formato: INV-{tenant_id}-{YYYYMM}-{secuencial}
 */
function generateInvoiceNumber($conn, $tenantId, $periodFrom) {
    $yearMonth = date('Ym', strtotime($periodFrom));
    $prefix = "INV-{$tenantId}-{$yearMonth}";
    
    // Buscar último número de factura para este tenant y mes
    $stmt = $conn->prepare("
        SELECT invoice_number 
        FROM invoices 
        WHERE tenant_id = ? AND invoice_number LIKE ?
        ORDER BY invoice_number DESC 
        LIMIT 1
    ");
    $likePattern = $prefix . '%';
    $stmt->bind_param('is', $tenantId, $likePattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $lastInvoice = $result->fetch_assoc();
    $stmt->close();
    
    if ($lastInvoice) {
        // Extraer secuencial y aumentar
        $lastNumber = $lastInvoice['invoice_number'];
        $parts = explode('-', $lastNumber);
        $sequential = isset($parts[3]) ? (int)$parts[3] : 0;
        $sequential++;
    } else {
        $sequential = 1;
    }
    
    return "{$prefix}-" . str_pad($sequential, 3, '0', STR_PAD_LEFT);
}

