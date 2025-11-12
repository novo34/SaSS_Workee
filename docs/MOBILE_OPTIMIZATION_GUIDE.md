# Guía de Optimización Móvil - Workee

**Sprint 5.10: Optimización Móvil SaaS (Mejores Prácticas 2025)**

Esta guía documenta todas las optimizaciones móviles implementadas en el sistema Workee y cómo utilizarlas.

---

## 📱 Componentes Móviles Implementados

### 1. Swipe Gestures (`frontend/js/core/swipe-gestures.js`)

Helper reutilizable para manejar gestos de deslizamiento.

#### Uso Básico

```javascript
// Inicializar swipe gestures en un elemento
SwipeGestures.init(element, {
    onSwipeLeft: () => console.log('Swipe izquierda'),
    onSwipeRight: () => console.log('Swipe derecha'),
    onSwipeUp: () => console.log('Swipe arriba'),
    onSwipeDown: () => console.log('Swipe abajo'),
    threshold: 50 // Distancia mínima en px (default: 50)
});
```

#### Métodos Específicos

**Swipe en filas de tabla:**
```javascript
SwipeGestures.initTableRowSwipe(tableRow, {
    onSwipeLeft: () => {
        // Mostrar acciones (editar, eliminar)
    },
    onSwipeRight: () => {
        // Ocultar acciones
    }
});
```

**Swipe to dismiss:**
```javascript
SwipeGestures.initSwipeToDismiss(element, {
    onDismiss: () => {
        // Eliminar o ocultar elemento
    },
    direction: 'up' // 'up', 'down', 'left', 'right'
});
```

#### Integración Automática

- **Sidebar**: Swipe desde borde izquierdo para abrir, swipe derecho para cerrar (implementado en `layout.js`)
- **DataTable**: Swipe left/right en filas para acciones rápidas (opcional, activar con `swipeActions: true`)
- **Toast**: Swipe up para dismiss (implementado automáticamente)

---

### 2. Bottom Sheet (`frontend/js/components/bottom-sheet.js`)

Componente reutilizable para modales optimizados para móvil.

#### Uso Básico

```javascript
const bottomSheet = new BottomSheet({
    title: 'Título del Bottom Sheet',
    body: '<p>Contenido del bottom sheet</p>',
    footer: '<button class="btn btn-primary">Aceptar</button>',
    fullscreen: false, // true para fullscreen en móvil
    onClose: () => console.log('Cerrado')
});

// Mostrar
bottomSheet.show();

// Ocultar
bottomSheet.hide();

// Actualizar contenido
bottomSheet.update({
    body: '<p>Nuevo contenido</p>'
});

// Destruir
bottomSheet.destroy();
```

#### Características

- **Handle draggable**: Indicador visual para arrastrar
- **Swipe down to close**: Deslizar hacia abajo para cerrar
- **Backdrop blur**: Efecto glassmorphism
- **Animación suave**: Slide up desde bottom
- **Body scroll bloqueado**: Previene scroll del body cuando está abierto
- **Focus management**: Maneja focus automáticamente

#### Integración Automática

Los modales estándar se convierten automáticamente en bottom sheet en móvil si tienen `data-mobile-mode="bottom-sheet"`.

---

### 3. Floating Action Button (FAB) (`frontend/js/components/floating-action-button.js`)

Botón flotante para acción principal en móvil.

#### Uso Básico

```javascript
const fab = new FloatingActionButton({
    icon: 'plus', // Nombre del icono SVG
    label: 'Nueva Solicitud', // Opcional
    onClick: () => {
        // Acción principal
    },
    badge: 3, // Contador opcional
    position: 'bottom-right', // 'bottom-right', 'bottom-left', 'top-right', 'top-left'
    hideOnScroll: true // Ocultar al hacer scroll down, mostrar al scroll up
});

// Mostrar
fab.show();

// Ocultar
fab.hide();

// Actualizar badge
fab.updateBadge(5);

// Actualizar icono
fab.updateIcon('edit');

// Actualizar label
fab.updateLabel('Editar');

// Destruir
fab.destroy();
```

#### Características

- **Solo visible en móvil**: Automáticamente oculto en desktop (< 992px)
- **Posición fija**: Bottom-right por defecto
- **Badge opcional**: Contador de notificaciones o items
- **Animación suave**: Entrada y salida animadas
- **Hide on scroll**: Opcional, oculta al hacer scroll down

---

### 4. Pull to Refresh (`frontend/js/core/pull-to-refresh.js`)

Helper para implementar pull-to-refresh en listas y dashboards.

#### Uso Básico

```javascript
PullToRefresh.init(containerElement, {
    onRefresh: async () => {
        // Recargar datos
        await loadData();
    },
    threshold: 80, // Distancia mínima para activar (default: 80px)
    resistance: 2.5 // Resistencia al arrastrar (default: 2.5)
});
```

#### Características

- **Indicador visual**: Spinner y texto dinámico ("Pull to refresh", "Release to refresh", "Refreshing...")
- **Animación suave**: Transición suave del indicador
- **Solo móvil**: Automáticamente deshabilitado en desktop
- **Respeto de prefers-reduced-motion**: Respeta preferencias de accesibilidad

#### Integración Automática

- **DataTable**: Activado automáticamente con `pullToRefresh: true` (default en móvil)
- **Dashboard**: Implementado en `dashboard.js` para recargar datos completos

---

### 5. Form Mobile Optimizer (`frontend/js/core/form-mobile-optimizer.js`)

Helper para optimizar formularios automáticamente en móvil.

#### Uso

Se inicializa automáticamente en `UIInitializer.init()`. Optimiza:

- **Altura mínima de inputs**: 44px
- **Font-size mínimo**: 16px (evita zoom en iOS)
- **Inputmode apropiado**: `numeric`, `tel`, `email`, etc.
- **Autocomplete**: Atributos HTML5
- **Botones sticky**: En formularios largos
- **Date pickers nativos**: En móvil
- **Validación optimizada**: Sin bloquear teclado

---

### 6. Performance Mobile (`frontend/js/core/performance-mobile.js`)

Helper para optimizaciones de performance en móvil.

#### Métodos Disponibles

**Debounce:**
```javascript
const debouncedFunction = PerformanceMobile.debounce(() => {
    // Función a ejecutar
}, 300); // Delay en ms
```

**Throttle:**
```javascript
const throttledFunction = PerformanceMobile.throttle(() => {
    // Función a ejecutar
}, 100); // Intervalo en ms
```

**Cache de datos:**
```javascript
// Obtener datos cacheados
const cachedData = PerformanceMobile.getCachedData('dashboard', async () => {
    // Función para obtener datos si no están en cache
    return await fetchDashboardData();
}, 300000); // TTL en ms (5 minutos)

// Obtener lista cachead
const cachedList = PerformanceMobile.getCachedList('requests', async () => {
    return await fetchRequests();
}, 300000);
```

**Verificar lazy loading de imágenes:**
```javascript
PerformanceMobile.verifyImageLazyLoading();
```

#### Integración Automática

- **Scroll events**: Throttle automático en scroll
- **Dashboard**: Cache agresivo de datos
- **DataTable**: Cache de listas

---

### 7. Virtual Scroll (`frontend/js/core/virtual-scroll.js`)

Helper para virtual scrolling en listas largas.

#### Uso

```javascript
VirtualScroll.init(containerElement, {
    items: arrayOfItems, // Array de items a renderizar
    renderItem: (item) => {
        // Función para renderizar un item
        return `<div>${item.name}</div>`;
    },
    itemHeight: 50, // Altura de cada item en px
    buffer: 5 // Número de items extra a renderizar fuera del viewport
});
```

#### Integración Automática

- **DataTable**: Activado automáticamente para listas > 50 items en móvil con `virtualScroll: true`

---

### 8. Image Mobile Optimizer (`frontend/js/core/image-mobile-optimizer.js`)

Helper para optimizar imágenes y videos automáticamente en móvil.

#### Funcionalidades Automáticas

- **Srcset con tamaños móviles**: 400w, 800w, 1200w
- **WebP con fallback**: Conversión automática a WebP
- **Lazy loading mejorado**: Intersection Observer con rootMargin 50px
- **Aspect ratio**: Detección automática para evitar CLS
- **Videos optimizados**: Aspect ratio y object-fit automáticos

#### Uso Manual

```javascript
// Optimizar una imagen específica
ImageMobileOptimizer.optimizeImage(imgElement);

// Optimizar un video específico
ImageMobileOptimizer.optimizeVideo(videoElement);

// Optimizar todas las imágenes
ImageMobileOptimizer.optimizeAll();

// Optimizar todos los videos
ImageMobileOptimizer.optimizeVideos();
```

#### Integración Automática

Se inicializa automáticamente en `UIInitializer.init()` y observa nuevos elementos agregados dinámicamente.

---

## 🎨 Estilos CSS Móviles

### Archivos CSS Específicos para Móvil

1. **`_swipe-actions.css`**: Estilos para acciones de swipe en tablas
2. **`_bottom-sheet.css`**: Estilos para bottom sheets
3. **`_floating-action-button.css`**: Estilos para FAB
4. **`_pull-to-refresh.css`**: Estilos para pull-to-refresh
5. **`_dashboard-mobile.css`**: Optimizaciones de dashboards para móvil
6. **`_forms-mobile.css`**: Optimizaciones de formularios para móvil
7. **`_text-mobile.css`**: Optimizaciones de texto y contraste para móvil
8. **`_animations-mobile.css`**: Optimizaciones de animaciones para móvil
9. **`_images-mobile.css`**: Optimizaciones de imágenes y videos para móvil
10. **`_mobile-compliance.css`**: Reglas obligatorias (touch targets 44px, font-size 16px)

---

## 📋 Mejores Prácticas

### 1. Touch Targets

- **Mínimo 44x44px**: Todos los elementos interactivos
- **Espaciado mínimo 8px**: Entre elementos táctiles
- **Verificación**: Usar `MobileTestingVerification.verifyTouchTargets()`

### 2. Font Sizes

- **Mínimo 16px**: Para evitar zoom automático en iOS
- **Excepciones permitidas**: Badges (12px), texto pequeño (14px)
- **Verificación**: Usar `MobileTestingVerification.verifyFontSizes()`

### 3. Performance

- **Lazy loading**: Imágenes y videos fuera del viewport
- **Cache agresivo**: Datos de dashboard y listas
- **Virtual scrolling**: Para listas > 50 items
- **Debounce/Throttle**: En eventos frecuentes (scroll, resize, input)

### 4. Animaciones

- **Duración rápida**: 150-200ms en móvil
- **Transform y opacity**: Para mejor performance
- **Respetar prefers-reduced-motion**: Siempre

### 5. Formularios

- **Inputs grandes**: Mínimo 44px de altura
- **Font-size 16px**: Para evitar zoom
- **Teclado apropiado**: `inputmode` y `type` correctos
- **Botones sticky**: En formularios largos

---

## 🧪 Testing Móvil

### Script de Verificación Automática

```javascript
// Ejecutar verificación completa
MobileTestingVerification.runAll();

// Ver resultados
console.log(window.mobileTestingResults);
```

### Checklist Manual

Ver `docs/TESTING_MOVIL_COMPLETO.md` para checklist completo.

### Herramientas

- **Chrome DevTools**: Device Toolbar (F12 → Ctrl+Shift+M)
- **Lighthouse**: Mobile Audit
- **Script de verificación**: `scripts/dev/mobile-testing-verification.js`
- **Checklist HTML**: `scripts/dev/mobile-testing-checklist.html`

---

## 📚 Referencias

- **Testing Móvil**: `docs/TESTING_MOVIL_COMPLETO.md`
- **Cómo Ejecutar Testing**: `docs/COMO_EJECUTAR_TESTING_MOVIL.md`
- **Verificación Tab Order**: `docs/NAV_132_VERIFICACION_TAB_ORDER.md`
- **Roadmap**: `docs/TODO_ROADMAP_COMPLETO.md`

---

**Última actualización**: 2025-01-XX
**Responsable**: Equipo de Desarrollo Workee

