<?php
/**
 * Script para verificar y corregir relaciones usuario-tenant
 */

require_once __DIR__ . '/../../backend/helpers/db_helper.php';

echo "🔍 Verificando relaciones usuario-tenant...\n\n";

$conn = DBHelper::getConnection();

// Buscar usuarios de prueba
$users = $conn->query("SELECT id, email, first_name, last_name FROM users WHERE email IN ('admin@empresa.com', 'admin@temporera.com')");

echo "Usuarios encontrados:\n";
$userIds = [];
while ($user = $users->fetch_assoc()) {
    echo "  - ID: {$user['id']}, Email: {$user['email']}, Nombre: {$user['first_name']} {$user['last_name']}\n";
    $userIds[] = $user['id'];
    
    // Verificar si tiene tenant asignado
    $tenantCheck = $conn->prepare("SELECT tenant_id, role, is_active FROM tenant_users WHERE user_id = ?");
    $tenantCheck->bind_param('i', $user['id']);
    $tenantCheck->execute();
    $result = $tenantCheck->get_result();
    $tenantUser = $result->fetch_assoc();
    $tenantCheck->close();
    
    if ($tenantUser) {
        echo "    ✅ Tiene tenant asignado: ID {$tenantUser['tenant_id']}, Rol: {$tenantUser['role']}, Activo: {$tenantUser['is_active']}\n";
    } else {
        echo "    ❌ NO tiene tenant asignado\n";
        
        // Buscar tenant correspondiente
        $orgType = strpos($user['email'], 'empresa') !== false ? 'empresa' : 'temporera';
        $tenantQuery = $conn->prepare("SELECT id FROM tenants WHERE org_type = ? ORDER BY id DESC LIMIT 1");
        $tenantQuery->bind_param('s', $orgType);
        $tenantQuery->execute();
        $tenantResult = $tenantQuery->get_result();
        $tenant = $tenantResult->fetch_assoc();
        $tenantQuery->close();
        
        if ($tenant) {
            echo "    🔧 Asignando tenant ID {$tenant['id']}...\n";
            $role = $orgType === 'empresa' ? 'empresa_admin' : 'temporera_admin';
            $insert = $conn->prepare("INSERT INTO tenant_users (tenant_id, user_id, role, is_active) VALUES (?, ?, ?, 1)");
            $insert->bind_param('iis', $tenant['id'], $user['id'], $role);
            if ($insert->execute()) {
                echo "    ✅ Tenant asignado correctamente\n";
            } else {
                echo "    ❌ Error al asignar: " . $insert->error . "\n";
            }
            $insert->close();
        } else {
            echo "    ❌ No se encontró tenant de tipo {$orgType}\n";
        }
    }
    echo "\n";
}

$conn->close();
echo "✅ Verificación completada\n";

