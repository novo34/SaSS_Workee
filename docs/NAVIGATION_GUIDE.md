# Guía de Navegación - Workee

**Sprint 5.9: Estructura de Navegación y Agrupación Lógica**

Esta guía documenta la estructura completa de navegación del sistema Workee, su organización lógica, y cómo está implementada.

---

## 📐 Estructura de Navegación

### Jerarquía de Navegación

```
Sidebar (app-sidebar)
├── Skip Link (accesibilidad)
├── Brand/Logo
└── Navigation (sidebar-nav)
    ├── Dashboard Section (sin título)
    │   └── Dashboard
    ├── Main Section (Principal)
    │   ├── Relaciones
    │   ├── Configuración
    │   ├── Notificaciones
    │   └── Importar/Exportar
    ├── Empresa Section (solo empresa_admin, planificador)
    │   ├── Planificación (subsección colapsable)
    │   │   ├── Calendario
    │   │   ├── Solicitudes de Personal
    │   │   ├── Constructor de Grupos
    │   │   └── Asignaciones
    │   ├── Órdenes de Trabajo (subsección colapsable)
    │   │   ├── Órdenes de Trabajo
    │   │   ├── Calendario OT
    │   │   └── Parte Diario OT
    │   ├── Reportes y Control (subsección colapsable)
    │   │   ├── Parte del Líder
    │   │   ├── Aprobaciones
    │   │   ├── Disputas
    │   │   ├── Reportes
    │   │   └── Valoraciones
    │   └── Recursos (subsección colapsable)
    │       ├── Empleados
    │       └── Fichaje
    ├── Temporera Section (solo temporera_admin, coordinador)
    │   ├── Propuestas de Candidatos
    │   └── Trabajadores Temporeros
    └── Owner Section (solo owner_admin)
        ├── Dashboard Owner
        └── Billing
```

---

## 🎯 Agrupación Lógica

### Principios de Agrupación

1. **Por Funcionalidad**: Items relacionados funcionalmente están agrupados
2. **Por Rol**: Secciones visibles según rol del usuario
3. **Por Frecuencia de Uso**: Items más usados aparecen primero
4. **Máximo 7-9 Items**: Por sección principal para evitar sobrecarga cognitiva

### Secciones Principales

#### 1. Dashboard Section
- **Propósito**: Acceso rápido al dashboard principal
- **Items**: 1 (Dashboard)
- **Visibilidad**: Todos los roles
- **Sin título**: Item único no necesita título de sección

#### 2. Main Section (Principal)
- **Propósito**: Funcionalidades comunes a todos los roles
- **Items**: 4 (Relaciones, Configuración, Notificaciones, Importar/Exportar)
- **Visibilidad**: Todos los roles
- **Agrupación**: Funcionalidades administrativas y de configuración

#### 3. Empresa Section
- **Propósito**: Funcionalidades específicas de empresas
- **Subsecciones**: 4
  - **Planificación**: Calendario, solicitudes, grupos, asignaciones
  - **Órdenes de Trabajo**: Gestión de OT, calendario, partes diarios
  - **Reportes y Control**: Partes, aprobaciones, disputas, reportes, valoraciones
  - **Recursos**: Empleados, fichaje
- **Visibilidad**: Solo `empresa_admin`, `planificador`
- **Agrupación**: Por flujo de trabajo (planificación → ejecución → control → recursos)

#### 4. Temporera Section
- **Propósito**: Funcionalidades específicas de temporeras
- **Items**: 2 (Propuestas, Trabajadores)
- **Visibilidad**: Solo `temporera_admin`, `coordinador`
- **Agrupación**: Funcionalidades de gestión de trabajadores y propuestas

#### 5. Owner Section
- **Propósito**: Funcionalidades de administración del SaaS
- **Items**: 2 (Dashboard Owner, Billing)
- **Visibilidad**: Solo `owner_admin`
- **Agrupación**: Administración y facturación

---

## 🏗️ Estructura HTML

### Componentes Base

#### Nav Section
```html
<div class="nav-section">
    <div class="nav-section-title" data-i18n="nav.section_name">Nombre Sección</div>
    <!-- Items de navegación -->
</div>
```

#### Nav Item
```html
<div class="nav-item">
    <a href="/page" class="nav-link" data-page="page-name" aria-label="Ir a Página">
        <span class="nav-icon icon icon-md" data-icon="iconName" aria-hidden="true"></span>
        <span class="nav-text" data-i18n="nav.page_name">Nombre Página</span>
    </a>
</div>
```

#### Nav Subsection (Colapsable)
```html
<div class="nav-subsection" data-nav-group="group-name">
    <button class="nav-subsection-title nav-subsection-title--toggle" 
            type="button" 
            aria-expanded="true" 
            aria-label="Expandir o colapsar sección Nombre">
        <span class="nav-subsection-chevron icon icon-sm" data-icon="chevronDown" aria-hidden="true"></span>
        <span class="nav-subsection-title-text">Nombre Subsección</span>
    </button>
    <div class="nav-subsection-content">
        <!-- Items de navegación -->
    </div>
</div>
```

---

## 🎨 Iconografía

### Sistema de Iconos SVG

El sistema usa `IconHelper` para iconos SVG consistentes.

#### Iconos Disponibles

**Acciones:**
- `add`, `edit`, `delete`, `view`, `save`, `cancel`

**Navegación:**
- `calendar`, `groups`, `settings`, `dashboard`

**Documentos:**
- `document`, `request`

**Estado:**
- `check`, `close`, `warning`, `info`

**Usuarios:**
- `user`, `users`

**Otros:**
- `search`, `filter`, `download`, `upload`, `refresh`
- `link`, `bell`, `pin`, `scale`, `star`, `clock`
- `briefcase`, `crown`, `money`, `menu`
- `chevronDown`, `chevronRight`

#### Uso de Iconos

**En HTML:**
```html
<span class="nav-icon icon icon-md" data-icon="dashboard" aria-hidden="true"></span>
```

**En JavaScript:**
```javascript
const iconHtml = IconHelper.get('dashboard', { size: 'md' });
element.innerHTML = iconHtml;
```

**Procesamiento Automático:**
```javascript
// Procesar todos los iconos en un contenedor
IconHelper.processDataIcons('#main-navigation', { lazy: false });

// Procesar con lazy loading (para subsecciones colapsadas)
IconHelper.processDataIcons('.nav-subsection[data-collapsed="true"]', { lazy: true });
```

---

## 🔧 Funcionalidades Implementadas

### 1. Secciones Colapsables (NAV-126)

**Características:**
- Estado guardado en `localStorage` (`nav-subsection-{group}`)
- Animación suave de expandir/colapsar
- Icono chevron cambia según estado (chevronDown/chevronRight)
- Lazy loading de iconos cuando se expande

**Implementación:**
- Función: `initCollapsibleSections()` en `layout.js`
- Atributo: `data-nav-group` identifica la subsección
- Estado: `data-collapsed="true|false"`

### 2. Sidebar Colapsable (NAV-127)

**Características:**
- Colapsa a 80px de ancho (solo iconos)
- Toggle button en header
- Tooltips dinámicos cuando está colapsado
- Estado guardado en `localStorage` (`sidebar-collapsed`)

**Implementación:**
- Función: `initSidebarCollapse()` en `layout.js`
- CSS: `.app-sidebar--collapsed` class

### 3. Estados Visuales (NAV-128)

**Estados Implementados:**
- **Activo**: Borde izquierdo 4px, fondo sutil, texto negrita
- **Hover**: Transform translateX(2px), fondo sutil, sombra
- **Loading**: Spinner animado
- **Disabled**: Opacidad reducida, cursor not-allowed
- **Badge**: Contador en color primario

**Clases CSS:**
- `.nav-link.active` - Item activo
- `.nav-link.loading` - Estado de carga
- `.nav-link.disabled` - Deshabilitado
- `.nav-badge` - Badge de notificación

### 4. Badges de Notificación (NAV-129)

**Características:**
- Actualización dinámica desde API
- Animación pulse al actualizar
- Formato "99+" para números grandes
- Actualización periódica cada 30 segundos

**Implementación:**
```javascript
// Actualizar badge de un item
updateNavItemBadge('approvals', 5);

// Actualización automática desde API
initNavigationBadges(); // En layout.js
```

### 5. Breadcrumbs (NAV-130)

**Estructura Semántica:**
```html
<nav aria-label="Breadcrumb">
    <ol class="breadcrumb-nav__list">
        <li class="breadcrumb-nav__item">
            <a href="/dashboard" class="breadcrumb-nav__link">Dashboard</a>
        </li>
        <li class="breadcrumb-nav__item">
            <span class="breadcrumb-nav__current" aria-current="page">Página Actual</span>
        </li>
    </ol>
</nav>
```

**Características:**
- Estructura semántica (`<nav>`, `<ol>`, `<li>`)
- Separadores modernos (`/`)
- Último item no clickeable
- Responsive (muestra solo últimos 3 niveles en móvil)

### 6. Navegación Responsive (NAV-131)

**Breakpoints:**
- **Mobile** (< 992px): Sidebar oculto, toggle visible, overlay
- **Tablet** (768px-991px): Sidebar colapsable, toggle visible
- **Desktop** (≥ 992px): Sidebar fijo, toggle opcional

**Gestos:**
- Swipe desde borde izquierdo para abrir
- Swipe derecho para cerrar
- Esc para cerrar en móvil

### 7. Navegación por Teclado (NAV-132)

**Atajos:**
- **Tab**: Navegar entre items
- **ArrowDown/ArrowUp**: Navegar entre items en sidebar
- **Home/End**: Ir al primer/último item
- **Enter/Space**: Activar botones de subsecciones
- **Esc**: Cerrar sidebar en móvil
- **S**: Activar skip link

**Focus Management:**
- Focus automático al primer item al abrir sidebar
- Focus vuelve al toggle al cerrar
- Focus visible claro con outline

### 8. Accesibilidad (NAV-133)

**ARIA:**
- `aria-label` en todos los items
- `aria-expanded` en botones colapsables
- `aria-current="page"` en item activo
- `aria-hidden="true"` en iconos decorativos

**Contraste:**
- WCAG AA mínimo (4.5:1)
- Soporte para `prefers-contrast: more`

**Screen Readers:**
- Estructura semántica completa
- Roles apropiados (`navigation`, `menu`, `menuitem`)

### 9. Mega Menu (NAV-134)

**Características:**
- Layout en columnas (máximo 3)
- Iconos grandes (48x48px)
- Descripciones opcionales
- Quick actions destacadas
- Trigger en hover (desktop) / click (móvil)

**Uso:**
```javascript
const megaMenu = new MegaMenu(triggerElement, {
    items: [
        { title: 'Item 1', icon: 'dashboard', href: '/page1', description: 'Descripción' },
        // Más items...
    ],
    columns: 3,
    position: 'bottom'
});
```

### 10. Performance (NAV-135)

**Optimizaciones:**
- Lazy loading de iconos en subsecciones colapsadas
- Cache de estado en localStorage
- Preload de páginas frecuentes (`<link rel="prefetch">`)

---

## 📱 Responsive Design

### Mobile (< 992px)
- Sidebar oculto por defecto
- Toggle button visible
- Overlay al abrir
- Swipe gestures habilitados
- Breadcrumbs muestran solo últimos 3 niveles

### Tablet (768px-991px)
- Sidebar colapsable
- Toggle button visible
- Sidebar puede expandirse/colapsarse

### Desktop (≥ 992px)
- Sidebar fijo
- Toggle opcional para colapsar
- Mega menu en hover
- Breadcrumbs completos

---

## 🎯 Mejores Prácticas

### Agregar Nuevo Item de Navegación

1. **Identificar la sección apropiada**
   - ¿Es común a todos los roles? → Main Section
   - ¿Es específico de empresa? → Empresa Section
   - ¿Es específico de temporera? → Temporera Section
   - ¿Es específico de owner? → Owner Section

2. **Identificar la subsección apropiada**
   - ¿Ya existe una subsección relacionada? → Agregar ahí
   - ¿Necesita nueva subsección? → Crear nueva con `data-nav-group`

3. **Agregar el HTML**
```html
<div class="nav-item">
    <a href="/new-page" class="nav-link" data-page="new-page" aria-label="Ir a Nueva Página">
        <span class="nav-icon icon icon-md" data-icon="iconName" aria-hidden="true"></span>
        <span class="nav-text" data-i18n="nav.new_page">Nueva Página</span>
    </a>
</div>
```

4. **Agregar traducción**
```php
// En backend/lang/es/nav.php
return [
    'new_page' => 'Nueva Página',
    // ...
];
```

5. **Agregar icono si no existe**
```javascript
// En frontend/js/core/icon-helper.js
static icons = {
    // ...
    iconName: `<svg>...</svg>`,
};
```

### Agregar Nueva Subsección

1. **Crear estructura HTML**
```html
<div class="nav-subsection" data-nav-group="new-group">
    <button class="nav-subsection-title nav-subsection-title--toggle" 
            type="button" 
            aria-expanded="true" 
            aria-label="Expandir o colapsar sección Nueva Subsección"
            data-i18n="nav.new_subsection">
        <span class="nav-subsection-chevron icon icon-sm" data-icon="chevronDown" aria-hidden="true"></span>
        <span class="nav-subsection-title-text">Nueva Subsección</span>
    </button>
    <div class="nav-subsection-content">
        <!-- Items aquí -->
    </div>
</div>
```

2. **Agregar traducción**
```php
// En backend/lang/es/nav.php
return [
    'new_subsection' => 'Nueva Subsección',
    // ...
];
```

3. **La funcionalidad colapsable se activa automáticamente** (ya implementada en `initCollapsibleSections()`)

---

## 🔍 Visibilidad por Rol

### Control de Visibilidad

Las secciones se muestran/ocultan según el rol del usuario:

```javascript
// En layout.js o en la página específica
function showNavigationByRole(userRole) {
    const empresaSection = document.getElementById('empresa-section');
    const temporeraSection = document.getElementById('temporera-section');
    const ownerSection = document.getElementById('owner-section');
    
    if (['empresa_admin', 'planificador'].includes(userRole)) {
        empresaSection.style.display = 'block';
        empresaSection.setAttribute('aria-hidden', 'false');
    }
    
    if (['temporera_admin', 'coordinador'].includes(userRole)) {
        temporeraSection.style.display = 'block';
        temporeraSection.setAttribute('aria-hidden', 'false');
    }
    
    if (userRole === 'owner_admin') {
        ownerSection.style.display = 'block';
        ownerSection.setAttribute('aria-hidden', 'false');
    }
}
```

---

## 📚 Referencias

- **Componente Sidebar**: `frontend/components/sidebar.html`
- **JavaScript Layout**: `frontend/js/layout.js`
- **Icon Helper**: `frontend/js/core/icon-helper.js`
- **Mega Menu**: `frontend/js/components/mega-menu.js`
- **Estilos CSS**: `frontend/css/05-objects/_layout.css`
- **Verificación Tab Order**: `scripts/dev/verify-tab-order.js`
- **Roadmap**: `docs/TODO_ROADMAP_COMPLETO.md`

---

**Última actualización**: 2025-01-XX
**Responsable**: Equipo de Desarrollo Workee

