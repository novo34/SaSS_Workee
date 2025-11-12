/**
 * Floating Action Button (FAB) - Workee
 * 
 * Componente FAB para acción principal en móvil
 * MOB-139: Implementar Floating Action Button para acción principal
 * 
 * @class FloatingActionButton
 * @description Botón flotante para acción principal en móvil, con badge opcional y ocultar/mostrar en scroll
 * 
 * @example
 * const fab = new FloatingActionButton({
 *   icon: 'add',
 *   label: 'Nueva solicitud',
 *   onClick: () => console.log('Click'),
 *   badge: 3
 * });
 * fab.show();
 */

class FloatingActionButton {
    constructor(options = {}) {
        this.options = {
            id: options.id || `fab-${Date.now()}`,
            icon: options.icon || 'add',
            label: options.label || '',
            onClick: options.onClick || null,
            badge: options.badge || null,
            position: options.position || 'bottom-right', // 'bottom-right', 'bottom-left', 'bottom-center'
            hideOnScroll: options.hideOnScroll !== undefined ? options.hideOnScroll : true, // MOB-139: Ocultar en scroll down, mostrar en scroll up
            zIndex: options.zIndex || null,
            ...options
        };
        
        this.element = null;
        this.isVisible = true;
        this.lastScrollY = 0;
        this.scrollTimeout = null;
        this.create();
    }

    /**
     * Crea el elemento FAB
     */
    create() {
        // Si ya existe, removerlo
        const existing = document.getElementById(this.options.id);
        if (existing) {
            existing.remove();
        }
        
        this.element = document.createElement('button');
        this.element.id = this.options.id;
        this.element.className = `fab fab--${this.options.position}`;
        this.element.setAttribute('aria-label', this.options.label || 'Acción principal');
        this.element.setAttribute('role', 'button');
        this.element.setAttribute('tabindex', '0');
        
        // Icono
        let iconHtml = '';
        if (typeof IconHelper !== 'undefined') {
            iconHtml = IconHelper.get(this.options.icon, { className: 'fab__icon', size: 'lg' });
        } else {
            iconHtml = `<span class="fab__icon">+</span>`;
        }
        
        // Badge opcional
        let badgeHtml = '';
        if (this.options.badge !== null && this.options.badge !== undefined && this.options.badge > 0) {
            badgeHtml = `<span class="fab__badge" aria-label="${this.options.badge} notificaciones">${this.options.badge > 99 ? '99+' : this.options.badge}</span>`;
        }
        
        this.element.innerHTML = `
            ${iconHtml}
            ${badgeHtml}
            ${this.options.label ? `<span class="fab__label">${this.escapeHtml(this.options.label)}</span>` : ''}
        `;
        
        // Event listener
        if (this.options.onClick) {
            this.element.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.options.onClick(e);
            });
        }
        
        // MOB-139: Ocultar/mostrar en scroll
        if (this.options.hideOnScroll) {
            this.initScrollBehavior();
        }
        
        document.body.appendChild(this.element);
        
        // Animación de entrada
        requestAnimationFrame(() => {
            this.element.classList.add('fab--show');
        });
    }

    /**
     * Inicializa el comportamiento de ocultar/mostrar en scroll
     */
    initScrollBehavior() {
        let ticking = false;
        
        const handleScroll = () => {
            if (!ticking) {
                window.requestAnimationFrame(() => {
                    const currentScrollY = window.scrollY || window.pageYOffset;
                    
                    // Si el scroll es hacia abajo y el FAB está visible, ocultarlo
                    if (currentScrollY > this.lastScrollY && currentScrollY > 100 && this.isVisible) {
                        this.hide();
                    }
                    // Si el scroll es hacia arriba y el FAB está oculto, mostrarlo
                    else if (currentScrollY < this.lastScrollY && !this.isVisible) {
                        this.show();
                    }
                    
                    this.lastScrollY = currentScrollY;
                    ticking = false;
                });
                
                ticking = true;
            }
        };
        
        // Throttle del scroll
        let scrollTimeout = null;
        window.addEventListener('scroll', () => {
            if (scrollTimeout) {
                clearTimeout(scrollTimeout);
            }
            scrollTimeout = setTimeout(handleScroll, 10);
        }, { passive: true });
    }

    /**
     * Muestra el FAB
     */
    show() {
        if (this.isVisible || !this.element) return;
        
        this.isVisible = true;
        this.element.classList.add('fab--show');
        this.element.classList.remove('fab--hidden');
    }

    /**
     * Oculta el FAB
     */
    hide() {
        if (!this.isVisible || !this.element) return;
        
        this.isVisible = false;
        this.element.classList.add('fab--hidden');
        this.element.classList.remove('fab--show');
    }

    /**
     * Actualiza el badge
     */
    updateBadge(count) {
        if (!this.element) return;
        
        this.options.badge = count;
        const badgeEl = this.element.querySelector('.fab__badge');
        
        if (count !== null && count !== undefined && count > 0) {
            if (badgeEl) {
                badgeEl.textContent = count > 99 ? '99+' : count;
                badgeEl.setAttribute('aria-label', `${count} notificaciones`);
            } else {
                const badge = document.createElement('span');
                badge.className = 'fab__badge';
                badge.textContent = count > 99 ? '99+' : count;
                badge.setAttribute('aria-label', `${count} notificaciones`);
                this.element.appendChild(badge);
            }
        } else if (badgeEl) {
            badgeEl.remove();
        }
    }

    /**
     * Actualiza el icono
     */
    updateIcon(iconName) {
        if (!this.element) return;
        
        this.options.icon = iconName;
        const iconEl = this.element.querySelector('.fab__icon');
        
        if (iconEl) {
            if (typeof IconHelper !== 'undefined') {
                iconEl.outerHTML = IconHelper.get(iconName, { className: 'fab__icon', size: 'lg' });
            } else {
                iconEl.textContent = '+';
            }
        }
    }

    /**
     * Actualiza el label
     */
    updateLabel(label) {
        if (!this.element) return;
        
        this.options.label = label;
        const labelEl = this.element.querySelector('.fab__label');
        
        if (label) {
            if (labelEl) {
                labelEl.textContent = label;
            } else {
                const labelSpan = document.createElement('span');
                labelSpan.className = 'fab__label';
                labelSpan.textContent = label;
                this.element.appendChild(labelSpan);
            }
            this.element.setAttribute('aria-label', label);
        } else if (labelEl) {
            labelEl.remove();
        }
    }

    /**
     * Destruye el FAB
     */
    destroy() {
        if (this.element && this.element.parentNode) {
            this.element.remove();
        }
        this.element = null;
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
    window.FloatingActionButton = FloatingActionButton;
}

