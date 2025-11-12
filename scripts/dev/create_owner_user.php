<?php
/**
 * Script para crear usuario Owner Admin
 * 
 * El Owner es un usuario especial que gestiona todo el sistema SaaS
 * No está limitado a un tenant específico, pero necesita uno para el sistema de autenticación
 * 
 * Ejecutar: php scripts/dev/create_owner_user.php
 */

require_once __DIR__ . '/../../backend/helpers/db_helper.php';
require_once __DIR__ . '/../../backend/helpers/env.php';

echo "🔧 Creando usuario Owner Admin...\n\n";

$conn = DBHelper::getConnection();

try {
    // 1. Verificar si ya existe un usuario owner
    $checkStmt = $conn->prepare("
        SELECT u.id, u.email, tu.tenant_id, tu.role
        FROM users u
        INNER JOIN tenant_users tu ON u.id = tu.user_id
        WHERE tu.role = 'owner_admin'
        LIMIT 1
    ");
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $existingOwner = $result->fetch_assoc();
    $checkStmt->close();
    
    if ($existingOwner) {
        echo "ℹ️  Usuario Owner ya existe:\n";
        echo "  - Email: {$existingOwner['email']}\n";
        echo "  - User ID: {$existingOwner['id']}\n";
        echo "  - Tenant ID: {$existingOwner['tenant_id']}\n";
        echo "  - Password: password (si es el usuario por defecto)\n\n";
        echo "✅ Usa estas credenciales para acceder como Owner\n";
        exit(0);
    }
    
    // 2. Crear o usar tenant especial para Owner (tenant_id = 0 o crear uno especial)
    // Por ahora, crearemos un tenant especial llamado "Workee System"
    echo "📦 Creando tenant especial para Owner...\n";
    $stmt = $conn->prepare("SELECT id FROM tenants WHERE slug = 'workee-system' LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $ownerTenant = $result->fetch_assoc();
    $stmt->close();
    
    $ownerTenantId = null;
    if ($ownerTenant) {
        $ownerTenantId = $ownerTenant['id'];
        echo "  ℹ️  Tenant Owner ya existe (ID: {$ownerTenantId})\n\n";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO tenants (name, slug, org_type, plan, currency, timezone, locale, is_active)
            VALUES ('Workee System', 'workee-system', 'empresa', 'enterprise', 'CHF', 'Europe/Zurich', 'es', 1)
        ");
        $stmt->execute();
        $ownerTenantId = $conn->insert_id;
        $stmt->close();
        echo "  ✅ Tenant Owner creado (ID: {$ownerTenantId})\n\n";
    }
    
    // 3. Crear usuario Owner
    echo "👤 Creando usuario Owner Admin...\n";
    $passwordHash = password_hash('password', PASSWORD_BCRYPT);
    $email = 'owner@workee.app';
    $firstName = 'Owner';
    $lastName = 'Admin';
    
    // Verificar si el usuario ya existe
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $existingUser = $result->fetch_assoc();
    $checkStmt->close();
    
    if ($existingUser) {
        $ownerUserId = $existingUser['id'];
        echo "  ℹ️  Usuario ya existe (ID: {$ownerUserId})\n";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO users (email, password_hash, first_name, last_name, is_active, email_verified_at)
            VALUES (?, ?, ?, ?, 1, NOW())
        ");
        $stmt->bind_param('ssss', $email, $passwordHash, $firstName, $lastName);
        if (!$stmt->execute()) {
            throw new Exception('Error creando usuario owner: ' . $stmt->error);
        }
        $ownerUserId = $conn->insert_id;
        $stmt->close();
        echo "  ✅ Usuario creado (ID: {$ownerUserId}, Email: {$email})\n";
    }
    
    // 4. Asignar rol owner_admin al tenant especial
    echo "🔐 Asignando rol owner_admin...\n";
    $checkTenantUser = $conn->prepare("
        SELECT id FROM tenant_users 
        WHERE user_id = ? AND tenant_id = ?
    ");
    $checkTenantUser->bind_param('ii', $ownerUserId, $ownerTenantId);
    $checkTenantUser->execute();
    $result = $checkTenantUser->get_result();
    $existingTenantUser = $result->fetch_assoc();
    $checkTenantUser->close();
    
    if (!$existingTenantUser) {
        $stmt = $conn->prepare("
            INSERT INTO tenant_users (tenant_id, user_id, role, is_active)
            VALUES (?, ?, 'owner_admin', 1)
        ");
        $stmt->bind_param('ii', $ownerTenantId, $ownerUserId);
        if (!$stmt->execute()) {
            throw new Exception('Error asignando rol owner: ' . $stmt->error);
        }
        $stmt->close();
        echo "  ✅ Rol owner_admin asignado\n\n";
    } else {
        echo "  ℹ️  Usuario ya tiene rol owner_admin\n\n";
    }
    
    echo "✅ Usuario Owner creado exitosamente!\n\n";
    echo "📋 Credenciales de acceso:\n";
    echo "  Email: {$email}\n";
    echo "  Password: password\n";
    echo "  Tenant ID: {$ownerTenantId}\n";
    echo "  Role: owner_admin\n\n";
    echo "🌐 Accede a: http://localhost/SaSS_Workee/login\n";
    echo "📊 Dashboard Owner: http://localhost/SaSS_Workee/owner/dashboard\n\n";
    echo "⚠️  NOTA: El Owner puede gestionar todos los tenants del sistema\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();

