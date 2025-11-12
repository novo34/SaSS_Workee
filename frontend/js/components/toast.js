/**
 * Componente Toast/Notification - Workee
 * 
 * Sistema de notificaciones toast reutilizable
 * Reemplaza alert(), confirm() y mensajes inline
 * 
 * @class Toast
 * @description Sistema de notificaciones toast para mostrar mensajes al usuario
 * 
 * @example
 * // Mensajes de éxito, error, advertencia e información
 * Toast.success('Operación completada correctamente');
 * Toast.error('Error al guardar los datos');
 * Toast.warning('Advertencia: Revisar los datos');
 * Toast.info('Información importante');
 * 
 * @example
 * // Con duración personalizada
 * Toast.success('Mensaje', 5000); // 5 segundos
 * 
 * @static
 */

class Toast {
    static container = null;
    static defaultDuration = 3000;
    static quietMode = false;
    static quietHours = { start: null, end: null };

    /**
     * Inicializa el contenedor de toasts
     */
    static init() {
        if (this.container) return;
        
        this.container = document.createElement('div');
        this.container.className = 'toast-container';
        this.container.setAttribute('aria-live', 'polite');
        this.container.setAttribute('aria-atomic', 'true');
        document.body.appendChild(this.container);
        
        // Cargar preferencias de notificaciones
        this.loadPreferences();
    }
    
    /**
     * Carga preferencias de notificaciones
     */
    static loadPreferences() {
        const prefs = localStorage.getItem('workee_notification_prefs');
        if (prefs) {
            try {
                const prefsObj = JSON.parse(prefs);
                this.quietMode = prefsObj.quietMode === true;
                this.quietHours = {
                    start: prefsObj.quietHoursStart || null,
                    end: prefsObj.quietHoursEnd || null
                };
            } catch (e) {
                // Ignorar errores de parsing
            }
        }
    }
    
    /**
     * Verifica si está en quiet hours
     */
    static isQuietHours() {
        if (!this.quietHours.start || !this.quietHours.end) {
            return false;
        }
        
        const now = new Date();
        const currentTime = now.getHours() * 60 + now.getMinutes();
        
        const [startHour, startMin] = this.quietHours.start.split(':').map(Number);
        const [endHour, endMin] = this.quietHours.end.split(':').map(Number);
        const startTime = startHour * 60 + startMin;
        const endTime = endHour * 60 + endMin;
        
        if (startTime <= endTime) {
            return currentTime >= startTime && currentTime <= endTime;
        } else {
            // Quiet hours cruzan medianoche
            return currentTime >= startTime || currentTime <= endTime;
        }
    }
    
    /**
     * Verifica si se debe mostrar la notificación
     */
    static shouldShow(type = 'info', critical = false) {
        // Notificaciones críticas siempre se muestran
        if (critical) {
            return true;
        }
        
        // Si está en quiet mode, no mostrar (excepto críticas)
        if (this.quietMode) {
            return false;
        }
        
        // Si está en quiet hours, no mostrar (excepto críticas)
        if (this.isQuietHours()) {
            return false;
        }
        
        return true;
    }

    /**
     * Muestra un toast de éxito
     * @param {string} message - Mensaje
     * @param {number} duration - Duración en ms
     * @param {boolean} critical - Si es crítico
     */
    static success(message, duration = this.defaultDuration, critical = false) {
        return this.show(message, 'success', duration, critical);
    }

    /**
     * Muestra un toast de error
     * @param {string|Object} message - Mensaje o objeto de error del backend
     * @param {number} duration - Duración en ms
     * @param {boolean} critical - Si es crítico (errores son críticos por defecto)
     * @param {Object} options - Opciones adicionales
     * @param {boolean} options.constructive - Si mostrar mensaje constructivo (título + descripción + solución)
     */
    static error(message, duration = this.defaultDuration, critical = true, options = {}) {
        // Si es un objeto de error del backend, formatearlo
        if (typeof message === 'object' && message !== null) {
            if (typeof ErrorHelper !== 'undefined') {
                const formatted = ErrorHelper.formatError(message);
                return this.showConstructive(formatted, 'error', duration, critical);
            } else {
                // Fallback: usar message si existe
                message = message.message || JSON.stringify(message);
            }
        }
        
        // Si options.constructive es true y el mensaje tiene formato multilínea, mostrar constructivo
        if (options.constructive && typeof message === 'string' && message.includes('\n')) {
            const lines = message.split('\n').filter(l => l.trim());
            if (lines.length >= 3) {
                const formatted = {
                    title: lines[0].trim(),
                    description: lines[1].trim(),
                    solution: lines.slice(2).join(' ').trim()
                };
                return this.showConstructive(formatted, 'error', duration, critical);
            }
        }
        
        return this.show(message, 'error', duration, critical);
    }
    
    /**
     * Muestra un toast constructivo (con título, descripción y solución)
     * @param {Object} formatted - Objeto con title, description, solution
     * @param {string} type - Tipo de toast
     * @param {number} duration - Duración en ms
     * @param {boolean} critical - Si es crítico
     */
    static showConstructive(formatted, type = 'error', duration = this.defaultDuration, critical = true) {
        this.init();
        
        // Verificar si se debe mostrar
        if (!this.shouldShow(type, critical)) {
            return null;
        }
        
        const toast = document.createElement('div');
        toast.className = `toast toast--${type} toast--constructive`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        
        // Icono según tipo
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        
        toast.innerHTML = `
            <div class="toast__content">
                <div class="toast__header">
                    <span class="toast__icon">${icons[type] || icons.info}</span>
                    <span class="toast__title">${this.escapeHtml(formatted.title || 'Error')}</span>
                    <button class="toast__close" aria-label="Cerrar" onclick="this.closest('.toast').remove()">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="toast__body">
                    <p class="toast__description">${this.escapeHtml(formatted.description || '')}</p>
                    ${formatted.solution ? `<p class="toast__solution"><strong>Solución:</strong> ${this.escapeHtml(formatted.solution)}</p>` : ''}
                </div>
            </div>
        `;
        
        this.container.appendChild(toast);
        
        // Animación de entrada
        requestAnimationFrame(() => {
            toast.classList.add('toast--show');
        });
        
        // MOB-137: Swipe to dismiss en móvil
        if (window.innerWidth < 992 && typeof SwipeGestures !== 'undefined') {
            SwipeGestures.initSwipeToDismiss(toast, () => {
                this.hide(toast);
            }, 'up');
        }
        
        // Auto-dismiss (más tiempo para mensajes constructivos)
        const finalDuration = duration > 0 ? Math.max(duration, 5000) : 0;
        if (finalDuration > 0) {
            setTimeout(() => {
                this.hide(toast);
            }, finalDuration);
        }
        
        return toast;
    }

    /**
     * Muestra un toast de advertencia
     * @param {string} message - Mensaje
     * @param {number} duration - Duración en ms
     * @param {boolean} critical - Si es crítico
     */
    static warning(message, duration = this.defaultDuration, critical = false) {
        return this.show(message, 'warning', duration, critical);
    }

    /**
     * Muestra un toast informativo
     * @param {string} message - Mensaje
     * @param {number} duration - Duración en ms
     * @param {boolean} critical - Si es crítico
     */
    static info(message, duration = this.defaultDuration, critical = false) {
        return this.show(message, 'info', duration, critical);
    }

    /**
     * Muestra un toast genérico
     * @param {string} message - Mensaje a mostrar
     * @param {string} type - Tipo de toast (success, error, warning, info)
     * @param {number} duration - Duración en ms (0 = no auto-dismiss)
     * @param {boolean} critical - Si es crítico, se muestra incluso en quiet mode/hours
     */
    static show(message, type = 'info', duration = this.defaultDuration, critical = false) {
        this.init();
        
        // Verificar si se debe mostrar
        if (!this.shouldShow(type, critical)) {
            return null;
        }
        
        const toast = document.createElement('div');
        toast.className = `toast toast--${type}`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        
        // Icono según tipo
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        
        toast.innerHTML = `
            <div class="toast__content">
                <span class="toast__icon">${icons[type] || icons.info}</span>
                <span class="toast__message">${this.escapeHtml(message)}</span>
                <button class="toast__close" aria-label="Cerrar" onclick="this.closest('.toast').remove()">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `;
        
        this.container.appendChild(toast);
        
        // Animación de entrada
        requestAnimationFrame(() => {
            toast.classList.add('toast--show');
        });
        
        // MOB-137: Swipe to dismiss en móvil
        if (window.innerWidth < 992 && typeof SwipeGestures !== 'undefined') {
            SwipeGestures.initSwipeToDismiss(toast, () => {
                this.hide(toast);
            }, 'up');
        }
        
        // Auto-dismiss
        if (duration > 0) {
            setTimeout(() => {
                this.hide(toast);
            }, duration);
        }
        
        return toast;
    }

    /**
     * Oculta un toast
     */
    static hide(toast) {
        if (!toast) return;
        
        toast.classList.remove('toast--show');
        toast.classList.add('toast--hide');
        
        setTimeout(() => {
            toast.remove();
        }, 300);
    }

    /**
     * Escapa HTML para prevenir XSS
     */
    static escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Inicializar al cargar
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => Toast.init());
} else {
    Toast.init();
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.Toast = Toast;
}

