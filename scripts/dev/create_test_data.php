<?php

/**
 * Script para crear datos de prueba
 * 
 * Crea tenants, usuarios y relaciones de prueba
 * Ejecutar: php scripts/dev/create_test_data.php
 */

require_once __DIR__ . '/../../backend/helpers/db_helper.php';
require_once __DIR__ . '/../../backend/helpers/env.php';

echo "🔧 Creando datos de prueba...\n\n";

$conn = DBHelper::getConnection();

try {
    // 1. Crear tenant empresa
    echo "📦 Creando tenant Empresa...\n";
    $name1 = 'Empresa Test';
    $slug1 = 'empresa-test-' . time();
    $stmt = $conn->prepare("INSERT INTO tenants (name, slug, org_type, plan, currency, timezone, locale) VALUES (?, ?, 'empresa', 'basic', 'CHF', 'Europe/Zurich', 'es')");
    if (!$stmt) {
        throw new Exception('Error preparando query: ' . $conn->error);
    }
    $stmt->bind_param('ss', $name1, $slug1);
    if (!$stmt->execute()) {
        throw new Exception('Error ejecutando query: ' . $stmt->error);
    }
    $companyId = $conn->insert_id;
    $stmt->close();
    echo "  ✅ Empresa creada (ID: {$companyId})\n\n";

    // 2. Crear tenant temporera
    echo "📦 Creando tenant Temporera...\n";
    $name2 = 'Temporera Test';
    $slug2 = 'temporera-test-' . time();
    $stmt = $conn->prepare("INSERT INTO tenants (name, slug, org_type, plan, currency, timezone, locale) VALUES (?, ?, 'temporera', 'basic', 'CHF', 'Europe/Zurich', 'es')");
    if (!$stmt) {
        throw new Exception('Error preparando query: ' . $conn->error);
    }
    $stmt->bind_param('ss', $name2, $slug2);
    if (!$stmt->execute()) {
        throw new Exception('Error ejecutando query: ' . $stmt->error);
    }
    $agencyId = $conn->insert_id;
    $stmt->close();
    echo "  ✅ Temporera creada (ID: {$agencyId})\n\n";

    // 3. Crear suscripciones
    echo "📦 Creando suscripciones...\n";
    $stmt = $conn->prepare("INSERT INTO subscriptions (tenant_id, billing_cycle_anchor, fixed_price, variable_price_per_employee, measure) VALUES (?, CURDATE(), 49.00, 5.00, 'monthly_avg')");
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $stmt->close();

    $stmt2 = $conn->prepare("INSERT INTO subscriptions (tenant_id, billing_cycle_anchor, fixed_price, variable_price_per_employee, measure) VALUES (?, CURDATE(), 49.00, 5.00, 'monthly_avg')");
    $stmt2->bind_param('i', $agencyId);
    $stmt2->execute();
    $stmt2->close();
    echo "  ✅ Suscripciones creadas\n\n";

    // 4. Crear usuario admin empresa
    echo "👤 Creando usuario admin empresa...\n";
    $passwordHash = password_hash('password', PASSWORD_BCRYPT);

    // Verificar si el usuario ya existe
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $email = 'admin@empresa.com';
    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $existingUser = $checkResult->fetch_assoc();
    $checkStmt->close();

    if ($existingUser) {
        $adminCompanyId = $existingUser['id'];
        echo "  ℹ️  Usuario ya existe (ID: {$adminCompanyId}, Email: {$email})\n";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (email, password_hash, first_name, last_name, is_active) VALUES (?, ?, ?, ?, 1)");
        $firstName = 'Admin';
        $lastName = 'Empresa';
        $stmt->bind_param('ssss', $email, $passwordHash, $firstName, $lastName);
        if (!$stmt->execute()) {
            throw new Exception('Error creando usuario empresa: ' . $stmt->error);
        }
        $adminCompanyId = $conn->insert_id;
        $stmt->close();
        echo "  ✅ Usuario creado (ID: {$adminCompanyId}, Email: {$email})\n";
    }

    // 5. Asignar usuario a empresa (verificar si ya existe)
    $checkTenantUser = $conn->prepare("SELECT id FROM tenant_users WHERE user_id = ? AND tenant_id = ?");
    $checkTenantUser->bind_param('ii', $adminCompanyId, $companyId);
    $checkTenantUser->execute();
    $checkTenantResult = $checkTenantUser->get_result();
    $existingTenantUser = $checkTenantResult->fetch_assoc();
    $checkTenantUser->close();

    if (!$existingTenantUser) {
        $stmt = $conn->prepare("INSERT INTO tenant_users (tenant_id, user_id, role, is_active) VALUES (?, ?, 'empresa_admin', 1)");
        $stmt->bind_param('ii', $companyId, $adminCompanyId);
        if (!$stmt->execute()) {
            throw new Exception('Error asignando usuario a empresa: ' . $stmt->error);
        }
        $stmt->close();
        echo "  ✅ Usuario asignado a empresa como empresa_admin\n\n";
    } else {
        echo "  ℹ️  Usuario ya está asignado a empresa\n\n";
    }

    // 6. Crear usuario admin temporera
    echo "👤 Creando usuario admin temporera...\n";

    // Verificar si el usuario ya existe
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $email = 'admin@temporera.com';
    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $existingUser = $checkResult->fetch_assoc();
    $checkStmt->close();

    if ($existingUser) {
        $adminAgencyId = $existingUser['id'];
        echo "  ℹ️  Usuario ya existe (ID: {$adminAgencyId}, Email: {$email})\n";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (email, password_hash, first_name, last_name, is_active) VALUES (?, ?, ?, ?, 1)");
        $firstName = 'Admin';
        $lastName = 'Temporera';
        $stmt->bind_param('ssss', $email, $passwordHash, $firstName, $lastName);
        if (!$stmt->execute()) {
            throw new Exception('Error creando usuario temporera: ' . $stmt->error);
        }
        $adminAgencyId = $conn->insert_id;
        $stmt->close();
        echo "  ✅ Usuario creado (ID: {$adminAgencyId}, Email: {$email})\n";
    }

    // 7. Asignar usuario a temporera (verificar si ya existe)
    $checkTenantUser = $conn->prepare("SELECT id FROM tenant_users WHERE user_id = ? AND tenant_id = ?");
    $checkTenantUser->bind_param('ii', $adminAgencyId, $agencyId);
    $checkTenantUser->execute();
    $checkTenantResult = $checkTenantUser->get_result();
    $existingTenantUser = $checkTenantResult->fetch_assoc();
    $checkTenantUser->close();

    if (!$existingTenantUser) {
        $stmt = $conn->prepare("INSERT INTO tenant_users (tenant_id, user_id, role, is_active) VALUES (?, ?, 'temporera_admin', 1)");
        $stmt->bind_param('ii', $agencyId, $adminAgencyId);
        if (!$stmt->execute()) {
            throw new Exception('Error asignando usuario a temporera: ' . $stmt->error);
        }
        $stmt->close();
        echo "  ✅ Usuario asignado a temporera como temporera_admin\n\n";
    } else {
        echo "  ℹ️  Usuario ya está asignado a temporera\n\n";
    }

    // 8. Crear vínculo entre empresa y temporera
    echo "🔗 Creando vínculo Empresa ↔ Temporera...\n";
    $stmt = $conn->prepare("INSERT INTO agency_company_links (tenant_company_id, tenant_agency_id, status) VALUES (?, ?, 'active')");
    $stmt->bind_param('ii', $companyId, $agencyId);
    $stmt->execute();
    $linkId = $conn->insert_id;
    $stmt->close();
    echo "  ✅ Vínculo creado (ID: {$linkId})\n\n";

    echo "✅ Datos de prueba creados exitosamente!\n\n";
    echo "📋 Credenciales de acceso:\n";
    echo "  Empresa:\n";
    echo "    Email: admin@empresa.com\n";
    echo "    Password: password\n";
    echo "    Tenant ID: {$companyId}\n\n";
    echo "  Temporera:\n";
    echo "    Email: admin@temporera.com\n";
    echo "    Password: password\n";
    echo "    Tenant ID: {$agencyId}\n\n";
    echo "🌐 Accede a: http://localhost/SaSS_Workee/login\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
