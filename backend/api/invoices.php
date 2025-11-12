<?php
/**
 * API Facturas
 * 
 * Endpoints:
 * - GET /api/invoices - Listar facturas (con filtros y paginación)
 * - GET /api/invoices/{id} - Obtener factura
 * - GET /api/invoices/{id}/download - Descargar PDF de factura
 * - PUT /api/invoices/{id}/retry-payment - Reintentar pago
 * - PUT /api/invoices/{id}/mark-paid - Marcar como pagada (manual)
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
    
    // Procesar middleware
    $middlewareResult = TenantMiddleware::process();
    $tenantId = $middlewareResult['tenant_id'] ?? null;
    $GLOBALS['USER_ID'] = $middlewareResult['user_id'] ?? null;
    
    // Para facturas, el tenant_id puede ser null si es Owner (ver todas las facturas)
    // O puede ser específico si es tenant (ver solo sus facturas)
    
    // Verificar permisos
    $action = null;
    if ($method === 'GET' && strpos($path, '/download') === false) {
        $action = 'invoices.view';
    } elseif ($method === 'GET' && strpos($path, '/download') !== false) {
        $action = 'invoices.download';
    } elseif ($method === 'PUT' && strpos($path, '/retry-payment') !== false) {
        $action = 'invoices.retry_payment';
    } elseif ($method === 'PUT' && strpos($path, '/mark-paid') !== false) {
        $action = 'invoices.mark_paid';
    }
    
    if ($action && !RBACMiddleware::checkPermission($action)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'auth.forbidden',
            'error_key' => 'auth.forbidden',
            'message' => t('auth.forbidden')
        ]);
        exit;
    }
    
    $conn = DBHelper::getConnection();
    
    // Parsear path
    $pathParts = explode('/', trim($path, '/'));
    $invoiceId = null;
    $subAction = null;
    
    if (count($pathParts) >= 2) {
        if (is_numeric($pathParts[1])) {
            $invoiceId = (int)$pathParts[1];
            if (isset($pathParts[2])) {
                $subAction = $pathParts[2];
            }
        }
    }
    
    // Routing
    switch ($method) {
        case 'GET':
            if ($invoiceId && $subAction === 'download') {
                downloadInvoicePDF($conn, $tenantId, $invoiceId);
            } elseif ($invoiceId) {
                getInvoice($conn, $tenantId, $invoiceId);
            } else {
                listInvoices($conn, $tenantId);
            }
            break;
            
        case 'PUT':
            if ($invoiceId && $subAction === 'retry-payment') {
                retryPayment($conn, $tenantId, $invoiceId);
            } elseif ($invoiceId && $subAction === 'mark-paid') {
                markInvoicePaid($conn, $tenantId, $invoiceId);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'validation.action_required',
                    'error_key' => 'validation.action_required',
                    'message' => t('validation.action_required')
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
 * Listar facturas con filtros y paginación
 */
function listInvoices($conn, $tenantId) {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, (int)$_GET['page_size'])) : 20;
    $offset = ($page - 1) * $pageSize;
    
    // Si tenantId es null, es Owner y puede ver todas las facturas
    $where = [];
    $params = [];
    $types = '';
    
    if ($tenantId) {
        $where[] = "i.tenant_id = ?";
        $params[] = $tenantId;
        $types .= 'i';
    }
    
    if (isset($_GET['status'])) {
        $where[] = "i.status = ?";
        $params[] = $_GET['status'];
        $types .= 's';
    }
    
    if (isset($_GET['from'])) {
        $where[] = "i.period_from >= ?";
        $params[] = $_GET['from'];
        $types .= 's';
    }
    
    if (isset($_GET['to'])) {
        $where[] = "i.period_to <= ?";
        $params[] = $_GET['to'];
        $types .= 's';
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Contar total
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM invoices i
        {$whereClause}
    ");
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    // Obtener facturas
    $stmt = $conn->prepare("
        SELECT 
            i.id,
            i.tenant_id,
            i.invoice_number,
            i.period_from,
            i.period_to,
            i.fixed_amount,
            i.variable_units,
            i.variable_rate,
            i.total_amount,
            i.pdf_path,
            i.status,
            i.paid_at,
            i.created_at,
            t.name as tenant_name,
            t.currency
        FROM invoices i
        LEFT JOIN tenants t ON i.tenant_id = t.id
        {$whereClause}
        ORDER BY i.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $params[] = $pageSize;
    $params[] = $offset;
    $types .= 'ii';
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $invoices = [];
    while ($row = $result->fetch_assoc()) {
        $invoices[] = formatInvoice($row);
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $invoices,
        'page' => $page,
        'page_size' => $pageSize,
        'total' => $total,
        'total_pages' => ceil($total / $pageSize)
    ]);
}

/**
 * Obtener factura por ID
 */
function getInvoice($conn, $tenantId, $invoiceId) {
    $where = "i.id = ?";
    $params = [$invoiceId];
    $types = 'i';
    
    if ($tenantId) {
        $where .= " AND i.tenant_id = ?";
        $params[] = $tenantId;
        $types .= 'i';
    }
    
    $stmt = $conn->prepare("
        SELECT 
            i.id,
            i.tenant_id,
            i.invoice_number,
            i.period_from,
            i.period_to,
            i.fixed_amount,
            i.variable_units,
            i.variable_rate,
            i.total_amount,
            i.pdf_path,
            i.status,
            i.paid_at,
            i.created_at,
            t.name as tenant_name,
            t.currency,
            t.address,
            t.tax_id
        FROM invoices i
        LEFT JOIN tenants t ON i.tenant_id = t.id
        WHERE {$where}
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $invoice = $result->fetch_assoc();
    $stmt->close();
    
    if (!$invoice) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'invoices.not_found',
            'error_key' => 'invoices.not_found',
            'message' => t('invoices.not_found')
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => formatInvoice($invoice)
    ]);
}

/**
 * Descargar PDF de factura
 */
function downloadInvoicePDF($conn, $tenantId, $invoiceId) {
    // Verificar que existe
    $where = "i.id = ?";
    $params = [$invoiceId];
    $types = 'i';
    
    if ($tenantId) {
        $where .= " AND i.tenant_id = ?";
        $params[] = $tenantId;
        $types .= 'i';
    }
    
    $stmt = $conn->prepare("
        SELECT i.*, t.name as tenant_name, t.currency
        FROM invoices i
        LEFT JOIN tenants t ON i.tenant_id = t.id
        WHERE {$where}
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $invoice = $result->fetch_assoc();
    $stmt->close();
    
    if (!$invoice) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'invoices.not_found',
            'error_key' => 'invoices.not_found',
            'message' => t('invoices.not_found')
        ]);
        return;
    }
    
    // Si ya existe PDF, redirigir
    if (!empty($invoice['pdf_path']) && file_exists($invoice['pdf_path'])) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $invoice['invoice_number'] . '.pdf"');
        readfile($invoice['pdf_path']);
        exit;
    }
    
    // Generar PDF si no existe
    require_once __DIR__ . '/../helpers/pdf_invoice.php';
    $pdfResult = generateInvoicePDFHelper($invoiceId, $conn, $tenantId);
    
    if ($pdfResult['success'] && !empty($pdfResult['file_path'])) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $invoice['invoice_number'] . '.pdf"');
        readfile($pdfResult['file_path']);
        exit;
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'invoices.pdf_generation_failed',
            'error_key' => 'invoices.pdf_generation_failed',
            'message' => t('invoices.pdf_generation_failed')
        ]);
    }
}

/**
 * Reintentar pago de factura
 */
function retryPayment($conn, $tenantId, $invoiceId) {
    // Verificar que existe y está en estado failed
    $where = "i.id = ? AND i.status = 'failed'";
    $params = [$invoiceId];
    $types = 'i';
    
    if ($tenantId) {
        $where .= " AND i.tenant_id = ?";
        $params[] = $tenantId;
        $types .= 'i';
    }
    
    $stmt = $conn->prepare("
        SELECT * FROM invoices WHERE {$where}
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $invoice = $result->fetch_assoc();
    $stmt->close();
    
    if (!$invoice) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'invoices.cannot_retry',
            'error_key' => 'invoices.cannot_retry',
            'message' => t('invoices.cannot_retry')
        ]);
        return;
    }
    
    // Aquí se integraría con Stripe o sistema de pago
    // Por ahora, solo cambiar estado a issued para reintentar
    $updateStmt = $conn->prepare("
        UPDATE invoices 
        SET status = 'issued'
        WHERE id = ?
    ");
    $updateStmt->bind_param('i', $invoiceId);
    $updateStmt->execute();
    $updateStmt->close();
    
    // Obtener factura actualizada
    getInvoice($conn, $tenantId, $invoiceId);
}

/**
 * Marcar factura como pagada (manual)
 */
function markInvoicePaid($conn, $tenantId, $invoiceId) {
    // Verificar que existe y no está pagada
    $where = "i.id = ? AND i.status != 'paid'";
    $params = [$invoiceId];
    $types = 'i';
    
    if ($tenantId) {
        $where .= " AND i.tenant_id = ?";
        $params[] = $tenantId;
        $types .= 'i';
    }
    
    $stmt = $conn->prepare("
        SELECT * FROM invoices WHERE {$where}
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $invoice = $result->fetch_assoc();
    $stmt->close();
    
    if (!$invoice) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'invoices.cannot_mark_paid',
            'error_key' => 'invoices.cannot_mark_paid',
            'message' => t('invoices.cannot_mark_paid')
        ]);
        return;
    }
    
    // Actualizar estado
    $updateStmt = $conn->prepare("
        UPDATE invoices 
        SET status = 'paid', paid_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->bind_param('i', $invoiceId);
    $updateStmt->execute();
    $updateStmt->close();
    
    // Obtener factura actualizada
    getInvoice($conn, $tenantId, $invoiceId);
}

/**
 * Formatear factura para respuesta
 */
function formatInvoice($row) {
    $variableAmount = (float)$row['variable_units'] * (float)$row['variable_rate'];
    
    return [
        'id' => (int)$row['id'],
        'tenant_id' => (int)$row['tenant_id'],
        'tenant_name' => $row['tenant_name'],
        'invoice_number' => $row['invoice_number'],
        'period_from' => $row['period_from'],
        'period_to' => $row['period_to'],
        'fixed_amount' => (float)$row['fixed_amount'],
        'variable_units' => (int)$row['variable_units'],
        'variable_rate' => (float)$row['variable_rate'],
        'variable_amount' => $variableAmount,
        'total_amount' => (float)$row['total_amount'],
        'currency' => $row['currency'] ?? 'CHF',
        'pdf_path' => $row['pdf_path'],
        'pdf_url' => $row['pdf_path'] ? '/api/invoices/' . $row['id'] . '/download' : null,
        'status' => $row['status'],
        'paid_at' => $row['paid_at'],
        'created_at' => $row['created_at']
    ];
}

