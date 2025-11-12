<?php
/**
 * API Import/Export Masivo
 * 
 * Endpoints:
 * - POST /api/import-export/import - Importar datos desde CSV/XLSX
 * - GET /api/import-export/export - Exportar datos masivos a CSV/XLSX
 * - GET /api/import-export/templates - Descargar plantillas de importación
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../core/TenantMiddleware.php';
require_once __DIR__ . '/../core/RBACMiddleware.php';
require_once __DIR__ . '/../helpers/db_helper.php';
require_once __DIR__ . '/../helpers/i18n.php';

// Deshabilitar display_errors para evitar HTML en respuestas JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $GLOBALS['API_PATH'] ?? $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '';
    
    // Procesar middleware
    $middlewareResult = TenantMiddleware::process();
    $tenantId = $middlewareResult['tenant_id'] ?? null;
    $GLOBALS['USER_ID'] = $middlewareResult['user_id'] ?? null;
    $userId = $GLOBALS['USER_ID'];
    
    if (!$tenantId) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'auth.tenant_required',
            'error_key' => 'auth.tenant_required',
            'message' => t('auth.tenant_required')
        ]);
        exit;
    }
    
    // Verificar permisos (solo admin puede importar/exportar)
    RBACMiddleware::requirePermission('settings.update');
    
    $conn = DBHelper::getConnection();
    
    // Parsear path
    $pathParts = explode('/', trim($path, '/'));
    $action = $pathParts[1] ?? null;
    
    // Routing
    switch ($method) {
        case 'POST':
            if ($action === 'import') {
                handleImport($conn, $tenantId, $userId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.invalid_action',
                    'error_key' => 'validation.invalid_action',
                    'message' => t('validation.invalid_action')
                ]);
            }
            break;
            
        case 'GET':
            if ($action === 'export') {
                handleExport($conn, $tenantId, $userId);
            } elseif ($action === 'templates') {
                handleTemplates($conn, $tenantId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.invalid_action',
                    'error_key' => 'validation.invalid_action',
                    'message' => t('validation.invalid_action')
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'method_not_allowed',
                'message' => 'Método no permitido'
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'internal_error',
        'message' => $e->getMessage()
    ]);
}

/**
 * Maneja importación de datos
 */
function handleImport($conn, $tenantId, $userId) {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'import_export.file_required',
            'error_key' => 'import_export.file_required',
            'message' => t('import_export.file_required')
        ]);
        exit;
    }
    
    $file = $_FILES['file'];
    $resourceType = $_POST['resource_type'] ?? null;
    
    if (!$resourceType) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'validation.required',
            'error_key' => 'validation.required',
            'error_params' => ['field' => 'resource_type'],
            'message' => t('validation.required', ['field' => 'resource_type'])
        ]);
        exit;
    }
    
    // Validar tipo de archivo
    $allowedTypes = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    $fileType = $file['type'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileType, $allowedTypes) && !in_array($fileExtension, ['csv', 'xls', 'xlsx'])) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'import_export.invalid_file_type',
            'error_key' => 'import_export.invalid_file_type',
            'message' => t('import_export.invalid_file_type')
        ]);
        exit;
    }
    
    // Leer archivo
    $data = readImportFile($file['tmp_name'], $fileExtension);
    
    if (empty($data)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'import_export.empty_file',
            'error_key' => 'import_export.empty_file',
            'message' => t('import_export.empty_file')
        ]);
        exit;
    }
    
    // Validar y procesar según tipo de recurso
    $result = processImport($conn, $tenantId, $userId, $resourceType, $data);
    
    echo json_encode($result);
}

/**
 * Lee archivo CSV o XLSX
 */
function readImportFile($filePath, $extension) {
    $data = [];
    
    if ($extension === 'csv') {
        // Leer CSV
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return [];
        }
        
        // Leer primera línea como headers
        $headers = fgetcsv($handle, 0, ';'); // Separador punto y coma
        if ($headers === false) {
            fclose($handle);
            return [];
        }
        
        // Leer datos
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            }
        }
        
        fclose($handle);
    } else {
        // Para XLSX, por ahora solo log (requeriría librería como PhpSpreadsheet)
        error_log("XLSX import not yet implemented - file: {$filePath}");
        return [];
    }
    
    return $data;
}

/**
 * Procesa importación según tipo de recurso
 */
function processImport($conn, $tenantId, $userId, $resourceType, $data) {
    $imported = 0;
    $failed = 0;
    $errors = [];
    
    switch ($resourceType) {
        case 'employees':
            $result = importEmployees($conn, $tenantId, $userId, $data);
            break;
            
        case 'workers':
            $result = importWorkers($conn, $tenantId, $userId, $data);
            break;
            
        case 'clients':
            $result = importClients($conn, $tenantId, $userId, $data);
            break;
            
        case 'projects':
            $result = importProjects($conn, $tenantId, $userId, $data);
            break;
            
        default:
            return [
                'success' => false,
                'error' => 'import_export.unsupported_resource',
                'error_key' => 'import_export.unsupported_resource',
                'message' => t('import_export.unsupported_resource', ['type' => $resourceType])
            ];
    }
    
    return $result;
}

/**
 * Importa empleados
 */
function importEmployees($conn, $tenantId, $userId, $data) {
    $imported = 0;
    $failed = 0;
    $errors = [];
    
    foreach ($data as $index => $row) {
        $rowNum = $index + 2; // +2 porque la primera fila son headers y empezamos desde 1
        
        try {
            // Validar campos requeridos
            if (empty($row['first_name']) || empty($row['last_name']) || empty($row['email'])) {
                $errors[] = "Fila {$rowNum}: Campos requeridos faltantes (first_name, last_name, email)";
                $failed++;
                continue;
            }
            
            // Verificar si ya existe
            $checkStmt = $conn->prepare("
                SELECT id FROM own_employees
                WHERE tenant_id = ? AND email = ?
            ");
            $checkStmt->bind_param('is', $tenantId, $row['email']);
            $checkStmt->execute();
            $existing = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();
            
            if ($existing) {
                // Actualizar existente
                $updateStmt = $conn->prepare("
                    UPDATE own_employees
                    SET first_name = ?,
                        last_name = ?,
                        phone = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->bind_param('sssi', 
                    $row['first_name'],
                    $row['last_name'],
                    $row['phone'] ?? null,
                    $existing['id']
                );
                $updateStmt->execute();
                $updateStmt->close();
            } else {
                // Crear nuevo
                $insertStmt = $conn->prepare("
                    INSERT INTO own_employees (tenant_id, first_name, last_name, email, phone)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insertStmt->bind_param('issss',
                    $tenantId,
                    $row['first_name'],
                    $row['last_name'],
                    $row['email'],
                    $row['phone'] ?? null
                );
                $insertStmt->execute();
                $insertStmt->close();
            }
            
            $imported++;
        } catch (Exception $e) {
            $errors[] = "Fila {$rowNum}: " . $e->getMessage();
            $failed++;
        }
    }
    
    return [
        'success' => $imported > 0,
        'imported' => $imported,
        'failed' => $failed,
        'errors' => $errors
    ];
}

/**
 * Importa trabajadores temporeros
 */
function importWorkers($conn, $tenantId, $userId, $data) {
    $imported = 0;
    $failed = 0;
    $errors = [];
    
    foreach ($data as $index => $row) {
        $rowNum = $index + 2;
        
        try {
            if (empty($row['first_name']) || empty($row['last_name']) || empty($row['email'])) {
                $errors[] = "Fila {$rowNum}: Campos requeridos faltantes";
                $failed++;
                continue;
            }
            
            // Similar a importEmployees pero para agency_workers
            $checkStmt = $conn->prepare("
                SELECT id FROM agency_workers
                WHERE tenant_id = ? AND email = ?
            ");
            $checkStmt->bind_param('is', $tenantId, $row['email']);
            $checkStmt->execute();
            $existing = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();
            
            if ($existing) {
                $updateStmt = $conn->prepare("
                    UPDATE agency_workers
                    SET first_name = ?, last_name = ?, phone = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->bind_param('sssi', 
                    $row['first_name'],
                    $row['last_name'],
                    $row['phone'] ?? null,
                    $existing['id']
                );
                $updateStmt->execute();
                $updateStmt->close();
            } else {
                $insertStmt = $conn->prepare("
                    INSERT INTO agency_workers (tenant_id, first_name, last_name, email, phone)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insertStmt->bind_param('issss',
                    $tenantId,
                    $row['first_name'],
                    $row['last_name'],
                    $row['email'],
                    $row['phone'] ?? null
                );
                $insertStmt->execute();
                $insertStmt->close();
            }
            
            $imported++;
        } catch (Exception $e) {
            $errors[] = "Fila {$rowNum}: " . $e->getMessage();
            $failed++;
        }
    }
    
    return [
        'success' => $imported > 0,
        'imported' => $imported,
        'failed' => $failed,
        'errors' => $errors
    ];
}

/**
 * Importa clientes
 */
function importClients($conn, $tenantId, $userId, $data) {
    $imported = 0;
    $failed = 0;
    $errors = [];
    
    foreach ($data as $index => $row) {
        $rowNum = $index + 2;
        
        try {
            if (empty($row['name'])) {
                $errors[] = "Fila {$rowNum}: Campo name requerido";
                $failed++;
                continue;
            }
            
            $checkStmt = $conn->prepare("
                SELECT id FROM clients
                WHERE tenant_id = ? AND name = ?
            ");
            $checkStmt->bind_param('is', $tenantId, $row['name']);
            $checkStmt->execute();
            $existing = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();
            
            if ($existing) {
                $updateStmt = $conn->prepare("
                    UPDATE clients
                    SET contact_email = ?, contact_phone = ?, address = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->bind_param('sssi',
                    $row['contact_email'] ?? null,
                    $row['contact_phone'] ?? null,
                    $row['address'] ?? null,
                    $existing['id']
                );
                $updateStmt->execute();
                $updateStmt->close();
            } else {
                $insertStmt = $conn->prepare("
                    INSERT INTO clients (tenant_id, name, contact_email, contact_phone, address)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insertStmt->bind_param('issss',
                    $tenantId,
                    $row['name'],
                    $row['contact_email'] ?? null,
                    $row['contact_phone'] ?? null,
                    $row['address'] ?? null
                );
                $insertStmt->execute();
                $insertStmt->close();
            }
            
            $imported++;
        } catch (Exception $e) {
            $errors[] = "Fila {$rowNum}: " . $e->getMessage();
            $failed++;
        }
    }
    
    return [
        'success' => $imported > 0,
        'imported' => $imported,
        'failed' => $failed,
        'errors' => $errors
    ];
}

/**
 * Importa proyectos
 */
function importProjects($conn, $tenantId, $userId, $data) {
    $imported = 0;
    $failed = 0;
    $errors = [];
    
    foreach ($data as $index => $row) {
        $rowNum = $index + 2;
        
        try {
            if (empty($row['name']) || empty($row['client_id'])) {
                $errors[] = "Fila {$rowNum}: Campos name y client_id requeridos";
                $failed++;
                continue;
            }
            
            // Verificar que el cliente existe
            $clientStmt = $conn->prepare("SELECT id FROM clients WHERE tenant_id = ? AND id = ?");
            $clientStmt->bind_param('ii', $tenantId, $row['client_id']);
            $clientStmt->execute();
            $client = $clientStmt->get_result()->fetch_assoc();
            $clientStmt->close();
            
            if (!$client) {
                $errors[] = "Fila {$rowNum}: Cliente con ID {$row['client_id']} no existe";
                $failed++;
                continue;
            }
            
            $checkStmt = $conn->prepare("
                SELECT id FROM projects
                WHERE tenant_id = ? AND name = ? AND client_id = ?
            ");
            $checkStmt->bind_param('isi', $tenantId, $row['name'], $row['client_id']);
            $checkStmt->execute();
            $existing = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();
            
            if ($existing) {
                $updateStmt = $conn->prepare("
                    UPDATE projects
                    SET description = ?, location = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->bind_param('ssi',
                    $row['description'] ?? null,
                    $row['location'] ?? null,
                    $existing['id']
                );
                $updateStmt->execute();
                $updateStmt->close();
            } else {
                $insertStmt = $conn->prepare("
                    INSERT INTO projects (tenant_id, client_id, name, description, location)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insertStmt->bind_param('iisss',
                    $tenantId,
                    $row['client_id'],
                    $row['name'],
                    $row['description'] ?? null,
                    $row['location'] ?? null
                );
                $insertStmt->execute();
                $insertStmt->close();
            }
            
            $imported++;
        } catch (Exception $e) {
            $errors[] = "Fila {$rowNum}: " . $e->getMessage();
            $failed++;
        }
    }
    
    return [
        'success' => $imported > 0,
        'imported' => $imported,
        'failed' => $failed,
        'errors' => $errors
    ];
}

/**
 * Maneja exportación de datos
 */
function handleExport($conn, $tenantId, $userId) {
    $resourceType = $_GET['resource_type'] ?? null;
    $format = $_GET['format'] ?? 'csv';
    
    if (!$resourceType) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'validation.required',
            'error_key' => 'validation.required',
            'error_params' => ['field' => 'resource_type'],
            'message' => t('validation.required', ['field' => 'resource_type'])
        ]);
        exit;
    }
    
    // Generar export
    $data = generateExport($conn, $tenantId, $resourceType);
    
    if ($format === 'csv') {
        // Enviar CSV
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $resourceType . '_export_' . date('Y-m-d') . '.csv"');
        
        // BOM para UTF-8 (Excel compatibility)
        echo "\xEF\xBB\xBF";
        
        // Headers
        if (!empty($data)) {
            $headers = array_keys($data[0]);
            echo implode(';', $headers) . "\n";
            
            // Datos
            foreach ($data as $row) {
                echo implode(';', array_map(function($value) {
                    return '"' . str_replace('"', '""', $value) . '"';
                }, $row)) . "\n";
            }
        }
    } else {
        // JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $data
        ], JSON_PRETTY_PRINT);
    }
}

/**
 * Genera datos para exportación
 */
function generateExport($conn, $tenantId, $resourceType) {
    switch ($resourceType) {
        case 'employees':
            return exportEmployees($conn, $tenantId);
            
        case 'workers':
            return exportWorkers($conn, $tenantId);
            
        case 'clients':
            return exportClients($conn, $tenantId);
            
        case 'projects':
            return exportProjects($conn, $tenantId);
            
        case 'work_logs':
            return exportWorkLogs($conn, $tenantId);
            
        default:
            return [];
    }
}

/**
 * Exporta empleados
 */
function exportEmployees($conn, $tenantId) {
    $stmt = $conn->prepare("
        SELECT id, first_name, last_name, email, phone, created_at
        FROM own_employees
        WHERE tenant_id = ?
        ORDER BY last_name, first_name
    ");
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['id'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $row['email'],
            'phone' => $row['phone'] ?? '',
            'created_at' => $row['created_at']
        ];
    }
    $stmt->close();
    
    return $data;
}

/**
 * Exporta trabajadores
 */
function exportWorkers($conn, $tenantId) {
    $stmt = $conn->prepare("
        SELECT id, first_name, last_name, email, phone, created_at
        FROM agency_workers
        WHERE tenant_id = ?
        ORDER BY last_name, first_name
    ");
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['id'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $row['email'],
            'phone' => $row['phone'] ?? '',
            'created_at' => $row['created_at']
        ];
    }
    $stmt->close();
    
    return $data;
}

/**
 * Exporta clientes
 */
function exportClients($conn, $tenantId) {
    $stmt = $conn->prepare("
        SELECT id, name, contact_email, contact_phone, address, created_at
        FROM clients
        WHERE tenant_id = ?
        ORDER BY name
    ");
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'contact_email' => $row['contact_email'] ?? '',
            'contact_phone' => $row['contact_phone'] ?? '',
            'address' => $row['address'] ?? '',
            'created_at' => $row['created_at']
        ];
    }
    $stmt->close();
    
    return $data;
}

/**
 * Exporta proyectos
 */
function exportProjects($conn, $tenantId) {
    $stmt = $conn->prepare("
        SELECT p.id, p.client_id, c.name as client_name, p.name, p.description, p.location, p.created_at
        FROM projects p
        LEFT JOIN clients c ON p.client_id = c.id
        WHERE p.tenant_id = ?
        ORDER BY p.name
    ");
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['id'],
            'client_id' => $row['client_id'],
            'client_name' => $row['client_name'] ?? '',
            'name' => $row['name'],
            'description' => $row['description'] ?? '',
            'location' => $row['location'] ?? '',
            'created_at' => $row['created_at']
        ];
    }
    $stmt->close();
    
    return $data;
}

/**
 * Exporta work logs
 */
function exportWorkLogs($conn, $tenantId) {
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    
    $where = ["wl.tenant_id = ?"];
    $params = [$tenantId];
    $types = 'i';
    
    if ($dateFrom) {
        $where[] = "wl.date >= ?";
        $params[] = $dateFrom;
        $types .= 's';
    }
    
    if ($dateTo) {
        $where[] = "wl.date <= ?";
        $params[] = $dateTo;
        $types .= 's';
    }
    
    $whereClause = implode(' AND ', $where);
    
    $stmt = $conn->prepare("
        SELECT 
            wl.id,
            wl.date,
            wl.clock_in,
            wl.clock_out,
            wl.breaks_minutes,
            wl.status,
            COALESCE(oe.first_name, aw.first_name) as first_name,
            COALESCE(oe.last_name, aw.last_name) as last_name,
            COALESCE(oe.email, aw.email) as email,
            p.name as project_name
        FROM work_logs wl
        LEFT JOIN own_employees oe ON wl.own_employee_id = oe.id AND wl.employee_type = 'own_employee'
        LEFT JOIN agency_workers aw ON wl.agency_worker_id = aw.id AND wl.employee_type = 'agency_worker'
        LEFT JOIN projects p ON wl.project_id = p.id
        WHERE {$whereClause}
        ORDER BY wl.date DESC, wl.clock_in DESC
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['id'],
            'date' => $row['date'],
            'clock_in' => $row['clock_in'],
            'clock_out' => $row['clock_out'] ?? '',
            'breaks_minutes' => $row['breaks_minutes'],
            'status' => $row['status'],
            'first_name' => $row['first_name'] ?? '',
            'last_name' => $row['last_name'] ?? '',
            'email' => $row['email'] ?? '',
            'project_name' => $row['project_name'] ?? ''
        ];
    }
    $stmt->close();
    
    return $data;
}

/**
 * Maneja descarga de plantillas
 */
function handleTemplates($conn, $tenantId) {
    $resourceType = $_GET['resource_type'] ?? null;
    
    if (!$resourceType) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'validation.required',
            'error_key' => 'validation.required',
            'error_params' => ['field' => 'resource_type'],
            'message' => t('validation.required', ['field' => 'resource_type'])
        ]);
        exit;
    }
    
    // Generar plantilla CSV
    $headers = getTemplateHeaders($resourceType);
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="template_' . $resourceType . '.csv"');
    
    // BOM para UTF-8
    echo "\xEF\xBB\xBF";
    
    // Headers
    echo implode(';', $headers) . "\n";
    
    // Fila de ejemplo
    $example = getTemplateExample($resourceType);
    if ($example) {
        echo implode(';', array_map(function($value) {
            return '"' . str_replace('"', '""', $value) . '"';
        }, $example)) . "\n";
    }
}

/**
 * Obtiene headers de plantilla según tipo
 */
function getTemplateHeaders($resourceType) {
    switch ($resourceType) {
        case 'employees':
            return ['first_name', 'last_name', 'email', 'phone'];
            
        case 'workers':
            return ['first_name', 'last_name', 'email', 'phone'];
            
        case 'clients':
            return ['name', 'contact_email', 'contact_phone', 'address'];
            
        case 'projects':
            return ['name', 'client_id', 'description', 'location'];
            
        default:
            return [];
    }
}

/**
 * Obtiene ejemplo de plantilla
 */
function getTemplateExample($resourceType) {
    switch ($resourceType) {
        case 'employees':
            return ['Juan', 'Pérez', 'juan.perez@example.com', '+41 12 345 67 89'];
            
        case 'workers':
            return ['María', 'García', 'maria.garcia@example.com', '+41 12 345 67 90'];
            
        case 'clients':
            return ['Cliente Ejemplo', 'contacto@cliente.com', '+41 12 345 67 91', 'Calle Principal 123'];
            
        case 'projects':
            return ['Proyecto Ejemplo', '1', 'Descripción del proyecto', 'Ubicación del proyecto'];
            
        default:
            return null;
    }
}

