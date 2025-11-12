<?php

/**
 * Helper de Base de Datos con tenant_id obligatorio
 * 
 * Todas las funciones SIEMPRE incluyen tenant_id en las queries
 * NUNCA permitir queries sin tenant_id para tablas de negocio
 */

require_once __DIR__ . '/env.php';

class DBHelper
{
    private static $conn = null;

    /**
     * Obtiene la conexión mysqli
     */
    public static function getConnection()
    {
        if (self::$conn === null) {
            $host = Env::get('DB_HOST', 'localhost');
            $dbname = Env::get('DB_NAME', 'workee');
            $username = Env::get('DB_USER', 'root');
            $password = Env::get('DB_PASSWORD', '');

            self::$conn = new mysqli($host, $username, $password, $dbname);

            if (self::$conn->connect_error) {
                throw new Exception('Error de conexión: ' . self::$conn->connect_error);
            }

            self::$conn->set_charset('utf8mb4');
        }

        return self::$conn;
    }

    /**
     * Ejecuta una query preparada con tenant_id obligatorio
     * 
     * @param string $sql Query SQL con ? para parámetros
     * @param string $types Tipos de parámetros (i, d, s, b)
     * @param array $params Parámetros [tenant_id, ...]
     * @return mysqli_stmt
     */
    public static function executeWithTenant($sql, $types, $params)
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception('Error preparando query: ' . $conn->error);
        }

        // Verificar que tenant_id esté en los parámetros
        if (empty($params) || !isset($params[0])) {
            throw new Exception('tenant_id es obligatorio en todas las queries');
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        if ($stmt->error) {
            throw new Exception('Error ejecutando query: ' . $stmt->error);
        }

        return $stmt;
    }

    /**
     * SELECT con tenant_id obligatorio
     * 
     * @param string $table Nombre de la tabla
     * @param int $tenant_id ID del tenant
     * @param array $conditions Condiciones adicionales ['field' => 'value']
     * @param string $orderBy Campo para ordenar
     * @param string $orderDir ASC o DESC
     * @param int $limit Límite de resultados
     * @param int $offset Offset para paginación
     * @return array Resultados
     */
    public static function selectWithTenant($table, $tenant_id, $conditions = [], $orderBy = null, $orderDir = 'ASC', $limit = null, $offset = null)
    {
        // Validar tenant_id
        if ($tenant_id === null || $tenant_id <= 0) {
            throw new Exception('tenant_id debe ser un entero positivo');
        }

        $conn = self::getConnection();

        $sql = "SELECT * FROM {$table} WHERE tenant_id = ?";
        $types = 'i';
        $params = [$tenant_id];

        // Agregar condiciones adicionales
        foreach ($conditions as $field => $value) {
            $sql .= " AND {$field} = ?";
            $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
            $params[] = $value;
        }

        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy} {$orderDir}";
        }

        if ($limit !== null) {
            $sql .= " LIMIT ?";
            $types .= 'i';
            $params[] = $limit;

            if ($offset !== null) {
                $sql .= " OFFSET ?";
                $types .= 'i';
                $params[] = $offset;
            }
        }

        $stmt = self::executeWithTenant($sql, $types, $params);
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $stmt->close();
        return $rows;
    }

    /**
     * INSERT con tenant_id obligatorio
     * 
     * @param string $table Nombre de la tabla
     * @param int $tenant_id ID del tenant
     * @param array $data Datos a insertar ['field' => 'value']
     * @return int ID del registro insertado
     */
    public static function insertWithTenant($table, $tenant_id, $data)
    {
        // Validar tenant_id
        if ($tenant_id === null || $tenant_id <= 0) {
            throw new Exception('tenant_id debe ser un entero positivo');
        }

        $conn = self::getConnection();

        // Asegurar que tenant_id esté en los datos
        $data['tenant_id'] = $tenant_id;

        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';

        $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES ({$placeholders})";

        $types = '';
        $params = [];
        foreach ($data as $value) {
            $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
            $params[] = $value;
        }

        $stmt = self::executeWithTenant($sql, $types, $params);
        $insertId = $conn->insert_id;
        $stmt->close();

        return $insertId;
    }

    /**
     * UPDATE con tenant_id obligatorio
     * 
     * @param string $table Nombre de la tabla
     * @param int $tenant_id ID del tenant
     * @param int $id ID del registro
     * @param array $data Datos a actualizar ['field' => 'value']
     * @return bool Éxito
     */
    public static function updateWithTenant($table, $tenant_id, $id, $data)
    {
        // Validar tenant_id
        if ($tenant_id === null || $tenant_id <= 0) {
            throw new Exception('tenant_id debe ser un entero positivo');
        }

        $conn = self::getConnection();

        $setParts = [];
        $types = '';
        $params = [];

        foreach ($data as $field => $value) {
            $setParts[] = "{$field} = ?";
            $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
            $params[] = $value;
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE id = ? AND tenant_id = ?";
        $types .= 'ii';
        $params[] = $id;
        $params[] = $tenant_id;

        $stmt = self::executeWithTenant($sql, $types, $params);
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected > 0;
    }

    /**
     * DELETE con tenant_id obligatorio
     * 
     * @param string $table Nombre de la tabla
     * @param int $tenant_id ID del tenant
     * @param int $id ID del registro
     * @return bool Éxito
     */
    public static function deleteWithTenant($table, $tenant_id, $id)
    {
        // Validar tenant_id
        if ($tenant_id === null || $tenant_id <= 0) {
            throw new Exception('tenant_id debe ser un entero positivo');
        }

        $conn = self::getConnection();

        $sql = "DELETE FROM {$table} WHERE id = ? AND tenant_id = ?";
        $types = 'ii';
        $params = [$id, $tenant_id];

        $stmt = self::executeWithTenant($sql, $types, $params);
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected > 0;
    }

    /**
     * Obtiene un registro por ID con tenant_id
     * 
     * @param string $table Nombre de la tabla
     * @param int $tenant_id ID del tenant
     * @param int $id ID del registro
     * @return array|null Registro o null
     */
    public static function getByIdWithTenant($table, $tenant_id, $id)
    {
        // Validar tenant_id
        if ($tenant_id === null || $tenant_id <= 0) {
            throw new Exception('tenant_id debe ser un entero positivo');
        }

        $conn = self::getConnection();

        $sql = "SELECT * FROM {$table} WHERE id = ? AND tenant_id = ? LIMIT 1";
        $types = 'ii';
        $params = [$id, $tenant_id];

        $stmt = self::executeWithTenant($sql, $types, $params);
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    /**
     * Cuenta registros con tenant_id
     * 
     * @param string $table Nombre de la tabla
     * @param int $tenant_id ID del tenant
     * @param array $conditions Condiciones adicionales
     * @return int Cantidad
     */
    public static function countWithTenant($table, $tenant_id, $conditions = [])
    {
        // Validar tenant_id
        if ($tenant_id === null || $tenant_id <= 0) {
            throw new Exception('tenant_id debe ser un entero positivo');
        }

        $conn = self::getConnection();

        $sql = "SELECT COUNT(*) as total FROM {$table} WHERE tenant_id = ?";
        $types = 'i';
        $params = [$tenant_id];

        foreach ($conditions as $field => $value) {
            $sql .= " AND {$field} = ?";
            $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
            $params[] = $value;
        }

        $stmt = self::executeWithTenant($sql, $types, $params);
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return (int)$row['total'];
    }
}
