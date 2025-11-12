<?php
/**
 * Script para buscar colores hardcodeados en archivos CSS
 * 
 * Busca valores de color hexadecimales (#rgb, #rrggbb) y colores nombrados
 * que deberían usar variables CSS del sistema de diseño
 * 
 * Uso: php scripts/dev/find_hardcoded_colors.php
 */

$cssDir = __DIR__ . '/../../frontend/css';
$results = [
    'hex_colors' => [],
    'named_colors' => [],
    'rgb_colors' => [],
    'rgba_colors' => [],
    'total_files' => 0,
    'total_issues' => 0
];

/**
 * Busca colores hexadecimales (#rgb, #rrggbb, #rrggbbaa)
 */
function findHexColors($content, $file) {
    $colors = [];
    // Patrón para colores hex: #rgb, #rrggbb, #rrggbbaa
    preg_match_all('/#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})\b/', $content, $matches, PREG_OFFSET_CAPTURE);
    
    foreach ($matches[0] as $index => $match) {
        $color = $match[0];
        $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
        
        // Ignorar colores en comentarios
        $beforeMatch = substr($content, 0, $match[1]);
        $lastCommentStart = strrpos($beforeMatch, '/*');
        $lastCommentEnd = strrpos($beforeMatch, '*/');
        
        if ($lastCommentStart !== false && ($lastCommentEnd === false || $lastCommentStart > $lastCommentEnd)) {
            continue; // Está dentro de un comentario
        }
        
        // Ignorar si está en una variable CSS (--color-*)
        $context = substr($content, max(0, $match[1] - 50), 100);
        if (preg_match('/--[a-zA-Z-]+:\s*' . preg_quote($color, '/') . '/', $context)) {
            continue; // Es una definición de variable CSS
        }
        
        $colors[] = [
            'color' => $color,
            'line' => $line,
            'context' => getLineContext($content, $match[1])
        ];
    }
    
    return $colors;
}

/**
 * Busca colores nombrados (red, blue, white, etc.)
 */
function findNamedColors($content, $file) {
    $namedColors = [
        'black', 'white', 'red', 'green', 'blue', 'yellow', 'orange', 
        'purple', 'pink', 'brown', 'gray', 'grey', 'cyan', 'magenta',
        'transparent', 'currentColor', 'inherit', 'initial', 'unset'
    ];
    
    $colors = [];
    foreach ($namedColors as $color) {
        // Buscar el color como valor (no como parte de otra palabra)
        $pattern = '/\b' . $color . '\b/i';
        preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
        
        foreach ($matches[0] as $match) {
            $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
            
            // Verificar que es un valor de color (después de :)
            $beforeMatch = substr($content, 0, $match[1]);
            $lastColon = strrpos($beforeMatch, ':');
            $lastSemicolon = strrpos($beforeMatch, ';');
            
            if ($lastColon !== false && ($lastSemicolon === false || $lastColon > $lastSemicolon)) {
                // Ignorar si está en comentario
                $lastCommentStart = strrpos($beforeMatch, '/*');
                $lastCommentEnd = strrpos($beforeMatch, '*/');
                
                if ($lastCommentStart !== false && ($lastCommentEnd === false || $lastCommentStart > $lastCommentEnd)) {
                    continue;
                }
                
                // Ignorar si es parte de una variable CSS
                $context = substr($content, max(0, $match[1] - 50), 100);
                if (preg_match('/--[a-zA-Z-]+:\s*' . preg_quote($color, '/') . '/', $context)) {
                    continue;
                }
                
                $colors[] = [
                    'color' => $color,
                    'line' => $line,
                    'context' => getLineContext($content, $match[1])
                ];
            }
        }
    }
    
    return $colors;
}

/**
 * Busca colores rgb() y rgba()
 */
function findRgbColors($content, $file) {
    $colors = [];
    // Patrón para rgb() y rgba()
    preg_match_all('/rgba?\([^)]+\)/', $content, $matches, PREG_OFFSET_CAPTURE);
    
    foreach ($matches[0] as $match) {
        $color = $match[0];
        $line = substr_count(substr($content, 0, $match[1]), "\n") + 1;
        
        // Ignorar si está en comentario
        $beforeMatch = substr($content, 0, $match[1]);
        $lastCommentStart = strrpos($beforeMatch, '/*');
        $lastCommentEnd = strrpos($beforeMatch, '*/');
        
        if ($lastCommentStart !== false && ($lastCommentEnd === false || $lastCommentStart > $lastCommentEnd)) {
            continue;
        }
        
        // Ignorar si es una definición de variable CSS
        $context = substr($content, max(0, $match[1] - 50), 100);
        if (preg_match('/--[a-zA-Z-]+:\s*' . preg_quote($color, '/') . '/', $context)) {
            continue;
        }
        
        // Ignorar rgba con variables (rgba(var(--color-primary-rgb), 0.5))
        if (preg_match('/rgba\(var\(--[^)]+\)/', $color)) {
            continue;
        }
        
        $colors[] = [
            'color' => $color,
            'line' => $line,
            'context' => getLineContext($content, $match[1])
        ];
    }
    
    return $colors;
}

/**
 * Obtiene el contexto de una línea
 */
function getLineContext($content, $position) {
    $start = max(0, $position - 40);
    $end = min(strlen($content), $position + 40);
    $context = substr($content, $start, $end - $start);
    return trim($context);
}

/**
 * Recorre recursivamente los archivos CSS
 */
function scanDirectory($dir, &$results) {
    $files = glob($dir . '/*.css');
    
    foreach ($files as $file) {
        $results['total_files']++;
        $content = file_get_contents($file);
        $relativePath = str_replace(__DIR__ . '/../../', '', $file);
        
        $hexColors = findHexColors($content, $file);
        $namedColors = findNamedColors($content, $file);
        $rgbColors = findRgbColors($content, $file);
        
        if (!empty($hexColors)) {
            $results['hex_colors'][$relativePath] = $hexColors;
            $results['total_issues'] += count($hexColors);
        }
        
        if (!empty($namedColors)) {
            $results['named_colors'][$relativePath] = $namedColors;
            $results['total_issues'] += count($namedColors);
        }
        
        if (!empty($rgbColors)) {
            $results['rgba_colors'][$relativePath] = $rgbColors;
            $results['total_issues'] += count($rgbColors);
        }
    }
    
    // Recursión para subdirectorios
    $subdirs = glob($dir . '/*', GLOB_ONLYDIR);
    foreach ($subdirs as $subdir) {
        scanDirectory($subdir, $results);
    }
}

// Ejecutar análisis
echo "🔍 Buscando colores hardcodeados en archivos CSS...\n\n";
scanDirectory($cssDir, $results);

// Generar reporte
$report = "# Análisis de Colores Hardcodeados en CSS\n\n";
$report .= "**Fecha:** " . date('Y-m-d H:i:s') . "\n\n";
$report .= "## Resumen\n\n";
$report .= "- **Archivos analizados:** {$results['total_files']}\n";
$report .= "- **Total de colores hardcodeados encontrados:** {$results['total_issues']}\n\n";

if ($results['total_issues'] > 0) {
    $report .= "## ⚠️ Colores Hardcodeados Encontrados\n\n";
    
    if (!empty($results['hex_colors'])) {
        $report .= "### Colores Hexadecimales (#rgb, #rrggbb)\n\n";
        foreach ($results['hex_colors'] as $file => $colors) {
            $report .= "#### `{$file}`\n\n";
            foreach ($colors as $color) {
                $report .= "- **Línea {$color['line']}:** `{$color['color']}`\n";
                $report .= "  - Contexto: `{$color['context']}`\n";
            }
            $report .= "\n";
        }
    }
    
    if (!empty($results['named_colors'])) {
        $report .= "### Colores Nombrados (red, blue, etc.)\n\n";
        foreach ($results['named_colors'] as $file => $colors) {
            $report .= "#### `{$file}`\n\n";
            foreach ($colors as $color) {
                $report .= "- **Línea {$color['line']}:** `{$color['color']}`\n";
                $report .= "  - Contexto: `{$color['context']}`\n";
            }
            $report .= "\n";
        }
    }
    
    if (!empty($results['rgba_colors'])) {
        $report .= "### Colores RGB/RGBA (rgb(), rgba())\n\n";
        foreach ($results['rgba_colors'] as $file => $colors) {
            $report .= "#### `{$file}`\n\n";
            foreach ($colors as $color) {
                $report .= "- **Línea {$color['line']}:** `{$color['color']}`\n";
                $report .= "  - Contexto: `{$color['context']}`\n";
            }
            $report .= "\n";
        }
    }
    
    $report .= "## Recomendaciones\n\n";
    $report .= "1. Reemplazar todos los colores hardcodeados con variables CSS del sistema de diseño\n";
    $report .= "2. Usar `var(--color-*)` para colores corporativos\n";
    $report .= "3. Usar `rgba(var(--color-*-rgb), opacity)` para colores con transparencia\n";
    $report .= "4. Consultar `frontend/css/01-settings/theme.css` para ver todas las variables disponibles\n";
} else {
    $report .= "## ✅ No se encontraron colores hardcodeados\n\n";
    $report .= "¡Excelente! Todos los colores están usando variables CSS del sistema de diseño.\n";
}

// Guardar reporte
$reportFile = __DIR__ . '/../../docs/CSS_HARDCODED_COLORS_ANALYSIS.md';
file_put_contents($reportFile, $report);

echo "✅ Análisis completado\n";
echo "📄 Reporte guardado en: docs/CSS_HARDCODED_COLORS_ANALYSIS.md\n";
echo "\n";
echo "Resumen:\n";
echo "- Archivos analizados: {$results['total_files']}\n";
echo "- Colores hardcodeados encontrados: {$results['total_issues']}\n";

if ($results['total_issues'] > 0) {
    echo "\n⚠️ Se encontraron colores hardcodeados. Revisa el reporte para más detalles.\n";
} else {
    echo "\n✅ No se encontraron colores hardcodeados. Todo está usando variables CSS.\n";
}

