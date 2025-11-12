<?php
/**
 * API Empleados Propios (Own Employees)
 * 
 * Endpoints:
 * - GET /api/own-employees - Listar empleados propios del tenant
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../core/TenantMiddleware.php';
require_once __DIR__ . '/../core/RBACMiddleware.php';
require_once __DIR__ . '/../helpers/db_helper.php';
require_once __DIR__ . '/../helpers/i18n.php';
require_once __DIR__ . '/../helpers/env.php';

// Deshabilitar display_errors para evitar HTML en respuestas JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $GLOBALS['API_PATH'] ?? $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '';
    
    // Procesar middleware para extraer tenant_id
    $middlewareResult = TenantMiddleware::process();
    $tenantId = $middlewareResult['tenant_id'] ?? null;
    
    if (!$tenantId) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'auth.tenant_required',
            'error_key' => 'auth.tenant_required',
            'message' => t('auth.tenant_required')
        ]);
        exit;
    }
    
    // Verificar que el tenant es una empresa (solo empresas tienen empleados propios)
    $conn = DBHelper::getConnection();
    $stmt = $conn->prepare("SELECT org_type FROM tenants WHERE id = ?");
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $tenant = $result->fetch_assoc();
    $stmt->close();
    
    if (!$tenant || $tenant['org_type'] !== 'empresa') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'employees.only_companies',
            'error_key' => 'employees.only_companies',
            'message' => t('employees.only_companies')
        ]);
        exit;
    }
    
    // Obtener user_id del middleware
    $userId = $middlewareResult['user_id'] ?? null;
    
    switch ($method) {
        case 'GET':
            // Listar empleados propios con paginación
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 100; // Por defecto 100 para selects
            $offset = ($page - 1) * $pageSize;
            
            $isActive = isset($_GET['is_active']) ? (int)$_GET['is_active'] : 1; // Por defecto solo activos
            
            // Contar total
            $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM own_employees WHERE tenant_id = ? AND is_active = ?");
            $countStmt->bind_param('ii', $tenantId, $isActive);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $total = $countResult->fetch_assoc()['total'];
            $countStmt->close();
            
            // Obtener empleados
            $stmt = $conn->prepare("
                SELECT 
                    id,
                    tenant_id,
                    user_id,
                    employee_code,
                    first_name,
                    last_name,
                    email,
                    phone,
                    is_active,
                    created_at,
                    updated_at
                FROM own_employees
                WHERE tenant_id = ? AND is_active = ?
                ORDER BY first_name, last_name
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param('iiii', $tenantId, $isActive, $pageSize, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $employees = [];
            while ($row = $result->fetch_assoc()) {
                $employees[] = [
                    'id' => (int)$row['id'],
                    'tenant_id' => (int)$row['tenant_id'],
                    'user_id' => $row['user_id'] ? (int)$row['user_id'] : null,
                    'employee_code' => $row['employee_code'],
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'full_name' => trim($row['first_name'] . ' ' . $row['last_name']),
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                    'is_active' => (bool)$row['is_active'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at']
                ];
            }
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'data' => $employees,
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => ceil($total / $pageSize)
            ]);
            break;
            
        default:
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'errors.method_not_allowed',
                'error_key' => 'errors.method_not_allowed',
                'message' => t('errors.method_not_allowed')
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'errors.internal_error',
        'error_key' => 'errors.internal_error',
        'message' => Env::isDebug() ? $e->getMessage() : t('errors.internal_error')
    ]);
    exit;
} catch (Error $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'errors.internal_error',
        'error_key' => 'errors.internal_error',
        'message' => Env::isDebug() ? $e->getMessage() : t('errors.internal_error')
    ]);
    exit;
}

