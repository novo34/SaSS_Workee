<?php
/**
 * Observability
 * 
 * Sistema de observabilidad:
 * - Logs estructurados (JSON)
 * - Métricas de performance
 * - Request ID tracking
 * - Tiempos por endpoint
 */

require_once __DIR__ . '/../helpers/db_helper.php';
require_once __DIR__ . '/../helpers/env.php';

class Observability {
    /**
     * Niveles de log
     */
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARN = 'WARN';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';
    
    /**
     * Genera un Request ID único
     * 
     * @return string UUID v4
     */
    public static function generateRequestId() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Obtiene el Request ID actual o genera uno nuevo
     * 
     * @return string Request ID
     */
    public static function getRequestId() {
        if (!isset($GLOBALS['REQUEST_ID'])) {
            // Intentar obtener de header
            $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
            
            if (!$requestId) {
                $requestId = self::generateRequestId();
            }
            
            $GLOBALS['REQUEST_ID'] = $requestId;
        }
        
        return $GLOBALS['REQUEST_ID'];
    }
    
    /**
     * Agrega Request ID a headers de respuesta
     */
    public static function addRequestIdHeader() {
        $requestId = self::getRequestId();
        header("X-Request-ID: {$requestId}");
    }
    
    /**
     * Registra un log estructurado
     * 
     * @param string $level Nivel de log
     * @param string $message Mensaje
     * @param array $context Contexto adicional
     */
    public static function log($level, $message, $context = []) {
        $logEntry = [
            'timestamp' => date('c'), // ISO 8601
            'level' => $level,
            'request_id' => self::getRequestId(),
            'tenant_id' => $GLOBALS['TENANT_ID'] ?? null,
            'user_id' => $GLOBALS['USER_ID'] ?? null,
            'endpoint' => $_SERVER['REQUEST_URI'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'message' => $message
        ];
        
        // Agregar contexto
        if (!empty($context)) {
            $logEntry = array_merge($logEntry, $context);
        }
        
        // Agregar duración si está disponible
        if (isset($GLOBALS['REQUEST_START_TIME'])) {
            $duration = (microtime(true) - $GLOBALS['REQUEST_START_TIME']) * 1000; // ms
            $logEntry['duration_ms'] = round($duration, 2);
        }
        
        // Convertir a JSON
        $json = json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Escribir a archivo o stdout según entorno
        $logFile = Env::get('LOG_FILE', 'php://stderr');
        error_log($json, 3, $logFile);
    }
    
    /**
     * Log de debug
     */
    public static function debug($message, $context = []) {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * Log de info
     */
    public static function info($message, $context = []) {
        self::log(self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * Log de warning
     */
    public static function warn($message, $context = []) {
        self::log(self::LEVEL_WARN, $message, $context);
    }
    
    /**
     * Log de error
     */
    public static function error($message, $context = []) {
        self::log(self::LEVEL_ERROR, $message, $context);
    }
    
    /**
     * Log de critical
     */
    public static function critical($message, $context = []) {
        self::log(self::LEVEL_CRITICAL, $message, $context);
    }
    
    /**
     * Registra métrica de endpoint
     * 
     * @param string $endpoint Endpoint
     * @param string $method Método HTTP
     * @param int $statusCode Código de estado HTTP
     * @param float $duration Duración en milisegundos
     * @param int|null $tenantId ID del tenant
     */
    public static function recordEndpointMetric($endpoint, $method, $statusCode, $duration, $tenantId = null) {
        $conn = DBHelper::getConnection();
        
        // Crear tabla de métricas si no existe
        self::ensureMetricsTable($conn);
        
        $stmt = $conn->prepare("
            INSERT INTO endpoint_metrics (
                endpoint,
                method,
                status_code,
                duration_ms,
                tenant_id,
                request_id,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $requestId = self::getRequestId();
        $stmt->bind_param(
            'ssidss',
            $endpoint,
            $method,
            $statusCode,
            $duration,
            $tenantId,
            $requestId
        );
        
        $stmt->execute();
        $stmt->close();
        
        // Alertar si la duración excede umbral (1 segundo)
        if ($duration > 1000) {
            self::warn("Endpoint lento detectado", [
                'endpoint' => $endpoint,
                'method' => $method,
                'duration_ms' => $duration
            ]);
        }
        
        // Alertar si hay error (5xx)
        if ($statusCode >= 500) {
            self::error("Error en endpoint", [
                'endpoint' => $endpoint,
                'method' => $method,
                'status_code' => $statusCode
            ]);
        }
    }
    
    /**
     * Obtiene métricas de endpoint
     * 
     * @param string|null $endpoint Filtrar por endpoint
     * @param string|null $dateFrom Fecha desde (Y-m-d)
     * @param string|null $dateTo Fecha hasta (Y-m-d)
     * @return array Métricas agregadas
     */
    public static function getEndpointMetrics($endpoint = null, $dateFrom = null, $dateTo = null) {
        $conn = DBHelper::getConnection();
        
        $where = [];
        $params = [];
        $types = '';
        
        if ($endpoint !== null) {
            $where[] = "endpoint = ?";
            $params[] = $endpoint;
            $types .= 's';
        }
        
        if ($dateFrom !== null) {
            $where[] = "DATE(created_at) >= ?";
            $params[] = $dateFrom;
            $types .= 's';
        }
        
        if ($dateTo !== null) {
            $where[] = "DATE(created_at) <= ?";
            $params[] = $dateTo;
            $types .= 's';
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $stmt = $conn->prepare("
            SELECT 
                endpoint,
                method,
                COUNT(*) as request_count,
                AVG(duration_ms) as avg_duration_ms,
                MIN(duration_ms) as min_duration_ms,
                MAX(duration_ms) as max_duration_ms,
                SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) as error_count,
                SUM(CASE WHEN status_code >= 400 AND status_code < 500 THEN 1 ELSE 0 END) as client_error_count
            FROM endpoint_metrics
            {$whereClause}
            GROUP BY endpoint, method
            ORDER BY request_count DESC
        ");
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $metrics = [];
        while ($row = $result->fetch_assoc()) {
            $metrics[] = [
                'endpoint' => $row['endpoint'],
                'method' => $row['method'],
                'request_count' => (int)$row['request_count'],
                'avg_duration_ms' => round((float)$row['avg_duration_ms'], 2),
                'min_duration_ms' => round((float)$row['min_duration_ms'], 2),
                'max_duration_ms' => round((float)$row['max_duration_ms'], 2),
                'error_count' => (int)$row['error_count'],
                'client_error_count' => (int)$row['client_error_count']
            ];
        }
        $stmt->close();
        
        return $metrics;
    }
    
    /**
     * Asegura que la tabla endpoint_metrics existe
     */
    private static function ensureMetricsTable($conn) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS endpoint_metrics (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                endpoint VARCHAR(255) NOT NULL,
                method VARCHAR(10) NOT NULL,
                status_code INT NOT NULL,
                duration_ms DECIMAL(10, 2) NOT NULL,
                tenant_id INT NULL,
                request_id VARCHAR(36) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_endpoint (endpoint, method),
                INDEX idx_created (created_at),
                INDEX idx_tenant (tenant_id)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci
        ");
    }
    
    /**
     * Inicializa el tracking de request
     */
    public static function startRequest() {
        $GLOBALS['REQUEST_START_TIME'] = microtime(true);
        self::getRequestId();
        self::addRequestIdHeader();
    }
    
    /**
     * Finaliza el tracking de request y registra métrica
     */
    public static function endRequest($endpoint, $method, $statusCode) {
        if (isset($GLOBALS['REQUEST_START_TIME'])) {
            $duration = (microtime(true) - $GLOBALS['REQUEST_START_TIME']) * 1000; // ms
            $tenantId = $GLOBALS['TENANT_ID'] ?? null;
            self::recordEndpointMetric($endpoint, $method, $statusCode, $duration, $tenantId);
        }
    }
}

