<?php
// Script rápido para recrear la base de datos

$env = [];
foreach(file('.env') as $line) {
    $line = trim($line);
    if(empty($line) || strpos($line, '#') === 0) continue;
    if(strpos($line, '=') !== false) {
        list($k, $v) = explode('=', $line, 2);
        $env[trim($k)] = trim($v);
    }
}

$host = $env['DB_HOST'] ?? 'localhost';
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASSWORD'] ?? '';
$name = $env['DB_NAME'] ?? 'workee';
$port = (int)($env['DB_PORT'] ?? 3306);

echo "Eliminando base de datos...\n";
$conn = new mysqli($host, $user, $pass, '', $port);
$conn->query("DROP DATABASE IF EXISTS `{$name}`");
$conn->query("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->close();

echo "Importando esquema...\n";
$conn = new mysqli($host, $user, $pass, $name, $port);
$schema = file_get_contents('backend/db/schema_v2.sql');
$conn->multi_query($schema);
do {
    if($r = $conn->store_result()) $r->free();
} while($conn->next_result());

$r = $conn->query('SHOW TABLES');
$tables = [];
while($row = $r->fetch_array()) $tables[] = $row[0];

echo "✅ Base de datos recreada. Tablas: " . count($tables) . "\n";
$conn->close();

