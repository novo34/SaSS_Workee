<?php
/**
 * Script para aplicar componentes modernos en todas las páginas HTML
 * 
 * Este script identifica todos los usos de .btn y .form-control/.form-select
 * y los reemplaza por .btn-modern y .form-modern__* respectivamente
 * 
 * IMPORTANTE: Este script NO elimina código, solo identifica y sugiere cambios
 * Los cambios deben aplicarse manualmente para tener control total
 * 
 * Uso: php scripts/dev/apply_modern_components.php
 */

$baseDir = __DIR__ . '/../../';
$pagesDir = $baseDir . 'frontend/pages';

echo "🔍 Analizando páginas HTML para aplicar componentes modernos...\n\n";

$htmlFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pagesDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'html') {
        $htmlFiles[] = $file->getPathname();
    }
}

$results = [
    'total_files' => count($htmlFiles),
    'files_with_buttons' => [],
    'files_with_forms' => [],
    'total_buttons' => 0,
    'total_form_inputs' => 0
];

echo "📋 Archivos encontrados: " . count($htmlFiles) . "\n\n";

foreach ($htmlFiles as $filePath) {
    $relativePath = str_replace($baseDir, '', $filePath);
    $content = file_get_contents($filePath);
    
    $hasButtons = preg_match_all('/class=["\'][^"\']*btn[^"\']*["\']/', $content, $buttonMatches);
    $hasForms = preg_match_all('/class=["\'][^"\']*form-(control|select|check)[^"\']*["\']/', $content, $formMatches);
    
    if ($hasButtons > 0) {
        $results['files_with_buttons'][] = [
            'file' => $relativePath,
            'count' => $hasButtons
        ];
        $results['total_buttons'] += $hasButtons;
    }
    
    if ($hasForms > 0) {
        $results['files_with_forms'][] = [
            'file' => $relativePath,
            'count' => $hasForms
        ];
        $results['total_form_inputs'] += $hasForms;
    }
}

echo "═══════════════════════════════════════════════════════════\n";
echo "📊 RESUMEN DE ANÁLISIS\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "📁 Total de archivos HTML: {$results['total_files']}\n";
echo "🔘 Archivos con botones: " . count($results['files_with_buttons']) . "\n";
echo "📝 Archivos con formularios: " . count($results['files_with_forms']) . "\n";
echo "🔘 Total de botones encontrados: {$results['total_buttons']}\n";
echo "📝 Total de inputs de formulario encontrados: {$results['total_form_inputs']}\n\n";

echo "═══════════════════════════════════════════════════════════\n";
echo "📋 ARCHIVOS CON BOTONES\n";
echo "═══════════════════════════════════════════════════════════\n\n";

foreach ($results['files_with_buttons'] as $item) {
    echo "  - {$item['file']}: {$item['count']} botón(es)\n";
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "📋 ARCHIVOS CON FORMULARIOS\n";
echo "═══════════════════════════════════════════════════════════\n\n";

foreach ($results['files_with_forms'] as $item) {
    echo "  - {$item['file']}: {$item['count']} input(s)\n";
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "💡 RECOMENDACIONES\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "1. Reemplazar clases de botones:\n";
echo "   - .btn.btn-primary → .btn-modern.btn-modern--primary\n";
echo "   - .btn.btn-secondary → .btn-modern.btn-modern--secondary\n";
echo "   - .btn.btn-outline-primary → .btn-modern.btn-modern--ghost\n";
echo "   - .btn.btn-success → .btn-modern.btn-modern--success\n";
echo "   - .btn.btn-danger → .btn-modern.btn-modern--danger\n";
echo "   - .btn.btn-sm → .btn-modern.btn-modern--sm\n";
echo "   - .btn.btn-lg → .btn-modern.btn-modern--lg\n\n";

echo "2. Reemplazar clases de formularios:\n";
echo "   - Agregar .form-modern al <form>\n";
echo "   - .form-control → .form-modern__input (dentro de .form-modern__group)\n";
echo "   - .form-select → .form-modern__select (dentro de .form-modern__group)\n";
echo "   - .form-label → .form-modern__label (dentro de .form-modern__group)\n";
echo "   - .form-check → .form-modern__check\n";
echo "   - .form-check-input → .form-modern__check-input\n";
echo "   - .form-check-label → .form-modern__check-label\n\n";

echo "3. IMPORTANTE:\n";
echo "   - NO eliminar estilos de Bootstrap (mantener compatibilidad)\n";
echo "   - Aplicar cambios página por página\n";
echo "   - Verificar funcionalidad después de cada cambio\n";
echo "   - Los iconos <i class=\"bi...\"> deben convertirse a SVG\n\n";

echo "✅ Análisis completado. Listo para aplicar cambios.\n\n";

