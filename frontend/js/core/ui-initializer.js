/**
 * UI Initializer - Workee
 * 
 * Helper para inicializar componentes UI comunes en todas las páginas
 * - Tooltips
 * - Iconos SVG consistentes
 * 
 * @class UIInitializer
 * @description Helper para inicializar componentes UI comunes
 */

class UIInitializer {
    /**
     * Inicializa todos los componentes UI comunes
     * @param {Object} options - Opciones
     * @param {boolean} options.tooltips - Si inicializar tooltips (default: true)
     * @param {boolean} options.icons - Si inicializar iconos (default: true)
     * @param {string} options.tooltipSelector - Selector para tooltips (default: '[data-tooltip]')
     * @param {string} options.iconSelector - Selector para iconos (default: '[data-icon]')
     */
    static init(options = {}) {
        const {
            tooltips = true,
            icons = true,
            tooltipSelector = '[data-tooltip]',
            iconSelector = '[data-icon]'
        } = options;
        
        if (tooltips && typeof Tooltip !== 'undefined') {
            Tooltip.init(tooltipSelector);
        }
        
        if (icons && typeof IconHelper !== 'undefined') {
            const iconElements = document.querySelectorAll(iconSelector);
            iconElements.forEach(el => {
                const iconName = el.getAttribute('data-icon');
                if (iconName) {
                    const size = el.classList.contains('icon-lg') ? 'lg' : 
                                el.classList.contains('icon-sm') ? 'sm' : 
                                el.classList.contains('icon-xl') ? 'xl' : 'md';
                    
                    const iconHtml = IconHelper.get(iconName, {
                        className: el.className,
                        size: size
                    });
                    if (iconHtml) {
                        el.innerHTML = iconHtml;
                    }
                }
            });
        }

        // MOB-142: Optimizar formularios para móvil
        if (typeof FormMobileOptimizer !== 'undefined') {
            FormMobileOptimizer.init();
        }

        // MOB-143: Inicializar optimizaciones de performance móvil
        if (typeof PerformanceMobile !== 'undefined') {
            PerformanceMobile.init();
        }

        // MOB-149: Optimizar imágenes y videos para móvil
        if (typeof ImageMobileOptimizer !== 'undefined') {
            ImageMobileOptimizer.init();
        }

        // MOB-148: Cargar script de verificación de testing móvil en desarrollo
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            // Solo cargar en desarrollo
            const script = document.createElement('script');
            script.src = `${window.BASE_URL || ''}/scripts/dev/mobile-testing-verification.js`;
            script.onload = () => {
                console.log('📱 Mobile Testing Verification cargado. Ejecutar: MobileTestingVerification.runAll()');
            };
            script.onerror = () => {
                // Silenciar error si el script no existe
            };
            document.head.appendChild(script);
        }
    }
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.UIInitializer = UIInitializer;
}

