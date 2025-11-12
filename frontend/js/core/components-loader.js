/**
 * Cargador de Componentes - Workee
 * 
 * Carga todos los componentes reutilizables en el orden correcto
 * Este archivo debe ser incluido antes de cualquier código que use componentes
 */

/**
 * Obtiene BASE_URL esperando a que esté disponible
 */
async function getBaseUrl() {
    const maxAttempts = 50; // 5 segundos máximo
    let attempts = 0;
    
    while (attempts < maxAttempts) {
        if (typeof window !== 'undefined' && window.BASE_URL !== undefined) {
            return window.BASE_URL || '';
        }
        await new Promise(resolve => setTimeout(resolve, 100));
        attempts++;
    }
    
    // Si no está disponible, usar ruta relativa desde el script actual
    const currentScript = document.currentScript;
    if (currentScript && currentScript.src) {
        const scriptPath = currentScript.src;
        const match = scriptPath.match(/^(.*?)\/frontend\/js\/core\/components-loader\.js/);
        if (match) {
            return match[1];
        }
    }
    
    return '';
}

/**
 * Carga componentes de forma asíncrona
 */
async function loadComponents() {
    // Esperar a que BASE_URL esté disponible
    const BASE_URL = await getBaseUrl();
    
    // Componentes en orden de dependencias
    const components = [
        // Core primero
        `${BASE_URL}/frontend/js/core/api.js`,
        `${BASE_URL}/frontend/js/core/error-helper.js`,
        `${BASE_URL}/frontend/js/core/button-state.js`,
        `${BASE_URL}/frontend/js/core/icon-helper.js`,
        `${BASE_URL}/frontend/js/core/ui-initializer.js`,
        `${BASE_URL}/frontend/js/core/swipe-gestures.js`,
        `${BASE_URL}/frontend/js/core/pull-to-refresh.js`,
        `${BASE_URL}/frontend/js/core/form-mobile-optimizer.js`,
        `${BASE_URL}/frontend/js/core/performance-mobile.js`,
        `${BASE_URL}/frontend/js/core/virtual-scroll.js`,
        `${BASE_URL}/frontend/js/core/image-mobile-optimizer.js`,
        
        // Componentes base (sin dependencias)
        `${BASE_URL}/frontend/js/components/toast.js`,
        `${BASE_URL}/frontend/js/components/loading-spinner.js`,
        `${BASE_URL}/frontend/js/components/empty-state.js`,
        `${BASE_URL}/frontend/js/components/skeleton-screen.js`,
        
        // Componentes con dependencias
        `${BASE_URL}/frontend/js/components/modal.js`,
        `${BASE_URL}/frontend/js/components/bottom-sheet.js`,
        `${BASE_URL}/frontend/js/components/floating-action-button.js`,
        `${BASE_URL}/frontend/js/components/data-table.js`,
        `${BASE_URL}/frontend/js/components/form-validator.js`,
        `${BASE_URL}/frontend/js/components/progress-bar.js`,
        `${BASE_URL}/frontend/js/components/tooltip.js`,
        `${BASE_URL}/frontend/js/components/achievements.js`,
        `${BASE_URL}/frontend/js/components/task-progress.js`,
        `${BASE_URL}/frontend/js/components/mega-menu.js`,
        // Core utilities
        `${BASE_URL}/frontend/js/core/keyboard-shortcuts.js`,
        `${BASE_URL}/frontend/js/core/autocomplete.js`,
        `${BASE_URL}/frontend/js/core/global-search.js`,
        `${BASE_URL}/frontend/js/core/filter-manager.js`,
        `${BASE_URL}/frontend/js/core/mega-menu-init.js`
    ];
    
    const loadScript = (src) => {
        return new Promise((resolve, reject) => {
            // Verificar si el script ya está cargado
            const existingScript = document.querySelector(`script[src="${src}"]`);
            if (existingScript) {
                resolve();
                return;
            }
            
            const script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = () => {
                console.error(`Failed to load component: ${src}`);
                reject(new Error(`Failed to load: ${src}`));
            };
            document.head.appendChild(script);
        });
    };
    
    for (const component of components) {
        try {
            await loadScript(component);
        } catch (error) {
            console.error(`Error loading component: ${component}`, error);
        }
    }
}

// Cargar componentes si estamos en el navegador
if (typeof window !== 'undefined' && document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadComponents);
} else if (typeof window !== 'undefined') {
    loadComponents();
}

