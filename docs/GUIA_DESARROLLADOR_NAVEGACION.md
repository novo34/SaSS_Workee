# Guía del Desarrollador - Navegación Workee

**Sprint 5.9: Guía Práctica para Desarrolladores**

Esta guía proporciona ejemplos prácticos y casos de uso para desarrolladores que trabajan con el sistema de navegación de Workee.

---

## 🚀 Inicio Rápido

### Estructura Básica de un Item de Navegación

```html
<div class="nav-item">
    <a href="/page-url" 
       class="nav-link" 
       data-page="page-name" 
       aria-label="Ir a Nombre de Página">
        <span class="nav-icon icon icon-md" data-icon="iconName" aria-hidden="true"></span>
        <span class="nav-text" data-i18n="nav.page_key">Texto Visible</span>
    </a>
</div>
```

**Elementos Requeridos:**
- `nav-item`: Contenedor del item
- `nav-link`: Enlace con `href`, `data-page`, `aria-label`
- `nav-icon`: Icono SVG con `data-icon`
- `nav-text`: Texto visible con `data-i18n` para traducción

---

## 📝 Casos de Uso Comunes

### 1. Agregar Item Simple a Sección Existente

**Ejemplo:** Agregar "Reportes Avanzados" a la subsección "Reportes y Control"

```html
<!-- En frontend/components/sidebar.html, dentro de .nav-subsection[data-nav-group="reports-control"] -->
<div class="nav-item">
    <a href="/advanced-reports" 
       class="nav-link" 
       data-page="advanced-reports" 
       aria-label="Ir a Reportes Avanzados">
        <span class="nav-icon icon icon-md" data-icon="dashboard" aria-hidden="true"></span>
        <span class="nav-text" data-i18n="nav.advanced_reports">Reportes Avanzados</span>
    </a>
</div>
```

**Agregar traducción:**
```php
// En backend/lang/es/nav.php
return [
    // ... traducciones existentes
    'advanced_reports' => 'Reportes Avanzados',
];
```

---

### 2. Crear Nueva Subsección

**Ejemplo:** Crear subsección "Analytics" en Empresa Section

```html
<!-- En frontend/components/sidebar.html, dentro de #empresa-section -->
<div class="nav-subsection" data-nav-group="analytics">
    <button class="nav-subsection-title nav-subsection-title--toggle" 
            type="button" 
            aria-expanded="true" 
            aria-label="Expandir o colapsar sección Analytics"
            data-i18n="nav.analytics">
        <span class="nav-subsection-chevron icon icon-sm" data-icon="chevronDown" aria-hidden="true"></span>
        <span class="nav-subsection-title-text">Analytics</span>
    </button>
    <div class="nav-subsection-content">
        <div class="nav-item">
            <a href="/analytics/dashboard" 
               class="nav-link" 
               data-page="analytics-dashboard" 
               aria-label="Ir a Dashboard de Analytics">
                <span class="nav-icon icon icon-md" data-icon="dashboard" aria-hidden="true"></span>
                <span class="nav-text" data-i18n="nav.analytics_dashboard">Dashboard</span>
            </a>
        </div>
        <!-- Más items... -->
    </div>
</div>
```

**Agregar traducciones:**
```php
// En backend/lang/es/nav.php
return [
    'analytics' => 'Analytics',
    'analytics_dashboard' => 'Dashboard',
];
```

---

### 3. Agregar Badge de Notificación

**Ejemplo:** Agregar badge con contador de notificaciones pendientes

```javascript
// En tu archivo JS (ej: notifications.js)
function updateNotificationsBadge() {
    // Obtener contador desde API
    API.get('/notifications/pending-count')
        .then(response => {
            if (response.success) {
                const count = response.data.count;
                
                // Actualizar badge en navegación
                if (typeof updateNavItemBadge !== 'undefined') {
                    updateNavItemBadge('notifications', count);
                }
            }
        });
}

// Actualizar periódicamente
setInterval(updateNotificationsBadge, 30000); // Cada 30 segundos
```

**El badge se muestra automáticamente** si el contador > 0.

---

### 4. Marcar Item como Activo

**Ejemplo:** Marcar item activo según la página actual

```javascript
// En layout.js (ya implementado)
function setActiveNavItem() {
    const currentPage = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        const linkPage = link.getAttribute('data-page');
        const linkHref = link.getAttribute('href');
        
        // Comparar pathname con href o data-page
        if (currentPage === linkHref || currentPage.includes(linkPage)) {
            link.classList.add('active');
            link.setAttribute('aria-current', 'page');
        } else {
            link.classList.remove('active');
            link.removeAttribute('aria-current');
        }
    });
}
```

---

### 5. Agregar Estado de Loading

**Ejemplo:** Mostrar spinner mientras carga una página

```javascript
// Al iniciar navegación
function navigateToPage(url) {
    const navLink = document.querySelector(`[href="${url}"]`);
    
    if (navLink && typeof setNavItemLoading !== 'undefined') {
        setNavItemLoading(navLink);
    }
    
    // Navegar
    window.location.href = url;
}

// Al terminar de cargar (en la nueva página)
window.addEventListener('load', () => {
    const navLinks = document.querySelectorAll('.nav-link.loading');
    navLinks.forEach(link => {
        if (typeof removeNavItemLoading !== 'undefined') {
            removeNavItemLoading(link);
        }
    });
});
```

---

### 6. Deshabilitar Item de Navegación

**Ejemplo:** Deshabilitar item si no hay permisos

```javascript
function disableNavItem(pageName) {
    const navLink = document.querySelector(`[data-page="${pageName}"]`);
    if (navLink) {
        navLink.classList.add('disabled');
        navLink.setAttribute('aria-disabled', 'true');
        navLink.style.pointerEvents = 'none';
    }
}

// Ejemplo de uso
if (!hasPermission('view_reports')) {
    disableNavItem('reports');
}
```

---

### 7. Crear Mega Menu Personalizado

**Ejemplo:** Mega menu para sección con muchos items

```javascript
// En tu archivo JS
function initCustomMegaMenu() {
    const trigger = document.querySelector('.nav-section-title--custom-trigger');
    
    if (!trigger || typeof MegaMenu === 'undefined') {
        return;
    }
    
    const megaMenu = new MegaMenu(trigger, {
        items: [
            {
                title: 'Dashboard',
                icon: 'dashboard',
                href: '/dashboard',
                description: 'Vista general del sistema'
            },
            {
                title: 'Reportes',
                icon: 'document',
                href: '/reports',
                description: 'Generar y ver reportes'
            },
            // Más items...
        ],
        columns: 3,
        quickActions: [
            {
                title: 'Nuevo Reporte',
                icon: 'add',
                onClick: () => {
                    window.location.href = '/reports/create';
                },
                quickAction: true
            }
        ]
    });
}
```

---

### 8. Personalizar Iconos

**Agregar nuevo icono:**

```javascript
// En frontend/js/core/icon-helper.js
static icons = {
    // ... iconos existentes
    nuevoIcono: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="..."></path>
    </svg>`,
};
```

**Usar icono personalizado:**

```html
<span class="nav-icon icon icon-md" data-icon="nuevoIcono" aria-hidden="true"></span>
```

---

### 9. Integrar con Sistema de Permisos RBAC

**Ejemplo:** Mostrar/ocultar items según permisos

```javascript
// En layout.js o en página específica
async function filterNavigationByPermissions() {
    // Obtener permisos del usuario
    const response = await API.get('/users/me/permissions');
    if (!response.success) return;
    
    const permissions = response.data.permissions;
    
    // Ocultar items sin permisos
    const navLinks = document.querySelectorAll('.nav-link[data-permission]');
    navLinks.forEach(link => {
        const requiredPermission = link.getAttribute('data-permission');
        if (!permissions.includes(requiredPermission)) {
            link.closest('.nav-item').style.display = 'none';
        }
    });
}
```

**En HTML:**
```html
<a href="/reports" 
   class="nav-link" 
   data-page="reports"
   data-permission="view_reports"
   aria-label="Ir a Reportes">
    <!-- ... -->
</a>
```

---

### 10. Agregar Breadcrumb Dinámicamente

**Ejemplo:** Actualizar breadcrumb desde JavaScript

```javascript
// En tu archivo JS
function updateBreadcrumb(items) {
    // items = [{ label: 'Dashboard', href: '/dashboard' }, { label: 'Página Actual', href: null }]
    
    if (typeof updateBreadcrumb !== 'undefined') {
        // Función ya implementada en layout.js
        updateBreadcrumb(items);
    } else {
        // Implementación manual
        const breadcrumbList = document.querySelector('.breadcrumb-nav__list');
        if (!breadcrumbList) return;
        
        breadcrumbList.innerHTML = '';
        items.forEach((item, index) => {
            const li = document.createElement('li');
            li.className = 'breadcrumb-nav__item';
            
            if (index === items.length - 1) {
                // Último item (no clickeable)
                const span = document.createElement('span');
                span.className = 'breadcrumb-nav__current';
                span.setAttribute('aria-current', 'page');
                span.textContent = item.label;
                li.appendChild(span);
            } else {
                // Items clickeables
                const a = document.createElement('a');
                a.href = item.href || '#';
                a.className = 'breadcrumb-nav__link';
                a.textContent = item.label;
                li.appendChild(a);
            }
            
            breadcrumbList.appendChild(li);
        });
    }
}

// Ejemplo de uso
updateBreadcrumb([
    { label: 'Dashboard', href: '/dashboard' },
    { label: 'Reportes', href: '/reports' },
    { label: 'Reporte Detallado', href: null }
]);
```

---

## 🔧 Funciones Disponibles

### Funciones Globales (layout.js)

```javascript
// Marcar item activo
setActiveNavItem();

// Inicializar secciones colapsables
initCollapsibleSections();

// Inicializar badges de notificación
initNavigationBadges();

// Actualizar badge de un item específico
updateNavItemBadge('approvals', 5);

// Agregar estado de loading
setNavItemLoading(navLinkElement);

// Remover estado de loading
removeNavItemLoading(navLinkElement);

// Actualizar breadcrumb
updateBreadcrumb([{ label: '...', href: '...' }]);

// Inicializar mega menus
initMegaMenus();
```

### IconHelper

```javascript
// Obtener HTML de icono
const iconHtml = IconHelper.get('dashboard', { size: 'md' });

// Procesar iconos en contenedor
IconHelper.processDataIcons('#main-navigation', { lazy: false });

// Procesar con lazy loading
IconHelper.processDataIcons('.nav-subsection', { lazy: true });
```

### MegaMenu

```javascript
// Crear nuevo mega menu
const megaMenu = new MegaMenu(triggerElement, {
    items: [...],
    columns: 3,
    position: 'bottom'
});

// Mostrar/ocultar
megaMenu.show();
megaMenu.hide();

// Actualizar items
megaMenu.updateItems(newItems);

// Destruir
megaMenu.destroy();
```

---

## 📋 Checklist para Nuevos Items

Al agregar un nuevo item de navegación, verifica:

- [ ] HTML con estructura correcta (`nav-item` > `nav-link`)
- [ ] `href` apunta a la página correcta
- [ ] `data-page` coincide con el nombre de la página
- [ ] `aria-label` descriptivo y claro
- [ ] Icono SVG agregado (`data-icon` con nombre válido)
- [ ] Texto con `data-i18n` para traducción
- [ ] Traducción agregada en `backend/lang/es/nav.php`
- [ ] Icono agregado en `IconHelper.icons` si no existe
- [ ] Item visible según rol (si aplica)
- [ ] Permisos RBAC verificados (si aplica)
- [ ] Probado en móvil y desktop
- [ ] Accesibilidad verificada (keyboard navigation, screen reader)

---

## 🐛 Troubleshooting

### El icono no aparece

**Solución:**
1. Verificar que el nombre del icono existe en `IconHelper.icons`
2. Verificar que `IconHelper.processDataIcons()` se ejecutó
3. Verificar que el elemento tiene `data-icon` attribute

### La subsección no se colapsa

**Solución:**
1. Verificar que tiene `data-nav-group` attribute
2. Verificar que `initCollapsibleSections()` se ejecutó
3. Verificar que el toggle button tiene clase `nav-subsection-title--toggle`

### El badge no se actualiza

**Solución:**
1. Verificar que `initNavigationBadges()` se ejecutó
2. Verificar que la función `updateNavItemBadge()` está disponible
3. Verificar que el `data-page` coincide con el usado en `updateNavItemBadge()`

### El item no se marca como activo

**Solución:**
1. Verificar que `setActiveNavItem()` se ejecutó
2. Verificar que `data-page` o `href` coincide con la URL actual
3. Verificar que la clase `active` se aplica correctamente

---

## 📚 Referencias

- **Estructura de Navegación**: `docs/NAVIGATION_GUIDE.md`
- **Componente Sidebar**: `frontend/components/sidebar.html`
- **JavaScript Layout**: `frontend/js/layout.js`
- **Icon Helper**: `frontend/js/core/icon-helper.js`
- **Mega Menu**: `frontend/js/components/mega-menu.js`
- **Estilos CSS**: `frontend/css/05-objects/_layout.css`
- **Roadmap**: `docs/TODO_ROADMAP_COMPLETO.md`

---

**Última actualización**: 2025-01-XX
**Responsable**: Equipo de Desarrollo Workee

