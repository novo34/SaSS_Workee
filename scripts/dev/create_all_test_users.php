<?php
/**
 * Script para crear todos los usuarios de prueba
 * 
 * Crea usuarios para todos los roles del sistema
 * Ejecutar: php scripts/dev/create_all_test_users.php
 */

require_once __DIR__ . '/../../backend/helpers/db_helper.php';
require_once __DIR__ . '/../../backend/helpers/env.php';

echo "🔧 Creando todos los usuarios de prueba...\n\n";

$conn = DBHelper::getConnection();

try {
    // 1. Obtener o crear tenants
    echo "📦 Verificando tenants...\n";
    
    // Buscar empresa
    $stmt = $conn->prepare("SELECT id FROM tenants WHERE org_type = 'empresa' AND slug LIKE 'empresa-test%' ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $empresa = $result->fetch_assoc();
    $stmt->close();
    
    if (!$empresa) {
        echo "  ⚠️  No se encontró empresa. Ejecuta primero: php scripts/dev/create_test_data.php\n";
        $empresaId = null;
    } else {
        $empresaId = $empresa['id'];
        echo "  ✅ Empresa encontrada (ID: {$empresaId})\n";
    }
    
    // Buscar temporera
    $stmt = $conn->prepare("SELECT id FROM tenants WHERE org_type = 'temporera' AND slug LIKE 'temporera-test%' ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $temporera = $result->fetch_assoc();
    $stmt->close();
    
    if (!$temporera) {
        echo "  ⚠️  No se encontró temporera. Ejecuta primero: php scripts/dev/create_test_data.php\n";
        $temporeraId = null;
    } else {
        $temporeraId = $temporera['id'];
        echo "  ✅ Temporera encontrada (ID: {$temporeraId})\n";
    }
    
    // Buscar tenant Owner
    $stmt = $conn->prepare("SELECT id FROM tenants WHERE slug = 'workee-system' LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $ownerTenant = $result->fetch_assoc();
    $stmt->close();
    
    if (!$ownerTenant) {
        echo "  ⚠️  No se encontró tenant Owner. Ejecuta primero: php scripts/dev/create_owner_user.php\n";
        $ownerTenantId = null;
    } else {
        $ownerTenantId = $ownerTenant['id'];
        echo "  ✅ Tenant Owner encontrado (ID: {$ownerTenantId})\n";
    }
    
    echo "\n";
    
    $passwordHash = password_hash('password', PASSWORD_BCRYPT);
    $usersCreated = 0;
    $usersSkipped = 0;
    
    // 2. Crear usuarios de Empresa
    if ($empresaId) {
        echo "🏢 Creando usuarios de Empresa...\n";
        
        $empresaUsers = [
            ['email' => 'planificador@empresa.com', 'first_name' => 'Planificador', 'last_name' => 'Test', 'role' => 'planificador'],
            ['email' => 'supervisor@empresa.com', 'first_name' => 'Supervisor', 'last_name' => 'Test', 'role' => 'supervisor'],
            ['email' => 'empleado@empresa.com', 'first_name' => 'Empleado', 'last_name' => 'Test', 'role' => 'empleado']
        ];
        
        foreach ($empresaUsers as $userData) {
            $userId = createOrGetUser($conn, $userData['email'], $passwordHash, $userData['first_name'], $userData['last_name']);
            $assigned = assignUserToTenant($conn, $userId, $empresaId, $userData['role']);
            
            if ($assigned) {
                echo "  ✅ {$userData['role']}: {$userData['email']}\n";
                $usersCreated++;
            } else {
                echo "  ℹ️  {$userData['role']}: {$userData['email']} (ya existe)\n";
                $usersSkipped++;
            }
        }
        echo "\n";
    }
    
    // 3. Crear usuarios de Temporera
    if ($temporeraId) {
        echo "🏭 Creando usuarios de Temporera...\n";
        
        $temporeraUsers = [
            ['email' => 'coordinador@temporera.com', 'first_name' => 'Coordinador', 'last_name' => 'Test', 'role' => 'coordinador'],
            ['email' => 'trabajador@temporera.com', 'first_name' => 'Trabajador', 'last_name' => 'Test', 'role' => 'trabajador']
        ];
        
        foreach ($temporeraUsers as $userData) {
            $userId = createOrGetUser($conn, $userData['email'], $passwordHash, $userData['first_name'], $userData['last_name']);
            $assigned = assignUserToTenant($conn, $userId, $temporeraId, $userData['role']);
            
            if ($assigned) {
                echo "  ✅ {$userData['role']}: {$userData['email']}\n";
                $usersCreated++;
            } else {
                echo "  ℹ️  {$userData['role']}: {$userData['email']} (ya existe)\n";
                $usersSkipped++;
            }
        }
        echo "\n";
    }
    
    echo "✅ Proceso completado!\n\n";
    echo "📊 Resumen:\n";
    echo "  - Usuarios creados: {$usersCreated}\n";
    echo "  - Usuarios ya existentes: {$usersSkipped}\n\n";
    
    echo "📋 Credenciales de acceso (todos con password: password):\n\n";
    
    if ($empresaId) {
        echo "🏢 Empresa (Tenant ID: {$empresaId}):\n";
        echo "  - admin@empresa.com (empresa_admin)\n";
        echo "  - planificador@empresa.com (planificador)\n";
        echo "  - supervisor@empresa.com (supervisor)\n";
        echo "  - empleado@empresa.com (empleado)\n\n";
    }
    
    if ($temporeraId) {
        echo "🏭 Temporera (Tenant ID: {$temporeraId}):\n";
        echo "  - admin@temporera.com (temporera_admin)\n";
        echo "  - coordinador@temporera.com (coordinador)\n";
        echo "  - trabajador@temporera.com (trabajador)\n\n";
    }
    
    if ($ownerTenantId) {
        echo "👑 Owner (Tenant ID: {$ownerTenantId}):\n";
        echo "  - owner@workee.app (owner_admin)\n\n";
    }
    
    echo "🌐 Accede a: http://localhost/SaSS_Workee/login\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();

/**
 * Crea o obtiene un usuario
 */
function createOrGetUser($conn, $email, $passwordHash, $firstName, $lastName) {
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $existing = $result->fetch_assoc();
    $checkStmt->close();
    
    if ($existing) {
        return $existing['id'];
    }
    
    $stmt = $conn->prepare("
        INSERT INTO users (email, password_hash, first_name, last_name, is_active, email_verified_at)
        VALUES (?, ?, ?, ?, 1, NOW())
    ");
    $stmt->bind_param('ssss', $email, $passwordHash, $firstName, $lastName);
    $stmt->execute();
    $userId = $conn->insert_id;
    $stmt->close();
    
    return $userId;
}

/**
 * Asigna usuario a tenant con rol
 */
function assignUserToTenant($conn, $userId, $tenantId, $role) {
    $checkStmt = $conn->prepare("
        SELECT id FROM tenant_users 
        WHERE user_id = ? AND tenant_id = ?
    ");
    $checkStmt->bind_param('ii', $userId, $tenantId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $existing = $result->fetch_assoc();
    $checkStmt->close();
    
    if ($existing) {
        return false; // Ya existe
    }
    
    $stmt = $conn->prepare("
        INSERT INTO tenant_users (tenant_id, user_id, role, is_active)
        VALUES (?, ?, ?, 1)
    ");
    $stmt->bind_param('iis', $tenantId, $userId, $role);
    $stmt->execute();
    $stmt->close();
    
    return true;
}

