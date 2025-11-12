<?php
/**
 * Generador de PDF para Facturas
 * 
 * Genera un PDF profesional de la factura con detalle de billing
 * 
 * Requisitos opcionales:
 * - TCPDF (recomendado): composer require tecnickcom/tcpdf
 * - O usar servicio externo de conversión HTML a PDF
 * 
 * @param int $invoiceId ID de la factura
 * @param mysqli $conn Conexión a la base de datos
 * @param int|null $tenantId ID del tenant (null para Owner)
 * @return array ['success' => bool, 'file_path' => string|null, 'url' => string|null, 'error' => string|null]
 */

require_once __DIR__ . '/db_helper.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/env.php';

function generateInvoicePDFHelper($invoiceId, $conn, $tenantId = null) {
    // Obtener datos completos de la factura
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
            i.*,
            t.name as tenant_name,
            t.org_type,
            t.currency,
            t.address,
            t.tax_id,
            t.email as tenant_email,
            t.phone as tenant_phone,
            s.measure as subscription_measure
        FROM invoices i
        INNER JOIN tenants t ON i.tenant_id = t.id
        LEFT JOIN subscriptions s ON i.tenant_id = s.tenant_id AND s.status = 'active'
        WHERE {$where}
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $invoice = $result->fetch_assoc();
    $stmt->close();
    
    if (!$invoice) {
        return [
            'success' => false,
            'error' => t('invoices.not_found')
        ];
    }
    
    // Generar HTML del PDF
    $html = generateInvoiceHTML($invoice);
    
    // Intentar generar PDF
    // Opción 1: Si TCPDF está disponible
    if (class_exists('TCPDF')) {
        return generatePDFWithTCPDF($html, $invoice, $tenantId ?? $invoice['tenant_id']);
    }
    
    // Opción 2: Guardar HTML para conversión externa
    $htmlPath = saveHTMLForPDF($html, $invoiceId, $tenantId ?? $invoice['tenant_id']);
    
    // Actualizar factura con path del PDF
    $updateStmt = $conn->prepare("
        UPDATE invoices 
        SET pdf_path = ?
        WHERE id = ?
    ");
    $updateStmt->bind_param('si', $htmlPath, $invoiceId);
    $updateStmt->execute();
    $updateStmt->close();
    
    return [
        'success' => true,
        'file_path' => $htmlPath,
        'url' => getPDFUrl($htmlPath),
        'message' => t('invoices.pdf_html_generated')
    ];
}

/**
 * Genera HTML del PDF de factura
 */
function generateInvoiceHTML($invoice) {
    $currency = $invoice['currency'] ?? 'CHF';
    $fixedAmount = number_format((float)$invoice['fixed_amount'], 2, ',', '.');
    $variableAmount = number_format((float)$invoice['variable_units'] * (float)$invoice['variable_rate'], 2, ',', '.');
    $totalAmount = number_format((float)$invoice['total_amount'], 2, ',', '.');
    
    $periodFrom = date('d/m/Y', strtotime($invoice['period_from']));
    $periodTo = date('d/m/Y', strtotime($invoice['period_to']));
    $createdAt = date('d/m/Y', strtotime($invoice['created_at']));
    
    $statusLabels = [
        'draft' => 'Borrador',
        'issued' => 'Emitida',
        'paid' => 'Pagada',
        'failed' => 'Fallida',
        'canceled' => 'Cancelada'
    ];
    $statusLabel = $statusLabels[$invoice['status']] ?? $invoice['status'];
    
    $html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            color: #333;
        }
        .header { 
            border-bottom: 3px solid #0066cc; 
            padding-bottom: 20px; 
            margin-bottom: 30px; 
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .header-left {
            flex: 1;
        }
        .header-right {
            text-align: right;
        }
        .title { 
            font-size: 28px; 
            font-weight: bold; 
            margin-bottom: 5px; 
            color: #0066cc;
        }
        .invoice-number { 
            font-size: 18px; 
            color: #666; 
            margin-top: 10px;
        }
        .company-info {
            margin-top: 20px;
            font-size: 12px;
            color: #666;
        }
        .section { 
            margin-bottom: 25px; 
        }
        .section-title { 
            font-size: 16px; 
            font-weight: bold; 
            margin-bottom: 15px; 
            border-bottom: 1px solid #ddd; 
            padding-bottom: 8px; 
            color: #0066cc;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 20px; 
        }
        th, td { 
            border: 1px solid #ddd; 
            padding: 12px; 
            text-align: left; 
        }
        th { 
            background-color: #f8f9fa; 
            font-weight: bold;
            color: #333;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals {
            margin-top: 20px;
            margin-left: auto;
            width: 300px;
        }
        .totals table {
            margin: 0;
        }
        .totals td {
            border: none;
            border-top: 1px solid #ddd;
            padding: 8px 12px;
        }
        .totals td:first-child {
            text-align: right;
            font-weight: bold;
        }
        .totals .total-row {
            border-top: 2px solid #0066cc;
            font-size: 18px;
            font-weight: bold;
            color: #0066cc;
        }
        .footer { 
            margin-top: 50px; 
            padding-top: 20px; 
            border-top: 1px solid #ddd; 
            font-size: 10px; 
            color: #999; 
            text-align: center;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 10px;
        }
        .status-issued { background-color: #ffc107; color: #000; }
        .status-paid { background-color: #28a745; color: #fff; }
        .status-failed { background-color: #dc3545; color: #fff; }
        .status-draft { background-color: #6c757d; color: #fff; }
        .status-canceled { background-color: #6c757d; color: #fff; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <div class="title">FACTURA</div>
            <div class="invoice-number">' . htmlspecialchars($invoice['invoice_number']) . '</div>
            <div class="company-info">
                <strong>Workee SaaS</strong><br>
                Sistema de Gestión de Personal<br>
                www.workee.app
            </div>
        </div>
        <div class="header-right">
            <div><strong>Cliente:</strong></div>
            <div>' . htmlspecialchars($invoice['tenant_name']) . '</div>';
    
    if (!empty($invoice['address'])) {
        $html .= '<div style="margin-top: 10px; font-size: 11px;">' . nl2br(htmlspecialchars($invoice['address'])) . '</div>';
    }
    
    if (!empty($invoice['tax_id'])) {
        $html .= '<div style="margin-top: 5px; font-size: 11px;">NIF: ' . htmlspecialchars($invoice['tax_id']) . '</div>';
    }
    
    $statusClass = 'status-' . $invoice['status'];
    $html .= '<div class="status-badge ' . $statusClass . '">' . htmlspecialchars($statusLabel) . '</div>';
    
    $html .= '</div>
    </div>
    
    <div class="section">
        <div class="section-title">Período de Facturación</div>
        <table>
            <tr>
                <th>Desde</th>
                <th>Hasta</th>
                <th>Fecha de Emisión</th>
            </tr>
            <tr>
                <td>' . $periodFrom . '</td>
                <td>' . $periodTo . '</td>
                <td>' . $createdAt . '</td>
            </tr>
        </table>
    </div>
    
    <div class="section">
        <div class="section-title">Detalle de Facturación</div>
        <table>
            <tr>
                <th>Concepto</th>
                <th class="text-center">Unidades</th>
                <th class="text-right">Tarifa</th>
                <th class="text-right">Importe</th>
            </tr>
            <tr>
                <td>Cuota Fija Mensual</td>
                <td class="text-center">-</td>
                <td class="text-right">-</td>
                <td class="text-right">' . $fixedAmount . ' ' . $currency . '</td>
            </tr>';
    
    if ((int)$invoice['variable_units'] > 0) {
        $measureLabels = [
            'monthly_avg' => 'Promedio Mensual',
            'monthly_peak' => 'Pico Mensual',
            'daily' => 'Diario'
        ];
        $measureLabel = $measureLabels[$invoice['subscription_measure'] ?? 'monthly_avg'] ?? 'Variable';
        
        $html .= '<tr>
                <td>Empleados/Trabajadores Activos (' . htmlspecialchars($measureLabel) . ')</td>
                <td class="text-center">' . (int)$invoice['variable_units'] . '</td>
                <td class="text-right">' . number_format((float)$invoice['variable_rate'], 2, ',', '.') . ' ' . $currency . '</td>
                <td class="text-right">' . $variableAmount . ' ' . $currency . '</td>
            </tr>';
    }
    
    $html .= '</table>
    </div>
    
    <div class="totals">
        <table>
            <tr>
                <td>Subtotal (Fijo):</td>
                <td class="text-right">' . $fixedAmount . ' ' . $currency . '</td>
            </tr>';
    
    if ((int)$invoice['variable_units'] > 0) {
        $html .= '<tr>
                <td>Subtotal (Variable):</td>
                <td class="text-right">' . $variableAmount . ' ' . $currency . '</td>
            </tr>';
    }
    
    $html .= '<tr class="total-row">
                <td>TOTAL:</td>
                <td class="text-right">' . $totalAmount . ' ' . $currency . '</td>
            </tr>
        </table>
    </div>';
    
    if ($invoice['status'] === 'paid' && !empty($invoice['paid_at'])) {
        $paidAt = date('d/m/Y H:i', strtotime($invoice['paid_at']));
        $html .= '<div class="section">
            <p><strong>Fecha de Pago:</strong> ' . $paidAt . '</p>
        </div>';
    }
    
    $html .= '<div class="footer">
        Factura generada el ' . date('d/m/Y H:i') . ' - Workee SaaS<br>
        Esta es una factura generada automáticamente por el sistema.
    </div>
</body>
</html>';
    
    return $html;
}

/**
 * Genera PDF usando TCPDF (si está disponible)
 */
function generatePDFWithTCPDF($html, $invoice, $tenantId) {
    // Esta función requiere que TCPDF esté instalado
    // composer require tecnickcom/tcpdf
    
    if (!class_exists('TCPDF')) {
        return [
            'success' => false,
            'error' => 'TCPDF no está instalado. Instalar con: composer require tecnickcom/tcpdf'
        ];
    }
    
    try {
        require_once __DIR__ . '/../../vendor/autoload.php';
        
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $pdf->SetCreator('Workee SaaS');
        $pdf->SetAuthor($invoice['tenant_name'] ?: 'Workee');
        $pdf->SetTitle('Factura ' . $invoice['invoice_number']);
        $pdf->SetSubject('Factura');
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Guardar PDF
        $filename = 'invoice_' . $invoice['id'] . '_' . date('YmdHis') . '.pdf';
        $filepath = __DIR__ . '/../../storage/pdfs/' . $tenantId . '/' . $filename;
        
        // Crear directorio si no existe
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $pdf->Output($filepath, 'F');
        
        // Actualizar factura con path del PDF
        $conn = DBHelper::getConnection();
        $updateStmt = $conn->prepare("
            UPDATE invoices 
            SET pdf_path = ?
            WHERE id = ?
        ");
        $updateStmt->bind_param('si', $filepath, $invoice['id']);
        $updateStmt->execute();
        $updateStmt->close();
        
        return [
            'success' => true,
            'file_path' => $filepath,
            'url' => '/storage/pdfs/' . $tenantId . '/' . $filename,
            'filename' => $filename
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Guarda HTML para conversión externa
 */
function saveHTMLForPDF($html, $invoiceId, $tenantId) {
    $filename = 'invoice_' . $invoiceId . '_' . date('YmdHis') . '.html';
    $filepath = __DIR__ . '/../../storage/pdfs/' . $tenantId . '/' . $filename;
    
    // Crear directorio si no existe
    $dir = dirname($filepath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    file_put_contents($filepath, $html);
    
    return $filepath;
}

/**
 * Obtiene URL del PDF/HTML generado
 */
function getPDFUrl($filepath) {
    $relativePath = str_replace(__DIR__ . '/../../', '', $filepath);
    $baseUrl = getenv('APP_URL') ?: 'http://localhost';
    return $baseUrl . '/' . str_replace('\\', '/', $relativePath);
}

