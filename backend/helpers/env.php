<?php

/**
 * Helper de Entorno
 * 
 * Carga .env automáticamente y detecta desarrollo vs producción
 * Detecta automáticamente el entorno basado en:
 * - Nombre del servidor (localhost = desarrollo)
 * - Variable APP_ENV en .env
 * - IP del servidor (127.0.0.1 = desarrollo)
 */

class Env
{
    private static $loaded = false;
    private static $env = [];
    private static $isProduction = null;

    /**
     * Carga el archivo .env si no está cargado
     */
    public static function load()
    {
        if (self::$loaded) {
            return;
        }

        $envFile = self::getEnvFilePath();

        if (!file_exists($envFile)) {
            // Si no existe .env, intentar .env.example
            $exampleFile = str_replace('.env', '.env.example', $envFile);
            if (file_exists($exampleFile)) {
                copy($exampleFile, $envFile);
            } else {
                // Crear .env básico
                self::createDefaultEnv($envFile);
            }
        }

        // Cargar variables del archivo .env
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Saltar comentarios y líneas vacías
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            // Parsear KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remover comillas si existen
                $value = trim($value, '"\'');

                // Guardar en array local
                self::$env[$key] = $value;

                // Establecer en $_ENV y $_SERVER si no existe
                if (!isset($_ENV[$key])) {
                    $_ENV[$key] = $value;
                }
                if (!isset($_SERVER[$key])) {
                    $_SERVER[$key] = $value;
                }
            }
        }

        // Detectar entorno automáticamente
        self::detectEnvironment();

        self::$loaded = true;
    }

    /**
     * Obtiene la ruta del archivo .env
     */
    private static function getEnvFilePath()
    {
        // Buscar .env en la raíz del proyecto (3 niveles arriba desde helpers/)
        $root = dirname(dirname(__DIR__));
        return $root . '/.env';
    }

    /**
     * Crea un archivo .env por defecto
     */
    private static function createDefaultEnv($filePath)
    {
        $defaultContent = <<<'ENV'
# Workee SaaS - Configuración de Entorno
# Auto-generado

# Entorno (development/production) - Se detecta automáticamente
APP_ENV=development
APP_DEBUG=true

# Base de Datos
DB_HOST=localhost
DB_NAME=workee
DB_USER=root
DB_PASSWORD=
DB_PORT=3306

# JWT
JWT_SECRET=change-me-in-production

# Aplicación
APP_URL=http://localhost
APP_TIMEZONE=Europe/Zurich
APP_LOCALE=es

# SMTP
SMTP_HOST=
SMTP_PORT=587
SMTP_USER=
SMTP_PASSWORD=
SMTP_FROM=noreply@workee.app

# S3
S3_ENDPOINT=
S3_BUCKET=
S3_KEY=
S3_SECRET=
S3_REGION=

# Stripe
STRIPE_SECRET_KEY=
STRIPE_PUBLISHABLE_KEY=
STRIPE_WEBHOOK_SECRET=

# Redis
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=
ENV;

        file_put_contents($filePath, $defaultContent);
    }

    /**
     * Detecta automáticamente si estamos en producción o desarrollo
     */
    private static function detectEnvironment()
    {
        // 1. Verificar APP_ENV explícito (acceder directamente a self::$env para evitar bucle)
        $appEnv = strtolower(self::$env['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'development');

        if ($appEnv === 'production' || $appEnv === 'prod') {
            self::$isProduction = true;
            return;
        }

        if ($appEnv === 'development' || $appEnv === 'dev' || $appEnv === 'local') {
            self::$isProduction = false;
            return;
        }

        // 2. Detectar por nombre del servidor
        $serverName = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';

        if (in_array(strtolower($serverName), ['localhost', '127.0.0.1', '::1'])) {
            self::$isProduction = false;
            self::$env['APP_ENV'] = 'development';
            $_ENV['APP_ENV'] = 'development';
            return;
        }

        // 3. Detectar por IP
        $serverAddr = $_SERVER['SERVER_ADDR'] ?? '';
        if ($serverAddr === '127.0.0.1' || $serverAddr === '::1') {
            self::$isProduction = false;
            self::$env['APP_ENV'] = 'development';
            $_ENV['APP_ENV'] = 'development';
            return;
        }

        // 4. Detectar por dominio (si contiene .local, .dev, .test = desarrollo)
        if (preg_match('/\.(local|dev|test|localhost)(:\d+)?$/', $serverName)) {
            self::$isProduction = false;
            self::$env['APP_ENV'] = 'development';
            $_ENV['APP_ENV'] = 'development';
            return;
        }

        // 5. Por defecto, asumir producción si no es localhost
        self::$isProduction = true;
        self::$env['APP_ENV'] = 'production';
        $_ENV['APP_ENV'] = 'production';
    }

    /**
     * Obtiene una variable de entorno
     * 
     * @param string $key Clave
     * @param mixed $default Valor por defecto
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        if (!self::$loaded) {
            self::load();
        }

        return self::$env[$key] ?? $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }

    /**
     * Verifica si estamos en producción
     * 
     * @return bool
     */
    public static function isProduction()
    {
        self::load();

        if (self::$isProduction === null) {
            self::detectEnvironment();
        }

        return self::$isProduction === true;
    }

    /**
     * Verifica si estamos en desarrollo
     * 
     * @return bool
     */
    public static function isDevelopment()
    {
        return !self::isProduction();
    }

    /**
     * Obtiene el entorno actual
     * 
     * @return string 'development' o 'production'
     */
    public static function environment()
    {
        return self::isProduction() ? 'production' : 'development';
    }

    /**
     * Verifica si el debug está habilitado
     * 
     * @return bool
     */
    public static function isDebug()
    {
        if (self::isProduction()) {
            return false; // Nunca debug en producción
        }

        return filter_var(self::get('APP_DEBUG', true), FILTER_VALIDATE_BOOLEAN);
    }
}

// NO cargar automáticamente - se carga bajo demanda
// Env::load(); // Comentado para evitar bucles infinitos
