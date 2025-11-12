<?php
/**
 * Script para crear datos de prueba necesarios para Sprint 3
 * 
 * Crea:
 * 1. Usuarios de empresa (si no existen)
 * 2. Empleados propios (necesarios para crear turnos)
 * 3. Grupos de trabajo (opcional pero recomendado)
 * 
 * Ejecutar: php scripts/dev/create_sprint3_test_data.php
 */

require_once __DIR__ . '/../../backend/helpers/db_helper.php';
require_once __DIR__ . '/../../backend/helpers/env.php';

echo "🔧 Creando datos de prueba para Sprint 3...\n\n";

$conn = DBHelper::getConnection();

try {
    // 1. Verificar/Crear usuarios de empresa
    echo "👥 Paso 1: Verificando usuarios de empresa...\n";
    
    // Buscar empresa (excluir Workee System que es owner)
    $stmt = $conn->prepare("SELECT id, name FROM tenants WHERE org_type = 'empresa' AND slug NOT LIKE 'workee-system%' ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $empresa = $result->fetch_assoc();
    $stmt->close();
    
    if (!$empresa) {
        echo "  ⚠️  No se encontró empresa. Ejecuta primero: php scripts/dev/create_test_data.php\n";
        exit(1);
    }
    
    $empresaId = $empresa['id'];
    echo "  ✅ Empresa encontrada: {$empresa['name']} (ID: {$empresaId})\n";
    
    // Verificar si existen usuarios
    $stmt = $conn->prepare("
        SELECT u.id, u.email, tu.role 
        FROM users u
        INNER JOIN tenant_users tu ON u.id = tu.user_id
        WHERE tu.tenant_id = ? AND tu.role IN ('empresa_admin', 'planificador')
        LIMIT 1
    ");
    $stmt->bind_param('i', $empresaId);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();
    $stmt->close();
    
    if (!$usuario) {
        echo "  ⚠️  No hay usuarios de empresa. Ejecutando create_all_test_users.php...\n";
        // Ejecutar script de creación de usuarios
        exec('php ' . __DIR__ . '/create_all_test_users.php', $output, $returnCode);
        if ($returnCode === 0) {
            echo "  ✅ Usuarios creados correctamente\n";
        } else {
            echo "  ❌ Error al crear usuarios. Ejecuta manualmente: php scripts/dev/create_all_test_users.php\n";
        }
    } else {
        echo "  ✅ Usuario encontrado: {$usuario['email']} (rol: {$usuario['role']})\n";
    }
    
    // 2. Crear empleados propios
    echo "\n👷 Paso 2: Creando empleados propios...\n";
    
    // Verificar si ya existen empleados
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM own_employees WHERE tenant_id = ?");
    $stmt->bind_param('i', $empresaId);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    
    if ($count > 0) {
        echo "  ✅ Ya existen {$count} empleado(s) propio(s)\n";
    } else {
        // Crear 3 empleados de prueba
        $empleados = [
            [
                'first_name' => 'Juan',
                'last_name' => 'Pérez',
                'email' => 'juan.perez@empresa.com',
                'phone' => '+41 123 456 789',
                'employee_code' => 'EMP001',
                'skills_json' => json_encode(['Construcción', 'Albañilería', 'Español'])
            ],
            [
                'first_name' => 'María',
                'last_name' => 'García',
                'email' => 'maria.garcia@empresa.com',
                'phone' => '+41 123 456 790',
                'employee_code' => 'EMP002',
                'skills_json' => json_encode(['Carpintería', 'Alemán', 'Inglés'])
            ],
            [
                'first_name' => 'Carlos',
                'last_name' => 'López',
                'email' => 'carlos.lopez@empresa.com',
                'phone' => '+41 123 456 791',
                'employee_code' => 'EMP003',
                'skills_json' => json_encode(['Electricidad', 'Español', 'Francés'])
            ]
        ];
        
        $stmt = $conn->prepare("
            INSERT INTO own_employees (
                tenant_id, 
                employee_code,
                first_name, 
                last_name, 
                email, 
                phone,
                skills_json,
                is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $created = 0;
        foreach ($empleados as $emp) {
            $stmt->bind_param(
                'issssss',
                $empresaId,
                $emp['employee_code'],
                $emp['first_name'],
                $emp['last_name'],
                $emp['email'],
                $emp['phone'],
                $emp['skills_json']
            );
            
            if ($stmt->execute()) {
                $created++;
                echo "  ✅ Empleado creado: {$emp['first_name']} {$emp['last_name']} ({$emp['employee_code']})\n";
            } else {
                echo "  ⚠️  Error al crear empleado: {$emp['first_name']} {$emp['last_name']}\n";
            }
        }
        $stmt->close();
        
        if ($created > 0) {
            echo "  ✅ Total: {$created} empleado(s) creado(s)\n";
        }
    }
    
    // 3. Crear grupos de trabajo (opcional)
    echo "\n👥 Paso 3: Creando grupos de trabajo...\n";
    
    // Verificar si ya existen grupos
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM work_groups WHERE tenant_company_id = ?");
    $stmt->bind_param('i', $empresaId);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    
    if ($count > 0) {
        echo "  ✅ Ya existen {$count} grupo(s) de trabajo\n";
    } else {
        // Obtener el primer empleado como líder
        $stmt = $conn->prepare("SELECT id FROM own_employees WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
        $stmt->bind_param('i', $empresaId);
        $stmt->execute();
        $result = $stmt->get_result();
        $leader = $result->fetch_assoc();
        $stmt->close();
        
        if ($leader) {
            // Crear un grupo de trabajo (usando campos correctos de la tabla)
            $stmt = $conn->prepare("
                INSERT INTO work_groups (
                    tenant_company_id,
                    name,
                    leader_employee_id
                ) VALUES (?, ?, ?)
            ");
            
            $groupName = 'Grupo Construcción';
            
            $stmt->bind_param(
                'isi',
                $empresaId,
                $groupName,
                $leader['id']
            );
            
            if ($stmt->execute()) {
                $groupId = $conn->insert_id;
                echo "  ✅ Grupo creado: {$groupName} (ID: {$groupId})\n";
                echo "  ✅ Líder asignado: Empleado ID {$leader['id']}\n";
            } else {
                echo "  ⚠️  Error al crear grupo: " . $conn->error . "\n";
            }
            $stmt->close();
        } else {
            echo "  ⚠️  No hay empleados disponibles para crear grupo. Se requiere al menos 1 empleado como líder.\n";
        }
    }
    
    // Resumen final
    echo "\n📊 Resumen:\n";
    echo "  ✅ Empresa: {$empresa['name']} (ID: {$empresaId})\n";
    
    // Contar empleados
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM own_employees WHERE tenant_id = ?");
    $stmt->bind_param('i', $empresaId);
    $stmt->execute();
    $result = $stmt->get_result();
    $empleadosCount = $result->fetch_assoc()['count'];
    $stmt->close();
    echo "  ✅ Empleados propios: {$empleadosCount}\n";
    
    // Contar grupos
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM work_groups WHERE tenant_company_id = ?");
    $stmt->bind_param('i', $empresaId);
    $stmt->execute();
    $result = $stmt->get_result();
    $gruposCount = $result->fetch_assoc()['count'];
    $stmt->close();
    echo "  ✅ Grupos de trabajo: {$gruposCount}\n";
    
    echo "\n✅ Datos de prueba para Sprint 3 creados correctamente!\n";
    echo "\n📝 Próximos pasos:\n";
    echo "  1. Login como admin@empresa.com / password\n";
    echo "  2. Acceder a http://localhost/SaSS_Workee/calendar\n";
    echo "  3. Crear turnos asignando a los empleados creados\n";
    echo "  4. Probar drag & drop y validaciones\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "   Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

