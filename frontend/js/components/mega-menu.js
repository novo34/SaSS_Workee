/**
 * Mega Menu Component - Workee
 * 
 * NAV-134: Implementar Mega Menu (Opcional, si hay muchas secciones)
 * 
 * Componente reutilizable para mostrar mega menus con layout en columnas,
 * iconos grandes, descripciones y quick actions.
 * 
 * @class MegaMenu
 */
class MegaMenu {
    constructor(triggerElement, options = {}) {
        this.trigger = triggerElement;
        this.options = {
            items: options.items || [], // Array de items con { title, icon, href, description, quickAction }
            columns: Math.min(options.columns || 3, 4), // Máximo 4 columnas
            position: options.position || 'bottom', // 'bottom', 'right', 'left'
            onShow: options.onShow || null,
            onHide: options.onHide || null,
            ...options
        };
        
        this.menu = null;
        this.isVisible = false;
        this.hideTimeout = null;
        
        this.init();
    }
    
    init() {
        // Crear estructura del mega menu
        this.createMenu();
        
        // Agregar event listeners
        this.attachEvents();
    }
    
    createMenu() {
        // Crear contenedor del mega menu
        this.menu = document.createElement('div');
        this.menu.className = 'mega-menu';
        this.menu.setAttribute('role', 'menu');
        this.menu.setAttribute('aria-label', 'Mega menu de navegación');
        this.menu.setAttribute('aria-hidden', 'true');
        
        // Crear wrapper interno
        const wrapper = document.createElement('div');
        wrapper.className = 'mega-menu__wrapper';
        
        // Organizar items en columnas
        const columns = this.organizeItemsIntoColumns();
        
        // Crear columnas
        columns.forEach((columnItems, index) => {
            const column = document.createElement('div');
            column.className = 'mega-menu__column';
            column.setAttribute('role', 'group');
            column.setAttribute('aria-label', `Columna ${index + 1}`);
            
            columnItems.forEach(item => {
                const itemElement = this.createMenuItem(item);
                column.appendChild(itemElement);
            });
            
            wrapper.appendChild(column);
        });
        
        // Agregar quick actions si existen
        const quickActions = this.options.items.filter(item => item.quickAction);
        if (quickActions.length > 0) {
            const quickActionsSection = this.createQuickActionsSection(quickActions);
            wrapper.appendChild(quickActionsSection);
        }
        
        this.menu.appendChild(wrapper);
        
        // Insertar después del trigger
        this.trigger.parentNode.insertBefore(this.menu, this.trigger.nextSibling);
    }
    
    organizeItemsIntoColumns() {
        const items = this.options.items;
        const columns = this.options.columns;
        const itemsPerColumn = Math.ceil(items.length / columns);
        
        const result = [];
        for (let i = 0; i < columns; i++) {
            const start = i * itemsPerColumn;
            const end = start + itemsPerColumn;
            result.push(items.slice(start, end));
        }
        
        return result;
    }
    
    createMenuItem(item) {
        const menuItem = document.createElement('a');
        menuItem.href = item.href || '#';
        menuItem.className = 'mega-menu__item';
        menuItem.setAttribute('role', 'menuitem');
        menuItem.setAttribute('aria-label', item.title);
        
        // Icono grande
        if (item.icon) {
            const icon = document.createElement('span');
            icon.className = 'mega-menu__icon icon icon-lg';
            icon.setAttribute('data-icon', item.icon);
            icon.setAttribute('aria-hidden', 'true');
            menuItem.appendChild(icon);
        }
        
        // Contenido
        const content = document.createElement('div');
        content.className = 'mega-menu__content';
        
        const title = document.createElement('div');
        title.className = 'mega-menu__title';
        title.textContent = item.title;
        content.appendChild(title);
        
        // Descripción (opcional)
        if (item.description) {
            const description = document.createElement('div');
            description.className = 'mega-menu__description';
            description.textContent = item.description;
            content.appendChild(description);
        }
        
        menuItem.appendChild(content);
        
        // Event listener para click
        menuItem.addEventListener('click', (e) => {
            if (item.onClick) {
                e.preventDefault();
                item.onClick();
            }
            this.hide();
        });
        
        return menuItem;
    }
    
    createQuickActionsSection(quickActions) {
        const section = document.createElement('div');
        section.className = 'mega-menu__quick-actions';
        section.setAttribute('role', 'group');
        section.setAttribute('aria-label', 'Acciones rápidas');
        
        const title = document.createElement('div');
        title.className = 'mega-menu__quick-actions-title';
        title.textContent = 'Acciones Rápidas';
        section.appendChild(title);
        
        const actionsList = document.createElement('div');
        actionsList.className = 'mega-menu__quick-actions-list';
        
        quickActions.forEach(action => {
            const actionButton = document.createElement('button');
            actionButton.className = 'mega-menu__quick-action btn btn-primary btn-sm';
            actionButton.setAttribute('type', 'button');
            actionButton.setAttribute('aria-label', action.title);
            
            if (action.icon) {
                const icon = document.createElement('span');
                icon.className = 'icon icon-sm';
                icon.setAttribute('data-icon', action.icon);
                icon.setAttribute('aria-hidden', 'true');
                actionButton.appendChild(icon);
            }
            
            const text = document.createElement('span');
            text.textContent = action.title;
            actionButton.appendChild(text);
            
            actionButton.addEventListener('click', (e) => {
                e.preventDefault();
                if (action.onClick) {
                    action.onClick();
                }
                this.hide();
            });
            
            actionsList.appendChild(actionButton);
        });
        
        section.appendChild(actionsList);
        return section;
    }
    
    attachEvents() {
        // Mostrar en hover (desktop) o click (móvil)
        if (window.innerWidth >= 992) {
            // Desktop: hover
            this.trigger.addEventListener('mouseenter', () => {
                this.clearHideTimeout();
                this.show();
            });
            
            this.menu.addEventListener('mouseenter', () => {
                this.clearHideTimeout();
            });
            
            this.menu.addEventListener('mouseleave', () => {
                this.scheduleHide();
            });
            
            this.trigger.addEventListener('mouseleave', () => {
                this.scheduleHide();
            });
        } else {
            // Móvil: click
            this.trigger.addEventListener('click', (e) => {
                e.preventDefault();
                if (this.isVisible) {
                    this.hide();
                } else {
                    this.show();
                }
            });
        }
        
        // Cerrar con Esc
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isVisible) {
                this.hide();
                this.trigger.focus();
            }
        });
        
        // Cerrar al hacer click fuera
        document.addEventListener('click', (e) => {
            if (this.isVisible && 
                !this.menu.contains(e.target) && 
                !this.trigger.contains(e.target)) {
                this.hide();
            }
        });
    }
    
    show() {
        if (this.isVisible) return;
        
        this.isVisible = true;
        this.menu.classList.add('mega-menu--visible');
        this.menu.setAttribute('aria-hidden', 'false');
        this.trigger.setAttribute('aria-expanded', 'true');
        
        // Procesar iconos si IconHelper está disponible
        if (typeof IconHelper !== 'undefined') {
            IconHelper.processDataIcons(this.menu, { lazy: false });
        }
        
        // Callback
        if (this.options.onShow) {
            this.options.onShow();
        }
    }
    
    hide() {
        if (!this.isVisible) return;
        
        this.isVisible = false;
        this.menu.classList.remove('mega-menu--visible');
        this.menu.setAttribute('aria-hidden', 'true');
        this.trigger.setAttribute('aria-expanded', 'false');
        
        // Callback
        if (this.options.onHide) {
            this.options.onHide();
        }
    }
    
    scheduleHide() {
        this.clearHideTimeout();
        this.hideTimeout = setTimeout(() => {
            this.hide();
        }, 200); // Pequeño delay para evitar cierre accidental
    }
    
    clearHideTimeout() {
        if (this.hideTimeout) {
            clearTimeout(this.hideTimeout);
            this.hideTimeout = null;
        }
    }
    
    updateItems(newItems) {
        this.options.items = newItems;
        // Recrear menu
        if (this.menu) {
            this.menu.remove();
        }
        this.createMenu();
        this.attachEvents();
    }
    
    destroy() {
        this.clearHideTimeout();
        if (this.menu) {
            this.menu.remove();
        }
        // Remover event listeners (simplificado, en producción usar removeEventListener)
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.MegaMenu = MegaMenu;
}

