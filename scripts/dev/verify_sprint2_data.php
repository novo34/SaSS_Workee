<?php

/**
 * Script de Verificación - Sprint 2
 * 
 * Verifica que existan los datos necesarios para probar el Sprint 2:
 * - Empresa y Temporera creadas
 * - Vínculo activo entre ellas
 * - Trabajadores en la Temporera
 * - Tablas de solicitudes y propuestas existentes
 */

require_once __DIR__ . '/../../backend/helpers/db_helper.php';

echo "=== Verificación Sprint 2 - Solicitudes y Propuestas ===\n\n";

$conn = DBHelper::getConnection();
if (!$conn) {
    die("❌ Error: No se pudo conectar a la base de datos\n");
}

// 1. Verificar tablas
echo "1. Verificando tablas...\n";
$tables = ['staffing_requests', 'staffing_proposals', 'staffing_request_targets', 'agency_workers'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "   ✅ Tabla '$table' existe\n";
    } else {
        echo "   ❌ Tabla '$table' NO existe\n";
    }
}

// 2. Verificar Empresa
echo "\n2. Verificando Empresa...\n";
$result = $conn->query("SELECT id, name, org_type FROM tenants WHERE org_type = 'empresa' LIMIT 1");
if ($result && $result->num_rows > 0) {
    $empresa = $result->fetch_assoc();
    echo "   ✅ Empresa encontrada: ID={$empresa['id']}, Nombre={$empresa['name']}\n";
    $empresaId = $empresa['id'];
} else {
    echo "   ❌ No se encontró ninguna Empresa\n";
    echo "   💡 Ejecuta: php scripts/dev/create_test_data.php\n";
    $empresaId = null;
}

// 3. Verificar Temporera
echo "\n3. Verificando Temporera...\n";
$result = $conn->query("SELECT id, name, org_type FROM tenants WHERE org_type = 'temporera' LIMIT 1");
if ($result && $result->num_rows > 0) {
    $temporera = $result->fetch_assoc();
    echo "   ✅ Temporera encontrada: ID={$temporera['id']}, Nombre={$temporera['name']}\n";
    $temporeraId = $temporera['id'];
} else {
    echo "   ❌ No se encontró ninguna Temporera\n";
    echo "   💡 Ejecuta: php scripts/dev/create_test_data.php\n";
    $temporeraId = null;
}

// 4. Verificar Vínculo
echo "\n4. Verificando Vínculo Empresa ↔ Temporera...\n";
if ($empresaId && $temporeraId) {
    $stmt = $conn->prepare("SELECT id, status FROM agency_company_links WHERE tenant_company_id = ? AND tenant_agency_id = ?");
    $stmt->bind_param('ii', $empresaId, $temporeraId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $link = $result->fetch_assoc();
        echo "   ✅ Vínculo encontrado: ID={$link['id']}, Status={$link['status']}\n";
        if ($link['status'] !== 'active') {
            echo "   ⚠️  Vínculo NO está activo. Debe estar en 'active' para funcionar.\n";
        }
    } else {
        echo "   ❌ No se encontró vínculo entre Empresa (ID=$empresaId) y Temporera (ID=$temporeraId)\n";
        echo "   💡 Crea un vínculo desde: http://localhost/SaSS_Workee/agency-company-links\n";
    }
    $stmt->close();
} else {
    echo "   ⚠️  No se puede verificar vínculo (falta Empresa o Temporera)\n";
}

// 5. Verificar Trabajadores
echo "\n5. Verificando Trabajadores en Temporera...\n";
if ($temporeraId) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM agency_workers WHERE tenant_id = ? AND is_active = 1");
    $stmt->bind_param('i', $temporeraId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['count'];
    echo "   ✅ Trabajadores activos encontrados: $count\n";
    if ($count == 0) {
        echo "   ⚠️  No hay trabajadores activos. El auto-matching no funcionará.\n";
        echo "   💡 Crea trabajadores desde la UI o directamente en BD:\n";
        echo "      INSERT INTO agency_workers (tenant_id, first_name, last_name, is_active) VALUES ($temporeraId, 'Juan', 'Pérez', 1);\n";
    }
    $stmt->close();
} else {
    echo "   ⚠️  No se puede verificar trabajadores (falta Temporera)\n";
}

// 6. Verificar Solicitudes existentes
echo "\n6. Verificando Solicitudes existentes...\n";
$result = $conn->query("SELECT COUNT(*) as count FROM staffing_requests");
if ($result) {
    $row = $result->fetch_assoc();
    $count = $row['count'];
    echo "   ℹ️  Solicitudes en BD: $count\n";
}

// 7. Verificar Propuestas existentes
echo "\n7. Verificando Propuestas existentes...\n";
$result = $conn->query("SELECT COUNT(*) as count FROM staffing_proposals");
if ($result) {
    $row = $result->fetch_assoc();
    $count = $row['count'];
    echo "   ℹ️  Propuestas en BD: $count\n";
}

// 8. Verificar MatchingService
echo "\n8. Verificando MatchingService...\n";
$matchingServicePath = __DIR__ . '/../../backend/core/MatchingService.php';
if (file_exists($matchingServicePath)) {
    echo "   ✅ MatchingService.php existe\n";
} else {
    echo "   ❌ MatchingService.php NO existe\n";
}

echo "\n=== Resumen ===\n";
echo "✅ Si todos los checks pasan, puedes probar el Sprint 2\n";
echo "📋 Sigue las instrucciones en docs/COMO_PROBAR_SPRINT2.md\n";
echo "\n";

$conn->close();
