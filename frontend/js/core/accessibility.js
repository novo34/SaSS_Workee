/**
 * Utilidades de Accesibilidad - Workee
 * 
 * Funciones helper para mejorar accesibilidad del frontend
 */

/**
 * Maneja navegación por teclado en listas
 */
function setupKeyboardNavigation(container, selector = 'button, a, [tabindex="0"]') {
    const items = Array.from(container.querySelectorAll(selector));
    
    container.addEventListener('keydown', (e) => {
        const currentIndex = items.indexOf(document.activeElement);
        
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                if (currentIndex < items.length - 1) {
                    items[currentIndex + 1].focus();
                }
                break;
            case 'ArrowUp':
                e.preventDefault();
                if (currentIndex > 0) {
                    items[currentIndex - 1].focus();
                }
                break;
            case 'Home':
                e.preventDefault();
                items[0]?.focus();
                break;
            case 'End':
                e.preventDefault();
                items[items.length - 1]?.focus();
                break;
        }
    });
}

/**
 * Añade ARIA labels a elementos que no los tienen
 */
function enhanceAriaLabels() {
    // Botones sin texto
    document.querySelectorAll('button:not([aria-label]):not([aria-labelledby])').forEach(btn => {
        const icon = btn.querySelector('i[class*="bi-"]');
        if (icon) {
            const iconClass = Array.from(icon.classList).find(c => c.startsWith('bi-'));
            if (iconClass) {
                const label = iconClass.replace('bi-', '').replace('-', ' ');
                btn.setAttribute('aria-label', label);
            }
        }
    });
    
    // Imágenes sin alt
    document.querySelectorAll('img:not([alt])').forEach(img => {
        img.setAttribute('alt', '');
        img.setAttribute('role', 'presentation');
    });
}

/**
 * Mejora el contraste de colores (verificación)
 */
function checkContrast(element) {
    const style = window.getComputedStyle(element);
    const bgColor = style.backgroundColor;
    const textColor = style.color;
    
    // Función para calcular luminancia relativa
    const getLuminance = (rgb) => {
        const [r, g, b] = rgb.match(/\d+/g).map(Number);
        const [rs, gs, bs] = [r, g, b].map(val => {
            val = val / 255;
            return val <= 0.03928 ? val / 12.92 : Math.pow((val + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs;
    };
    
    // Calcular ratio de contraste
    const bgLum = getLuminance(bgColor);
    const textLum = getLuminance(textColor);
    const ratio = (Math.max(bgLum, textLum) + 0.05) / (Math.min(bgLum, textLum) + 0.05);
    
    return ratio >= 4.5; // WCAG AA mínimo
}

/**
 * Añade skip links para navegación rápida
 */
function addSkipLinks() {
    const skipLinks = document.createElement('div');
    skipLinks.className = 'skip-links';
    skipLinks.innerHTML = `
        <a href="#main-content" class="skip-link">Saltar al contenido principal</a>
        <a href="#navigation" class="skip-link">Saltar a navegación</a>
    `;
    document.body.insertBefore(skipLinks, document.body.firstChild);
}

/**
 * Mejora el foco visible
 */
function enhanceFocusVisible() {
    const style = document.createElement('style');
    style.textContent = `
        *:focus-visible {
            outline: 2px solid var(--color-primary);
            outline-offset: 2px;
        }
        
        .skip-link {
            position: absolute;
            top: -40px;
            left: 0;
            background: var(--color-primary);
            color: white;
            padding: 8px;
            text-decoration: none;
            z-index: 10000;
        }
        
        .skip-link:focus {
            top: 0;
        }
    `;
    document.head.appendChild(style);
}

/**
 * Añade roles ARIA apropiados
 */
function enhanceAriaRoles() {
    // Buscar elementos que necesitan roles
    document.querySelectorAll('[role="button"]:not([aria-label])').forEach(btn => {
        if (!btn.getAttribute('aria-label') && btn.textContent.trim()) {
            btn.setAttribute('aria-label', btn.textContent.trim());
        }
    });
}

/**
 * Mejora formularios para screen readers
 */
function enhanceForms() {
    document.querySelectorAll('form').forEach(form => {
        // Añadir aria-describedby a campos con errores
        form.querySelectorAll('.is-invalid').forEach(field => {
            const errorId = `error-${field.name || field.id}`;
            const errorMsg = field.parentNode.querySelector('.invalid-feedback');
            if (errorMsg && !errorMsg.id) {
                errorMsg.id = errorId;
                field.setAttribute('aria-describedby', errorId);
            }
        });
        
        // Añadir aria-required a campos requeridos
        form.querySelectorAll('input[required], select[required], textarea[required]').forEach(field => {
            if (!field.hasAttribute('aria-required')) {
                field.setAttribute('aria-required', 'true');
            }
        });
    });
}

/**
 * Inicializa todas las mejoras de accesibilidad
 */
function initAccessibility() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            enhanceAriaLabels();
            enhanceFocusVisible();
            enhanceAriaRoles();
            enhanceForms();
            addSkipLinks();
        });
    } else {
        enhanceAriaLabels();
        enhanceFocusVisible();
        enhanceAriaRoles();
        enhanceForms();
        addSkipLinks();
    }
}

// Inicializar automáticamente
initAccessibility();

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.setupKeyboardNavigation = setupKeyboardNavigation;
    window.enhanceAriaLabels = enhanceAriaLabels;
    window.checkContrast = checkContrast;
    window.initAccessibility = initAccessibility;
}

