/**
 * Componente Modal Reutilizable - Workee
 * 
 * Wrapper sobre Bootstrap Modal con API simplificada
 * 
 * @class Modal
 * @description Componente modal reutilizable que envuelve Bootstrap Modal con API simplificada
 * 
 * @example
 * // Modal simple
 * const modal = new Modal({
 *   title: 'Título',
 *   body: '<p>Contenido</p>',
 *   footer: '<button>OK</button>',
 *   size: 'lg' // 'sm', 'lg', 'xl'
 * });
 * modal.show();
 * 
 * @example
 * // Confirmación rápida (método estático)
 * const confirmed = await Modal.confirm({
 *   title: 'Confirmar',
 *   message: '¿Está seguro?',
 *   confirmLabel: 'Sí',
 *   cancelLabel: 'No'
 * });
 * 
 * @param {Object} options - Opciones de configuración
 * @param {string} [options.id] - ID único del modal (auto-generado si no se proporciona)
 * @param {string} [options.title=''] - Título del modal
 * @param {string} [options.body=''] - Contenido del modal (HTML)
 * @param {string} [options.footer=null] - Footer del modal (HTML)
 * @param {string} [options.size=''] - Tamaño: 'sm', 'lg', 'xl'
 * @param {boolean} [options.backdrop=true] - Si mostrar backdrop
 * @param {boolean} [options.keyboard=true] - Si cerrar con tecla ESC
 * @param {boolean} [options.focus=true] - Si enfocar automáticamente
 * @param {Function} [options.onShow] - Callback cuando se muestra
 * @param {Function} [options.onHide] - Callback cuando se oculta
 * @param {Function} [options.onShown] - Callback después de mostrar
 * @param {Function} [options.onHidden] - Callback después de ocultar
 */

class Modal {
    constructor(options = {}) {
        this.options = {
            id: options.id || `modal-${Date.now()}`,
            title: options.title || '',
            body: options.body || '',
            footer: options.footer || null,
            size: options.size || '', // 'sm', 'lg', 'xl'
            backdrop: options.backdrop !== undefined ? options.backdrop : true,
            keyboard: options.keyboard !== undefined ? options.keyboard : true,
            focus: options.focus !== undefined ? options.focus : true,
            onShow: options.onShow || null,
            onHide: options.onHide || null,
            onShown: options.onShown || null,
            onHidden: options.onHidden || null,
            mobileMode: options.mobileMode || 'bottom-sheet', // MOB-138: 'bottom-sheet', 'fullscreen', o 'modal' (deshabilitar optimización móvil)
            swipeToDismiss: options.swipeToDismiss !== undefined ? options.swipeToDismiss : true,
            ...options
        };
        
        this.element = null;
        this.bootstrapModal = null;
        this.bottomSheet = null; // MOB-138: Instancia de BottomSheet si se usa en móvil
        this.create();
    }

    /**
     * Crea el elemento modal
     */
    create() {
        // Si ya existe, removerlo
        const existing = document.getElementById(this.options.id);
        if (existing) {
            existing.remove();
        }
        
        this.element = document.createElement('div');
        this.element.className = 'modal fade';
        this.element.id = this.options.id;
        this.element.setAttribute('tabindex', '-1');
        this.element.setAttribute('aria-labelledby', `${this.options.id}-title`);
        this.element.setAttribute('aria-hidden', 'true');
        // MOB-138: Marcar modo móvil para CSS (solo si no es modal normal)
        if (this.options.mobileMode !== 'modal') {
            this.element.setAttribute('data-mobile-mode', this.options.mobileMode);
        }
        
        const sizeClass = this.options.size ? `modal-${this.options.size}` : '';
        
        let footerHtml = '';
        if (this.options.footer) {
            footerHtml = `
                <div class="modal-footer">
                    ${this.options.footer}
                </div>
            `;
        }
        
        this.element.innerHTML = `
            <div class="modal-dialog ${sizeClass}">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="${this.options.id}-title">${this.escapeHtml(this.options.title)}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        ${this.options.body}
                    </div>
                    ${footerHtml}
                </div>
            </div>
        `;
        
        document.body.appendChild(this.element);
        
        // Inicializar Bootstrap Modal
        this.bootstrapModal = new bootstrap.Modal(this.element, {
            backdrop: this.options.backdrop,
            keyboard: this.options.keyboard,
            focus: this.options.focus
        });
        
        // Event listeners
        this.element.addEventListener('show.bs.modal', () => {
            if (this.options.onShow) {
                this.options.onShow(this);
            }
        });
        
        this.element.addEventListener('shown.bs.modal', () => {
            // MOB-137: Swipe to dismiss en móvil (solo en modales no críticos)
            if (window.innerWidth < 992 && typeof SwipeGestures !== 'undefined' && this.options.swipeToDismiss !== false) {
                const modalContent = this.element.querySelector('.modal-content');
                if (modalContent) {
                    SwipeGestures.initSwipeToDismiss(modalContent, () => {
                        this.hide();
                    }, 'down');
                }
            }
            
            if (this.options.onShown) {
                this.options.onShown(this);
            }
        });
        
        this.element.addEventListener('hide.bs.modal', () => {
            if (this.options.onHide) {
                this.options.onHide(this);
            }
        });
        
        this.element.addEventListener('hidden.bs.modal', () => {
            if (this.options.onHidden) {
                this.options.onHidden(this);
            }
        });
    }

    /**
     * Muestra el modal
     */
    show() {
        // MOB-138: En móvil, usar BottomSheet o fullscreen en lugar de modal centrado
        if (window.innerWidth < 992 && this.options.mobileMode !== 'modal') {
            if (!this.bottomSheet) {
                // Crear bottom sheet
                if (typeof BottomSheet !== 'undefined') {
                    this.bottomSheet = new BottomSheet({
                        id: this.options.id,
                        title: this.options.title,
                        body: this.options.body,
                        footer: this.options.footer,
                        fullscreen: this.options.mobileMode === 'fullscreen',
                        swipeToDismiss: this.options.swipeToDismiss,
                        onShow: this.options.onShow ? () => this.options.onShow(this) : null,
                        onHide: this.options.onHide ? () => this.options.onHide(this) : null,
                        onShown: this.options.onShown ? () => this.options.onShown(this) : null,
                        onHidden: this.options.onHidden ? () => {
                            if (this.options.onHidden) {
                                this.options.onHidden(this);
                            }
                            this.bottomSheet = null;
                        } : null
                    });
                } else {
                    // Fallback: usar modal normal si BottomSheet no está disponible
                    console.warn('BottomSheet no está disponible, usando modal normal');
                    if (!this.bootstrapModal) {
                        this.create();
                    }
                    this.bootstrapModal.show();
                    return;
                }
            }
            this.bottomSheet.show();
        } else {
            // Desktop: usar modal normal
            if (!this.bootstrapModal) {
                this.create();
            }
            this.bootstrapModal.show();
        }
    }

    /**
     * Oculta el modal
     */
    hide() {
        // MOB-138: Cerrar bottom sheet si está activo
        if (this.bottomSheet) {
            this.bottomSheet.hide();
            this.bottomSheet = null;
        } else if (this.bootstrapModal) {
            this.bootstrapModal.hide();
        }
    }

    /**
     * Actualiza el contenido del modal
     */
    update(options) {
        this.options = { ...this.options, ...options };
        
        // MOB-138: Actualizar bottom sheet si está activo
        if (this.bottomSheet) {
            this.bottomSheet.update(options);
        } else if (this.element) {
            const titleEl = this.element.querySelector('.modal-title');
            if (titleEl && options.title !== undefined) {
                titleEl.textContent = options.title;
            }
            
            const bodyEl = this.element.querySelector('.modal-body');
            if (bodyEl && options.body !== undefined) {
                bodyEl.innerHTML = options.body;
            }
            
            const footerEl = this.element.querySelector('.modal-footer');
            if (options.footer !== undefined) {
                if (options.footer) {
                    if (!footerEl) {
                        const contentEl = this.element.querySelector('.modal-content');
                        const footer = document.createElement('div');
                        footer.className = 'modal-footer';
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
     * Destruye el modal
     */
    destroy() {
        // MOB-138: Destruir bottom sheet si está activo
        if (this.bottomSheet) {
            this.bottomSheet.destroy();
            this.bottomSheet = null;
        }
        
        if (this.bootstrapModal) {
            this.bootstrapModal.dispose();
        }
        if (this.element && this.element.parentNode) {
            this.element.parentNode.removeChild(this.element);
        }
        this.element = null;
        this.bootstrapModal = null;
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
     * Método estático para confirmación rápida
     */
    static confirm(options = {}) {
        return new Promise((resolve) => {
            const modal = new Modal({
                title: options.title || 'Confirmar',
                body: options.message || '¿Está seguro?',
                footer: `
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${options.cancelLabel || 'Cancelar'}</button>
                    <button type="button" class="btn btn-primary" id="modal-confirm-btn">${options.confirmLabel || 'Confirmar'}</button>
                `,
                size: options.size || '',
                onShown: () => {
                    const confirmBtn = document.getElementById('modal-confirm-btn');
                    if (confirmBtn) {
                        confirmBtn.addEventListener('click', () => {
                            modal.hide();
                            resolve(true);
                        });
                    }
                },
                onHidden: () => {
                    if (!document.getElementById('modal-confirm-btn')) {
                        resolve(false);
                    }
                    modal.destroy();
                }
            });
            
            modal.show();
        });
    }

    /**
     * Método estático para alerta rápida
     */
    static alert(options = {}) {
        return new Promise((resolve) => {
            const modal = new Modal({
                title: options.title || 'Información',
                body: options.message || '',
                footer: `
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">${options.buttonLabel || 'OK'}</button>
                `,
                size: options.size || '',
                onHidden: () => {
                    modal.destroy();
                    resolve();
                }
            });
            
            modal.show();
        });
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.Modal = Modal;
}

