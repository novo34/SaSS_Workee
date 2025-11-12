<?php
/**
 * Script para identificar código CSS duplicado
 * 
 * Analiza todos los archivos CSS y detecta:
 * - Reglas CSS duplicadas (mismo selector y propiedades)
 * - Propiedades duplicadas dentro de la misma regla
 * - Valores duplicados que podrían consolidarse en tokens
 * 
 * Uso: php scripts/dev/find_duplicate_css.php
 */

$cssDir = __DIR__ . '/../../frontend/css';
$results = [
    'duplicate_rules' => [],
    'duplicate_properties' => [],
    'duplicate_values' => [],
    'similar_selectors' => []
];

/**
 * Normaliza CSS removiendo espacios y comentarios
 */
function normalizeCSS($css) {
    // Remover comentarios
    $css = preg_replace('/\/\*.*?\*\//s', '', $css);
    // Remover espacios extra
    $css = preg_replace('/\s+/', ' ', $css);
    return trim($css);
}

/**
 * Extrae reglas CSS de un archivo
 */
function extractRules($css) {
    $rules = [];
    // Buscar selectores y sus bloques
    preg_match_all('/([^{]+)\{([^}]+)\}/', $css, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $selector = trim($match[1]);
        $properties = trim($match[2]);
        
        // Normalizar selector (remover espacios extra)
        $selector = preg_replace('/\s+/', ' ', $selector);
        
        // Extraer propiedades individuales
        $props = [];
        preg_match_all('/([^:;]+):([^;]+);/', $properties, $propMatches, PREG_SET_ORDER);
        foreach ($propMatches as $propMatch) {
            $propName = trim($propMatch[1]);
            $propValue = trim($propMatch[2]);
            $props[$propName] = $propValue;
        }
        
        if (!empty($props)) {
            $rules[] = [
                'selector' => $selector,
                'properties' => $props,
                'normalized' => normalizeCSS($match[0])
            ];
        }
    }
    
    return $rules;
}

/**
 * Compara dos reglas para ver si son duplicadas
 */
function areRulesDuplicate($rule1, $rule2) {
    // Comparar selectores normalizados
    if ($rule1['selector'] !== $rule2['selector']) {
        return false;
    }
    
    // Comparar propiedades
    if (count($rule1['properties']) !== count($rule2['properties'])) {
        return false;
    }
    
    foreach ($rule1['properties'] as $prop => $value) {
        if (!isset($rule2['properties'][$prop]) || $rule2['properties'][$prop] !== $value) {
            return false;
        }
    }
    
    return true;
}

/**
 * Encuentra valores hardcodeados que se repiten
 */
function findDuplicateValues($rules) {
    $valueCounts = [];
    
    foreach ($rules as $rule) {
        foreach ($rule['properties'] as $prop => $value) {
            // Solo valores que no son variables CSS
            if (!preg_match('/var\(--/', $value)) {
                $key = $prop . ':' . $value;
                if (!isset($valueCounts[$key])) {
                    $valueCounts[$key] = [
                        'property' => $prop,
                        'value' => $value,
                        'count' => 0,
                        'files' => []
                    ];
                }
                $valueCounts[$key]['count']++;
            }
        }
    }
    
    // Filtrar solo los que aparecen más de 3 veces
    return array_filter($valueCounts, function($item) {
        return $item['count'] > 3;
    });
}

/**
 * Encuentra selectores similares
 */
function findSimilarSelectors($rules) {
    $selectors = [];
    
    foreach ($rules as $rule) {
        $base = preg_replace('/--[a-z-]+/', '', $rule['selector']); // Remover modificadores BEM
        if (!isset($selectors[$base])) {
            $selectors[$base] = [];
        }
        $selectors[$base][] = $rule['selector'];
    }
    
    // Filtrar solo los que tienen variantes
    return array_filter($selectors, function($variants) {
        return count($variants) > 1;
    });
}

// Recopilar todos los archivos CSS
$cssFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($cssDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'css') {
        $cssFiles[] = $file->getPathname();
    }
}

echo "🔍 Analizando " . count($cssFiles) . " archivos CSS...\n\n";

$allRules = [];
$fileRules = [];

// Procesar cada archivo
foreach ($cssFiles as $filePath) {
    $relativePath = str_replace($cssDir . '/', '', $filePath);
    $content = file_get_contents($filePath);
    $rules = extractRules($content);
    
    $fileRules[$relativePath] = $rules;
    $allRules = array_merge($allRules, array_map(function($rule) use ($relativePath) {
        $rule['file'] = $relativePath;
        return $rule;
    }, $rules));
}

// 1. Buscar reglas duplicadas exactas
echo "📋 1. Buscando reglas CSS duplicadas exactas...\n";
$duplicateRules = [];
for ($i = 0; $i < count($allRules); $i++) {
    for ($j = $i + 1; $j < count($allRules); $j++) {
        if (areRulesDuplicate($allRules[$i], $allRules[$j])) {
            $key = $allRules[$i]['selector'] . '|' . md5($allRules[$i]['normalized']);
            if (!isset($duplicateRules[$key])) {
                $duplicateRules[$key] = [
                    'selector' => $allRules[$i]['selector'],
                    'properties' => $allRules[$i]['properties'],
                    'files' => []
                ];
            }
            if (!in_array($allRules[$i]['file'], $duplicateRules[$key]['files'])) {
                $duplicateRules[$key]['files'][] = $allRules[$i]['file'];
            }
            if (!in_array($allRules[$j]['file'], $duplicateRules[$key]['files'])) {
                $duplicateRules[$key]['files'][] = $allRules[$j]['file'];
            }
        }
    }
}

if (empty($duplicateRules)) {
    echo "   ✅ No se encontraron reglas duplicadas exactas.\n\n";
} else {
    echo "   ⚠️  Se encontraron " . count($duplicateRules) . " reglas duplicadas:\n";
    foreach ($duplicateRules as $key => $duplicate) {
        echo "   - Selector: " . $duplicate['selector'] . "\n";
        echo "     Archivos: " . implode(', ', $duplicate['files']) . "\n";
        echo "     Propiedades: " . count($duplicate['properties']) . "\n\n";
    }
}

// 2. Buscar propiedades duplicadas dentro de reglas
echo "📋 2. Buscando propiedades duplicadas dentro de reglas...\n";
$duplicateProps = [];
foreach ($allRules as $rule) {
    $props = array_keys($rule['properties']);
    $duplicates = array_diff_assoc($props, array_unique($props));
    if (!empty($duplicates)) {
        $duplicateProps[] = [
            'file' => $rule['file'],
            'selector' => $rule['selector'],
            'properties' => array_unique($duplicates)
        ];
    }
}

if (empty($duplicateProps)) {
    echo "   ✅ No se encontraron propiedades duplicadas.\n\n";
} else {
    echo "   ⚠️  Se encontraron " . count($duplicateProps) . " reglas con propiedades duplicadas:\n";
    foreach ($duplicateProps as $dup) {
        echo "   - Archivo: " . $dup['file'] . "\n";
        echo "     Selector: " . $dup['selector'] . "\n";
        echo "     Propiedades duplicadas: " . implode(', ', $dup['properties']) . "\n\n";
    }
}

// 3. Buscar valores que se repiten (candidatos para tokens)
echo "📋 3. Buscando valores repetidos (candidatos para design tokens)...\n";
$duplicateValues = findDuplicateValues($allRules);
if (empty($duplicateValues)) {
    echo "   ✅ No se encontraron valores repetidos significativos.\n\n";
} else {
    echo "   ⚠️  Se encontraron " . count($duplicateValues) . " valores que se repiten (considerar tokens):\n";
    foreach ($duplicateValues as $item) {
        echo "   - " . $item['property'] . ": " . $item['value'] . " (aparece " . $item['count'] . " veces)\n";
    }
    echo "\n";
}

// 4. Buscar selectores similares
echo "📋 4. Buscando selectores similares (posibles variantes BEM)...\n";
$similarSelectors = findSimilarSelectors($allRules);
if (empty($similarSelectors)) {
    echo "   ✅ No se encontraron selectores similares problemáticos.\n\n";
} else {
    $count = 0;
    foreach ($similarSelectors as $base => $variants) {
        if (count($variants) > 3) { // Solo mostrar si hay más de 3 variantes
            $count++;
            echo "   - Base: " . $base . " (" . count($variants) . " variantes)\n";
        }
    }
    if ($count === 0) {
        echo "   ✅ No se encontraron selectores similares problemáticos.\n\n";
    } else {
        echo "\n";
    }
}

// Generar reporte
$report = [
    'timestamp' => date('Y-m-d H:i:s'),
    'files_analyzed' => count($cssFiles),
    'total_rules' => count($allRules),
    'duplicate_rules' => count($duplicateRules),
    'duplicate_properties' => count($duplicateProps),
    'duplicate_values' => count($duplicateValues),
    'details' => [
        'duplicate_rules' => $duplicateRules,
        'duplicate_properties' => $duplicateProps,
        'duplicate_values' => $duplicateValues
    ]
];

$reportFile = __DIR__ . '/../../docs/CSS_DUPLICATE_ANALYSIS.md';
$reportContent = "# Análisis de Código CSS Duplicado\n\n";
$reportContent .= "**Fecha:** " . $report['timestamp'] . "\n\n";
$reportContent .= "## Resumen\n\n";
$reportContent .= "- Archivos analizados: " . $report['files_analyzed'] . "\n";
$reportContent .= "- Total de reglas CSS: " . $report['total_rules'] . "\n";
$reportContent .= "- Reglas duplicadas exactas: " . $report['duplicate_rules'] . "\n";
$reportContent .= "- Reglas con propiedades duplicadas: " . $report['duplicate_properties'] . "\n";
$reportContent .= "- Valores repetidos (candidatos para tokens): " . $report['duplicate_values'] . "\n\n";

if (!empty($duplicateRules)) {
    $reportContent .= "## Reglas Duplicadas Exactas\n\n";
    foreach ($duplicateRules as $key => $dup) {
        $reportContent .= "### " . $dup['selector'] . "\n\n";
        $reportContent .= "- **Archivos:** " . implode(', ', $dup['files']) . "\n";
        $reportContent .= "- **Propiedades:** " . count($dup['properties']) . "\n";
        $reportContent .= "\n**Propiedades:**\n";
        foreach ($dup['properties'] as $prop => $value) {
            $reportContent .= "- `" . $prop . ": " . $value . "`\n";
        }
        $reportContent .= "\n";
    }
}

if (!empty($duplicateProps)) {
    $reportContent .= "## Reglas con Propiedades Duplicadas\n\n";
    foreach ($duplicateProps as $dup) {
        $reportContent .= "- **Archivo:** " . $dup['file'] . "\n";
        $reportContent .= "- **Selector:** " . $dup['selector'] . "\n";
        $reportContent .= "- **Propiedades duplicadas:** " . implode(', ', $dup['properties']) . "\n\n";
    }
}

if (!empty($duplicateValues)) {
    $reportContent .= "## Valores Repetidos (Candidatos para Design Tokens)\n\n";
    $reportContent .= "Estos valores aparecen múltiples veces y podrían consolidarse en design tokens:\n\n";
    foreach ($duplicateValues as $item) {
        $reportContent .= "- `" . $item['property'] . ": " . $item['value'] . "` (aparece " . $item['count'] . " veces)\n";
    }
}

file_put_contents($reportFile, $reportContent);

echo "✅ Análisis completado. Reporte guardado en: docs/CSS_DUPLICATE_ANALYSIS.md\n";

