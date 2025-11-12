/**
 * Componente Skeleton Screen - Workee
 * 
 * Helper para generar skeleton screens durante la carga de datos
 * Mejora la velocidad percibida del sistema
 * 
 * @class SkeletonScreen
 * @description Helper para generar skeleton screens
 */

class SkeletonScreen {
    /**
     * Genera HTML para un skeleton de texto
     * @param {number} lines - Número de líneas
     * @param {string} variant - Variante (text, title, subtitle)
     * @returns {string} HTML del skeleton
     */
    static text(lines = 3, variant = 'text') {
        const classes = {
            text: 'skeleton skeleton--text',
            title: 'skeleton skeleton--title',
            subtitle: 'skeleton skeleton--subtitle'
        };
        
        const className = classes[variant] || classes.text;
        let html = '';
        
        for (let i = 0; i < lines; i++) {
            html += `<div class="${className}"></div>`;
        }
        
        return html;
    }
    
    /**
     * Genera HTML para un skeleton de card
     * @param {Object} options - Opciones
     * @param {boolean} options.withHeader - Si incluye header
     * @param {number} options.textLines - Número de líneas de texto
     * @param {boolean} options.withButton - Si incluye botón
     * @returns {string} HTML del skeleton
     */
    static card(options = {}) {
        const {
            withHeader = true,
            textLines = 3,
            withButton = false
        } = options;
        
        let html = '<div class="skeleton-card">';
        
        if (withHeader) {
            html += `
                <div class="skeleton-card__header">
                    <div class="skeleton skeleton--avatar"></div>
                    <div class="skeleton-card__title skeleton skeleton--title"></div>
                </div>
            `;
        }
        
        html += '<div class="skeleton-card__body">';
        html += this.text(textLines, 'text');
        html += '</div>';
        
        if (withButton) {
            html += `
                <div class="skeleton-card__footer">
                    <div class="skeleton skeleton--button"></div>
                </div>
            `;
        }
        
        html += '</div>';
        
        return html;
    }
    
    /**
     * Genera HTML para un skeleton de tabla
     * @param {number} rows - Número de filas
     * @param {number} cols - Número de columnas
     * @returns {string} HTML del skeleton
     */
    static table(rows = 5, cols = 4) {
        let html = '<div class="skeleton-table">';
        
        // Header
        html += '<div class="skeleton-table__row">';
        for (let i = 0; i < cols; i++) {
            html += '<div class="skeleton-table__cell skeleton skeleton--text"></div>';
        }
        html += '</div>';
        
        // Rows
        for (let r = 0; r < rows; r++) {
            html += '<div class="skeleton-table__row">';
            for (let c = 0; c < cols; c++) {
                const isShort = c === 0 || c === cols - 1;
                const cellClass = isShort ? 'skeleton-table__cell--short' : 'skeleton-table__cell--long';
                html += `<div class="skeleton-table__cell ${cellClass} skeleton skeleton--text"></div>`;
            }
            html += '</div>';
        }
        
        html += '</div>';
        
        return html;
    }
    
    /**
     * Genera HTML para un skeleton de lista de cards
     * @param {number} count - Número de cards
     * @param {Object} cardOptions - Opciones para cada card
     * @returns {string} HTML del skeleton
     */
    static cardList(count = 3, cardOptions = {}) {
        let html = '<div class="skeleton-grid">';
        
        for (let i = 0; i < count; i++) {
            html += this.card(cardOptions);
        }
        
        html += '</div>';
        
        return html;
    }
    
    /**
     * Genera HTML para un skeleton de dashboard KPI
     * @param {number} count - Número de KPIs
     * @returns {string} HTML del skeleton
     */
    static kpiCards(count = 4) {
        let html = '<div class="skeleton-grid skeleton-grid--4">';
        
        for (let i = 0; i < count; i++) {
            html += `
                <div class="skeleton-card">
                    <div class="skeleton-card__body">
                        <div class="skeleton skeleton--subtitle mb-2"></div>
                        <div class="skeleton skeleton--title mb-2"></div>
                        <div class="skeleton skeleton--text"></div>
                    </div>
                </div>
            `;
        }
        
        html += '</div>';
        
        return html;
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.SkeletonScreen = SkeletonScreen;
}

