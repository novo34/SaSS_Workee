# Prompt para Implementar Tareas Pendientes - Sprint 5.9 y 5.10

## Contexto
Necesito implementar todas las tareas pendientes de los Sprints 5.9 (Mejora de Navegación SaaS) y 5.10 (Optimización Móvil SaaS) según el roadmap `TODO_ROADMAP_COMPLETO.md` y las reglas de `.cursorrules`.

## Sprint 5.9 - Tareas Pendientes de Navegación

### ✅ NAV-128 - Completar Estados Visuales de Navegación - COMPLETADO

**IMPLEMENTADO:**
1. ✅ **Estilos para item activo** en `frontend/css/05-objects/_layout.css`:
   - ✅ Regla `.sidebar-nav .nav-link.active` agregada con:
     - ✅ `border-left-width: 4px` (no usar `--border-width-thicker` que es 3px)
     - ✅ `border-left-color: var(--color-primary)`
     - ✅ `background-color: rgba(var(--color-primary-rgb), 0.1)`
     - ✅ `font-weight: var(--font-weight-bold)`
   - ✅ Estilos existentes para `.nav-link.active .nav-icon` mantenidos y mejorados
   - ✅ Borde izquierdo de 4px visible y destacado

**Archivos modificados:**
- ✅ `frontend/css/05-objects/_layout.css` - Estilos para `.nav-link.active` agregados

**Criterios cumplidos:**
- ✅ Item activo tiene borde izquierdo de 4px en color primario
- ✅ Fondo sutil con rgba del color primario al 10%
- ✅ Texto en negrita para destacar el item activo
- ✅ Visible y claro visualmente

---

### ✅ NAV-130 - Mejorar Breadcrumbs con Estructura Semántica - COMPLETADO

**IMPLEMENTADO:**
1. ✅ **Estructura semántica** ya implementada:
   - ✅ Estructura con `<nav aria-label="breadcrumb">` y `<ol>` ya existe en `header.html`
   - ✅ Cada item es `<li>` con `<a>` para items clickeables
   - ✅ Último item es `<li>` con `<span>` (no clickeable)
   - ✅ Separadores implementados con `::after` pseudo-elemento estilizado

2. ✅ **Estilos modernos** mejorados en `frontend/css/05-objects/_layout.css`:
   - ✅ Separadores estilizados con `::after` (color, padding, opacidad)
   - ✅ Hover effects en items clickeables (color primario, fondo sutil)
   - ✅ Responsive: en móvil mostrar solo últimos 2-3 niveles con ellipsis

3. ✅ **JavaScript** en `frontend/js/layout.js`:
   - ✅ Función `updateBreadcrumb()` ya genera estructura semántica
   - ✅ Último item no es clickeable (usa `<span>` en lugar de `<a>`)

**Archivos modificados:**
- ✅ `frontend/css/05-objects/_layout.css` - Estilos de breadcrumbs mejorados (hover effects, separadores, responsive)
- ✅ `frontend/js/layout.js` - Función `updateBreadcrumb()` ya genera estructura semántica correcta
- ✅ `frontend/components/header.html` - Estructura semántica ya implementada

**Criterios cumplidos:**
- ✅ Estructura semántica con `<nav>`, `<ol>`, `<li>`
- ✅ Separadores modernos estilizados con `::after`
- ✅ Último item no clickeable (usa `<span>`)
- ✅ Responsive en móvil (mostrar solo últimos 3 niveles con ellipsis)
- ✅ Hover effects en items clickeables

---

### ✅ NAV-132 - Completar Navegación por Teclado - COMPLETADO

**IMPLEMENTADO:**
1. ✅ **Skip link** agregado en `frontend/components/sidebar.html`:
   - ✅ Agregado al inicio del sidebar (antes del brand):
     ```html
     <a href="#main-content" class="skip-link" tabindex="-1">Saltar a contenido principal</a>
     ```
   - ✅ CSS ya existe en `frontend/css/04-elements/_accessibility.css`
   - ✅ Visible solo al hacer focus (usando clase `.skip-link` con `tabindex="-1"` por defecto)
   - ✅ Función `initSkipLink()` en `layout.js` maneja la activación del skip link

**Archivos modificados:**
- ✅ `frontend/components/sidebar.html` - Skip link agregado al inicio
- ✅ `frontend/js/layout.js` - Función `initSkipLink()` implementada

**Criterios cumplidos:**
- ✅ Skip link visible solo al hacer focus
- ✅ Salta al `#main-content` (verificado que existe en todas las páginas)
- ✅ Estilos implementados en `_accessibility.css`
- ✅ No interfiere con el tab order normal (tabindex="-1" por defecto)

---

### NAV-133 - Mejorar Accesibilidad de Navegación

**IMPLEMENTAR:**
1. **ARIA labels** en todos los items de navegación:
   - Agregar `aria-label` descriptivo a todos los `<a>` y botones de navegación
   - Usar claves de traducción para labels (ej: `aria-label="<?= t('nav.dashboard') ?> - Dashboard"`)

2. **Roles semánticos** (verificar):
   - Sidebar debe tener `role="navigation"` y `aria-label` (ya implementado)
   - Verificar que todos los `<nav>` tienen roles apropiados

3. **Contraste WCAG AA**:
   - Verificar contraste de texto y fondos en navegación
   - Usar herramientas para verificar (WAVE, axe DevTools)
   - Ajustar colores si es necesario

4. **Screen readers**:
   - Verificar estructura semántica
   - Agregar `aria-current="page"` al item activo
   - Agregar `aria-expanded` a botones de subsecciones (ya implementado)

**Archivos a modificar:**
- `frontend/components/sidebar.html` - Agregar ARIA labels y `aria-current`
- `frontend/css/05-objects/_layout.css` - Verificar y ajustar contraste si es necesario
- `backend/lang/es/nav.php` - Agregar traducciones para ARIA labels si es necesario

**Criterios:**
- Todos los items tienen `aria-label` descriptivo
- Item activo tiene `aria-current="page"`
- Contraste WCAG AA verificado
- Estructura semántica correcta para screen readers

---

### NAV-135 - Optimizar Performance de Navegación

**IMPLEMENTAR:**
1. **Lazy loading de iconos SVG**:
   - Modificar `IconHelper.processDataIcons()` para cargar iconos solo cuando son visibles
   - Usar Intersection Observer para detectar cuando iconos entran en viewport
   - Cargar iconos SVG dinámicamente solo cuando se necesitan

2. **Debounce en búsqueda** (si se implementa búsqueda integrada):
   - Usar debounce de 300ms para búsqueda en tiempo real
   - Ya existe función `debounce` en `frontend/js/core/performance.js`

3. **Cache de estado** (ya implementado parcialmente):
   - Verificar que estado colapsado/expandido se guarda en localStorage (ya implementado)
   - Agregar cache para estado de subsecciones expandidas/colapsadas

4. **Preload de páginas frecuentes** (opcional):
   - Agregar `<link rel="preload">` para páginas más usadas (dashboard, calendar, etc.)
   - Solo en desktop, no en móvil para ahorrar ancho de banda

**Archivos a modificar:**
- `frontend/js/core/icon-helper.js` - Implementar lazy loading con Intersection Observer
- `frontend/js/layout.js` - Mejorar cache de estado de subsecciones
- `frontend/components/sidebar.html` - Agregar preload links si se implementa

**Criterios:**
- Iconos cargados solo cuando son visibles
- Debounce en búsqueda (si se implementa)
- Cache de estado completo
- Preload opcional de páginas frecuentes

---

## Sprint 5.10 - Optimización Móvil SaaS (Todas las tareas)

### MOB-137 - Implementar Swipe Gestures para Navegación

**IMPLEMENTAR:**
1. **Swipe gestures mejorados** (ya existe `initSwipeGestures()` para sidebar, extender):
   - Swipe left/right en listas (DataTable) para acciones rápidas
   - Swipe to dismiss en notificaciones (Toast)
   - Swipe to dismiss en modales
   - Crear helper reutilizable `frontend/js/core/swipe-gestures.js`

2. **Funcionalidades:**
   - Swipe left en lista: mostrar acciones (eliminar, editar)
   - Swipe right: ocultar acciones
   - Swipe down en modal/toast: cerrar
   - Umbral configurable (default: 50px)
   - Detectar dirección correcta

**Archivos a crear:**
- `frontend/js/core/swipe-gestures.js` - Helper reutilizable para swipe gestures

**Archivos a modificar:**
- `frontend/js/components/data-table.js` - Agregar swipe actions en móvil
- `frontend/js/components/toast.js` - Agregar swipe to dismiss
- `frontend/js/components/modal.js` - Agregar swipe to dismiss en móvil
- `frontend/css/06-components/_data-table.css` - Estilos para swipe actions

**Criterios:**
- Swipe gestures funcionales en listas, toasts y modales
- Detección de dirección correcta
- Umbral configurable
- Solo activo en móvil/tablet

---

### MOB-138 - Optimizar Modales para Móvil (Bottom Sheet / Fullscreen)

**IMPLEMENTAR:**
1. **Componente BottomSheet** reutilizable:
   - Crear `frontend/js/components/bottom-sheet.js`
   - API similar a Modal pero optimizado para móvil
   - Animación slide up desde bottom
   - Swipe down para cerrar
   - Backdrop con blur mejorado

2. **Modificar Modal existente**:
   - En móvil, modales deben ser fullscreen o bottom sheet
   - Detectar tamaño de pantalla y aplicar estilo apropiado
   - Opción `mobileMode: 'fullscreen' | 'bottom-sheet'` en Modal

**Archivos a crear:**
- `frontend/js/components/bottom-sheet.js` - Componente BottomSheet
- `frontend/css/06-components/_bottom-sheet.css` - Estilos de bottom sheet

**Archivos a modificar:**
- `frontend/js/components/modal.js` - Agregar soporte para fullscreen/bottom-sheet en móvil
- `frontend/css/06-components/_modal.css` - Estilos para modales fullscreen/bottom-sheet en móvil

**Criterios:**
- BottomSheet funcional con slide up animation
- Swipe down para cerrar
- Modales en móvil son fullscreen o bottom sheet (no centrados)
- Backdrop con blur mejorado

---

### MOB-139 - Implementar Floating Action Button (FAB)

**IMPLEMENTAR:**
1. **Componente FAB** reutilizable:
   - Crear `frontend/js/components/fab.js`
   - Posición fija en bottom-right
   - Animación de entrada suave
   - Badge opcional para contador
   - Ocultar en scroll down, mostrar en scroll up (opcional)

2. **Integración:**
   - Agregar FAB en páginas que necesitan acción principal (ej: "Nueva solicitud", "Fichar")
   - Usar claves de traducción para texto

**Archivos a crear:**
- `frontend/js/components/fab.js` - Componente FAB
- `frontend/css/06-components/_fab.css` - Estilos de FAB

**Archivos a modificar:**
- Páginas que necesitan FAB (staffing-requests, time-clock, etc.)
- `backend/lang/es/nav.php` o archivos de traducción apropiados

**Criterios:**
- FAB funcional con posición fija bottom-right
- Animación de entrada suave
- Badge opcional funcional
- Ocultar/mostrar en scroll (opcional)

---

### MOB-140 - Implementar Pull to Refresh

**IMPLEMENTAR:**
1. **Helper Pull to Refresh**:
   - Crear `frontend/js/core/pull-to-refresh.js`
   - Funcionalidad reutilizable para listas y dashboards
   - Indicador visual de refresh
   - Animación suave de pull

2. **Integración:**
   - Agregar a DataTable para listas
   - Agregar a dashboards
   - Solo activo en móvil/tablet

**Archivos a crear:**
- `frontend/js/core/pull-to-refresh.js` - Helper pull to refresh
- `frontend/css/06-components/_pull-to-refresh.css` - Estilos del indicador

**Archivos a modificar:**
- `frontend/js/components/data-table.js` - Integrar pull to refresh
- `frontend/js/dashboard.js` - Integrar pull to refresh en dashboards

**Criterios:**
- Pull to refresh funcional en listas y dashboards
- Indicador visual claro
- Animación suave
- Solo activo en móvil/tablet

---

### MOB-141 - Optimizar Dashboards para Móvil

**IMPLEMENTAR:**
1. **Verificar y mejorar KPI Cards**:
   - Verificar que en móvil están en 1 columna (ya implementado parcialmente)
   - Valores grandes y legibles
   - Ajustar tamaños de fuente para móvil

2. **Gráficos adaptados**:
   - Scroll horizontal en gráficos en móvil
   - O versión simplificada (solo datos clave)
   - Usar librerías que soporten responsive (Chart.js, ApexCharts)

3. **Filtros en bottom sheet**:
   - Mover filtros a bottom sheet o drawer colapsable en móvil
   - Toggle para mostrar/ocultar filtros

4. **Quick actions adaptados**:
   - Adaptar quick actions para móvil (botones más grandes, mejor espaciado)

5. **Lazy loading agresivo**:
   - Cargar gráficos solo cuando son visibles (Intersection Observer)
   - Cargar datos secundarios después de datos críticos

**Archivos a modificar:**
- `frontend/css/05-objects/_dashboard-grid.css` - Verificar responsive en móvil
- `frontend/css/06-components/_cards.css` - Ajustar KPI cards para móvil
- `frontend/css/06-components/_charts.css` - Agregar scroll horizontal en móvil
- `frontend/js/dashboard.js` - Implementar lazy loading de gráficos
- `frontend/pages/dashboard.html` - Adaptar filtros y quick actions para móvil

**Criterios:**
- KPI Cards en 1 columna en móvil, valores grandes
- Gráficos con scroll horizontal o versión simplificada
- Filtros en bottom sheet o drawer colapsable
- Quick actions adaptados para móvil
- Lazy loading agresivo implementado

---

### MOB-142 - Optimizar Formularios para Móvil

**IMPLEMENTAR:**
1. **Verificar inputs**:
   - Todos los inputs deben tener altura mínima 44px
   - Verificar en todas las páginas con formularios
   - Agregar `min-height: 44px` si falta

2. **Teclado apropiado**:
   - Agregar `inputmode` y `type` correctos:
     - Teléfonos: `type="tel"` o `inputmode="tel"`
     - Emails: `type="email"`
     - Números: `type="number"` o `inputmode="numeric"`
     - URLs: `type="url"`
   - Verificar en todos los formularios

3. **Autocompletado nativo**:
   - Agregar atributo `autocomplete` HTML5 en todos los inputs
   - Valores apropiados: `name`, `email`, `tel`, `organization`, etc.

4. **Botones de acción fijos**:
   - Botones de submit/guardar deben ser sticky en bottom en móvil
   - Crear clase `.form-actions-sticky` para móvil

5. **Date pickers nativos**:
   - Usar `<input type="date">` en móvil (ya implementado parcialmente)
   - Verificar que todos los date pickers usan input nativo en móvil

6. **Validación sin bloquear teclado**:
   - Verificar que validación en tiempo real no bloquea el teclado
   - Usar eventos `input` y `blur` apropiadamente

**Archivos a modificar:**
- `frontend/css/06-components/_forms-modern.css` - Agregar estilos para inputs grandes, botones sticky
- Todas las páginas con formularios - Agregar `inputmode`, `autocomplete`, verificar altura
- `frontend/js/components/form-validator.js` - Verificar que no bloquea teclado

**Criterios:**
- Todos los inputs tienen altura mínima 44px
- `inputmode` y `type` correctos en todos los inputs
- `autocomplete` HTML5 en todos los inputs
- Botones de acción sticky en bottom en móvil
- Date pickers nativos en móvil
- Validación no bloquea teclado

---

### MOB-143 - Mejorar Performance Móvil

**IMPLEMENTAR:**
1. **Code splitting agresivo**:
   - Verificar que cada página carga solo su código necesario
   - Usar `loadModule()` de `performance.js` para lazy loading
   - Separar código crítico de no crítico

2. **Debounce en scroll**:
   - Agregar debounce a eventos de scroll para mejor performance
   - Usar función `debounce` de `performance.js`

3. **Virtual scrolling**:
   - Implementar virtual scrolling en DataTable para listas >50 items
   - Usar `VirtualScroller` de `performance.js` si existe, o crear implementación

4. **Cache agresivo**:
   - Cachear datos de dashboard en localStorage con TTL
   - Cachear listas frecuentes
   - Usar `DataCache` de `performance.js`

5. **Minimizar re-renders**:
   - Verificar que componentes no se re-renderizan innecesariamente
   - Usar técnicas de optimización (memoización, etc.)

6. **Lazy loading de imágenes**:
   - Verificar que todas las imágenes usan `loading="lazy"`
   - Usar Intersection Observer para lazy loading mejorado

**Archivos a modificar:**
- `frontend/js/core/performance.js` - Verificar y mejorar funciones existentes
- `frontend/js/components/data-table.js` - Implementar virtual scrolling
- `frontend/js/dashboard.js` - Implementar cache agresivo
- Todas las páginas - Verificar lazy loading de imágenes

**Criterios:**
- Code splitting implementado por página
- Debounce en scroll
- Virtual scrolling en listas largas
- Cache agresivo de datos
- Re-renders minimizados
- Todas las imágenes con lazy loading

---

### MOB-144 - Optimizar Texto y Contraste para Móvil

**IMPLEMENTAR:**
1. **Texto mínimo 16px**:
   - Verificar que todo el texto normal tiene mínimo 16px
   - Usar `clamp()` para texto responsivo donde aplique
   - Evitar zoom automático en iOS

2. **Contraste WCAG AA/AAA**:
   - Verificar contraste de todos los textos en móvil
   - Ajustar colores si es necesario
   - Preferiblemente AAA en móvil

3. **Espaciado mínimo 8px**:
   - Verificar espaciado entre elementos táctiles en todas las páginas
   - Mínimo 8px entre elementos interactivos

4. **Tamaños responsivos**:
   - Usar `clamp()` para tamaños de fuente responsivos
   - Ejemplo: `font-size: clamp(1rem, 2.5vw, 1.25rem)`

**Archivos a modificar:**
- `frontend/css/01-settings/theme.css` - Verificar tamaños de fuente mínimos
- `frontend/css/04-elements/_typography.css` - Agregar `clamp()` donde aplique
- Todas las páginas CSS - Verificar espaciado y contraste

**Criterios:**
- Texto mínimo 16px en móvil
- Contraste WCAG AA/AAA verificado
- Espaciado mínimo 8px entre elementos táctiles
- Tamaños responsivos con `clamp()`

---

### MOB-145 - Optimizar Animaciones para Móvil

**IMPLEMENTAR:**
1. **Animaciones más rápidas**:
   - Crear variables CSS para transiciones móviles:
     - `--transition-fast-mobile: 150ms ease`
     - `--transition-base-mobile: 200ms ease`
   - Usar en media queries para móvil

2. **Transform y opacity**:
   - Verificar que todas las animaciones usan `transform` y `opacity`
   - Evitar animar `width`, `height`, `top`, `left` (mejor performance)

3. **Respetar prefers-reduced-motion**:
   - Verificar que todas las animaciones respetan `prefers-reduced-motion`
   - Ya implementado globalmente, verificar que funciona

4. **Evitar animaciones pesadas**:
   - Revisar animaciones que puedan afectar scroll performance
   - Optimizar o deshabilitar en móvil si es necesario

**Archivos a modificar:**
- `frontend/css/01-settings/theme.css` - Agregar variables de transición móvil
- `frontend/css/06-components/_microinteractions.css` - Optimizar animaciones para móvil
- Media queries en todos los archivos CSS - Usar transiciones más rápidas en móvil

**Criterios:**
- Animaciones más rápidas en móvil (150-200ms)
- Todas usan `transform` y `opacity`
- Respetan `prefers-reduced-motion`
- No afectan scroll performance

---

### MOB-148 - Testing Móvil Completo

**IMPLEMENTAR:**
1. **Checklist de testing**:
   - Crear `docs/MOBILE_TESTING_CHECKLIST.md` con checklist completo
   - Incluir: dispositivos, orientaciones, conexiones, touch events, performance

2. **Scripts de verificación**:
   - Crear script para verificar touch targets (mínimo 44x44px)
   - Crear script para verificar contraste WCAG
   - Crear script para verificar texto mínimo 16px

**Archivos a crear:**
- `docs/MOBILE_TESTING_CHECKLIST.md` - Checklist completo de testing móvil
- `scripts/dev/verify_mobile_compliance.php` - Script de verificación

**Criterios:**
- Checklist completo creado
- Scripts de verificación funcionales
- Documentación de testing móvil

---

### MOB-149 - Optimizar Videos e Imágenes para Móvil

**IMPLEMENTAR:**
1. **Videos responsivos**:
   - Agregar `aspect-ratio` y `object-fit: cover` a todos los videos
   - Verificar que videos se adaptan a diferentes tamaños

2. **Imágenes con srcset móvil**:
   - Agregar `srcset` con tamaños móviles específicos a todas las imágenes
   - Usar tamaños apropiados (400w, 800w, 1200w)
   - Agregar `sizes` attribute apropiado

3. **WebP con fallback**:
   - Implementar `<picture>` con WebP y fallback JPG/PNG
   - O usar servicio de imágenes que soporte WebP automático

4. **Lazy loading mejorado**:
   - Usar Intersection Observer para lazy loading mejorado
   - Ya existe en `performance.js`, verificar que se usa

**Archivos a modificar:**
- Todas las páginas HTML - Agregar `srcset`, `sizes`, `<picture>` con WebP
- `frontend/js/core/performance.js` - Verificar lazy loading con Intersection Observer
- `frontend/css/04-elements/_images.css` - Verificar estilos responsivos

**Criterios:**
- Videos responsivos con aspect-ratio
- Imágenes con srcset y tamaños móviles
- WebP con fallback
- Lazy loading mejorado con Intersection Observer

---

## Reglas Obligatorias a Seguir

1. **Consultar `.cursorrules`** antes de implementar cualquier cosa
2. **Mobile-First**: Todas las implementaciones deben ser mobile-first
3. **Usar design tokens**: NUNCA hardcodear valores, usar variables CSS de `theme.css`
4. **BEM consistente**: Usar convención BEM para todas las clases CSS
5. **ITCSS**: Organizar CSS según capas ITCSS
6. **i18n**: TODOS los textos visibles deben usar claves de traducción (`t()`)
7. **Aplicación completa**: Aplicar cambios en TODAS las páginas, no solo ejemplos
8. **Sin parches**: Resolver problemas desde la raíz, no workarounds
9. **Verificar antes de eliminar**: Verificar funcionalidad antes de eliminar código
10. **Documentar**: Documentar cambios importantes

## Orden de Implementación Recomendado

1. **Sprint 5.9 - Completar tareas pendientes** (NAV-128, NAV-130, NAV-132, NAV-133, NAV-135)
2. **Sprint 5.10 - Componentes base** (MOB-137, MOB-138, MOB-139, MOB-140)
3. **Sprint 5.10 - Optimizaciones** (MOB-141, MOB-142, MOB-143, MOB-144, MOB-145)
4. **Sprint 5.10 - Finalización** (MOB-148, MOB-149)
5. **Opcionales** (MOB-146, MOB-147, NAV-134, NAV-136)

## Verificación Final

Después de implementar cada tarea:
- ✅ Verificar que funciona en móvil (iOS y Android)
- ✅ Verificar que no rompe funcionalidad existente
- ✅ Verificar que usa design tokens (no valores hardcodeados)
- ✅ Verificar que usa claves de traducción (no texto hardcodeado)
- ✅ Verificar accesibilidad (WCAG AA mínimo)
- ✅ Actualizar estado en `TODO_ROADMAP_COMPLETO.md`
- ✅ Documentar cambios si es necesario

