<?php

/**
 * Script de Verificación - Sprint 3
 * 
 * Verifica que existan los datos necesarios para probar el Sprint 3:
 * - Tabla shifts existe
 * - Empresa y usuarios de empresa creados
 * - Empleados propios (own_employees) creados
 * - Grupos de trabajo (work_groups) creados
 * - API de shifts funciona
 * - ConflictValidator existe
 * - Página calendar.html existe
 * - Archivos JavaScript del calendario existen
 */

require_once __DIR__ . '/../../backend/helpers/db_helper.php';

echo "=== Verificación Sprint 3 - Calendario y Turnos ===\n\n";

$conn = DBHelper::getConnection();
if (!$conn) {
    die("❌ Error: No se pudo conectar a la base de datos\n");
}

$errors = [];
$warnings = [];
$success = [];

// 1. Verificar tabla shifts
echo "1. Verificando tabla shifts...\n";
$result = $conn->query("SHOW TABLES LIKE 'shifts'");
if ($result && $result->num_rows > 0) {
    echo "   ✅ Tabla 'shifts' existe\n";
    $success[] = "Tabla shifts";

    // Verificar estructura
    $result = $conn->query("DESCRIBE shifts");
    $fields = [];
    while ($row = $result->fetch_assoc()) {
        $fields[] = $row['Field'];
    }
    $requiredFields = ['id', 'tenant_id', 'start_at', 'end_at', 'employee_type', 'status'];
    $missingFields = array_diff($requiredFields, $fields);
    if (empty($missingFields)) {
        echo "   ✅ Estructura de tabla correcta\n";
    } else {
        echo "   ⚠️  Faltan campos: " . implode(', ', $missingFields) . "\n";
        $warnings[] = "Campos faltantes en shifts: " . implode(', ', $missingFields);
    }
} else {
    echo "   ❌ Tabla 'shifts' NO existe\n";
    echo "   💡 Ejecuta: mysql -u root -p workee < backend/db/schema_v2.sql\n";
    $errors[] = "Tabla shifts no existe";
}

// 2. Verificar tabla own_employees
echo "\n2. Verificando tabla own_employees...\n";
$result = $conn->query("SHOW TABLES LIKE 'own_employees'");
if ($result && $result->num_rows > 0) {
    echo "   ✅ Tabla 'own_employees' existe\n";
    $success[] = "Tabla own_employees";
} else {
    echo "   ❌ Tabla 'own_employees' NO existe\n";
    echo "   💡 Ejecuta: mysql -u root -p workee < backend/db/schema_v2.sql\n";
    $errors[] = "Tabla own_employees no existe";
}

// 3. Verificar tabla work_groups
echo "\n3. Verificando tabla work_groups...\n";
$result = $conn->query("SHOW TABLES LIKE 'work_groups'");
if ($result && $result->num_rows > 0) {
    echo "   ✅ Tabla 'work_groups' existe\n";
    $success[] = "Tabla work_groups";
} else {
    echo "   ❌ Tabla 'work_groups' NO existe\n";
    echo "   💡 Ejecuta: mysql -u root -p workee < backend/db/schema_v2.sql\n";
    $errors[] = "Tabla work_groups no existe";
}

// 4. Verificar Empresa
echo "\n4. Verificando Empresa...\n";
$result = $conn->query("SELECT id, name, org_type FROM tenants WHERE org_type = 'empresa' LIMIT 1");
if ($result && $result->num_rows > 0) {
    $empresa = $result->fetch_assoc();
    echo "   ✅ Empresa encontrada: ID={$empresa['id']}, Nombre={$empresa['name']}\n";
    $empresaId = $empresa['id'];
    $success[] = "Empresa existe";
} else {
    echo "   ❌ No se encontró ninguna Empresa\n";
    echo "   💡 Ejecuta: php scripts/dev/create_test_data.php\n";
    $errors[] = "No hay empresa";
    $empresaId = null;
}

// 5. Verificar usuarios de empresa
echo "\n5. Verificando usuarios de Empresa...\n";
if ($empresaId) {
    $stmt = $conn->prepare("
        SELECT u.email, tu.role 
        FROM users u
        INNER JOIN tenant_users tu ON u.id = tu.user_id
        WHERE tu.tenant_id = ? AND tu.role IN ('empresa_admin', 'planificador')
    ");
    $stmt->bind_param('i', $empresaId);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();

    if (count($users) > 0) {
        echo "   ✅ Usuarios encontrados:\n";
        foreach ($users as $user) {
            echo "      - {$user['email']} ({$user['role']})\n";
        }
        $success[] = "Usuarios de empresa";
    } else {
        echo "   ⚠️  No se encontraron usuarios de empresa\n";
        echo "   💡 Ejecuta: php scripts/dev/create_all_test_users.php\n";
        $warnings[] = "No hay usuarios de empresa";
    }
} else {
    echo "   ⚠️  No se puede verificar usuarios (falta Empresa)\n";
}

// 6. Verificar empleados propios
echo "\n6. Verificando Empleados Propios...\n";
if ($empresaId) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM own_employees WHERE tenant_id = ? AND is_active = 1");
    $stmt->bind_param('i', $empresaId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['count'];
    $stmt->close();

    echo "   ℹ️  Empleados activos encontrados: $count\n";
    if ($count == 0) {
        echo "   ⚠️  No hay empleados activos. Necesitas crear empleados para probar turnos.\n";
        echo "   💡 Crea empleados desde la UI o ejecuta script de creación (si existe)\n";
        $warnings[] = "No hay empleados propios";
    } else {
        $success[] = "Empleados propios existen";
    }
} else {
    echo "   ⚠️  No se puede verificar empleados (falta Empresa)\n";
}

// 7. Verificar grupos de trabajo
echo "\n7. Verificando Grupos de Trabajo...\n";
if ($empresaId) {
    // work_groups usa tenant_company_id, no tenant_id
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM work_groups WHERE tenant_company_id = ?");
    if (!$stmt) {
        echo "   ⚠️  Error preparando query: " . $conn->error . "\n";
    } else {
        $stmt->bind_param('i', $empresaId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $count = $row['count'];
        $stmt->close();

        echo "   ℹ️  Grupos encontrados: $count\n";
        if ($count == 0) {
            echo "   ⚠️  No hay grupos de trabajo. Los grupos son opcionales para turnos.\n";
            $warnings[] = "No hay grupos de trabajo";
        } else {
            $success[] = "Grupos de trabajo existen";
        }
    }
} else {
    echo "   ⚠️  No se puede verificar grupos (falta Empresa)\n";
}

// 8. Verificar turnos existentes
echo "\n8. Verificando Turnos existentes...\n";
if ($empresaId) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM shifts WHERE tenant_id = ?");
    $stmt->bind_param('i', $empresaId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['count'];
    $stmt->close();

    echo "   ℹ️  Turnos en BD: $count\n";
    if ($count == 0) {
        echo "   ℹ️  No hay turnos creados aún (esto es normal si es la primera vez)\n";
    }
} else {
    echo "   ⚠️  No se puede verificar turnos (falta Empresa)\n";
}

// 9. Verificar ConflictValidator
echo "\n9. Verificando ConflictValidator...\n";
$conflictValidatorPath = __DIR__ . '/../../backend/core/ConflictValidator.php';
if (file_exists($conflictValidatorPath)) {
    echo "   ✅ ConflictValidator.php existe\n";
    $success[] = "ConflictValidator existe";
} else {
    echo "   ❌ ConflictValidator.php NO existe\n";
    $errors[] = "ConflictValidator no existe";
}

// 10. Verificar página calendar.html
echo "\n10. Verificando página calendar.html...\n";
$calendarPagePath = __DIR__ . '/../../frontend/pages/calendar.html';
if (file_exists($calendarPagePath)) {
    echo "   ✅ calendar.html existe\n";
    $success[] = "Página calendar existe";
} else {
    echo "   ❌ calendar.html NO existe\n";
    $errors[] = "Página calendar no existe";
}

// 11. Verificar archivos JavaScript
echo "\n11. Verificando archivos JavaScript del calendario...\n";
$calendarJsPath = __DIR__ . '/../../frontend/js/calendar.js';
$calendarRequestBuilderPath = __DIR__ . '/../../frontend/js/calendar-request-builder.js';
if (file_exists($calendarJsPath)) {
    echo "   ✅ calendar.js existe\n";
    $success[] = "calendar.js existe";
} else {
    echo "   ❌ calendar.js NO existe\n";
    $errors[] = "calendar.js no existe";
}
if (file_exists($calendarRequestBuilderPath)) {
    echo "   ✅ calendar-request-builder.js existe\n";
    $success[] = "calendar-request-builder.js existe";
} else {
    echo "   ⚠️  calendar-request-builder.js NO existe (opcional para generación de solicitudes)\n";
    $warnings[] = "calendar-request-builder.js no existe";
}

// 12. Verificar archivo CSS
echo "\n12. Verificando archivo CSS del calendario...\n";
$calendarCssPath = __DIR__ . '/../../frontend/css/calendar.css';
if (file_exists($calendarCssPath)) {
    echo "   ✅ calendar.css existe\n";
    $success[] = "calendar.css existe";
} else {
    echo "   ⚠️  calendar.css NO existe (puede usar estilos comunes)\n";
    $warnings[] = "calendar.css no existe";
}

// 13. Verificar API shifts.php
echo "\n13. Verificando API shifts.php...\n";
$shiftsApiPath = __DIR__ . '/../../backend/api/shifts.php';
if (file_exists($shiftsApiPath)) {
    echo "   ✅ shifts.php existe\n";
    $success[] = "API shifts existe";
} else {
    echo "   ❌ shifts.php NO existe\n";
    $errors[] = "API shifts no existe";
}

// 14. Verificar traducciones
echo "\n14. Verificando traducciones...\n";
$shiftsLangPath = __DIR__ . '/../../backend/lang/es/shifts.php';
if (file_exists($shiftsLangPath)) {
    echo "   ✅ shifts.php (traducciones) existe\n";
    $success[] = "Traducciones shifts";
} else {
    echo "   ⚠️  shifts.php (traducciones) NO existe\n";
    $warnings[] = "Traducciones shifts no existen";
}

// 15. Verificar ruta en index.php
echo "\n15. Verificando ruta en index.php...\n";
$indexPath = __DIR__ . '/../../index.php';
$indexContent = file_get_contents($indexPath);
if (strpos($indexContent, "'calendar'") !== false || strpos($indexContent, '"calendar"') !== false) {
    echo "   ✅ Ruta 'calendar' configurada en index.php\n";
    $success[] = "Ruta calendar configurada";
} else {
    echo "   ⚠️  Ruta 'calendar' NO encontrada en index.php\n";
    $warnings[] = "Ruta calendar no configurada";
}

// Resumen
echo "\n=== Resumen ===\n";
echo "✅ Éxitos: " . count($success) . "\n";
echo "⚠️  Advertencias: " . count($warnings) . "\n";
echo "❌ Errores: " . count($errors) . "\n\n";

if (count($errors) > 0) {
    echo "❌ Hay errores que deben corregirse antes de probar:\n";
    foreach ($errors as $error) {
        echo "   - $error\n";
    }
    echo "\n";
}

if (count($warnings) > 0) {
    echo "⚠️  Advertencias (pueden afectar algunas funcionalidades):\n";
    foreach ($warnings as $warning) {
        echo "   - $warning\n";
    }
    echo "\n";
}

if (count($errors) == 0) {
    echo "✅ Sistema listo para pruebas básicas\n";
    echo "📋 Sigue las instrucciones en docs/COMO_PROBAR_SPRINT3.md\n";
    echo "\n";
    echo "🔧 Pasos manuales recomendados:\n";
    echo "   1. Crear al menos 1 empleado propio desde la UI o BD\n";
    echo "   2. (Opcional) Crear un grupo de trabajo\n";
    echo "   3. Acceder a http://localhost/SaSS_Workee/calendar\n";
    echo "   4. Probar crear un turno\n";
} else {
    echo "❌ Corrige los errores antes de continuar\n";
}

echo "\n";

$conn->close();
