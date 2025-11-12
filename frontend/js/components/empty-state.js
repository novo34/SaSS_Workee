/**
 * Componente EmptyState - Workee
 * 
 * Estado vacío reutilizable con mensaje y acción opcional
 * 
 * @class EmptyState
 * @description Componente para mostrar estados vacíos cuando no hay datos
 * 
 * @example
 * // Estado vacío básico
 * const emptyState = new EmptyState(container, {
 *   icon: '📭',
 *   title: 'No hay datos',
 *   message: 'No se encontraron elementos'
 * });
 * emptyState.show();
 * 
 * @example
 * // Con acción
 * const emptyState = new EmptyState(container, {
 *   icon: '📭',
 *   title: 'No hay solicitudes',
 *   message: 'Crea tu primera solicitud',
 *   action: {
 *     label: 'Crear Solicitud',
 *     callback: () => openNewRequestModal()
 *   }
 * });
 * emptyState.show();
 * 
 * @param {HTMLElement} container - Contenedor donde se mostrará el estado vacío
 * @param {Object} options - Opciones de configuración
 * @param {string} [options.icon='📭'] - Icono o emoji a mostrar
 * @param {string} [options.title='No hay datos'] - Título del estado vacío
 * @param {string} [options.message='No se encontraron elementos para mostrar.'] - Mensaje descriptivo
 * @param {Object} [options.action=null] - Acción opcional (botón)
 * @param {string} options.action.label - Texto del botón
 * @param {Function} options.action.callback - Función a ejecutar al hacer click
 */

class EmptyState {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            icon: options.icon || '📭',
            title: options.title || 'No hay datos',
            message: options.message || 'No se encontraron elementos para mostrar.',
            action: options.action || null,
            ...options
        };
        this.element = null;
    }

    /**
     * Muestra el estado vacío
     */
    show() {
        if (this.element) {
            this.hide();
        }
        
        this.element = document.createElement('div');
        this.element.className = 'empty-state';
        this.element.setAttribute('role', 'status');
        this.element.setAttribute('aria-live', 'polite');
        
        let actionHtml = '';
        if (this.options.action) {
            actionHtml = `
                <button 
                    class="btn-modern btn-modern--primary btn-modern--lg empty-state__action" 
                    onclick="this.closest('.empty-state').__actionCallback && this.closest('.empty-state').__actionCallback()"
                >
                    ${this.escapeHtml(this.options.action.label)}
                </button>
            `;
            // Guardar callback en el elemento
            this.element.__actionCallback = this.options.action.callback;
        }
        
        this.element.innerHTML = `
            <div class="empty-state__content">
                <div class="empty-state__icon">${this.escapeHtml(this.options.icon)}</div>
                <h3 class="empty-state__title">${this.escapeHtml(this.options.title)}</h3>
                <p class="empty-state__message">${this.escapeHtml(this.options.message)}</p>
                ${actionHtml}
            </div>
        `;
        
        this.container.appendChild(this.element);
    }

    /**
     * Oculta el estado vacío
     */
    hide() {
        if (this.element && this.element.parentNode) {
            this.element.parentNode.removeChild(this.element);
            this.element = null;
        }
    }

    /**
     * Actualiza el contenido
     */
    update(options) {
        this.options = { ...this.options, ...options };
        if (this.element) {
            this.hide();
            this.show();
        }
    }

    /**
     * Escapa HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Método estático para crear y mostrar
     */
    static show(container, options = {}) {
        const instance = new EmptyState(container, options);
        instance.show();
        return instance;
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.EmptyState = EmptyState;
}

