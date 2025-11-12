# Guía de Arquitectura CSS - Workee

## Introducción

Esta guía documenta la arquitectura CSS del sistema Workee, basada en **ITCSS (Inverted Triangle CSS)** y **Design Tokens**, siguiendo las mejores prácticas de escalabilidad y mantenibilidad.

## Estructura ITCSS

El CSS está organizado en **7 capas** según ITCSS, de especificidad creciente:

```
frontend/css/
├── 01-settings/        # Variables CSS, design tokens (sin CSS real)
│   └── theme.css
├── 02-tools/          # Mixins, funciones (vacío, no usamos preprocesador)
├── 03-generic/        # Reset, normalize (sin clases)
│   └── _reset.css
├── 04-elements/       # Elementos HTML sin clases (h1, p, a, etc.)
│   ├── _typography.css
│   ├── _forms.css
│   ├── _tables.css
│   ├── _images.css
│   └── _accessibility.css
├── 05-objects/        # Patrones de layout reutilizables
│   ├── _layout.css
│   ├── _common.css
│   └── _dashboard-grid.css
├── 06-components/     # Componentes UI específicos
│   ├── components.css (maestro)
│   ├── _toast.css
│   ├── _modal.css
│   ├── _data-table.css
│   ├── _empty-state.css
│   ├── _loading-spinner.css
│   ├── _form-validator.css
│   └── _modals.css
├── 07-utilities/      # Clases utilitarias (helpers, overrides)
│   ├── utilities.css (maestro)
│   ├── _spacing.css
│   ├── _text.css
│   ├── _display.css
│   ├── _sizing.css
│   ├── _border.css
│   ├── _background.css
│   ├── _shadow.css
│   ├── _opacity.css
│   ├── _cursor.css
│   └── _user-select.css
└── pages/             # Estilos específicos por página (mínimos)
    ├── calendar.css
    ├── approvals.css
    ├── assignments.css
    └── ...
```

### Orden de Importación

El archivo `main.css` importa todas las capas en el orden correcto:

```css
/* 01: Settings */
@import url('01-settings/theme.css');

/* 03: Generic */
@import url('03-generic/_reset.css');

/* 04: Elements */
@import url('04-elements/_typography.css');
@import url('04-elements/_forms.css');
@import url('04-elements/_tables.css');
@import url('04-elements/_images.css');
@import url('04-elements/_accessibility.css');

/* 05: Objects */
@import url('05-objects/_layout.css');
@import url('05-objects/_common.css');
@import url('05-objects/_dashboard-grid.css');

/* 06: Components */
@import url('06-components/components.css');

/* 07: Utilities */
@import url('07-utilities/utilities.css');
```

## Design Tokens

Todos los valores de diseño están definidos como **variables CSS** en `01-settings/theme.css`.

### Categorías de Tokens

#### 1. Colores Corporativos

```css
:root {
    /* Colores primarios */
    --color-primary: #0066cc;
    --color-primary-dark: #0052a3;
    --color-primary-light: #3385d6;
    
    /* Colores de estado */
    --color-success: #28a745;
    --color-warning: #ffc107;
    --color-danger: #dc3545;
    --color-info: #17a2b8;
    
    /* Colores neutros */
    --color-text: #212529;
    --color-text-muted: #6c757d;
    --color-bg: #ffffff;
    --color-bg-light: #f8f9fa;
    --color-border: #dee2e6;
}
```

#### 2. Tipografía

```css
:root {
    --font-family-base: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    --font-size-xs: 0.75rem;
    --font-size-sm: 0.875rem;
    --font-size-base: 1rem;
    --font-size-lg: 1.125rem;
    --font-size-xl: 1.25rem;
    --font-size-xxl: 1.5rem;
    --font-size-xxxl: 2rem;
    
    --font-weight-normal: 400;
    --font-weight-medium: 500;
    --font-weight-semibold: 600;
    --font-weight-bold: 700;
    
    --line-height-base: 1.5;
    --line-height-sm: 1.25;
    --line-height-lg: 1.75;
}
```

#### 3. Espaciado

```css
:root {
    --spacing-xs: 0.25rem;   /* 4px */
    --spacing-sm: 0.5rem;    /* 8px */
    --spacing-md: 1rem;      /* 16px */
    --spacing-lg: 1.5rem;    /* 24px */
    --spacing-xl: 2rem;      /* 32px */
    --spacing-xxl: 3rem;     /* 48px */
    --spacing-xxxl: 4rem;    /* 64px */
}
```

#### 4. Sombras

```css
:root {
    --shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.05);
    --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
    --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
    --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.15);
    --shadow-inner: inset 0 2px 4px rgba(0, 0, 0, 0.06);
}
```

#### 5. Transiciones

```css
:root {
    --transition-fast: 150ms ease;
    --transition-base: 250ms ease;
    --transition-slow: 350ms ease;
    --transition-all: all var(--transition-base);
}
```

#### 6. Z-Index (Sistema de Capas)

```css
:root {
    --z-base: 1;
    --z-dropdown: 1000;
    --z-sticky: 1020;
    --z-fixed: 1030;
    --z-modal-backdrop: 1040;
    --z-modal: 1050;
    --z-popover: 1060;
    --z-tooltip: 1070;
}
```

#### 7. Layout

```css
:root {
    --sidebar-width: 260px;
    --sidebar-collapsed-width: 80px;
    --header-height: 64px;
    --content-padding: var(--spacing-lg);
    --card-padding: var(--spacing-md);
}
```

## Convenciones de Nomenclatura

### BEM (Block Element Modifier)

**Formato:** `.block__element--modifier`

**Ejemplos:**
- `.dashboard-card` (block)
- `.dashboard-card__title` (element)
- `.dashboard-card--highlighted` (modifier)

### Prefijos ITCSS

- **Objects:** `.o-container`, `.o-grid` (opcional, no siempre usado)
- **Components:** Sin prefijo o `.c-*` (`.card`, `.modal`, `.toast`)
- **Utilities:** `.u-*` (`.u-text-center`, `.u-mt-md`)

## Reglas Obligatorias

### 1. NUNCA Hardcodear Valores

```css
/* ❌ INCORRECTO */
.card {
    padding: 16px;
    color: #212529;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* ✅ CORRECTO */
.card {
    padding: var(--spacing-md);
    color: var(--color-text);
    box-shadow: var(--shadow-sm);
}
```

### 2. Usar Design Tokens

**TODOS los valores deben venir de tokens:**
- Colores → `var(--color-*)`
- Espaciados → `var(--spacing-*)`
- Sombras → `var(--shadow-*)`
- Transiciones → `var(--transition-*)`
- Z-index → `var(--z-*)`
- Tipografía → `var(--font-*)`

### 3. Aplicar BEM Consistentemente

```css
/* ✅ CORRECTO - BEM */
.toast {
    /* block */
}

.toast__content {
    /* element */
}

.toast--success {
    /* modifier */
}

/* ❌ INCORRECTO - Sin BEM */
.toast-content {
    /* no sigue BEM */
}
```

### 4. Mobile-First Obligatorio

```css
/* ✅ CORRECTO - Mobile-first */
.card {
    padding: var(--spacing-sm); /* Mobile */
}

@media (min-width: 768px) {
    .card {
        padding: var(--spacing-md); /* Desktop */
    }
}

/* ❌ INCORRECTO - Desktop-first */
.card {
    padding: var(--spacing-md);
}

@media (max-width: 767px) {
    .card {
        padding: var(--spacing-sm);
    }
}
```

### 5. Máximo 3 Niveles de Anidamiento

```css
/* ✅ CORRECTO - 3 niveles máximo */
.card {
    .card__header {
        .card__title { }
    }
}

/* ❌ INCORRECTO - Demasiado anidado */
.page .section .container .card .header .title { }
```

### 6. Especificidad Baja

```css
/* ✅ CORRECTO - Especificidad baja */
.btn-primary { }

/* ❌ INCORRECTO - Especificidad alta */
#main-content .sidebar .btn.primary { }
```

## Uso de Utilidades

Las utilidades (Layer 07) usan prefijo `.u-` y `!important`:

```html
<!-- Espaciado -->
<div class="u-mt-md u-px-lg">Contenido</div>

<!-- Texto -->
<p class="u-text-center u-text-bold u-text-primary">Texto</p>

<!-- Display -->
<div class="u-flex u-justify-between u-items-center">Layout</div>

<!-- Sizing -->
<div class="u-w-full u-max-w-screen-lg">Contenedor</div>
```

## Estilos por Página

Los estilos específicos por página van en `pages/` y deben ser **mínimos**:

```css
/* ✅ CORRECTO - Mínimo, usando tokens */
.calendar-page {
    padding: var(--spacing-lg);
}

/* ❌ INCORRECTO - Mucho código específico */
.calendar-page {
    /* 500 líneas de CSS específico */
}
```

## Accesibilidad

### Focus Visible Obligatorio

```css
/* ✅ CORRECTO */
button:focus-visible {
    outline: var(--outline-width) solid var(--color-primary);
    outline-offset: var(--outline-offset);
}

/* ❌ INCORRECTO */
button:focus {
    outline: none; /* Sin reemplazo */
}
```

### Prefers-Reduced-Motion

```css
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
}
```

## Responsive Design

### Breakpoints Estándar

```css
/* Mobile: < 576px (default, sin prefijo) */
/* Tablet: ≥ 576px (sm) */
/* Desktop: ≥ 768px (md) */
/* Large Desktop: ≥ 992px (lg) */
/* XL Desktop: ≥ 1200px (xl) */

@media (min-width: 768px) {
    /* Tablet y Desktop */
}

@media (min-width: 992px) {
    /* Desktop */
}
```

## Imágenes Responsivas

Ver guía completa en `docs/GUIA_IMAGENES_RESPONSIVAS.md`.

**Reglas obligatorias:**
- `loading="lazy"` en imágenes no críticas
- `aspect-ratio` definido para evitar CLS
- `srcset` y `sizes` para imágenes grandes
- `<picture>` con WebP y fallback

## Validación

### Stylelint

Configuración en `.stylelintrc` (ver sección de configuración).

### Checklist Antes de Commit

- [ ] No hay colores hardcodeados (excepto en `theme.css`)
- [ ] No hay valores hardcodeados (usar tokens)
- [ ] BEM aplicado correctamente
- [ ] Mobile-first verificado
- [ ] Máximo 3 niveles de anidamiento
- [ ] Focus visible implementado
- [ ] Accesibilidad WCAG AA verificada

## Referencias

- [ITCSS Documentation](https://www.xfive.co/blog/itcss-scalable-maintainable-css-architecture/)
- [BEM Methodology](http://getbem.com/)
- [Design Tokens](https://www.designtokens.org/)
- [WCAG Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)

