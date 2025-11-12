/**
 * Componente Tooltip Contextual Reutilizable
 * 
 * Muestra tooltips informativos en elementos
 * 
 * @example
 * // Tooltip simple con atributo data-tooltip
 * <button data-tooltip="Este es un tooltip">Botón</button>
 * 
 * // Tooltip programático
 * Tooltip.init();
 * 
 * // Tooltip con opciones
 * new Tooltip(element, {
 *   content: 'Contenido del tooltip',
 *   position: 'top',
 *   delay: 300
 * });
 */

class Tooltip {
    constructor(element, options = {}) {
        this.element = typeof element === 'string' 
            ? document.querySelector(element) 
            : element;
        
        if (!this.element) {
            console.error('Tooltip: Elemento no encontrado');
            return;
        }
        
        this.options = {
            content: options.content || this.element.getAttribute('data-tooltip') || '',
            position: options.position || 'top', // top, bottom, left, right
            delay: options.delay || 200,
            html: options.html || false,
            trigger: options.trigger || 'hover', // hover, click, focus
            ...options
        };
        
        this.tooltipElement = null;
        this.showTimeout = null;
        this.hideTimeout = null;
        
        this.init();
    }
    
    init() {
        // Crear elemento tooltip
        this.tooltipElement = document.createElement('div');
        this.tooltipElement.className = `tooltip-modern tooltip-modern--${this.options.position}`;
        this.tooltipElement.setAttribute('role', 'tooltip');
        this.tooltipElement.innerHTML = `
            <div class="tooltip-modern__arrow"></div>
            <div class="tooltip-modern__content">${this.options.html ? this.options.content : this.escapeHtml(this.options.content)}</div>
        `;
        document.body.appendChild(this.tooltipElement);
        
        // Event listeners según trigger
        if (this.options.trigger === 'hover') {
            this.element.addEventListener('mouseenter', () => this.show());
            this.element.addEventListener('mouseleave', () => this.hide());
        } else if (this.options.trigger === 'click') {
            this.element.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggle();
            });
        } else if (this.options.trigger === 'focus') {
            this.element.addEventListener('focus', () => this.show());
            this.element.addEventListener('blur', () => this.hide());
        }
        
        // Cerrar al hacer clic fuera
        if (this.options.trigger === 'click') {
            document.addEventListener('click', (e) => {
                if (!this.element.contains(e.target) && !this.tooltipElement.contains(e.target)) {
                    this.hide();
                }
            });
        }
    }
    
    show() {
        clearTimeout(this.hideTimeout);
        
        this.showTimeout = setTimeout(() => {
            if (!this.tooltipElement) return;
            
            this.tooltipElement.classList.add('tooltip-modern--show');
            this.updatePosition();
        }, this.options.delay);
    }
    
    hide() {
        clearTimeout(this.showTimeout);
        
        this.hideTimeout = setTimeout(() => {
            if (this.tooltipElement) {
                this.tooltipElement.classList.remove('tooltip-modern--show');
            }
        }, 100);
    }
    
    toggle() {
        if (this.tooltipElement && this.tooltipElement.classList.contains('tooltip-modern--show')) {
            this.hide();
        } else {
            this.show();
        }
    }
    
    updatePosition() {
        if (!this.tooltipElement || !this.element) return;
        
        const rect = this.element.getBoundingClientRect();
        const tooltipRect = this.tooltipElement.getBoundingClientRect();
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
        
        let top = 0;
        let left = 0;
        
        switch (this.options.position) {
            case 'top':
                top = rect.top + scrollTop - tooltipRect.height - 8;
                left = rect.left + scrollLeft + (rect.width / 2) - (tooltipRect.width / 2);
                break;
            case 'bottom':
                top = rect.bottom + scrollTop + 8;
                left = rect.left + scrollLeft + (rect.width / 2) - (tooltipRect.width / 2);
                break;
            case 'left':
                top = rect.top + scrollTop + (rect.height / 2) - (tooltipRect.height / 2);
                left = rect.left + scrollLeft - tooltipRect.width - 8;
                break;
            case 'right':
                top = rect.top + scrollTop + (rect.height / 2) - (tooltipRect.height / 2);
                left = rect.right + scrollLeft + 8;
                break;
        }
        
        // Ajustar si se sale de la pantalla
        const padding = 8;
        if (left < padding) left = padding;
        if (left + tooltipRect.width > window.innerWidth - padding) {
            left = window.innerWidth - tooltipRect.width - padding;
        }
        if (top < padding) {
            top = rect.bottom + scrollTop + 8; // Cambiar a bottom
            this.tooltipElement.classList.remove('tooltip-modern--top');
            this.tooltipElement.classList.add('tooltip-modern--bottom');
        }
        
        this.tooltipElement.style.top = `${top}px`;
        this.tooltipElement.style.left = `${left}px`;
    }
    
    destroy() {
        if (this.tooltipElement) {
            this.tooltipElement.remove();
            this.tooltipElement = null;
        }
        clearTimeout(this.showTimeout);
        clearTimeout(this.hideTimeout);
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Inicializa tooltips automáticamente desde atributos data-tooltip
     */
    static init(selector = '[data-tooltip]') {
        const elements = document.querySelectorAll(selector);
        elements.forEach(element => {
            if (!element.tooltipInstance) {
                element.tooltipInstance = new Tooltip(element, {
                    content: element.getAttribute('data-tooltip'),
                    position: element.getAttribute('data-tooltip-position') || 'top',
                    trigger: element.getAttribute('data-tooltip-trigger') || 'hover'
                });
            }
        });
    }
}

// Inicializar automáticamente al cargar
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        Tooltip.init();
    });
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.Tooltip = Tooltip;
}

