<?php
/**
 * Script para identificar código CSS muerto (no usado)
 * 
 * Analiza todos los archivos HTML y JS para encontrar qué clases CSS se usan,
 * luego compara con las clases definidas en CSS para identificar código muerto.
 * 
 * Uso: php scripts/dev/find_unused_css.php
 */

$baseDir = __DIR__ . '/../../';
$cssDir = $baseDir . 'frontend/css';
$htmlDir = $baseDir . 'frontend/pages';
$jsDir = $baseDir . 'frontend/js';
$componentsDir = $baseDir . 'frontend/components';

$results = [
    'css_classes_defined' => [],
    'css_classes_used' => [],
    'css_classes_unused' => [],
    'dynamic_classes' => [],
    'false_positives' => []
];

/**
 * Extrae clases CSS de un archivo CSS
 */
function extractCSSClasses($cssContent) {
    $classes = [];
    
    // Buscar selectores de clase: .class-name
    preg_match_all('/\.([a-zA-Z0-9_-]+)(?::[a-zA-Z0-9_-]+)?(?:\s|,|{|$)/', $cssContent, $matches);
    
    foreach ($matches[1] as $class) {
        // Filtrar clases de utilidades (u-*) y componentes comunes
        if (!preg_match('/^(u-|is-|has-|js-)/', $class)) {
            $classes[$class] = true;
        }
    }
    
    // Buscar selectores con modificadores BEM: .block__element--modifier
    preg_match_all('/\.([a-zA-Z0-9_-]+__[a-zA-Z0-9_-]+)(?:--[a-zA-Z0-9_-]+)?/', $cssContent, $bemMatches);
    foreach ($bemMatches[1] as $class) {
        $classes[$class] = true;
    }
    
    return array_keys($classes);
}

/**
 * Extrae clases usadas de HTML
 */
function extractClassesFromHTML($htmlContent) {
    $classes = [];
    
    // Buscar class="..." o class='...'
    preg_match_all('/class=["\']([^"\']+)["\']/', $htmlContent, $matches);
    
    foreach ($matches[1] as $classString) {
        $classList = preg_split('/\s+/', trim($classString));
        foreach ($classList as $class) {
            if (!empty($class) && !preg_match('/^(u-|is-|has-|js-)/', $class)) {
                $classes[$class] = true;
            }
        }
    }
    
    return array_keys($classes);
}

/**
 * Extrae clases usadas de JavaScript
 */
function extractClassesFromJS($jsContent) {
    $classes = [];
    
    // Buscar classList.add, className, classList.toggle, etc.
    preg_match_all('/(?:classList\.(?:add|remove|toggle|contains)|className\s*[=:]\s*["\']|\.classList\s*=\s*["\'])([^"\']+)/', $jsContent, $matches);
    
    foreach ($matches[1] as $classString) {
        $classList = preg_split('/[\s,]+/', trim($classString));
        foreach ($classList as $class) {
            if (!empty($class) && !preg_match('/^(u-|is-|has-|js-)/', $class)) {
                $classes[$class] = true;
            }
        }
    }
    
    // Buscar template literals con clases
    preg_match_all('/class(?:Name|List)[\s=:]+[`"\'][^`"\']*\$?\{[^}]*\}[^`"\']*[`"\']/', $jsContent, $templateMatches);
    // Nota: Esto es básico, podría mejorarse para detectar clases dinámicas
    
    return array_keys($classes);
}

/**
 * Verifica si una clase podría ser generada dinámicamente
 */
function isDynamicClass($className) {
    // Patrones comunes de clases dinámicas
    $patterns = [
        '/^active$/',
        '/^show$/',
        '/^hide$/',
        '/^open$/',
        '/^close$/',
        '/^loading$/',
        '/^error$/',
        '/^success$/',
        '/^disabled$/',
        '/^selected$/',
        '/^hover$/',
        '/^focus$/',
        '/--(show|hide|active|open|close|loading|error|success|disabled|selected)$/'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $className)) {
            return true;
        }
    }
    
    return false;
}

echo "🔍 Analizando código CSS muerto...\n\n";

// 1. Recopilar todas las clases CSS definidas
echo "📋 1. Extrayendo clases CSS definidas...\n";
$cssFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($cssDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'css') {
        $relativePath = str_replace($cssDir . '/', '', $file->getPathname());
        $content = file_get_contents($file->getPathname());
        $classes = extractCSSClasses($content);
        
        foreach ($classes as $class) {
            if (!isset($results['css_classes_defined'][$class])) {
                $results['css_classes_defined'][$class] = [];
            }
            $results['css_classes_defined'][$class][] = $relativePath;
        }
    }
}

echo "   ✅ Encontradas " . count($results['css_classes_defined']) . " clases CSS únicas\n\n";

// 2. Recopilar clases usadas en HTML
echo "📋 2. Extrayendo clases usadas en HTML...\n";
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

// También buscar en components
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
    $content = file_get_contents($filePath);
    $classes = extractClassesFromHTML($content);
    foreach ($classes as $class) {
        $results['css_classes_used'][$class] = true;
    }
}

echo "   ✅ Analizados " . count($htmlFiles) . " archivos HTML\n";
echo "   ✅ Encontradas " . count($results['css_classes_used']) . " clases únicas usadas en HTML\n\n";

// 3. Recopilar clases usadas en JavaScript
echo "📋 3. Extrayendo clases usadas en JavaScript...\n";
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
    $classes = extractClassesFromJS($content);
    foreach ($classes as $class) {
        $results['css_classes_used'][$class] = true;
    }
}

echo "   ✅ Analizados " . count($jsFiles) . " archivos JavaScript\n";
echo "   ✅ Total de clases únicas usadas: " . count($results['css_classes_used']) . "\n\n";

// 4. Identificar clases no usadas
echo "📋 4. Identificando clases CSS no usadas...\n";
foreach ($results['css_classes_defined'] as $class => $files) {
    // Verificar si la clase se usa
    $used = false;
    
    // Verificar uso directo
    if (isset($results['css_classes_used'][$class])) {
        $used = true;
    }
    
    // Verificar si es clase dinámica (modificadores BEM, estados, etc.)
    if (!$used && isDynamicClass($class)) {
        $results['dynamic_classes'][$class] = $files;
        $used = true; // Considerar como usado si es dinámica
    }
    
    // Verificar si es parte de un bloque BEM usado
    if (!$used) {
        $block = preg_replace('/__.*$/', '', $class);
        if (isset($results['css_classes_used'][$block])) {
            $results['false_positives'][$class] = $files;
            $used = true; // Posible falso positivo (elemento BEM)
        }
    }
    
    if (!$used) {
        $results['css_classes_unused'][$class] = $files;
    }
}

$unusedCount = count($results['css_classes_unused']);
$dynamicCount = count($results['dynamic_classes']);
$falsePositivesCount = count($results['false_positives']);

if ($unusedCount === 0) {
    echo "   ✅ No se encontraron clases CSS no usadas.\n\n";
} else {
    echo "   ⚠️  Se encontraron " . $unusedCount . " clases potencialmente no usadas:\n";
    $count = 0;
    foreach ($results['css_classes_unused'] as $class => $files) {
        if ($count < 20) { // Mostrar solo las primeras 20
            echo "   - " . $class . " (en: " . implode(', ', array_unique($files)) . ")\n";
        }
        $count++;
    }
    if ($count > 20) {
        echo "   ... y " . ($count - 20) . " más\n";
    }
    echo "\n";
}

if ($dynamicCount > 0) {
    echo "   ℹ️  " . $dynamicCount . " clases dinámicas detectadas (probablemente usadas por JS):\n";
    $count = 0;
    foreach ($results['dynamic_classes'] as $class => $files) {
        if ($count < 10) {
            echo "   - " . $class . "\n";
        }
        $count++;
    }
    if ($count > 10) {
        echo "   ... y " . ($count - 10) . " más\n";
    }
    echo "\n";
}

// Generar reporte
$reportFile = __DIR__ . '/../../docs/CSS_UNUSED_ANALYSIS.md';
$reportContent = "# Análisis de Código CSS Muerto (No Usado)\n\n";
$reportContent .= "**Fecha:** " . date('Y-m-d H:i:s') . "\n\n";
$reportContent .= "## Resumen\n\n";
$reportContent .= "- Clases CSS definidas: " . count($results['css_classes_defined']) . "\n";
$reportContent .= "- Clases usadas en HTML/JS: " . count($results['css_classes_used']) . "\n";
$reportContent .= "- Clases potencialmente no usadas: " . $unusedCount . "\n";
$reportContent .= "- Clases dinámicas (probablemente usadas): " . $dynamicCount . "\n";
$reportContent .= "- Posibles falsos positivos (elementos BEM): " . $falsePositivesCount . "\n\n";

$reportContent .= "## ⚠️ Advertencia Importante\n\n";
$reportContent .= "**ANTES de eliminar cualquier clase CSS, DEBE verificarse manualmente:**\n\n";
$reportContent .= "1. ✅ Probar todas las páginas que usan el CSS\n";
$reportContent .= "2. ✅ Verificar en diferentes navegadores (Chrome, Firefox, Safari)\n";
$reportContent .= "3. ✅ Verificar en diferentes tamaños de pantalla (mobile, tablet, desktop)\n";
$reportContent .= "4. ✅ Verificar estados interactivos (hover, focus, active)\n";
$reportContent .= "5. ✅ Verificar componentes dinámicos (modales, dropdowns, tooltips)\n";
$reportContent .= "6. ✅ Verificar clases generadas dinámicamente por JavaScript\n";
$reportContent .= "7. ✅ Verificar clases de Bootstrap 5 (no eliminar)\n";
$reportContent .= "8. ✅ Verificar clases de utilidades (u-*) (no eliminar)\n\n";

if ($unusedCount > 0) {
    $reportContent .= "## Clases Potencialmente No Usadas\n\n";
    $reportContent .= "**⚠️ VERIFICAR MANUALMENTE ANTES DE ELIMINAR**\n\n";
    
    // Agrupar por archivo
    $byFile = [];
    foreach ($results['css_classes_unused'] as $class => $files) {
        foreach ($files as $file) {
            if (!isset($byFile[$file])) {
                $byFile[$file] = [];
            }
            $byFile[$file][] = $class;
        }
    }
    
    foreach ($byFile as $file => $classes) {
        $reportContent .= "### " . $file . "\n\n";
        $reportContent .= "Clases potencialmente no usadas (" . count($classes) . "):\n\n";
        foreach ($classes as $class) {
            $reportContent .= "- `." . $class . "`\n";
        }
        $reportContent .= "\n";
    }
}

if ($dynamicCount > 0) {
    $reportContent .= "## Clases Dinámicas (Probablemente Usadas por JavaScript)\n\n";
    $reportContent .= "Estas clases probablemente se agregan/remueven dinámicamente por JavaScript:\n\n";
    foreach ($results['dynamic_classes'] as $class => $files) {
        $reportContent .= "- `." . $class . "` (en: " . implode(', ', array_unique($files)) . ")\n";
    }
    $reportContent .= "\n";
}

if ($falsePositivesCount > 0) {
    $reportContent .= "## Posibles Falsos Positivos (Elementos BEM)\n\n";
    $reportContent .= "Estas clases son elementos BEM que probablemente se usan aunque no aparezcan directamente en HTML:\n\n";
    foreach ($results['false_positives'] as $class => $files) {
        $reportContent .= "- `." . $class . "` (bloque: " . preg_replace('/__.*$/', '', $class) . ")\n";
    }
    $reportContent .= "\n";
}

$reportContent .= "## Recomendaciones\n\n";
$reportContent .= "1. **Revisar manualmente** cada clase antes de eliminar\n";
$reportContent .= "2. **Probar funcionalidad** después de cada eliminación\n";
$reportContent .= "3. **Mantener clases de Bootstrap 5** y utilidades (u-*)\n";
$reportContent .= "4. **No eliminar clases dinámicas** sin verificar su uso en JavaScript\n";
$reportContent .= "5. **Considerar elementos BEM** como parte del bloque principal\n";
$reportContent .= "6. **Eliminar incrementalmente** (archivo por archivo o sección por sección)\n";
$reportContent .= "7. **Verificar después de cada eliminación** que no se rompió nada\n";

file_put_contents($reportFile, $reportContent);

echo "✅ Análisis completado. Reporte guardado en: docs/CSS_UNUSED_ANALYSIS.md\n";
echo "\n";
echo "⚠️  IMPORTANTE: Antes de eliminar cualquier clase, verificar manualmente siguiendo el checklist en el reporte.\n";

