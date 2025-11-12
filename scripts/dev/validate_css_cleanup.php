<?php
/**
 * Script de validación final post-eliminación de código CSS
 * 
 * Este script valida que después de eliminar código CSS duplicado/muerto,
 * no se hayan roto funcionalidades y que todo siga funcionando correctamente.
 * 
 * Uso: php scripts/dev/validate_css_cleanup.php
 */

$baseDir = __DIR__ . '/../../';
$cssDir = $baseDir . 'frontend/css';
$htmlDir = $baseDir . 'frontend/pages';
$jsDir = $baseDir . 'frontend/js';
$componentsDir = $baseDir . 'frontend/components';

$results = [
    'css_files_valid' => [],
    'css_files_invalid' => [],
    'html_files_valid' => [],
    'html_files_invalid' => [],
    'imports_valid' => [],
    'imports_invalid' => [],
    'warnings' => []
];

echo "🔍 Validando limpieza de CSS después de eliminaciones...\n\n";

// 1. Validar que todos los archivos CSS son válidos (sintaxis)
echo "📋 1. Validando sintaxis de archivos CSS...\n";
$cssFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($cssDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'css') {
        $relativePath = str_replace($cssDir . '/', '', $file->getPathname());
        $content = file_get_contents($file->getPathname());
        
        // Validación básica: verificar que no hay errores obvios
        $errors = [];
        
        // Verificar que no hay llaves sin cerrar
        $openBraces = substr_count($content, '{');
        $closeBraces = substr_count($content, '}');
        if ($openBraces !== $closeBraces) {
            $errors[] = "Llaves desbalanceadas: {$openBraces} abiertas, {$closeBraces} cerradas";
        }
        
        // Verificar que no hay paréntesis sin cerrar en media queries
        $openParens = substr_count($content, '(');
        $closeParens = substr_count($content, ')');
        if ($openParens !== $closeParens) {
            $errors[] = "Paréntesis desbalanceados: {$openParens} abiertos, {$closeParens} cerrados";
        }
        
        if (empty($errors)) {
            $results['css_files_valid'][] = $relativePath;
        } else {
            $results['css_files_invalid'][] = [
                'file' => $relativePath,
                'errors' => $errors
            ];
        }
    }
}

$validCount = count($results['css_files_valid']);
$invalidCount = count($results['css_files_invalid']);

echo "   ✅ {$validCount} archivos CSS válidos\n";
if ($invalidCount > 0) {
    echo "   ⚠️  {$invalidCount} archivos CSS con errores:\n";
    foreach ($results['css_files_invalid'] as $invalid) {
        echo "      - {$invalid['file']}: " . implode(', ', $invalid['errors']) . "\n";
    }
} else {
    echo "   ✅ No se encontraron errores de sintaxis\n";
}
echo "\n";

// 2. Validar que los imports en HTML son correctos
echo "📋 2. Validando imports de CSS en HTML...\n";
$htmlFiles = [];
$htmlIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($htmlDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($htmlIterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'html') {
        $htmlFiles[] = $file->getPathname();
    }
}

$mainCssFound = 0;
$mainCssMissing = [];

foreach ($htmlFiles as $filePath) {
    $relativePath = str_replace($baseDir, '', $filePath);
    $content = file_get_contents($filePath);
    
    // Verificar que main.css está importado
    if (preg_match('/href=["\']\/frontend\/css\/main\.css["\']/', $content)) {
        $mainCssFound++;
        $results['imports_valid'][] = $relativePath;
    } else {
        $mainCssMissing[] = $relativePath;
        $results['imports_invalid'][] = $relativePath;
    }
}

echo "   ✅ {$mainCssFound} archivos HTML importan main.css correctamente\n";
if (count($mainCssMissing) > 0) {
    echo "   ⚠️  " . count($mainCssMissing) . " archivos HTML NO importan main.css:\n";
    foreach ($mainCssMissing as $file) {
        echo "      - {$file}\n";
    }
} else {
    echo "   ✅ Todos los archivos HTML importan main.css\n";
}
echo "\n";

// 3. Verificar que no hay referencias a clases eliminadas
echo "📋 3. Verificando referencias a clases eliminadas...\n";
$eliminatedClasses = [
    'text-corporate',
    'text-corporate-muted',
    'bg-corporate',
    'bg-corporate-light',
    'btn-corporate',
    'border-corporate',
    'shadow-corporate',
    'shadow-corporate-hover'
];

$foundReferences = [];

foreach ($eliminatedClasses as $class) {
    // Buscar en HTML
    foreach ($htmlFiles as $filePath) {
        $content = file_get_contents($filePath);
        if (preg_match('/\b' . preg_quote($class, '/') . '\b/', $content)) {
            $relativePath = str_replace($baseDir, '', $filePath);
            $foundReferences[] = [
                'class' => $class,
                'file' => $relativePath,
                'type' => 'HTML'
            ];
        }
    }
    
    // Buscar en JavaScript
    $jsFiles = [];
    $jsIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($jsDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($jsIterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'js') {
            $jsFiles[] = $file->getPathname();
        }
    }
    
    foreach ($jsFiles as $filePath) {
        $content = file_get_contents($filePath);
        if (preg_match('/["\']' . preg_quote($class, '/') . '["\']/', $content)) {
            $relativePath = str_replace($baseDir, '', $filePath);
            $foundReferences[] = [
                'class' => $class,
                'file' => $relativePath,
                'type' => 'JavaScript'
            ];
        }
    }
}

if (empty($foundReferences)) {
    echo "   ✅ No se encontraron referencias a clases eliminadas\n";
} else {
    echo "   ⚠️  Se encontraron " . count($foundReferences) . " referencias a clases eliminadas:\n";
    foreach ($foundReferences as $ref) {
        echo "      - .{$ref['class']} en {$ref['file']} ({$ref['type']})\n";
    }
    $results['warnings'][] = "Referencias a clases eliminadas encontradas";
}
echo "\n";

// 4. Verificar estructura ITCSS
echo "📋 4. Validando estructura ITCSS...\n";
$itcssLayers = [
    '01-settings',
    '03-generic',
    '04-elements',
    '05-objects',
    '06-components',
    '07-utilities',
    'pages'
];

$missingLayers = [];
foreach ($itcssLayers as $layer) {
    $layerPath = $cssDir . '/' . $layer;
    if (!is_dir($layerPath)) {
        $missingLayers[] = $layer;
    }
}

if (empty($missingLayers)) {
    echo "   ✅ Todas las capas ITCSS están presentes\n";
} else {
    echo "   ⚠️  Faltan capas ITCSS: " . implode(', ', $missingLayers) . "\n";
    $results['warnings'][] = "Faltan capas ITCSS";
}
echo "\n";

// 5. Verificar que main.css importa todas las capas
echo "📋 5. Validando main.css...\n";
$mainCssPath = $cssDir . '/main.css';
if (file_exists($mainCssPath)) {
    $mainCssContent = file_get_contents($mainCssPath);
    
    $requiredImports = [
        '01-settings/theme.css',
        '03-generic/_reset.css',
        '04-elements/_typography.css',
        '04-elements/_forms.css',
        '04-elements/_tables.css',
        '04-elements/_images.css',
        '04-elements/_accessibility.css',
        '05-objects/_layout.css',
        '05-objects/_common.css',
        '05-objects/_dashboard-grid.css',
        '06-components/components.css',
        '07-utilities/utilities.css'
    ];
    
    $missingImports = [];
    foreach ($requiredImports as $import) {
        if (!preg_match('/' . preg_quote($import, '/') . '/', $mainCssContent)) {
            $missingImports[] = $import;
        }
    }
    
    if (empty($missingImports)) {
        echo "   ✅ main.css importa todas las capas ITCSS requeridas\n";
    } else {
        echo "   ⚠️  Faltan imports en main.css: " . implode(', ', $missingImports) . "\n";
        $results['warnings'][] = "Faltan imports en main.css";
    }
} else {
    echo "   ⚠️  main.css no existe\n";
    $results['warnings'][] = "main.css no existe";
}
echo "\n";

// Resumen
echo "═══════════════════════════════════════════════════════════\n";
echo "📊 RESUMEN DE VALIDACIÓN\n";
echo "═══════════════════════════════════════════════════════════\n\n";

$totalIssues = count($results['css_files_invalid']) + 
               count($results['imports_invalid']) + 
               count($foundReferences) + 
               count($missingLayers) + 
               count($results['warnings']);

if ($totalIssues === 0) {
    echo "✅ VALIDACIÓN EXITOSA: No se encontraron problemas\n";
    echo "\n";
    echo "Todas las verificaciones pasaron:\n";
    echo "  ✅ Sintaxis CSS válida\n";
    echo "  ✅ Imports correctos en HTML\n";
    echo "  ✅ No hay referencias a clases eliminadas\n";
    echo "  ✅ Estructura ITCSS completa\n";
    echo "  ✅ main.css importa todas las capas\n";
    echo "\n";
    echo "🎉 La limpieza de CSS se completó exitosamente.\n";
} else {
    echo "⚠️  VALIDACIÓN CON ADVERTENCIAS: Se encontraron {$totalIssues} problema(s)\n";
    echo "\n";
    echo "Revisar los detalles arriba y corregir los problemas antes de continuar.\n";
}

echo "\n";

