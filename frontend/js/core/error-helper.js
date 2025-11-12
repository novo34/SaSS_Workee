/**
 * Error Helper - Workee
 * 
 * Helper para formatear mensajes de error constructivos
 * Convierte errores del backend en mensajes útiles para el usuario
 * 
 * @class ErrorHelper
 * @description Helper para formatear errores en mensajes constructivos
 */

class ErrorHelper {
    /**
     * Formatea un error del backend en un mensaje constructivo
     * @param {Object} error - Objeto de error del backend
     * @param {string} error.error_key - Clave de traducción del error
     * @param {Object} error.error_params - Parámetros para la traducción
     * @param {string} error.message - Mensaje de error (ya traducido)
     * @param {number} error.error_code - Código de error numérico
     * @returns {Object} Objeto con título, descripción y solución
     */
    static formatError(error) {
        if (!error) {
            return {
                title: 'Error desconocido',
                description: 'Ocurrió un error inesperado',
                solution: 'Por favor, intenta nuevamente o contacta al soporte si el problema persiste.'
            };
        }

        // Si ya tiene mensaje traducido, usarlo
        if (error.message) {
            return this.parseConstructiveMessage(error.message, error.error_key, error.error_params);
        }

        // Si tiene error_key, intentar traducir
        if (error.error_key) {
            const translated = this.translateError(error.error_key, error.error_params || {});
            return this.parseConstructiveMessage(translated, error.error_key, error.error_params);
        }

        // Fallback genérico
        return {
            title: 'Error',
            description: error.error || 'Ocurrió un error',
            solution: 'Por favor, intenta nuevamente o contacta al soporte si el problema persiste.'
        };
    }

    /**
     * Parsea un mensaje para extraer título, descripción y solución
     * @param {string} message - Mensaje completo
     * @param {string} errorKey - Clave de error
     * @param {Object} params - Parámetros
     * @returns {Object} Objeto con título, descripción y solución
     */
    static parseConstructiveMessage(message, errorKey, params) {
        // Mapeo de errores comunes a mensajes constructivos
        const errorMap = {
            'validation.required': {
                title: 'Campo requerido',
                description: `El campo "${params.field || 'campo'}" es obligatorio`,
                solution: 'Por favor, completa todos los campos requeridos antes de continuar.'
            },
            'validation.email_invalid': {
                title: 'Email inválido',
                description: 'El formato del email no es válido',
                solution: 'Por favor, verifica que el email tenga el formato correcto (ejemplo: usuario@dominio.com).'
            },
            'validation.min_length': {
                title: 'Texto muy corto',
                description: `El campo "${params.field || 'campo'}" debe tener al menos ${params.min || 'caracteres'} caracteres`,
                solution: `Por favor, asegúrate de que el campo tenga al menos ${params.min || 'caracteres'} caracteres.`
            },
            'validation.max_length': {
                title: 'Texto muy largo',
                description: `El campo "${params.field || 'campo'}" no puede exceder ${params.max || 'caracteres'} caracteres`,
                solution: `Por favor, reduce el texto a máximo ${params.max || 'caracteres'} caracteres.`
            },
            'errors.not_found': {
                title: 'Recurso no encontrado',
                description: 'El recurso que intentas acceder no existe o fue eliminado',
                solution: 'Por favor, verifica que el recurso exista o intenta recargar la página.'
            },
            'errors.unauthorized': {
                title: 'No autorizado',
                description: 'No tienes permisos para realizar esta acción',
                solution: 'Por favor, contacta al administrador si necesitas acceso a esta funcionalidad.'
            },
            'errors.forbidden': {
                title: 'Acceso denegado',
                description: 'No tienes permisos para acceder a este recurso',
                solution: 'Por favor, contacta al administrador si necesitas acceso a este recurso.'
            },
            'errors.network_error': {
                title: 'Error de conexión',
                description: 'No se pudo conectar con el servidor',
                solution: 'Por favor, verifica tu conexión a internet e intenta nuevamente.'
            },
            'errors.internal_error': {
                title: 'Error del servidor',
                description: 'Ocurrió un error interno en el servidor',
                solution: 'Por favor, intenta nuevamente en unos momentos. Si el problema persiste, contacta al soporte.'
            }
        };

        // Buscar en el mapa
        if (errorKey && errorMap[errorKey]) {
            const mapped = errorMap[errorKey];
            return {
                title: mapped.title,
                description: mapped.description,
                solution: mapped.solution
            };
        }

        // Si el mensaje tiene formato "Título: Descripción. Solución"
        const parts = message.split(/[.:]/);
        if (parts.length >= 2) {
            return {
                title: parts[0].trim(),
                description: parts.slice(1, -1).join('.').trim() || message,
                solution: parts[parts.length - 1].trim() || 'Por favor, intenta nuevamente.'
            };
        }

        // Fallback: usar el mensaje como descripción
        return {
            title: 'Error',
            description: message,
            solution: 'Por favor, intenta nuevamente o contacta al soporte si el problema persiste.'
        };
    }

    /**
     * Traduce una clave de error
     * @param {string} errorKey - Clave de error
     * @param {Object} params - Parámetros
     * @returns {string} Mensaje traducido
     */
    static translateError(errorKey, params = {}) {
        // Intentar usar función de traducción global si existe
        if (typeof window.t === 'function') {
            return window.t(errorKey, params);
        }

        // Si no existe, usar traducciones cargadas
        if (window.translations && window.translations.errors) {
            let message = window.translations.errors[errorKey] || errorKey;
            
            // Reemplazar parámetros
            Object.keys(params).forEach(key => {
                message = message.replace(`{${key}}`, params[key]);
            });
            
            return message;
        }

        // Fallback: devolver la clave
        return errorKey;
    }

    /**
     * Muestra un error constructivo usando Toast
     * @param {Object} error - Objeto de error
     * @param {number} duration - Duración del toast
     */
    static showError(error, duration = 5000) {
        const formatted = this.formatError(error);
        
        if (typeof Toast !== 'undefined') {
            // Mostrar toast con mensaje constructivo
            const message = `${formatted.title}\n${formatted.description}\n\n${formatted.solution}`;
            Toast.error(message, duration, true);
        } else {
            // Fallback a console
            console.error('Error:', formatted);
        }
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.ErrorHelper = ErrorHelper;
}

