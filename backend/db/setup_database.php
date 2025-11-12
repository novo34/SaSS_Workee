<?php
/**
 * Script de configuración de base de datos para XAMPP
 * 
 * Crea la base de datos y importa el esquema
 * Ejecutar: php backend/db/setup_database.php
 */

// Cargar configuración desde .env
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        die("❌ Error: Archivo .env no encontrado en: {$filePath}\n");
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Comentario
        }
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $env[$key] = $value;
        }
    }
    
    return $env;
}

// Cargar .env
$env = loadEnv(__DIR__ . '/../../.env');

$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbUser = $env['DB_USER'] ?? 'root';
$dbPassword = $env['DB_PASSWORD'] ?? '';
$dbName = $env['DB_NAME'] ?? 'workee';
$dbPort = $env['DB_PORT'] ?? 3306;

echo "🚀 Configurando base de datos Workee...\n\n";
echo "Configuración:\n";
echo "  Host: {$dbHost}\n";
echo "  Usuario: {$dbUser}\n";
echo "  Base de datos: {$dbName}\n";
echo "  Puerto: {$dbPort}\n\n";

// Conectar sin especificar base de datos (para crearla)
echo "📡 Conectando a MySQL...\n";
$conn = new mysqli($dbHost, $dbUser, $dbPassword, '', $dbPort);

if ($conn->connect_error) {
    die("❌ Error de conexión: " . $conn->connect_error . "\n");
}

echo "✅ Conexión exitosa\n\n";

// Verificar si la base de datos existe
echo "🔍 Verificando si la base de datos existe...\n";
$result = $conn->query("SHOW DATABASES LIKE '{$dbName}'");

if ($result->num_rows > 0) {
    echo "⚠️  La base de datos '{$dbName}' ya existe.\n";
    echo "¿Desea eliminarla y recrearla? (s/n): ";
    
    if (php_sapi_name() === 'cli') {
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        if (trim(strtolower($line)) !== 's' && trim(strtolower($line)) !== 'y' && trim(strtolower($line)) !== 'si' && trim(strtolower($line)) !== 'yes') {
            echo "❌ Operación cancelada\n";
            $conn->close();
            exit(0);
        }
        
        echo "🗑️  Eliminando base de datos existente...\n";
        $conn->query("DROP DATABASE IF EXISTS `{$dbName}`");
        echo "✅ Base de datos eliminada\n\n";
    } else {
        // Si no es CLI, asumir que sí
        echo "🗑️  Eliminando base de datos existente...\n";
        $conn->query("DROP DATABASE IF EXISTS `{$dbName}`");
        echo "✅ Base de datos eliminada\n\n";
    }
}

// Crear base de datos
echo "📦 Creando base de datos '{$dbName}'...\n";
$sql = "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql)) {
    echo "✅ Base de datos creada\n\n";
} else {
    die("❌ Error creando base de datos: " . $conn->error . "\n");
}

// Seleccionar base de datos
$conn->select_db($dbName);

// Leer y ejecutar esquema
$schemaFile = __DIR__ . '/schema_v2.sql';

if (!file_exists($schemaFile)) {
    die("❌ Error: Archivo de esquema no encontrado: {$schemaFile}\n");
}

echo "📖 Leyendo esquema desde: {$schemaFile}\n";
$schema = file_get_contents($schemaFile);

if ($schema === false) {
    die("❌ Error leyendo archivo de esquema\n");
}

echo "⚙️  Ejecutando esquema SQL...\n";

// Dividir en statements (separados por ;)
// Remover comentarios y líneas vacías
$schema = preg_replace('/--.*$/m', '', $schema);
$statements = array_filter(
    array_map('trim', explode(';', $schema)),
    function($stmt) {
        return !empty($stmt) && !preg_match('/^SET\s+/i', $stmt);
    }
);

$successCount = 0;
$errorCount = 0;

foreach ($statements as $statement) {
    if (empty(trim($statement))) {
        continue;
    }
    
    // Ejecutar statement
    if ($conn->query($statement)) {
        $successCount++;
    } else {
        $errorCount++;
        // Solo mostrar errores críticos (no warnings de "table already exists")
        if (strpos($conn->error, 'already exists') === false) {
            echo "⚠️  Warning: " . $conn->error . "\n";
        }
    }
}

echo "\n📊 Resultados:\n";
echo "  ✅ Statements exitosos: {$successCount}\n";
if ($errorCount > 0) {
    echo "  ⚠️  Warnings: {$errorCount}\n";
}

// Verificar tablas creadas
echo "\n🔍 Verificando tablas creadas...\n";
$result = $conn->query("SHOW TABLES");
$tables = [];
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

echo "✅ Tablas creadas: " . count($tables) . "\n";
if (count($tables) > 0) {
    echo "  " . implode(", ", array_slice($tables, 0, 10));
    if (count($tables) > 10) {
        echo " ... y " . (count($tables) - 10) . " más";
    }
    echo "\n";
}

$conn->close();

echo "\n✅ ¡Base de datos configurada correctamente!\n";
echo "\n📝 Próximos pasos:\n";
echo "  1. Verificar que el archivo .env tiene la configuración correcta\n";
echo "  2. Ejecutar tests: php tests/tenancy_isolation.test.php\n";
echo "  3. Iniciar desarrollo del Sprint 1\n";
echo "\n";

