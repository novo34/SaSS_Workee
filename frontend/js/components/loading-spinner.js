/**
 * Componente LoadingSpinner - Workee
 * 
 * Indicador de carga reutilizable
 * 
 * @class LoadingSpinner
 * @description Componente para mostrar indicadores de carga con overlay
 * 
 * @example
 * // Uso con instancia
 * const spinner = new LoadingSpinner(container);
 * spinner.show('Cargando datos...');
 * // ... operación asíncrona ...
 * spinner.hide();
 * 
 * @example
 * // Uso estático (recomendado)
 * LoadingSpinner.show(container, 'Cargando...');
 * // ... operación asíncrona ...
 * LoadingSpinner.hide(container);
 * 
 * @param {HTMLElement} [container=document.body] - Contenedor donde se mostrará el spinner
 */

class LoadingSpinner {
    constructor(container = null) {
        this.container = container || document.body;
        this.spinner = null;
    }

    /**
     * Muestra el spinner
     */
    show(message = null) {
        if (this.spinner) {
            this.hide();
        }
        
        this.spinner = document.createElement('div');
        this.spinner.className = 'loading-spinner';
        this.spinner.setAttribute('role', 'status');
        this.spinner.setAttribute('aria-live', 'polite');
        this.spinner.setAttribute('aria-label', message || 'Cargando...');
        
        this.spinner.innerHTML = `
            <div class="loading-spinner__content">
                <div class="loading-spinner__spinner" role="status" aria-hidden="true"></div>
                ${message ? `<p class="loading-spinner__message">${this.escapeHtml(message)}</p>` : ''}
            </div>
        `;
        
        this.container.appendChild(this.spinner);
        
        // Animación de entrada
        requestAnimationFrame(() => {
            this.spinner.classList.add('loading-spinner--show');
        });
    }

    /**
     * Oculta el spinner
     */
    hide() {
        if (!this.spinner) return;
        
        this.spinner.classList.remove('loading-spinner--show');
        this.spinner.classList.add('loading-spinner--hide');
        
        setTimeout(() => {
            if (this.spinner && this.spinner.parentNode) {
                this.spinner.parentNode.removeChild(this.spinner);
            }
            this.spinner = null;
        }, 200);
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
     * Método estático para mostrar spinner
     */
    static show(container = null, message = null) {
        const instance = new LoadingSpinner(container);
        instance.show(message);
        return instance;
    }

    /**
     * Método estático para ocultar spinner
     */
    static hide(container = null) {
        if (!container) {
            // Buscar todos los spinners y ocultarlos
            const spinners = document.querySelectorAll('.loading-spinner');
            spinners.forEach(spinner => {
                spinner.classList.remove('loading-spinner--show');
                spinner.classList.add('loading-spinner--hide');
                setTimeout(() => spinner.remove(), 200);
            });
            return;
        }
        
        const spinner = container.querySelector('.loading-spinner');
        if (spinner) {
            spinner.classList.remove('loading-spinner--show');
            spinner.classList.add('loading-spinner--hide');
            setTimeout(() => spinner.remove(), 200);
        }
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.LoadingSpinner = LoadingSpinner;
}

