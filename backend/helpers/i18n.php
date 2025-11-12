<?php
/**
 * Sistema de Internacionalización (i18n)
 * 
 * NUNCA hardcodear texto visible al usuario
 * SIEMPRE usar claves de traducción
 */

class I18n {
    private static $locale = 'es';
    private static $translations = [];
    private static $loaded = [];
    
    /**
     * Establece el locale actual
     */
    public static function setLocale($locale) {
        self::$locale = $locale;
    }
    
    /**
     * Obtiene el locale actual
     */
    public static function getLocale() {
        return self::$locale;
    }
    
    /**
     * Carga traducciones de un namespace
     */
    private static function loadNamespace($namespace) {
        if (isset(self::$loaded[$namespace])) {
            return;
        }
        
        $file = __DIR__ . '/../lang/' . self::$locale . '/' . $namespace . '.php';
        
        if (file_exists($file)) {
            $translations = require $file;
            if (is_array($translations)) {
                self::$translations[$namespace] = $translations;
                self::$loaded[$namespace] = true;
            }
        } else {
            // Fallback a español si no existe
            if (self::$locale !== 'es') {
                $fileEs = __DIR__ . '/../lang/es/' . $namespace . '.php';
                if (file_exists($fileEs)) {
                    $translations = require $fileEs;
                    if (is_array($translations)) {
                        self::$translations[$namespace] = $translations;
                        self::$loaded[$namespace] = true;
                    }
                }
            }
        }
    }
    
    /**
     * Traduce una clave
     * 
     * @param string $key Clave en formato namespace.key o key (usa 'common')
     * @param array $vars Variables para reemplazar {variable}
     * @param string|null $locale Locale específico (opcional)
     * @return string Texto traducido
     */
    public static function translate($key, $vars = [], $locale = null) {
        $originalLocale = self::$locale;
        
        if ($locale !== null) {
            self::setLocale($locale);
        }
        
        // Parsear namespace.key o usar 'common' por defecto
        $parts = explode('.', $key, 2);
        $namespace = count($parts) === 2 ? $parts[0] : 'common';
        $keyName = count($parts) === 2 ? $parts[1] : $parts[0];
        
        // Cargar namespace si no está cargado
        self::loadNamespace($namespace);
        
        // Obtener traducción
        $translation = self::$translations[$namespace][$keyName] ?? null;
        
        if ($translation === null) {
            // Si no existe, devolver la clave con prefijo [MISSING] en desarrollo
            $translation = '[MISSING] ' . $key;
        }
        
        // Reemplazar variables {variable}
        if (!empty($vars)) {
            foreach ($vars as $var => $value) {
                $translation = str_replace('{' . $var . '}', $value, $translation);
            }
        }
        
        if ($locale !== null) {
            self::setLocale($originalLocale);
        }
        
        return $translation;
    }
}

/**
 * Función helper global t()
 * 
 * @param string $key Clave de traducción
 * @param array $vars Variables
 * @param string|null $locale Locale específico
 * @return string
 */
function t($key, $vars = [], $locale = null) {
    return I18n::translate($key, $vars, $locale);
}

