# Guía de Design Tokens - Workee

## Introducción

Los **Design Tokens** son valores de diseño centralizados que definen la apariencia visual del sistema. Todos los valores (colores, espaciados, sombras, etc.) están definidos como variables CSS en `frontend/css/01-settings/theme.css`.

## Regla Fundamental

**NUNCA hardcodear valores directamente en CSS. SIEMPRE usar design tokens.**

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

## Categorías de Tokens

### 1. Colores

#### Colores Primarios

```css
--color-primary: #0066cc;
--color-primary-dark: #0052a3;
--color-primary-light: #3385d6;
--color-primary-rgb: 0, 102, 204;
```

**Uso:**
```css
.btn-primary {
    background-color: var(--color-primary);
    color: var(--color-text-inverse);
}

.btn-primary:hover {
    background-color: var(--color-primary-dark);
}
```

#### Colores de Estado

```css
--color-success: #28a745;
--color-success-dark: #218838;
--color-success-light: #34ce57;

--color-warning: #ffc107;
--color-warning-dark: #e0a800;
--color-warning-light: #ffcd39;

--color-danger: #dc3545;
--color-danger-dark: #c82333;
--color-danger-light: #e4606d;

--color-info: #17a2b8;
--color-info-dark: #138496;
--color-info-light: #3ab0c3;
```

**Uso:**
```css
.alert-success {
    background-color: var(--color-success);
    color: var(--color-text-inverse);
}

.badge-warning {
    background-color: var(--color-warning);
    color: var(--color-text); /* Texto oscuro para contraste */
}
```

#### Colores Neutros

```css
--color-text: #212529;
--color-text-muted: #6c757d;
--color-text-light: #868e96;
--color-text-inverse: #ffffff;

--color-bg: #ffffff;
--color-bg-light: #f8f9fa;
--color-bg-dark: #e9ecef;

--color-border: #dee2e6;
--color-border-light: #e9ecef;
--color-border-dark: #adb5bd;
```

**Uso:**
```css
body {
    background-color: var(--color-bg);
    color: var(--color-text);
}

.text-muted {
    color: var(--color-text-muted);
}

.card {
    background-color: var(--color-bg);
    border: var(--border-width) solid var(--color-border);
}
```

### 2. Tipografía

#### Familias de Fuente

```css
--font-family-base: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
--font-family-heading: var(--font-family-base);
--font-family-monospace: "SFMono-Regular", Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
```

#### Tamaños de Fuente

```css
--font-size-xs: 0.75rem;    /* 12px */
--font-size-sm: 0.875rem;   /* 14px */
--font-size-base: 1rem;     /* 16px */
--font-size-lg: 1.125rem;   /* 18px */
--font-size-xl: 1.25rem;    /* 20px */
--font-size-xxl: 1.5rem;    /* 24px */
--font-size-xxxl: 2rem;     /* 32px */
```

**Uso:**
```css
h1 {
    font-size: var(--font-size-xxxl);
}

.small-text {
    font-size: var(--font-size-sm);
}
```

#### Pesos de Fuente

```css
--font-weight-normal: 400;
--font-weight-medium: 500;
--font-weight-semibold: 600;
--font-weight-bold: 700;
```

**Uso:**
```css
.title {
    font-weight: var(--font-weight-bold);
}

.subtitle {
    font-weight: var(--font-weight-semibold);
}
```

#### Line Height

```css
--line-height-base: 1.5;
--line-height-sm: 1.25;
--line-height-lg: 1.75;
```

### 3. Espaciado

```css
--spacing-xs: 0.25rem;   /* 4px */
--spacing-sm: 0.5rem;    /* 8px */
--spacing-md: 1rem;      /* 16px */
--spacing-lg: 1.5rem;    /* 24px */
--spacing-xl: 2rem;      /* 32px */
--spacing-xxl: 3rem;     /* 48px */
--spacing-xxxl: 4rem;    /* 64px */
```

**Uso:**
```css
.card {
    padding: var(--spacing-md);
    margin-bottom: var(--spacing-lg);
}

.section {
    padding: var(--spacing-xl) 0;
}
```

### 4. Sombras

```css
--shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.05);
--shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
--shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
--shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
--shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.15);
--shadow-inner: inset 0 2px 4px rgba(0, 0, 0, 0.06);
```

**Uso:**
```css
.card {
    box-shadow: var(--shadow-sm);
}

.card:hover {
    box-shadow: var(--shadow-md);
}

.modal {
    box-shadow: var(--shadow-xl);
}
```

### 5. Transiciones

```css
--transition-fast: 150ms ease;
--transition-base: 250ms ease;
--transition-slow: 350ms ease;
--transition-all: all var(--transition-base);
```

**Uso:**
```css
.button {
    transition: var(--transition-all);
}

.card {
    transition: transform var(--transition-base), box-shadow var(--transition-base);
}
```

### 6. Z-Index (Sistema de Capas)

```css
--z-base: 1;
--z-dropdown: 1000;
--z-sticky: 1020;
--z-fixed: 1030;
--z-modal-backdrop: 1040;
--z-modal: 1050;
--z-popover: 1060;
--z-tooltip: 1070;
```

**Uso:**
```css
.modal-backdrop {
    z-index: var(--z-modal-backdrop);
}

.modal {
    z-index: var(--z-modal);
}

.tooltip {
    z-index: var(--z-tooltip);
}
```

### 7. Opacidad

```css
--opacity-disabled: 0.6;
--opacity-hover: 0.8;
--opacity-overlay: 0.5;
--opacity-spinner-bg: 0.9;
```

**Uso:**
```css
.button:disabled {
    opacity: var(--opacity-disabled);
}

.overlay {
    background-color: rgba(0, 0, 0, var(--opacity-overlay));
}
```

### 8. Layout

```css
--sidebar-width: 260px;
--sidebar-collapsed-width: 80px;
--header-height: 64px;
--content-padding: var(--spacing-lg);
--card-padding: var(--spacing-md);
```

**Uso:**
```css
.sidebar {
    width: var(--sidebar-width);
}

.header {
    height: var(--header-height);
}

.main-content {
    padding: var(--content-padding);
}
```

### 9. Bordes

```css
--border-width: 1px;
--border-width-thick: 2px;
--border-width-thicker: 3px;

--border-radius: 0.375rem;
--border-radius-sm: 0.25rem;
--border-radius-lg: 0.5rem;
```

**Uso:**
```css
.card {
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--border-radius-lg);
}

.input {
    border: var(--border-width-thick) solid var(--color-border);
    border-radius: var(--border-radius);
}
```

### 10. Outline (Focus)

```css
--outline-width: 2px;
--outline-offset: 2px;
```

**Uso:**
```css
button:focus-visible {
    outline: var(--outline-width) solid var(--color-primary);
    outline-offset: var(--outline-offset);
}
```

## Tokens RGB para RGBA

Algunos tokens incluyen versión RGB para usar con `rgba()`:

```css
--color-primary-rgb: 0, 102, 204;
--color-success-rgb: 40, 167, 69;
--color-danger-rgb: 220, 53, 69;
```

**Uso:**
```css
.button {
    box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.1);
}
```

## Tokens Específicos por Componente

Algunos componentes tienen tokens específicos:

```css
/* Toast */
--toast-container-max-width: 400px;
--toast-min-width: 300px;

/* Empty State */
--empty-state-min-height: 300px;
--empty-state-max-width: 400px;
--empty-state-icon-size: 4rem;

/* Modal */
--modal-footer-btn-min-width: 100px;
```

## Tokens Específicos por Página

Algunas páginas tienen tokens específicos:

```css
/* Calendar */
--calendar-ot-min-height: 600px;
--calendar-ot-min-height-mobile: 400px;

/* Work Orders */
--work-order-daily-report-max-width: 1200px;

/* Onboarding */
--onboarding-card-max-width: 600px;
--step-content-min-height: 300px;
```

## Cómo Agregar Nuevos Tokens

1. **Identificar el valor** que se repite o necesita centralizarse
2. **Crear el token** en `frontend/css/01-settings/theme.css`
3. **Nombrar descriptivamente**: `--categoria-propiedad-variante`
4. **Usar el token** en lugar del valor hardcodeado
5. **Documentar** el propósito del token en comentario

**Ejemplo:**
```css
/* En theme.css */
:root {
    /* Nuevo token */
    --button-icon-gap: var(--spacing-sm); /* Espacio entre icono y texto en botones */
}

/* Uso */
.btn-icon {
    gap: var(--button-icon-gap);
}
```

## Verificación

### Checklist

- [ ] No hay valores hardcodeados (excepto en `theme.css`)
- [ ] Todos los colores usan `var(--color-*)`
- [ ] Todos los espaciados usan `var(--spacing-*)`
- [ ] Todas las sombras usan `var(--shadow-*)`
- [ ] Todas las transiciones usan `var(--transition-*)`
- [ ] Todos los z-index usan `var(--z-*)`

### Script de Verificación

Ejecutar script para buscar valores hardcodeados:

```bash
php scripts/dev/find_hardcoded_colors.php
```

## Referencias

- [Design Tokens Community Group](https://www.designtokens.org/)
- [W3C Design Tokens](https://www.w3.org/community/design-tokens/)

