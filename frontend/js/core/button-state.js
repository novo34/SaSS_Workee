/**
 * Button State Helper - Workee
 * 
 * Helper para manejar estados visuales en botones (loading, success, error)
 * Proporciona feedback visual inmediato en todas las acciones
 * 
 * @class ButtonState
 * @description Helper para estados visuales de botones
 */

class ButtonState {
    /**
     * Establece un botón en estado de carga
     * @param {HTMLElement} button - Botón a modificar
     * @param {string} loadingText - Texto a mostrar durante la carga
     */
    static setLoading(button, loadingText = 'Cargando...') {
        if (!button) return;
        
        // Guardar estado original
        if (!button.dataset.originalText) {
            button.dataset.originalText = button.textContent || button.innerHTML;
        }
        if (!button.dataset.originalDisabled) {
            button.dataset.originalDisabled = button.disabled ? 'true' : 'false';
        }
        
        // Aplicar estado loading
        button.disabled = true;
        button.classList.add('btn--loading');
        
        // Guardar contenido original si no está guardado
        if (!button.dataset.originalContent) {
            button.dataset.originalContent = button.innerHTML;
        }
        
        // Mostrar spinner y texto
        button.innerHTML = `
            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            <span>${this.escapeHtml(loadingText)}</span>
        `;
    }
    
    /**
     * Establece un botón en estado de éxito
     * @param {HTMLElement} button - Botón a modificar
     * @param {string} successText - Texto a mostrar
     * @param {number} duration - Duración en ms antes de restaurar
     */
    static setSuccess(button, successText = '¡Completado!', duration = 2000) {
        if (!button) return;
        
        // Guardar estado original si no está guardado
        if (!button.dataset.originalContent) {
            button.dataset.originalContent = button.innerHTML;
        }
        
        // Aplicar estado success
        button.classList.remove('btn--loading');
        button.classList.add('btn--success');
        button.disabled = false;
        
        // Mostrar icono de éxito y texto
        button.innerHTML = `
            <span class="btn-success-icon me-2">✓</span>
            <span>${this.escapeHtml(successText)}</span>
        `;
        
        // Restaurar después de duration
        if (duration > 0) {
            setTimeout(() => {
                this.restore(button);
            }, duration);
        }
    }
    
    /**
     * Establece un botón en estado de error
     * @param {HTMLElement} button - Botón a modificar
     * @param {string} errorText - Texto a mostrar
     * @param {number} duration - Duración en ms antes de restaurar
     */
    static setError(button, errorText = 'Error', duration = 3000) {
        if (!button) return;
        
        // Guardar estado original si no está guardado
        if (!button.dataset.originalContent) {
            button.dataset.originalContent = button.innerHTML;
        }
        
        // Aplicar estado error
        button.classList.remove('btn--loading', 'btn--success');
        button.classList.add('btn--error');
        button.disabled = false;
        
        // Mostrar icono de error y texto
        button.innerHTML = `
            <span class="btn-error-icon me-2">✕</span>
            <span>${this.escapeHtml(errorText)}</span>
        `;
        
        // Restaurar después de duration
        if (duration > 0) {
            setTimeout(() => {
                this.restore(button);
            }, duration);
        }
    }
    
    /**
     * Restaura un botón a su estado original
     * @param {HTMLElement} button - Botón a restaurar
     */
    static restore(button) {
        if (!button) return;
        
        // Restaurar contenido original
        if (button.dataset.originalContent) {
            button.innerHTML = button.dataset.originalContent;
            delete button.dataset.originalContent;
        } else if (button.dataset.originalText) {
            button.textContent = button.dataset.originalText;
            delete button.dataset.originalText;
        }
        
        // Restaurar disabled
        if (button.dataset.originalDisabled) {
            button.disabled = button.dataset.originalDisabled === 'true';
            delete button.dataset.originalDisabled;
        } else {
            button.disabled = false;
        }
        
        // Remover clases de estado
        button.classList.remove('btn--loading', 'btn--success', 'btn--error');
    }
    
    /**
     * Escapa HTML
     */
    static escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.ButtonState = ButtonState;
}

