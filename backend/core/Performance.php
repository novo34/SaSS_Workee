<?php

/**
 * Performance
 * 
 * Utilidades para optimización de performance:
 * - Cache simple en memoria
 * - Helpers para evitar N+1 queries
 * - Validación de índices
 * - Optimización de queries
 */

require_once __DIR__ . '/../helpers/db_helper.php';

class Performance
{
    /**
     * Cache en memoria (simple, no persistente)
     */
    private static $cache = [];
    private static $cacheTimestamps = [];

    /**
     * TTL por defecto (segundos)
     */
    const DEFAULT_TTL = 300; // 5 minutos

    /**
     * Obtiene un valor del cache
     * 
     * @param string $key Clave del cache
     * @return mixed|null Valor o null si no existe/expirado
     */
    public static function getCache($key)
    {
        if (!isset(self::$cache[$key])) {
            return null;
        }

        // Verificar expiración
        if (isset(self::$cacheTimestamps[$key])) {
            $ttl = self::$cacheTimestamps[$key]['ttl'];
            $timestamp = self::$cacheTimestamps[$key]['timestamp'];

            if (time() - $timestamp > $ttl) {
                unset(self::$cache[$key], self::$cacheTimestamps[$key]);
                return null;
            }
        }

        return self::$cache[$key];
    }

    /**
     * Guarda un valor en el cache
     * 
     * @param string $key Clave del cache
     * @param mixed $value Valor a cachear
     * @param int $ttl TTL en segundos (default: 5 minutos)
     */
    public static function setCache($key, $value, $ttl = self::DEFAULT_TTL)
    {
        self::$cache[$key] = $value;
        self::$cacheTimestamps[$key] = [
            'timestamp' => time(),
            'ttl' => $ttl
        ];
    }

    /**
     * Elimina un valor del cache
     * 
     * @param string $key Clave del cache
     */
    public static function deleteCache($key)
    {
        unset(self::$cache[$key], self::$cacheTimestamps[$key]);
    }

    /**
     * Limpia todo el cache
     */
    public static function clearCache()
    {
        self::$cache = [];
        self::$cacheTimestamps = [];
    }

    /**
     * Obtiene múltiples registros con una sola query (evita N+1)
     * 
     * @param mysqli $conn Conexión
     * @param string $table Tabla
     * @param array $ids Array de IDs
     * @param string $idColumn Nombre de la columna ID (default: 'id')
     * @param string $tenantColumn Nombre de la columna tenant_id (default: 'tenant_id')
     * @param int|null $tenantId ID del tenant (opcional)
     * @return array Array asociativo [id => row]
     */
    public static function batchFetch($conn, $table, $ids, $idColumn = 'id', $tenantColumn = 'tenant_id', $tenantId = null)
    {
        if (empty($ids)) {
            return [];
        }

        // Sanitizar IDs
        $ids = array_map('intval', $ids);
        $ids = array_unique($ids);
        $idsPlaceholder = implode(',', array_fill(0, count($ids), '?'));

        $sql = "SELECT * FROM {$table} WHERE {$idColumn} IN ({$idsPlaceholder})";
        $params = $ids;
        $types = str_repeat('i', count($ids));

        if ($tenantId !== null) {
            $sql .= " AND {$tenantColumn} = ?";
            $params[] = $tenantId;
            $types .= 'i';
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[$row[$idColumn]] = $row;
        }
        $stmt->close();

        return $rows;
    }

    /**
     * Verifica que los índices necesarios existen
     * 
     * @param mysqli $conn Conexión
     * @param string $table Tabla
     * @param array $indexes Array de índices esperados ['idx_name' => ['column1', 'column2']]
     * @return array ['missing' => [...], 'existing' => [...]]
     */
    public static function checkIndexes($conn, $table, $indexes)
    {
        // Obtener índices existentes
        $stmt = $conn->prepare("SHOW INDEXES FROM {$table}");
        $stmt->execute();
        $result = $stmt->get_result();

        $existing = [];
        while ($row = $result->fetch_assoc()) {
            $indexName = $row['Key_name'];
            if ($indexName !== 'PRIMARY') {
                if (!isset($existing[$indexName])) {
                    $existing[$indexName] = [];
                }
                $existing[$indexName][] = $row['Column_name'];
            }
        }
        $stmt->close();

        // Comparar
        $missing = [];
        $found = [];

        foreach ($indexes as $indexName => $columns) {
            $columnsStr = implode(',', $columns);

            if (!isset($existing[$indexName])) {
                $missing[$indexName] = $columns;
            } else {
                $existingColumnsStr = implode(',', $existing[$indexName]);
                if ($existingColumnsStr !== $columnsStr) {
                    $missing[$indexName] = $columns;
                } else {
                    $found[$indexName] = $columns;
                }
            }
        }

        return [
            'missing' => $missing,
            'existing' => $found
        ];
    }

    /**
     * Optimiza una tabla (ANALYZE TABLE)
     * 
     * @param mysqli $conn Conexión
     * @param string $table Tabla
     * @return bool Éxito
     */
    public static function optimizeTable($conn, $table)
    {
        try {
            $conn->query("ANALYZE TABLE {$table}");
            return true;
        } catch (Exception $e) {
            error_log("Error optimizing table {$table}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene estadísticas de una tabla
     * 
     * @param mysqli $conn Conexión
     * @param string $table Tabla
     * @return array Estadísticas
     */
    public static function getTableStats($conn, $table)
    {
        $stmt = $conn->prepare("
            SELECT 
                table_rows,
                data_length,
                index_length,
                data_free
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
            AND table_name = ?
        ");
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = $result->fetch_assoc();
        $stmt->close();

        if ($stats) {
            return [
                'rows' => (int)$stats['table_rows'],
                'data_size_mb' => round($stats['data_length'] / 1024 / 1024, 2),
                'index_size_mb' => round($stats['index_length'] / 1024 / 1024, 2),
                'free_space_mb' => round($stats['data_free'] / 1024 / 1024, 2)
            ];
        }

        return null;
    }
}
