<?php

/**
 * Punto de entrada principal del sistema
 * Router básico para servir páginas y APIs
 */

// Cargar helpers necesarios
require_once __DIR__ . '/backend/helpers/i18n.php';

// Determinar si es una petición de API o página
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];

// Calcular la base URL del proyecto (maneja subdirectorios)
$basePath = dirname($scriptName);
// Normalizar: eliminar barras finales y asegurar que empiece con /
$basePath = '/' . trim($basePath, '/');
if ($basePath === '/') {
    $basePath = '/';
} else {
    // Asegurar que no termine en barra
    $basePath = rtrim($basePath, '/');
}

// Extraer el path relativo
$path = $requestUri;
if ($basePath !== '/') {
    $path = str_replace($basePath, '', $requestUri);
}
$path = trim($path, '/');

// Limpiar query string si existe
if (($pos = strpos($path, '?')) !== false) {
    $path = substr($path, 0, $pos);
}

// Si es una petición a API, redirigir al endpoint correspondiente
if (strpos($path, 'api/') === 0) {
    // Extraer el path después de 'api/'
    $apiPath = str_replace('api/', '', $path);

    // Manejar versionado: api/v1/tenants -> tenants
    // También soportar api/tenants (sin versión, asume v1)
    $parts = explode('/', $apiPath);

    // Si el primer segmento es una versión (v1, v2, etc.)
    if (count($parts) > 0 && preg_match('/^v\d+$/', $parts[0])) {
        $version = $parts[0];
        $apiPath = implode('/', array_slice($parts, 1));

        // EXCEPCIÓN: Rutas de autenticación NO deben pasar por API pública (no requieren API Key)
        $authRoutes = ['auth/login', 'auth/refresh', 'auth/logout'];
        $isAuthRoute = false;
        foreach ($authRoutes as $authRoute) {
            if (strpos($apiPath, $authRoute) === 0) {
                $isAuthRoute = true;
                break;
            }
        }

        // Si es ruta de autenticación, continuar con el flujo normal (no usar API pública)
        // Si NO es autenticación, continuar con el flujo normal para buscar en $apiRoutes
        // Solo usar API pública si es la ruta raíz /api/v1 y no hay path específico
        if ($version === 'v1' && empty($apiPath) && !$isAuthRoute) {
            // Ruta raíz /api/v1 - usar index de API pública
            $apiFile = __DIR__ . '/backend/api/v1/index.php';
            if (file_exists($apiFile)) {
                $GLOBALS['API_PATH'] = '/' . $apiPath;
                require $apiFile;
                exit;
            }
        }
        // Para rutas con path específico, continuar con el flujo normal para buscar en $apiRoutes
        // La API pública solo se usará si no se encuentra en $apiRoutes (ver más abajo)
    }

    // Mapeo de rutas API
    $apiRoutes = [
        'tenants' => 'tenants.php',
        'users' => 'users.php',
        'users/invite' => 'users.php',
        'agency-company-links' => 'agency_company_links.php',
        'staffing-requests' => 'staffing_requests.php',
        'staffing-proposals' => 'staffing_proposals.php',
        'shifts' => 'shifts.php',
        'work-groups' => 'work_groups.php',
        'work-orders' => 'work_orders.php',
        'work-order-assignments' => 'work_order_assignments.php',
        'work-order-daily-reports' => 'work_order_daily_reports.php',
        'work-order-signoffs' => 'work_order_signoffs.php',
        'group-shift-reports' => 'group_shift_reports.php',
        'approvals' => 'approvals.php',
        'disputes' => 'disputes.php',
        'ratings' => 'ratings.php',
        'invoices' => 'invoices.php',
        'reports' => 'reports.php',
        'exports' => 'exports.php',
        'api-keys' => 'api_keys.php',
        'webhooks' => 'webhooks.php',
        'notification-preferences' => 'notification_preferences.php',
        'health' => 'health.php',
        'import-export' => 'import_export.php',
        'gdpr' => 'gdpr.php',
        'clients' => 'clients.php',
        'projects' => 'projects.php',
        'employees' => 'employees.php',
        'agency-workers' => 'agency_workers.php',
        'time-clock' => 'time_clock.php',
        'i18n' => 'i18n.php',
        'assignments' => 'assignments.php',
        'own-employees' => 'own_employees.php',
        'auth/login' => 'auth.php',
        'auth/refresh' => 'auth.php',
        'config' => 'config.php'
    ];

    // Buscar ruta exacta o parcial
    $apiFile = null;

    // Primero buscar ruta exacta
    if (isset($apiRoutes[$apiPath])) {
        $apiFile = __DIR__ . '/backend/api/' . $apiRoutes[$apiPath];
    } else {
        // Buscar ruta parcial (ej: users/invite → users.php)
        foreach ($apiRoutes as $route => $file) {
            if (strpos($apiPath, $route) === 0) {
                $apiFile = __DIR__ . '/backend/api/' . $file;
                break;
            }
        }
    }

    // Si no se encuentra, intentar buscar archivo directo
    if (!$apiFile || !file_exists($apiFile)) {
        // Intentar encontrar archivo por nombre (primer segmento del path)
        $pathParts = explode('/', $apiPath);
        $possibleFile = __DIR__ . '/backend/api/' . $pathParts[0] . '.php';
        if (file_exists($possibleFile)) {
            $apiFile = $possibleFile;
        }
    }

    if ($apiFile && file_exists($apiFile)) {
        // Pasar el path procesado como variable global para que los endpoints puedan acceder
        // Esto permite manejar rutas con IDs como /api/v1/tenants/123
        $GLOBALS['API_PATH'] = $apiPath;
        $GLOBALS['API_FULL_PATH'] = $path;
        require_once $apiFile;
        exit;
    } else {
        // Si no se encontró en $apiRoutes y es /api/v1/*, intentar API pública como último recurso
        // Solo para rutas específicas de la API pública (shifts, work-logs, work-orders en modo read-only)
        if (preg_match('#^/api/v1/#', $path)) {
            $publicApiPath = preg_replace('#^/api/v1/?#', '', $path);
            $publicApiResource = explode('/', $publicApiPath)[0];
            
            // Solo recursos permitidos en API pública
            $publicApiResources = ['shifts', 'work-logs', 'work_logs', 'work-orders', 'work_orders'];
            
            if (in_array($publicApiResource, $publicApiResources)) {
                $apiFile = __DIR__ . '/backend/api/v1/index.php';
                if (file_exists($apiFile)) {
                    $GLOBALS['API_PATH'] = '/' . $publicApiPath;
                    require $apiFile;
                    exit;
                }
            }
        }
        
        // Si no se encontró en ningún lado, retornar 404
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'errors.not_found',
            'error_key' => 'errors.not_found',
            'message' => 'Endpoint no encontrado'
        ]);
        exit;
    }
}

// Si no es API, servir página HTML
// Páginas disponibles
$pages = [
    '' => 'frontend/pages/index.html',
    'index' => 'frontend/pages/index.html',
    'login' => 'frontend/pages/login.html',
    'onboarding' => 'frontend/pages/onboarding.html',
    'dashboard' => 'frontend/pages/dashboard.html',
    'owner/dashboard' => 'frontend/pages/owner/dashboard.html',
    'owner/billing' => 'frontend/pages/owner/billing.html',
    'reports' => 'frontend/pages/reports.html',
        'settings-notifications' => 'frontend/pages/settings-notifications.html',
        'import-export' => 'frontend/pages/import-export.html',
        'employees' => 'frontend/pages/employees.html',
        'agency-workers' => 'frontend/pages/agency-workers.html',
        'time-clock' => 'frontend/pages/time-clock.html',
    'agency-company-links' => 'frontend/pages/agency-company-links.html',
    'staffing-requests' => 'frontend/pages/staffing-requests.html',
    'staffing-proposals' => 'frontend/pages/staffing-proposals.html',
    'calendar' => 'frontend/pages/calendar.html',
    'group-builder' => 'frontend/pages/group-builder.html',
    'work-orders' => 'frontend/pages/work-orders.html',
    'work-orders-edit' => 'frontend/pages/work_orders_edit.html',
    'work-orders-calendar' => 'frontend/pages/work-orders-calendar.html',
    'assignments' => 'frontend/pages/assignments.html',
    'group-shift-report' => 'frontend/pages/group-shift-report.html',
    'work-order-daily-report' => 'frontend/pages/work-order-daily-report.html',
    'approvals' => 'frontend/pages/approvals.html',
    'disputes' => 'frontend/pages/disputes.html',
    'ratings' => 'frontend/pages/ratings.html',
    'settings' => 'frontend/pages/settings.html'
];

// Buscar página
$pageFile = null;
if (empty($path)) {
    $pageFile = __DIR__ . '/frontend/pages/index.html';
} else {
    // Buscar página exacta
    if (isset($pages[$path])) {
        $pageFile = __DIR__ . '/' . $pages[$path];
    } else {
        // Intentar buscar archivo directo
        $possibleFile = __DIR__ . '/frontend/pages/' . $path . '.html';
        if (file_exists($possibleFile)) {
            $pageFile = $possibleFile;
        }
    }
}

if ($pageFile && file_exists($pageFile)) {
    // Servir página HTML
    // Inyectar base URL en el HTML antes de servir
    $htmlContent = file_get_contents($pageFile);

    // Determinar si la página necesita layout (sidebar + header)
    // Páginas que NO necesitan layout: index, login, onboarding
    $needsLayout = !in_array($path, ['', 'index', 'login', 'onboarding']);

    if ($needsLayout) {
        // Cargar componentes sidebar y header
        $sidebarFile = __DIR__ . '/frontend/components/sidebar.html';
        $headerFile = __DIR__ . '/frontend/components/header.html';

        $sidebarContent = '';
        $headerContent = '';

        if (file_exists($sidebarFile)) {
            $sidebarContent = file_get_contents($sidebarFile);
        }

        if (file_exists($headerFile)) {
            $headerContent = file_get_contents($headerFile);
        }

        // Inyectar JS de layout si no está ya incluido
        // El CSS de layout ya está incluido en main.css (importado desde 05-objects/_layout.css)
        // Solo necesitamos inyectar el JS de layout
        if (strpos($htmlContent, 'layout.js') === false) {
            // Usar ruta relativa que empiece con / para que el procesamiento de rutas la maneje
            $layoutJsPath = '/frontend/js/layout.js';
            $layoutJs = '<script src="' . htmlspecialchars($layoutJsPath, ENT_QUOTES, 'UTF-8') . '"></script>';
            $htmlContent = str_replace('</body>', $layoutJs . '</body>', $htmlContent);
        }

        // Envolver el body con app-layout y agregar sidebar y header
        // Buscar el tag <body> y agregar clase app-layout
        $htmlContent = preg_replace('/<body([^>]*)>/i', '<body$1 class="app-layout">', $htmlContent);

        // Cargar skip links para accesibilidad
        $skipLinksFile = __DIR__ . '/frontend/components/skip-links.html';
        $skipLinksContent = '';
        if (file_exists($skipLinksFile)) {
            $skipLinksContent = file_get_contents($skipLinksFile);
        }

        // Insertar skip links primero (después del <body>)
        if ($skipLinksContent) {
            $htmlContent = preg_replace('/(<body[^>]*>)/i', '$1' . $skipLinksContent, $htmlContent);
        }

        // Insertar sidebar después de los skip links
        if ($sidebarContent) {
            // Buscar el final de </nav> (skip links) o después del body
            if (preg_match('/(<\/nav>)/i', $htmlContent)) {
                $htmlContent = preg_replace('/(<\/nav>)/i', '$1' . $sidebarContent, $htmlContent, 1);
            } else {
                $htmlContent = preg_replace('/(<body[^>]*>)/i', '$1' . $sidebarContent, $htmlContent);
            }
        }

        // Estructura final esperada:
        // <body class="app-layout">
        //   <aside>sidebar</aside>
        //   <div class="app-content">
        //     <header>header</header>
        //     <div class="app-content-inner">
        //       <div class="container-fluid">contenido</div>
        //     </div>
        //   </div>
        // </body>

        // Primero, crear el wrapper app-content después del sidebar
        // Insertar app-content después del sidebar (y sus elementos relacionados)
        $appContentStart = '<div class="app-content">';

        // Buscar el final del sidebar (incluyendo overlay y toggle si existen)
        // El sidebar termina con </aside>, pero también puede haber overlay y toggle después
        if (preg_match('/(<\/aside>.*?<\/button>)/is', $htmlContent)) {
            // Si hay sidebar + overlay + toggle
            $htmlContent = preg_replace('/(<\/aside>.*?<\/button>)/is', '$1' . $appContentStart, $htmlContent, 1);
        } else if (preg_match('/(<\/aside>)/i', $htmlContent)) {
            // Solo sidebar
            $htmlContent = preg_replace('/(<\/aside>)/i', '$1' . $appContentStart, $htmlContent, 1);
        } else {
            // Fallback: después del body
            $htmlContent = preg_replace('/(<body[^>]*class="app-layout"[^>]*>)/i', '$1' . $appContentStart, $htmlContent, 1);
        }

        // Ahora insertar el header dentro de app-content
        if ($headerContent) {
            // Buscar el inicio de app-content y insertar header después
            $htmlContent = preg_replace('/(<div class="app-content">)/i', '$1' . $headerContent, $htmlContent, 1);
        }

        // Ahora envolver el contenido en app-content-inner
        // NAV-132: Asegurar que haya un elemento con id="main-content" para el skip link
        // Buscar el primer container-fluid o el primer div después del header
        if (preg_match('/(<\/header>)/i', $htmlContent)) {
            // Si hay header, insertar app-content-inner después del header
            $htmlContent = preg_replace('/(<\/header>)/i', '$1<div class="app-content-inner">', $htmlContent, 1);
            
            // NAV-132: Agregar id="main-content" al primer elemento principal después del header si no lo tiene
            if (!preg_match('/id=["\']main-content["\']/i', $htmlContent)) {
                // Buscar el primer <main> o <div class="container"> después del header
                if (preg_match('/(<\/header>\s*<div class="app-content-inner">\s*)(<main)([^>]*>)/i', $htmlContent)) {
                    // Si hay <main>, agregar id="main-content"
                    $htmlContent = preg_replace('/(<\/header>\s*<div class="app-content-inner">\s*)(<main)([^>]*>)/i', '$1$2 id="main-content"$3', $htmlContent, 1);
                } else if (preg_match('/(<\/header>\s*<div class="app-content-inner">\s*)(<div[^>]*class="[^"]*container[^"]*")([^>]*>)/i', $htmlContent)) {
                    // Si hay container, agregar id="main-content"
                    $htmlContent = preg_replace('/(<\/header>\s*<div class="app-content-inner">\s*)(<div)([^>]*class="[^"]*container[^"]*")([^>]*>)/i', '$1$2$3 id="main-content"$4', $htmlContent, 1);
                } else {
                    // Agregar id="main-content" al primer div después de app-content-inner
                    $htmlContent = preg_replace('/(<\/header>\s*<div class="app-content-inner">\s*)(<div)([^>]*>)/i', '$1$2$3 id="main-content"$4', $htmlContent, 1);
                }
            }
        } else if (preg_match('/(<div[^>]*class="[^"]*container[^"]*"[^>]*>)/i', $htmlContent, $matches, PREG_OFFSET_CAPTURE)) {
            // Si hay container, envolver desde ahí
            $offset = $matches[0][1];
            $before = substr($htmlContent, 0, $offset);
            $after = substr($htmlContent, $offset);
            // NAV-132: Agregar id="main-content" al container si no lo tiene
            if (!preg_match('/id=["\']main-content["\']/i', $matches[0][0])) {
                $after = preg_replace('/(<div)([^>]*class="[^"]*container[^"]*")([^>]*>)/i', '$1$2 id="main-content"$3', $after, 1);
            }
            $htmlContent = $before . '<div class="app-content-inner">' . $after;
        } else {
            // Buscar el primer div después de app-content
            // NAV-132: Agregar id="main-content" si no existe
            if (!preg_match('/id=["\']main-content["\']/i', $htmlContent)) {
                $htmlContent = preg_replace('/(<div class="app-content">.*?)(<main)([^>]*>)/is', '$1$2 id="main-content"$3', $htmlContent, 1);
                if (!preg_match('/id=["\']main-content["\']/i', $htmlContent)) {
                    $htmlContent = preg_replace('/(<div class="app-content">.*?)(<div)([^>]*>)/is', '$1$2$3 id="main-content"$4', $htmlContent, 1);
                }
            }
            $htmlContent = preg_replace('/(<div class="app-content">.*?)(<div[^>]*>)/is', '$1<div class="app-content-inner">$2', $htmlContent, 1);
        }

        // Cerrar app-content-inner y app-content antes de </body>
        $htmlContent = str_replace('</body>', '</div></div></body>', $htmlContent);
    }

    // Procesar traducciones data-i18n en el servidor
    // 1. Procesar atributos data-i18n-placeholder (para inputs, textareas, etc.)
    $htmlContent = preg_replace_callback(
        '/<([a-zA-Z][a-zA-Z0-9]*)([^>]*?)\s+data-i18n-placeholder=["\']([^"\']+)["\']([^>]*?)>/is',
        function ($matches) {
            $tagName = $matches[1];
            $attrsBefore = trim($matches[2]);
            $key = $matches[3];
            $attrsAfter = trim($matches[4]);

            // Combinar todos los atributos
            $allAttrs = trim($attrsBefore . ' ' . $attrsAfter);

            // Obtener traducción
            $translation = t($key);

            // Remover data-i18n-placeholder y agregar placeholder con traducción
            $cleanAttrs = preg_replace('/\s+data-i18n-placeholder=["\'][^"\']+["\']/i', '', $allAttrs);
            $cleanAttrs = preg_replace('/\s+/', ' ', $cleanAttrs);
            $cleanAttrs = trim($cleanAttrs);

            // Agregar placeholder traducido
            if ($cleanAttrs) {
                return "<{$tagName} {$cleanAttrs} placeholder=\"" . htmlspecialchars($translation, ENT_QUOTES, 'UTF-8') . "\">";
            } else {
                return "<{$tagName} placeholder=\"" . htmlspecialchars($translation, ENT_QUOTES, 'UTF-8') . "\">";
            }
        },
        $htmlContent
    );

    // 2. Procesar contenido de elementos con data-i18n (labels, spans, divs, etc.)
    $htmlContent = preg_replace_callback(
        '/<([a-zA-Z][a-zA-Z0-9]*)([^>]*?)\s+data-i18n=["\']([^"\']+)["\']([^>]*?)>([^<]*?)<\/\1>/is',
        function ($matches) {
            $tagName = $matches[1];
            $attrsBefore = trim($matches[2]);
            $key = $matches[3];
            $attrsAfter = trim($matches[4]);
            $fallback = trim($matches[5]);

            // Combinar todos los atributos
            $allAttrs = trim($attrsBefore . ' ' . $attrsAfter);

            // Extraer variables data-i18n-vars
            $vars = [];
            if (preg_match('/data-i18n-vars=["\']([^"\']+)["\']/i', $allAttrs, $varMatches)) {
                $varsJson = html_entity_decode($varMatches[1], ENT_QUOTES, 'UTF-8');
                $vars = json_decode($varsJson, true) ?: [];
            }

            // Obtener traducción
            $translation = t($key, $vars);

            // Remover data-i18n y data-i18n-vars de los atributos, preservando el resto (incluido ID)
            $cleanAttrs = preg_replace('/\s+data-i18n(?:-vars)?=["\'][^"\']+["\']/i', '', $allAttrs);
            $cleanAttrs = preg_replace('/\s+/', ' ', $cleanAttrs); // Normalizar espacios múltiples
            $cleanAttrs = trim($cleanAttrs);

            // Construir el tag preservando todos los atributos importantes (ID, class, etc.)
            if ($cleanAttrs) {
                return "<{$tagName} {$cleanAttrs}>{$translation}</{$tagName}>";
            } else {
                return "<{$tagName}>{$translation}</{$tagName}>";
            }
        },
        $htmlContent
    );

    // 3. Procesar opciones de select con data-i18n (para elementos <option>)
    $htmlContent = preg_replace_callback(
        '/<option([^>]*?)\s+data-i18n=["\']([^"\']+)["\']([^>]*?)>([^<]*?)<\/option>/is',
        function ($matches) {
            $attrsBefore = trim($matches[1]);
            $key = $matches[2];
            $attrsAfter = trim($matches[3]);
            $fallback = trim($matches[4]);

            // Combinar todos los atributos
            $allAttrs = trim($attrsBefore . ' ' . $attrsAfter);

            // Obtener traducción
            $translation = t($key);

            // Remover data-i18n de los atributos
            $cleanAttrs = preg_replace('/\s+data-i18n=["\'][^"\']+["\']/i', '', $allAttrs);
            $cleanAttrs = preg_replace('/\s+/', ' ', $cleanAttrs);
            $cleanAttrs = trim($cleanAttrs);

            // Construir el option
            if ($cleanAttrs) {
                return "<option {$cleanAttrs}>{$translation}</option>";
            } else {
                return "<option>{$translation}</option>";
            }
        },
        $htmlContent
    );

    // Reemplazar rutas absolutas locales (no externas) con base URL
    // Solo reemplazar si no empiezan con http://, https://, //, o si son rutas relativas locales
    // IMPORTANTE: Evitar duplicar basePath si ya está presente
    if ($basePath !== '/') {
        $basePathEscaped = preg_quote($basePath, '/');

        // Primero limpiar cualquier duplicación existente
        $htmlContent = preg_replace(
            '/(href|src|action)=["\']' . $basePathEscaped . $basePathEscaped . '/i',
            '$1="' . $basePath . '/',
            $htmlContent
        );

        $patterns = [
            // href="/ruta" -> href="/SaSS_Workee/ruta" (solo si no es URL externa y no empieza con basePath)
            '/(href=["\'])\/(?!\/|http|https|mailto|tel|#|javascript:|' . $basePathEscaped . '\/)([^"\']+)/i' => '$1' . $basePath . '/$2',
            // src="/ruta" -> src="/SaSS_Workee/ruta" (solo si no es URL externa y no empieza con basePath)
            '/(src=["\'])\/(?!\/|http|https|data:|' . $basePathEscaped . '\/)([^"\']+)/i' => '$1' . $basePath . '/$2',
            // action="/ruta" -> action="/SaSS_Workee/ruta" (solo si no empieza con basePath)
            '/(action=["\'])\/(?!\/|http|https|' . $basePathEscaped . '\/)([^"\']+)/i' => '$1' . $basePath . '/$2',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $htmlContent = preg_replace($pattern, $replacement, $htmlContent);
        }

        // Limpiar duplicaciones finales (por si acaso)
        $htmlContent = preg_replace(
            '/(href|src|action)=["\']' . $basePathEscaped . $basePathEscaped . '/i',
            '$1="' . $basePath . '/',
            $htmlContent
        );
    }

    // Inyectar variable JavaScript con base URL antes de </head> o </body>
    $baseUrlScript = '<script>window.BASE_URL = "' . htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') . '";</script>';
    if (strpos($htmlContent, '</head>') !== false) {
        $htmlContent = str_replace('</head>', $baseUrlScript . '</head>', $htmlContent);
    } else if (strpos($htmlContent, '</body>') !== false) {
        $htmlContent = str_replace('</body>', $baseUrlScript . '</body>', $htmlContent);
    } else {
        $htmlContent .= $baseUrlScript;
    }

    echo $htmlContent;
} else {
    // Página no encontrada
    http_response_code(404);
    $basePathEscaped = htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html>
<html>
<head>
    <title>404 - Página no encontrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">
    <div class="text-center">
        <h1>404</h1>
        <p>Página no encontrada</p>
        <a href="' . $basePathEscaped . '/" class="btn btn-primary">Ir al inicio</a>
    </div>
</body>
</html>';
}
