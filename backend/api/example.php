<?php

/**
 * Ejemplo de API endpoint
 * 
 * Muestra cómo usar TenantMiddleware y RBACMiddleware
 * Este archivo es solo de referencia, eliminar en producción
 */

header('Content-Type: application/json');

// Cargar dependencias
require_once __DIR__ . '/../core/TenantMiddleware.php';
require_once __DIR__ . '/../core/RBACMiddleware.php';
require_once __DIR__ . '/../helpers/db_helper.php';
require_once __DIR__ . '/../helpers/i18n.php';

try {
    // 1. Procesar tenant (OBLIGATORIO)
    $context = TenantMiddleware::process();
    $tenantId = $context['tenant_id'];
    $userId = $context['user_id'];

    // 2. Verificar método HTTP
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            // Verificar permiso de lectura
            RBACMiddleware::requirePermission('shifts.view');

            // Ejemplo: obtener turnos del tenant
            $shifts = DBHelper::selectWithTenant('shifts', $tenantId, [], 'start_at', 'ASC', 20, 0);

            echo json_encode([
                'success' => true,
                'data' => $shifts,
                'total' => count($shifts)
            ]);
            break;

        case 'POST':
            // Verificar permiso de escritura
            RBACMiddleware::requirePermission('shifts.create');

            // Obtener datos del body
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input) {
                throw new Exception('Datos inválidos');
            }

            // Validar datos (ejemplo simplificado)
            if (empty($input['start_at']) || empty($input['end_at'])) {
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.required',
                    'error_key' => 'validation.required',
                    'error_params' => ['field' => 'start_at, end_at'],
                    'message' => t('validation.required', ['field' => 'start_at, end_at'])
                ]);
                http_response_code(422);
                exit;
            }

            // Insertar con tenant_id (OBLIGATORIO)
            $data = [
                'start_at' => $input['start_at'],
                'end_at' => $input['end_at'],
                'location' => $input['location'] ?? null,
                'employee_type' => $input['employee_type'] ?? 'own_employee',
                'own_employee_id' => $input['own_employee_id'] ?? null,
                'agency_worker_id' => $input['agency_worker_id'] ?? null,
                'status' => 'planned'
            ];

            $id = DBHelper::insertWithTenant('shifts', $tenantId, $data);

            echo json_encode([
                'success' => true,
                'data' => ['id' => $id],
                'message' => t('common.create_success')
            ]);
            break;

        case 'PUT':
        case 'PATCH':
            // Verificar permiso de actualización
            RBACMiddleware::requirePermission('shifts.update');

            // Obtener ID de la URL o body
            $id = $_GET['id'] ?? json_decode(file_get_contents('php://input'), true)['id'] ?? null;

            if (!$id) {
                throw new Exception('ID requerido');
            }

            $input = json_decode(file_get_contents('php://input'), true);

            // Actualizar con tenant_id (OBLIGATORIO)
            $updated = DBHelper::updateWithTenant('shifts', $tenantId, $id, $input);

            if (!$updated) {
                throw new Exception('No se pudo actualizar');
            }

            echo json_encode([
                'success' => true,
                'message' => t('common.update_success')
            ]);
            break;

        case 'DELETE':
            // Verificar permiso de eliminación
            RBACMiddleware::requirePermission('shifts.delete');

            $id = $_GET['id'] ?? null;

            if (!$id) {
                throw new Exception('ID requerido');
            }

            // Eliminar con tenant_id (OBLIGATORIO)
            $deleted = DBHelper::deleteWithTenant('shifts', $tenantId, $id);

            if (!$deleted) {
                throw new Exception('No se pudo eliminar');
            }

            echo json_encode([
                'success' => true,
                'message' => t('common.delete_success')
            ]);
            break;

        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'method_not_allowed',
                'error_key' => 'errors.method_not_allowed',
                'message' => 'Método no permitido'
            ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'internal_error',
        'error_key' => 'errors.internal_error',
        'message' => $e->getMessage()
    ]);
}
