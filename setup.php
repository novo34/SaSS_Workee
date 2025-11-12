<?php
/**
 * Script de configuración inicial para XAMPP
 * 
 * Crea .env si no existe y configura la base de datos
 * Ejecutar: php setup.php
 */

echo "🚀 Workee SaaS - Configuración Inicial\n";
echo "=====================================\n\n";

// 1. Crear .env si no existe
$envFile = __DIR__ . '/.env';
$envExample = __DIR__ . '/.env.example';

if (!file_exists($envFile)) {
    echo "📝 Creando archivo .env...\n";
    
    if (file_exists($envExample)) {
        copy($envExample, $envFile);
        echo "✅ Archivo .env creado desde .env.example\n";
    } else {
        // Crear .env con valores por defecto para XAMPP
        $envContent = <<<'ENV'
# Workee SaaS - Configuración de Entorno
# Configurado para XAMPP por defecto

# Base de Datos (XAMPP por defecto)
DB_HOST=localhost
DB_NAME=workee
DB_USER=root
DB_PASSWORD=
DB_PORT=3306

# JWT - IMPORTANTE: Cambiar en producción
JWT_SECRET=workee-dev-secret-key-change-in-production-2024

# Aplicación
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost

# Timezone
APP_TIMEZONE=Europe/Zurich

# Locale por defecto
APP_LOCALE=es

# SMTP (Notificaciones) - Configurar cuando sea necesario
SMTP_HOST=
SMTP_PORT=587
SMTP_USER=
SMTP_PASSWORD=
SMTP_FROM=noreply@workee.app

# S3 (Almacenamiento de archivos) - Configurar cuando sea necesario
S3_ENDPOINT=
S3_BUCKET=
S3_KEY=
S3_SECRET=
S3_REGION=

# Stripe (Billing) - Configurar cuando sea necesario
STRIPE_SECRET_KEY=
STRIPE_PUBLISHABLE_KEY=
STRIPE_WEBHOOK_SECRET=

# Rate Limiting (Redis opcional) - No necesario en desarrollo
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=
ENV;
        
        file_put_contents($envFile, $envContent);
        echo "✅ Archivo .env creado con valores por defecto para XAMPP\n";
    }
    echo "\n";
} else {
    echo "ℹ️  Archivo .env ya existe\n\n";
}

// 2. Cargar configuración
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        return [];
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remover comillas si existen
            $value = trim($value, '"\'');
            $env[$key] = $value;
        }
    }
    
    return $env;
}

$env = loadEnv($envFile);

// 3. Configurar base de datos
echo "📦 Configurando base de datos...\n\n";

$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbUser = $env['DB_USER'] ?? 'root';
$dbPassword = $env['DB_PASSWORD'] ?? '';
$dbName = $env['DB_NAME'] ?? 'workee';
$dbPort = isset($env['DB_PORT']) ? (int)$env['DB_PORT'] : 3306;

echo "Configuración de BD:\n";
echo "  Host: {$dbHost}\n";
echo "  Usuario: {$dbUser}\n";
echo "  Base de datos: {$dbName}\n";
echo "  Puerto: {$dbPort}\n\n";

// Conectar sin especificar base de datos
echo "📡 Conectando a MySQL...\n";
$conn = @new mysqli($dbHost, $dbUser, $dbPassword, '', $dbPort);

if ($conn->connect_error) {
    die("❌ Error de conexión: " . $conn->connect_error . "\n\n" .
        "💡 Verifica:\n" .
        "  - XAMPP MySQL está ejecutándose\n" .
        "  - Usuario y contraseña en .env son correctos\n");
}

echo "✅ Conexión exitosa\n\n";

// Verificar si la base de datos existe
echo "🔍 Verificando base de datos...\n";
$result = $conn->query("SHOW DATABASES LIKE '{$dbName}'");

if ($result && $result->num_rows > 0) {
    echo "⚠️  La base de datos '{$dbName}' ya existe.\n";
    echo "¿Desea eliminarla y recrearla? (s/n): ";
    
    if (php_sapi_name() === 'cli') {
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        $response = trim(strtolower($line));
        if (!in_array($response, ['s', 'y', 'si', 'yes', 'sí'])) {
            echo "❌ Operación cancelada\n";
            $conn->close();
            exit(0);
        }
    }
    
    echo "🗑️  Eliminando base de datos existente...\n";
    $conn->query("DROP DATABASE IF EXISTS `{$dbName}`");
    echo "✅ Base de datos eliminada\n\n";
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
$schemaFile = __DIR__ . '/backend/db/schema_v2.sql';

if (!file_exists($schemaFile)) {
    die("❌ Error: Archivo de esquema no encontrado: {$schemaFile}\n");
}

echo "📖 Importando esquema...\n";
$schema = file_get_contents($schemaFile);

if ($schema === false) {
    die("❌ Error leyendo archivo de esquema\n");
}

// Ejecutar el esquema completo
echo "⚙️  Ejecutando esquema SQL (esto puede tardar unos segundos)...\n";

// Ejecutar el esquema completo de una vez (más eficiente)
$conn->multi_query($schema);

// Procesar todos los resultados
do {
    if ($result = $conn->store_result()) {
        $result->free();
    }
} while ($conn->next_result());

if ($conn->error) {
    echo "⚠️  Advertencia: " . $conn->error . "\n";
}

// Verificar tablas creadas
echo "\n🔍 Verificando tablas creadas...\n";
$result = $conn->query("SHOW TABLES");
$tables = [];
if ($result) {
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
}

echo "✅ Tablas creadas: " . count($tables) . "\n";

if (count($tables) > 0) {
    echo "\nTablas principales:\n";
    $mainTables = ['users', 'tenants', 'tenant_users', 'shifts', 'work_logs', 'staffing_requests', 'work_orders'];
    foreach ($mainTables as $table) {
        if (in_array($table, $tables)) {
            echo "  ✅ {$table}\n";
        }
    }
    if (count($tables) > count($mainTables)) {
        echo "  ... y " . (count($tables) - count($mainTables)) . " más\n";
    }
}

$conn->close();

echo "\n✅ ¡Configuración completada exitosamente!\n";
echo "\n📝 Próximos pasos:\n";
echo "  1. Verificar configuración en .env (editar si es necesario)\n";
echo "  2. Ejecutar tests: php tests/tenancy_isolation.test.php\n";
echo "  3. Iniciar servidor: php -S localhost:8000\n";
echo "  4. Continuar con desarrollo del Sprint 1\n";
echo "\n";

