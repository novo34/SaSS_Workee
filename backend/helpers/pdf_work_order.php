<?php
/**
 * Generador de PDF para Órdenes de Trabajo
 * 
 * Genera un PDF profesional de la OT con QR para acceso rápido
 * 
 * Requisitos opcionales:
 * - TCPDF (recomendado): composer require tecnickcom/tcpdf
 * - O usar servicio externo de conversión HTML a PDF
 * 
 * @param int $workOrderId ID de la orden de trabajo
 * @param mysqli $conn Conexión a la base de datos
 * @param int $tenantId ID del tenant
 * @return array ['success' => bool, 'file_path' => string|null, 'url' => string|null, 'error' => string|null]
 */

require_once __DIR__ . '/db_helper.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/env.php';

function generateWorkOrderPDFHelper($workOrderId, $conn, $tenantId) {
    // Obtener datos completos de la OT
    $stmt = $conn->prepare("
        SELECT 
            wo.*,
            c.name as client_name,
            c.email as client_email,
            c.phone as client_phone,
            p.name as project_name,
            t.name as tenant_name
        FROM work_orders wo
        LEFT JOIN clients c ON wo.client_id = c.id
        LEFT JOIN projects p ON wo.project_id = p.id
        LEFT JOIN tenants t ON wo.tenant_id = t.id
        WHERE wo.id = ? AND wo.tenant_id = ?
    ");
    $stmt->bind_param('ii', $workOrderId, $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $workOrder = $result->fetch_assoc();
    $stmt->close();
    
    if (!$workOrder) {
        return [
            'success' => false,
            'error' => t('work_orders.not_found')
        ];
    }
    
    // Obtener tareas
    $stmt = $conn->prepare("SELECT * FROM work_order_tasks WHERE work_order_id = ? ORDER BY pos ASC");
    $stmt->bind_param('i', $workOrderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $tasks = [];
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
    $stmt->close();
    
    // Obtener checklist
    $stmt = $conn->prepare("SELECT * FROM work_order_checklists WHERE work_order_id = ?");
    $stmt->bind_param('i', $workOrderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $checklist = [];
    while ($row = $result->fetch_assoc()) {
        $checklist[] = $row;
    }
    $stmt->close();
    
    // Obtener materiales
    $stmt = $conn->prepare("SELECT * FROM work_order_materials WHERE work_order_id = ?");
    $stmt->bind_param('i', $workOrderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $materials = [];
    while ($row = $result->fetch_assoc()) {
        $materials[] = $row;
    }
    $stmt->close();
    
    // Generar token temporal para QR (válido por 7 días)
    $qrToken = generateQRToken($workOrderId, $tenantId);
    $qrUrl = getQRUrl($workOrderId, $qrToken);
    
    // Generar HTML del PDF
    $html = generateWorkOrderHTML($workOrder, $tasks, $checklist, $materials, $qrUrl);
    
    // Intentar generar PDF
    // Opción 1: Si TCPDF está disponible
    if (class_exists('TCPDF')) {
        return generatePDFWithTCPDF($html, $workOrder, $tenantId);
    }
    
    // Opción 2: Guardar HTML para conversión externa
    $htmlPath = saveHTMLForPDF($html, $workOrderId, $tenantId);
    
    return [
        'success' => true,
        'html_path' => $htmlPath,
        'url' => getPDFUrl($htmlPath),
        'message' => t('work_orders.pdf_html_generated')
    ];
}

/**
 * Genera token temporal para QR
 */
function generateQRToken($workOrderId, $tenantId) {
    $expires = time() + (7 * 24 * 60 * 60); // 7 días
    $data = [
        'work_order_id' => $workOrderId,
        'tenant_id' => $tenantId,
        'expires' => $expires
    ];
    
    // Usar JWT_SECRET si está disponible
    $secret = getenv('JWT_SECRET') ?: 'workee-dev-secret';
    $token = base64_encode(json_encode($data)) . '.' . hash_hmac('sha256', json_encode($data), $secret);
    
    return $token;
}

/**
 * Genera URL para acceso rápido via QR
 */
function getQRUrl($workOrderId, $token) {
    $baseUrl = getenv('APP_URL') ?: 'http://localhost';
    return $baseUrl . '/work-orders/view?token=' . urlencode($token);
}

/**
 * Genera HTML del PDF
 */
function generateWorkOrderHTML($workOrder, $tasks, $checklist, $materials, $qrUrl) {
    $html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 24px; font-weight: bold; margin-bottom: 5px; }
        .code { font-size: 14px; color: #666; }
        .section { margin-bottom: 20px; }
        .section-title { font-size: 16px; font-weight: bold; margin-bottom: 10px; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .qr-code { text-align: center; margin-top: 20px; }
        .footer { margin-top: 30px; font-size: 10px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">' . htmlspecialchars($workOrder['title']) . '</div>
        <div class="code">' . htmlspecialchars($workOrder['code'] ?: 'OT-' . $workOrder['id']) . '</div>
    </div>
    
    <div class="section">
        <div class="section-title">Información General</div>
        <table>
            <tr><th>Cliente</th><td>' . htmlspecialchars($workOrder['client_name'] ?: '-') . '</td></tr>
            <tr><th>Proyecto</th><td>' . htmlspecialchars($workOrder['project_name'] ?: '-') . '</td></tr>
            <tr><th>Ubicación</th><td>' . htmlspecialchars($workOrder['location'] ?: '-') . '</td></tr>
            <tr><th>Estado</th><td>' . htmlspecialchars($workOrder['status']) . '</td></tr>
            <tr><th>Inicio Planificado</th><td>' . htmlspecialchars($workOrder['start_planned'] ?: '-') . '</td></tr>
            <tr><th>Fin Planificado</th><td>' . htmlspecialchars($workOrder['end_planned'] ?: '-') . '</td></tr>
        </table>
    </div>';
    
    if ($workOrder['scope_text']) {
        $html .= '<div class="section">
            <div class="section-title">Alcance</div>
            <p>' . nl2br(htmlspecialchars($workOrder['scope_text'])) . '</p>
        </div>';
    }
    
    if (!empty($tasks)) {
        $html .= '<div class="section">
            <div class="section-title">Tareas</div>
            <table>
                <tr><th>#</th><th>Nombre</th><th>Descripción</th><th>Horas Estimadas</th></tr>';
        foreach ($tasks as $task) {
            $html .= '<tr>
                <td>' . htmlspecialchars($task['pos']) . '</td>
                <td>' . htmlspecialchars($task['name']) . '</td>
                <td>' . htmlspecialchars($task['description'] ?: '-') . '</td>
                <td>' . htmlspecialchars($task['estimated_hours'] ?: '-') . '</td>
            </tr>';
        }
        $html .= '</table>
        </div>';
    }
    
    if (!empty($checklist)) {
        $html .= '<div class="section">
            <div class="section-title">Checklist</div>
            <ul>';
        foreach ($checklist as $item) {
            $html .= '<li>' . htmlspecialchars($item['item']) . ($item['is_required'] ? ' <strong>(Requerido)</strong>' : '') . '</li>';
        }
        $html .= '</ul>
        </div>';
    }
    
    if (!empty($materials)) {
        $html .= '<div class="section">
            <div class="section-title">Materiales</div>
            <table>
                <tr><th>Nombre</th><th>Unidad</th><th>Cantidad Planificada</th><th>Cantidad Usada</th></tr>';
        foreach ($materials as $material) {
            $html .= '<tr>
                <td>' . htmlspecialchars($material['name']) . '</td>
                <td>' . htmlspecialchars($material['unit'] ?: '-') . '</td>
                <td>' . htmlspecialchars($material['qty_planned'] ?: '-') . '</td>
                <td>' . htmlspecialchars($material['qty_used'] ?: '-') . '</td>
            </tr>';
        }
        $html .= '</table>
        </div>';
    }
    
    if ($workOrder['safety_notes']) {
        $html .= '<div class="section">
            <div class="section-title">Notas de Seguridad</div>
            <p>' . nl2br(htmlspecialchars($workOrder['safety_notes'])) . '</p>
        </div>';
    }
    
    $html .= '<div class="section qr-code">
        <div class="section-title">Acceso Rápido</div>
        <p>Escanea el código QR para acceder a esta orden de trabajo</p>
        <p style="font-size: 12px; color: #666;">URL: ' . htmlspecialchars($qrUrl) . '</p>
        <p style="font-size: 10px; color: #999;">Token válido por 7 días</p>
    </div>
    
    <div class="footer">
        Generado el ' . date('d/m/Y H:i') . ' - Workee SaaS
    </div>
</body>
</html>';
    
    return $html;
}

/**
 * Genera PDF usando TCPDF (si está disponible)
 */
function generatePDFWithTCPDF($html, $workOrder, $tenantId) {
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
        $pdf->SetAuthor($workOrder['tenant_name'] ?: 'Workee');
        $pdf->SetTitle('OT-' . ($workOrder['code'] ?: $workOrder['id']) . ' - ' . $workOrder['title']);
        $pdf->SetSubject('Orden de Trabajo');
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Guardar PDF
        $filename = 'work_order_' . $workOrder['id'] . '_' . date('YmdHis') . '.pdf';
        $filepath = __DIR__ . '/../../storage/pdfs/' . $tenantId . '/' . $filename;
        
        // Crear directorio si no existe
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $pdf->Output($filepath, 'F');
        
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
function saveHTMLForPDF($html, $workOrderId, $tenantId) {
    $filename = 'work_order_' . $workOrderId . '_' . date('YmdHis') . '.html';
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

