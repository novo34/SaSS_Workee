<?php

/**
 * Health Check API
 * 
 * Endpoint: GET /api/health
 * 
 * Verifica el estado de los servicios:
 * - Base de datos
 * - S3 (si está configurado)
 * - Servicios externos
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../helpers/db_helper.php';
require_once __DIR__ . '/../helpers/env.php';

try {
    $checks = [];
    $overallStatus = 'healthy';

    // Check 1: Base de datos
    $dbCheck = checkDatabase();
    $checks['database'] = $dbCheck;
    if ($dbCheck['status'] !== 'healthy') {
        $overallStatus = 'unhealthy';
    }

    // Check 2: S3 (opcional)
    $s3Check = checkS3();
    if ($s3Check !== null) {
        $checks['s3'] = $s3Check;
        if ($s3Check['status'] !== 'healthy') {
            $overallStatus = 'degraded';
        }
    }

    // Check 3: Memoria disponible
    $memoryCheck = checkMemory();
    $checks['memory'] = $memoryCheck;
    if ($memoryCheck['status'] !== 'healthy') {
        $overallStatus = 'degraded';
    }

    // Determinar código HTTP
    $httpCode = 200;
    if ($overallStatus === 'unhealthy') {
        $httpCode = 503; // Service Unavailable
    } elseif ($overallStatus === 'degraded') {
        $httpCode = 200; // OK pero con advertencias
    }

    http_response_code($httpCode);

    echo json_encode([
        'status' => $overallStatus,
        'timestamp' => date('c'),
        'checks' => $checks
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(503);
    echo json_encode([
        'status' => 'unhealthy',
        'error' => $e->getMessage(),
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT);
}

/**
 * Verifica conexión a base de datos
 */
function checkDatabase()
{
    try {
        $conn = DBHelper::getConnection();

        // Test query simple
        $start = microtime(true);
        $result = $conn->query("SELECT 1");
        $duration = (microtime(true) - $start) * 1000; // ms

        if ($result) {
            return [
                'status' => 'healthy',
                'response_time_ms' => round($duration, 2),
                'message' => 'Database connection OK'
            ];
        } else {
            return [
                'status' => 'unhealthy',
                'message' => 'Database query failed'
            ];
        }
    } catch (Exception $e) {
        return [
            'status' => 'unhealthy',
            'message' => 'Database connection failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Verifica conexión a S3 (si está configurado)
 */
function checkS3()
{
    $s3Enabled = Env::get('S3_ENABLED', 'false');

    if ($s3Enabled !== 'true') {
        return null; // S3 no configurado, no es un error
    }

    // Por ahora, solo verificar configuración
    // En producción, hacer un test de conexión real
    $s3Endpoint = Env::get('S3_ENDPOINT');
    $s3Bucket = Env::get('S3_BUCKET');

    if (empty($s3Endpoint) || empty($s3Bucket)) {
        return [
            'status' => 'unhealthy',
            'message' => 'S3 configuration incomplete'
        ];
    }

    return [
        'status' => 'healthy',
        'message' => 'S3 configuration OK',
        'endpoint' => $s3Endpoint,
        'bucket' => $s3Bucket
    ];
}

/**
 * Verifica memoria disponible
 */
function checkMemory()
{
    $memoryLimit = ini_get('memory_limit');
    $memoryUsed = memory_get_usage(true);
    $memoryPeak = memory_get_peak_usage(true);

    // Convertir memory_limit a bytes
    $limitBytes = parseMemoryLimit($memoryLimit);

    // Calcular porcentaje usado
    $percentUsed = ($memoryUsed / $limitBytes) * 100;
    $percentPeak = ($memoryPeak / $limitBytes) * 100;

    $status = 'healthy';
    if ($percentUsed > 90) {
        $status = 'unhealthy';
    } elseif ($percentUsed > 75) {
        $status = 'degraded';
    }

    return [
        'status' => $status,
        'memory_limit' => $memoryLimit,
        'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
        'memory_peak_mb' => round($memoryPeak / 1024 / 1024, 2),
        'percent_used' => round($percentUsed, 2),
        'percent_peak' => round($percentPeak, 2),
        'message' => "Memory usage: {$percentUsed}%"
    ];
}

/**
 * Parsea memory_limit a bytes
 */
function parseMemoryLimit($limit)
{
    $limit = trim($limit);
    $last = strtolower($limit[strlen($limit) - 1]);
    $value = (int)$limit;

    switch ($last) {
        case 'g':
            $value *= 1024;
        case 'm':
            $value *= 1024;
        case 'k':
            $value *= 1024;
    }

    return $value;
}
