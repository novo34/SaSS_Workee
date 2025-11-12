/**
 * Gestor de Filtros Guardados/Favoritos
 * 
 * Guarda y restaura filtros usando localStorage
 * 
 * @example
 * FilterManager.save('staffing-requests', { status: 'pending', from: '2024-01-01' });
 * const filters = FilterManager.load('staffing-requests');
 */

class FilterManager {
    constructor() {
        this.storageKey = 'workee_saved_filters';
        this.filters = this.loadAll();
    }
    
    /**
     * Carga todos los filtros guardados
     */
    loadAll() {
        const stored = localStorage.getItem(this.storageKey);
        if (stored) {
            try {
                return JSON.parse(stored);
            } catch (e) {
                return {};
            }
        }
        return {};
    }
    
    /**
     * Guarda todos los filtros
     */
    saveAll() {
        localStorage.setItem(this.storageKey, JSON.stringify(this.filters));
    }
    
    /**
     * Guarda filtros para una página/módulo
     */
    save(module, filters, name = 'default') {
        if (!this.filters[module]) {
            this.filters[module] = {};
        }
        
        this.filters[module][name] = {
            filters,
            savedAt: new Date().toISOString()
        };
        
        this.saveAll();
    }
    
    /**
     * Carga filtros guardados para una página/módulo
     */
    load(module, name = 'default') {
        if (!this.filters[module] || !this.filters[module][name]) {
            return null;
        }
        
        return this.filters[module][name].filters;
    }
    
    /**
     * Lista todos los filtros guardados para un módulo
     */
    list(module) {
        if (!this.filters[module]) {
            return [];
        }
        
        return Object.entries(this.filters[module]).map(([name, data]) => ({
            name,
            filters: data.filters,
            savedAt: data.savedAt
        }));
    }
    
    /**
     * Elimina filtros guardados
     */
    delete(module, name) {
        if (this.filters[module] && this.filters[module][name]) {
            delete this.filters[module][name];
            this.saveAll();
            return true;
        }
        return false;
    }
    
    /**
     * Aplica filtros guardados a un formulario
     */
    applyToForm(module, formSelector, name = 'default') {
        const filters = this.load(module, name);
        if (!filters) {
            return false;
        }
        
        const form = document.querySelector(formSelector);
        if (!form) {
            return false;
        }
        
        Object.entries(filters).forEach(([key, value]) => {
            const input = form.querySelector(`[name="${key}"], [id="${key}"]`);
            if (input) {
                if (input.type === 'checkbox' || input.type === 'radio') {
                    input.checked = value;
                } else {
                    input.value = value;
                }
            }
        });
        
        return true;
    }
    
    /**
     * Guarda filtros desde un formulario
     */
    saveFromForm(module, formSelector, name = 'default') {
        const form = document.querySelector(formSelector);
        if (!form) {
            return false;
        }
        
        const filters = {};
        const formData = new FormData(form);
        
        for (const [key, value] of formData.entries()) {
            filters[key] = value;
        }
        
        // También incluir checkboxes y radios no seleccionados
        form.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(input => {
            if (!formData.has(input.name)) {
                filters[input.name] = false;
            }
        });
        
        this.save(module, filters, name);
        return true;
    }
    
    /**
     * Inicializa el gestor de filtros
     */
    static init() {
        if (!window.filterManagerInstance) {
            window.filterManagerInstance = new FilterManager();
        }
        return window.filterManagerInstance;
    }
}

// Auto-inicializar
if (typeof window !== 'undefined') {
    window.FilterManager = FilterManager;
    FilterManager.init();
}

