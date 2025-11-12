/**
 * Inicialización de Mega Menu - Workee
 * 
 * NAV-134: Inicializa mega menu para secciones con muchas subsecciones
 * 
 * Este helper inicializa automáticamente mega menus en secciones de navegación
 * que tienen múltiples subsecciones, mejorando la UX cuando hay muchas opciones.
 */

/**
 * Inicializa mega menu para una sección de navegación
 */
function initMegaMenuForSection(sectionElement) {
    if (!sectionElement || typeof MegaMenu === 'undefined') {
        return null;
    }
    
    const sectionTitle = sectionElement.querySelector('.nav-section-title');
    if (!sectionTitle) {
        return null;
    }
    
    // Obtener todas las subsecciones
    const subsections = sectionElement.querySelectorAll('.nav-subsection');
    if (subsections.length < 2) {
        // No necesita mega menu si tiene menos de 2 subsecciones
        return null;
    }
    
    // Recopilar items de todas las subsecciones
    const items = [];
    const quickActions = [];
    
    subsections.forEach(subsection => {
        const subsectionTitle = subsection.querySelector('.nav-subsection-title-text')?.textContent || '';
        const navItems = subsection.querySelectorAll('.nav-item .nav-link');
        
        navItems.forEach(navLink => {
            const icon = navLink.querySelector('[data-icon]')?.getAttribute('data-icon') || '';
            const text = navLink.querySelector('.nav-text')?.textContent || navLink.textContent.trim();
            const href = navLink.getAttribute('href') || '#';
            
            items.push({
                title: text,
                icon: icon,
                href: href,
                description: `${subsectionTitle} - ${text}`,
                onClick: () => {
                    window.location.href = href;
                }
            });
        });
    });
    
    // Agregar quick actions si hay items comunes
    // Ejemplo: "Nueva Solicitud" si estamos en sección de planificación
    const planningSection = sectionElement.querySelector('[data-nav-group="planning"]');
    if (planningSection) {
        quickActions.push({
            title: 'Nueva Solicitud',
            icon: 'plus',
            onClick: () => {
                window.location.href = '/staffing-requests?action=create';
            },
            quickAction: true
        });
    }
    
    const workOrdersSection = sectionElement.querySelector('[data-nav-group="work-orders"]');
    if (workOrdersSection) {
        quickActions.push({
            title: 'Nueva OT',
            icon: 'plus',
            onClick: () => {
                window.location.href = '/work-orders?action=create';
            },
            quickAction: true
        });
    }
    
    // Combinar items con quick actions
    const allItems = [...items, ...quickActions];
    
    // Convertir section title en trigger clickeable
    const trigger = document.createElement('button');
    trigger.className = 'nav-section-title nav-section-title--mega-trigger';
    trigger.setAttribute('type', 'button');
    trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-haspopup', 'true');
    trigger.innerHTML = sectionTitle.innerHTML;
    trigger.setAttribute('data-i18n', sectionTitle.getAttribute('data-i18n') || '');
    
    // Reemplazar section title con trigger
    sectionTitle.replaceWith(trigger);
    
    // Crear mega menu
    const megaMenu = new MegaMenu(trigger, {
        items: allItems,
        columns: Math.min(3, Math.ceil(allItems.length / 4)), // Máximo 3 columnas, ajustar según items
        position: 'bottom',
        onShow: () => {
            trigger.setAttribute('aria-expanded', 'true');
        },
        onHide: () => {
            trigger.setAttribute('aria-expanded', 'false');
        }
    });
    
    return megaMenu;
}

/**
 * Inicializa todos los mega menus en el sidebar
 */
function initMegaMenus() {
    if (typeof MegaMenu === 'undefined') {
        console.warn('[Mega Menu] Componente MegaMenu no disponible');
        return;
    }
    
    const sidebar = document.getElementById('main-navigation');
    if (!sidebar) {
        return;
    }
    
    // Buscar secciones que podrían beneficiarse de mega menu
    // Criterio: secciones con 2+ subsecciones
    const sections = sidebar.querySelectorAll('.nav-section');
    const megaMenus = [];
    
    sections.forEach(section => {
        const subsections = section.querySelectorAll('.nav-subsection');
        if (subsections.length >= 2) {
            // Esta sección tiene múltiples subsecciones, crear mega menu
            const megaMenu = initMegaMenuForSection(section);
            if (megaMenu) {
                megaMenus.push(megaMenu);
            }
        }
    });
    
    if (megaMenus.length > 0) {
        console.log(`[Mega Menu] ${megaMenus.length} mega menu(s) inicializado(s)`);
    }
    
    return megaMenus;
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.initMegaMenus = initMegaMenus;
    window.initMegaMenuForSection = initMegaMenuForSection;
}

