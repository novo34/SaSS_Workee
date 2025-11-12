<?php
/**
 * Script de verificación antes de eliminar código CSS
 * 
 * Este script ayuda a verificar que el código CSS a eliminar realmente no se usa
 * antes de proceder con la eliminación.
 * 
 * Uso: php scripts/dev/verify_css_before_delete.php <clase-css>
 * Ejemplo: php scripts/dev/verify_css_before_delete.php "text-corporate"
 */

$baseDir = __DIR__ . '/../../';
$cssDir = $baseDir . 'frontend/css';
$htmlDir = $baseDir . 'frontend/pages';
$jsDir = $baseDir . 'frontend/js';
$componentsDir = $baseDir . 'frontend/components';

// Obtener clase CSS a verificar desde argumentos
$className = isset($argv[1]) ? $argv[1] : null;

if (!$className) {
    echo "❌ Error: Debe proporcionar una clase CSS a verificar.\n";
    echo "Uso: php scripts/dev/verify_css_before_delete.php <clase-css>\n";
    echo "Ejemplo: php scripts/dev/verify_css_before_delete.php \"text-corporate\"\n";
    exit(1);
}

// Remover punto inicial si existe
$className = ltrim($className, '.');

echo "🔍 Verificando uso de clase CSS: .{$className}\n\n";

$results = [
    'found_in_css' => [],
    'found_in_html' => [],
    'found_in_js' => [],
    'found_in_comments' => [],
    'is_bootstrap' => false,
    'is_fullcalendar' => false,
    'is_dynamic' => false
];

// 1. Buscar en archivos CSS
echo "📋 1. Buscando definición en archivos CSS...\n";
$cssFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($cssDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'css') {
        $relativePath = str_replace($cssDir . '/', '', $file->getPathname());
        $content = file_get_contents($file->getPathname());
        
        // Buscar definición de clase
        if (preg_match('/\.' . preg_quote($className, '/') . '(?:[^a-zA-Z0-9_-]|$)/', $content)) {
            $results['found_in_css'][] = $relativePath;
            
            // Verificar si es parte de un selector más complejo
            preg_match_all('/\.' . preg_quote($className, '/') . '[^{]*\{[^}]*\}/', $content, $matches);
            if (!empty($matches[0])) {
                echo "   ✅ Encontrada en: {$relativePath}\n";
                foreach ($matches[0] as $match) {
                    echo "      Selector: " . substr($match, 0, 100) . "...\n";
                }
            }
        }
    }
}

if (empty($results['found_in_css'])) {
    echo "   ⚠️  No se encontró definición de .{$className} en archivos CSS\n";
} else {
    echo "   ✅ Encontrada en " . count($results['found_in_css']) . " archivo(s) CSS\n";
}
echo "\n";

// 2. Buscar en archivos HTML
echo "📋 2. Buscando uso en archivos HTML...\n";
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

if (is_dir($componentsDir)) {
    $componentsIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($componentsDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($componentsIterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'html') {
            $htmlFiles[] = $file->getPathname();
        }
    }
}

foreach ($htmlFiles as $filePath) {
    $relativePath = str_replace($baseDir, '', $filePath);
    $content = file_get_contents($filePath);
    
    // Buscar en atributos class
    if (preg_match('/class=["\'][^"\']*\b' . preg_quote($className, '/') . '\b/', $content)) {
        $results['found_in_html'][] = $relativePath;
        
        // Obtener línea de contexto
        $lines = explode("\n", $content);
        foreach ($lines as $lineNum => $line) {
            if (preg_match('/\b' . preg_quote($className, '/') . '\b/', $line)) {
                echo "   ✅ Encontrada en: {$relativePath} (línea " . ($lineNum + 1) . ")\n";
                echo "      " . trim($line) . "\n";
            }
        }
    }
}

if (empty($results['found_in_html'])) {
    echo "   ✅ No se encontró uso en archivos HTML\n";
} else {
    echo "   ⚠️  Encontrada en " . count($results['found_in_html']) . " archivo(s) HTML\n";
}
echo "\n";

// 3. Buscar en archivos JavaScript
echo "📋 3. Buscando uso en archivos JavaScript...\n";
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
    $relativePath = str_replace($baseDir, '', $filePath);
    $content = file_get_contents($filePath);
    
    // Buscar en classList, className, querySelector, etc.
    $patterns = [
        '/classList\.(?:add|remove|toggle|contains)\s*\(\s*["\']' . preg_quote($className, '/') . '["\']/',
        '/className\s*[=:]\s*["\'][^"\']*\b' . preg_quote($className, '/') . '\b/',
        '/querySelector\s*\(\s*["\']\.' . preg_quote($className, '/') . '["\']/',
        '/getElementsByClassName\s*\(\s*["\']' . preg_quote($className, '/') . '["\']/',
        '/\.' . preg_quote($className, '/') . '\b/'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            $results['found_in_js'][] = $relativePath;
            
            // Obtener línea de contexto
            $lines = explode("\n", $content);
            foreach ($lines as $lineNum => $line) {
                if (preg_match($pattern, $line)) {
                    echo "   ✅ Encontrada en: {$relativePath} (línea " . ($lineNum + 1) . ")\n";
                    echo "      " . trim($line) . "\n";
                    break;
                }
            }
            break;
        }
    }
}

if (empty($results['found_in_js'])) {
    echo "   ✅ No se encontró uso en archivos JavaScript\n";
} else {
    echo "   ⚠️  Encontrada en " . count($results['found_in_js']) . " archivo(s) JavaScript\n";
}
echo "\n";

// 4. Verificar si es clase de Bootstrap, FullCalendar, etc.
echo "📋 4. Verificando si es clase de librería externa...\n";

// Bootstrap
$bootstrapClasses = ['text-end', 'page-link', 'btn-group', 'btn-warning', 'sr-only', 'modal-backdrop'];
if (in_array($className, $bootstrapClasses)) {
    $results['is_bootstrap'] = true;
    echo "   ⚠️  .{$className} es una clase de Bootstrap 5 - NO ELIMINAR\n";
}

// FullCalendar
if (preg_match('/^fc-/', $className)) {
    $results['is_fullcalendar'] = true;
    echo "   ⚠️  .{$className} es una clase de FullCalendar - NO ELIMINAR\n";
}

// SortableJS
$sortableClasses = ['dragging', 'sortable-ghost', 'sortable-drag', 'drag-handle'];
if (in_array($className, $sortableClasses)) {
    echo "   ⚠️  .{$className} es una clase de SortableJS - NO ELIMINAR\n";
}

// Clases dinámicas comunes
$dynamicPatterns = ['--show$', '--hide$', '--active$', '--open$', '--close$', '--loading$', '--error$', '--success$', '--disabled$', '--selected$'];
foreach ($dynamicPatterns as $pattern) {
    if (preg_match('/' . $pattern . '/', $className)) {
        $results['is_dynamic'] = true;
        echo "   ⚠️  .{$className} parece ser una clase dinámica (modificador BEM) - VERIFICAR USO EN JS\n";
        break;
    }
}

if (!$results['is_bootstrap'] && !$results['is_fullcalendar'] && !$results['is_dynamic']) {
    echo "   ✅ No es clase de librería externa conocida\n";
}
echo "\n";

// 5. Buscar en comentarios
echo "📋 5. Buscando referencias en comentarios...\n";
$allFiles = array_merge($htmlFiles, $jsFiles);
$commentCount = 0;

foreach ($allFiles as $filePath) {
    $content = file_get_contents($filePath);
    
    // Buscar en comentarios HTML
    if (preg_match('/<!--[^>]*\b' . preg_quote($className, '/') . '\b[^>]*-->/', $content)) {
        $commentCount++;
    }
    
    // Buscar en comentarios JS
    if (preg_match('/\/\/[^\n]*\b' . preg_quote($className, '/') . '\b/', $content) ||
        preg_match('/\/\*[^*]*\b' . preg_quote($className, '/') . '\b[^*]*\*\//', $content)) {
        $commentCount++;
    }
}

if ($commentCount > 0) {
    echo "   ⚠️  Encontrada en {$commentCount} comentario(s) - Revisar contexto\n";
} else {
    echo "   ✅ No se encontró en comentarios\n";
}
echo "\n";

// Resumen y recomendación
echo "═══════════════════════════════════════════════════════════\n";
echo "📊 RESUMEN DE VERIFICACIÓN\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "Clase verificada: .{$className}\n\n";

echo "Definida en CSS: " . (empty($results['found_in_css']) ? "❌ NO" : "✅ SÍ (" . count($results['found_in_css']) . " archivo(s))") . "\n";
echo "Usada en HTML: " . (empty($results['found_in_html']) ? "✅ NO" : "⚠️  SÍ (" . count($results['found_in_html']) . " archivo(s))") . "\n";
echo "Usada en JS: " . (empty($results['found_in_js']) ? "✅ NO" : "⚠️  SÍ (" . count($results['found_in_js']) . " archivo(s))") . "\n";
echo "Es clase de librería: " . ($results['is_bootstrap'] || $results['is_fullcalendar'] ? "⚠️  SÍ" : "✅ NO") . "\n";
echo "Es clase dinámica: " . ($results['is_dynamic'] ? "⚠️  PROBABLEMENTE" : "✅ NO") . "\n\n";

// Recomendación
echo "═══════════════════════════════════════════════════════════\n";
echo "💡 RECOMENDACIÓN\n";
echo "═══════════════════════════════════════════════════════════\n\n";

if (!empty($results['found_in_html']) || !empty($results['found_in_js'])) {
    echo "❌ NO ELIMINAR: La clase se está usando en HTML o JavaScript\n";
    echo "   Archivos afectados:\n";
    foreach ($results['found_in_html'] as $file) {
        echo "   - {$file}\n";
    }
    foreach ($results['found_in_js'] as $file) {
        echo "   - {$file}\n";
    }
} elseif ($results['is_bootstrap'] || $results['is_fullcalendar']) {
    echo "❌ NO ELIMINAR: Es una clase de librería externa\n";
} elseif ($results['is_dynamic']) {
    echo "⚠️  VERIFICAR MANUALMENTE: Parece ser una clase dinámica\n";
    echo "   Revisar código JavaScript que podría agregar/remover esta clase\n";
} elseif (empty($results['found_in_css'])) {
    echo "✅ NO HACER NADA: La clase no está definida en CSS\n";
} else {
    echo "✅ PUEDE ELIMINARSE: La clase no se usa en HTML ni JavaScript\n";
    echo "   Sin embargo, completar checklist de verificación antes de eliminar:\n";
    echo "   - Verificar visualmente en navegador\n";
    echo "   - Verificar en diferentes tamaños de pantalla\n";
    echo "   - Verificar estados interactivos\n";
    echo "   - Verificar componentes dinámicos\n";
    echo "   - Ver documentación: docs/CSS_CLEANUP_CHECKLIST.md\n";
}

echo "\n";

