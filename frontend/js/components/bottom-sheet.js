/**
 * Componente Bottom Sheet - Workee
 * 
 * Componente reutilizable para modales tipo bottom sheet en móvil
 * MOB-138: Optimizar modales para móvil con bottom sheet o fullscreen
 * 
 * @class BottomSheet
 * @description Componente bottom sheet para móvil con animación slide up y swipe to dismiss
 * 
 * @example
 * const bottomSheet = new BottomSheet({
 *   title: 'Título',
 *   body: '<p>Contenido</p>',
 *   footer: '<button>OK</button>',
 *   onClose: () => console.log('Cerrado')
 * });
 * bottomSheet.show();
 */

class BottomSheet {
    constructor(options = {}) {
        this.options = {
            id: options.id || `bottom-sheet-${Date.now()}`,
            title: options.title || '',
            body: options.body || '',
            footer: options.footer || null,
            fullscreen: options.fullscreen !== undefined ? options.fullscreen : false, // MOB-138: Opción para fullscreen
            swipeToDismiss: options.swipeToDismiss !== undefined ? options.swipeToDismiss : true,
            onShow: options.onShow || null,
            onHide: options.onHide || null,
            onShown: options.onShown || null,
            onHidden: options.onHidden || null,
            ...options
        };
        
        this.element = null;
        this.backdrop = null;
        this.isVisible = false;
        this.create();
    }

    /**
     * Crea el elemento bottom sheet
     */
    create() {
        // Si ya existe, removerlo
        const existing = document.getElementById(this.options.id);
        if (existing) {
            existing.remove();
        }
        
        // Crear backdrop
        this.backdrop = document.createElement('div');
        this.backdrop.className = 'bottom-sheet-backdrop';
        this.backdrop.setAttribute('aria-hidden', 'true');
        
        // Crear bottom sheet
        this.element = document.createElement('div');
        this.element.className = `bottom-sheet ${this.options.fullscreen ? 'bottom-sheet--fullscreen' : ''}`;
        this.element.id = this.options.id;
        this.element.setAttribute('role', 'dialog');
        this.element.setAttribute('aria-modal', 'true');
        this.element.setAttribute('aria-labelledby', `${this.options.id}-title`);
        this.element.setAttribute('tabindex', '-1');
        
        let footerHtml = '';
        if (this.options.footer) {
            footerHtml = `
                <div class="bottom-sheet__footer">
                    ${this.options.footer}
                </div>
            `;
        }
        
        // MOB-138: Handle para arrastrar (opcional, solo si no es fullscreen)
        const handleHtml = !this.options.fullscreen ? `
            <div class="bottom-sheet__handle" aria-hidden="true"></div>
        ` : '';
        
        this.element.innerHTML = `
            <div class="bottom-sheet__content">
                ${handleHtml}
                <div class="bottom-sheet__header">
                    <h5 class="bottom-sheet__title" id="${this.options.id}-title">${this.escapeHtml(this.options.title)}</h5>
                    <button type="button" class="bottom-sheet__close" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="bottom-sheet__body">
                    ${this.options.body}
                </div>
                ${footerHtml}
            </div>
        `;
        
        document.body.appendChild(this.backdrop);
        document.body.appendChild(this.element);
        
        // Event listeners
        const closeBtn = this.element.querySelector('.bottom-sheet__close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.hide());
        }
        
        this.backdrop.addEventListener('click', () => this.hide());
        
        // MOB-138: Swipe to dismiss (ya implementado en MOB-137)
        if (this.options.swipeToDismiss && typeof SwipeGestures !== 'undefined') {
            const content = this.element.querySelector('.bottom-sheet__content');
            if (content) {
                SwipeGestures.initSwipeToDismiss(content, () => {
                    this.hide();
                }, 'down');
            }
        }
        
        // MOB-138: Prevenir scroll del body cuando está abierto
        this.preventBodyScroll();
    }

    /**
     * Previene el scroll del body cuando el bottom sheet está abierto
     */
    preventBodyScroll() {
        if (this.isVisible) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }

    /**
     * Muestra el bottom sheet
     */
    show() {
        if (this.isVisible) return;
        
        this.isVisible = true;
        this.preventBodyScroll();
        
        // Mostrar backdrop
        this.backdrop.classList.add('show');
        
        // Mostrar bottom sheet con animación
        requestAnimationFrame(() => {
            this.element.classList.add('show');
            
            // Focus en el primer elemento interactivo o en el título
            const firstInput = this.element.querySelector('input, textarea, select, button:not(.bottom-sheet__close)');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 100);
            } else {
                const title = this.element.querySelector('.bottom-sheet__title');
                if (title) {
                    title.setAttribute('tabindex', '0');
                    setTimeout(() => title.focus(), 100);
                }
            }
            
            if (this.options.onShow) {
                this.options.onShow(this);
            }
            
            // Callback después de mostrar
            setTimeout(() => {
                if (this.options.onShown) {
                    this.options.onShown(this);
                }
            }, 300);
        });
    }

    /**
     * Oculta el bottom sheet
     */
    hide() {
        if (!this.isVisible) return;
        
        this.isVisible = false;
        this.preventBodyScroll();
        
        // Ocultar con animación
        this.element.classList.remove('show');
        this.backdrop.classList.remove('show');
        
        if (this.options.onHide) {
            this.options.onHide(this);
        }
        
        // Callback después de ocultar
        setTimeout(() => {
            if (this.options.onHidden) {
                this.options.onHidden(this);
            }
            
            // Remover del DOM después de la animación
            if (this.element && this.element.parentNode) {
                this.element.remove();
            }
            if (this.backdrop && this.backdrop.parentNode) {
                this.backdrop.remove();
            }
        }, 300);
    }

    /**
     * Actualiza el contenido del bottom sheet
     */
    update(options) {
        this.options = { ...this.options, ...options };
        
        if (this.element) {
            const titleEl = this.element.querySelector('.bottom-sheet__title');
            if (titleEl && options.title !== undefined) {
                titleEl.textContent = options.title;
            }
            
            const bodyEl = this.element.querySelector('.bottom-sheet__body');
            if (bodyEl && options.body !== undefined) {
                bodyEl.innerHTML = options.body;
            }
            
            const footerEl = this.element.querySelector('.bottom-sheet__footer');
            if (options.footer !== undefined) {
                if (options.footer) {
                    if (!footerEl) {
                        const contentEl = this.element.querySelector('.bottom-sheet__content');
                        const footer = document.createElement('div');
                        footer.className = 'bottom-sheet__footer';
                        contentEl.appendChild(footer);
                    }
                    footerEl.innerHTML = options.footer;
                } else if (footerEl) {
                    footerEl.remove();
                }
            }
        }
    }

    /**
     * Destruye el bottom sheet
     */
    destroy() {
        this.hide();
        this.element = null;
        this.backdrop = null;
    }

    /**
     * Escapa HTML para prevenir XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.BottomSheet = BottomSheet;
}

