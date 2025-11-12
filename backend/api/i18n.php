<?php
/**
 * API Internacionalización (i18n)
 * 
 * Endpoints:
 * - GET /api/i18n/translations?locale={locale}&namespace={namespace1,namespace2,...} - Obtener traducciones
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../helpers/i18n.php';
require_once __DIR__ . '/../helpers/env.php';

// Deshabilitar display_errors para evitar HTML en respuestas JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = $GLOBALS['API_PATH'] ?? $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '';
    
    // Parsear path
    $pathParts = explode('/', trim($path, '/'));
    $resource = $pathParts[1] ?? null;
    
    if ($method === 'GET' && $resource === 'translations') {
        getTranslations();
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'not_found',
            'error_key' => 'errors.not_found',
            'message' => 'Endpoint no encontrado'
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
 * Obtener traducciones
 */
function getTranslations() {
    $locale = $_GET['locale'] ?? 'es';
    $namespaces = isset($_GET['namespace']) ? explode(',', $_GET['namespace']) : ['common'];
    
    $translations = [];
    
    foreach ($namespaces as $namespace) {
        $namespace = trim($namespace);
        $file = __DIR__ . '/../lang/' . $locale . '/' . $namespace . '.php';
        
        if (file_exists($file)) {
            $nsTranslations = require $file;
            if (is_array($nsTranslations)) {
                $translations[$namespace] = $nsTranslations;
            }
        } else {
            // Fallback a español
            if ($locale !== 'es') {
                $fileEs = __DIR__ . '/../lang/es/' . $namespace . '.php';
                if (file_exists($fileEs)) {
                    $nsTranslations = require $fileEs;
                    if (is_array($nsTranslations)) {
                        $translations[$namespace] = $nsTranslations;
                    }
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $translations,
        'locale' => $locale
    ]);
}

