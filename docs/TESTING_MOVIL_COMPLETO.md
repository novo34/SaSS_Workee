# Testing Móvil Completo - Workee

**MOB-148: Testing Móvil Completo**

Esta guía documenta el proceso completo de testing móvil para asegurar que todas las funcionalidades del SaaS funcionen correctamente en dispositivos móviles.

---

## 📱 Dispositivos a Probar

### iOS (iPhone)
- **iPhone SE (2020)** - 375x667px - iOS 14+
- **iPhone 12/13** - 390x844px - iOS 15+
- **iPhone 12/13 Pro Max** - 428x926px - iOS 15+
- **iPhone 14/15** - 393x852px - iOS 16+
- **iPhone 14/15 Pro Max** - 430x932px - iOS 16+

### Android
- **Samsung Galaxy S21** - 360x800px - Android 11+
- **Google Pixel 5** - 393x851px - Android 11+
- **Samsung Galaxy Note 20** - 412x915px - Android 11+
- **OnePlus 9** - 412x915px - Android 11+

### Tablets
- **iPad Mini** - 768x1024px - iOS 14+
- **iPad Air/Pro** - 820x1180px - iOS 14+
- **Samsung Galaxy Tab S7** - 800x1280px - Android 11+

---

## 🔍 Checklist de Verificación

### 1. Navegación y Layout

#### Sidebar y Navegación
- [ ] Sidebar se oculta correctamente en móvil (< 992px)
- [ ] Botón hamburguesa funciona para abrir/cerrar sidebar
- [ ] Swipe desde borde izquierdo abre sidebar
- [ ] Swipe derecho cierra sidebar
- [ ] Sidebar tiene overlay oscuro cuando está abierto
- [ ] Presionar Esc cierra sidebar
- [ ] Focus va al primer item cuando se abre sidebar
- [ ] Secciones colapsables funcionan correctamente
- [ ] Badges de notificación se muestran correctamente
- [ ] Navegación por teclado funciona (Tab, Arrow keys, Enter/Space)

#### Header
- [ ] Header es sticky y se mantiene visible al hacer scroll
- [ ] Breadcrumbs se muestran correctamente (solo últimos 2-3 niveles en móvil)
- [ ] Botones de acción (mensajes, notificaciones, perfil) son táctiles (44x44px mínimo)
- [ ] Búsqueda global funciona (Ctrl+K o Cmd+K)

#### Breadcrumbs
- [ ] Estructura semántica correcta (`<nav aria-label="Breadcrumb">`)
- [ ] Separadores modernos (/) se muestran correctamente
- [ ] Item actual no es clickeable
- [ ] Responsive: solo últimos 2-3 niveles visibles en móvil

### 2. Formularios

#### Inputs y Controles
- [ ] Todos los inputs tienen altura mínima de 44px
- [ ] Font-size mínimo de 16px (no hay zoom automático en iOS)
- [ ] Inputs tienen `inputmode` apropiado (numeric, tel, email, etc.)
- [ ] Inputs tienen `autocomplete` attributes
- [ ] Date pickers usan nativos en móvil (`<input type="date">`)
- [ ] Teclado apropiado aparece según tipo de input
- [ ] Labels son legibles y tienen tamaño adecuado
- [ ] Placeholders son claros y descriptivos

#### Validación
- [ ] Validación en tiempo real funciona sin bloquear teclado
- [ ] Mensajes de error son visibles y no ocultan inputs
- [ ] Feedback visual (success/error) es claro
- [ ] Validación no causa zoom automático en iOS

#### Botones de Acción
- [ ] Botones tienen altura mínima de 44px
- [ ] Botones sticky en bottom funcionan en formularios largos
- [ ] Espaciado mínimo de 8px entre botones
- [ ] Botones son fáciles de presionar con el pulgar

### 3. Dashboards

#### KPI Cards
- [ ] KPI Cards se muestran en 1 columna en móvil
- [ ] Valores son legibles (tamaño de fuente adecuado)
- [ ] Trend indicators se muestran correctamente
- [ ] Cards tienen espaciado adecuado entre ellas

#### Gráficos
- [ ] Gráficos tienen scroll horizontal si es necesario
- [ ] Gráficos son legibles en pantalla pequeña
- [ ] Tooltips funcionan correctamente en touch
- [ ] Leyendas son legibles

#### Filtros
- [ ] Filtros están en bottom sheet o drawer colapsable
- [ ] Toggle de filtros funciona correctamente
- [ ] Filtros son fáciles de usar en móvil

#### Quick Actions
- [ ] Quick actions son táctiles (44x44px mínimo)
- [ ] Atajos de teclado están ocultos en móvil
- [ ] Iconos son claros y reconocibles

#### Lazy Loading
- [ ] Datos secundarios (achievements, task progress) se cargan solo cuando son visibles
- [ ] Skeleton screens se muestran mientras cargan
- [ ] No hay contenido que "salte" al cargar

### 4. Tablas y Listas

#### DataTable
- [ ] Tablas se convierten a cards en móvil automáticamente
- [ ] Swipe left/right muestra acciones rápidas (editar, eliminar)
- [ ] Pull to refresh funciona correctamente
- [ ] Paginación es táctil y fácil de usar
- [ ] Búsqueda funciona correctamente
- [ ] Filtros son accesibles y fáciles de usar

#### Virtual Scrolling
- [ ] Virtual scrolling se activa automáticamente para listas >50 items
- [ ] Scroll es suave y performante
- [ ] No hay lag al hacer scroll rápido

### 5. Modales y Bottom Sheets

#### Bottom Sheets
- [ ] Modales se convierten en bottom sheet en móvil
- [ ] Bottom sheet tiene handle draggable visible
- [ ] Swipe down cierra bottom sheet
- [ ] Backdrop tiene blur effect
- [ ] Animación de entrada es suave (slide up)
- [ ] Body scroll está bloqueado cuando bottom sheet está abierto

#### Modales Fullscreen
- [ ] Modales fullscreen ocupan toda la pantalla
- [ ] Botón de cerrar es fácil de alcanzar
- [ ] Contenido es scrolleable si es necesario

### 6. Componentes Específicos

#### Floating Action Button (FAB)
- [ ] FAB solo visible en móvil (< 992px)
- [ ] FAB está en bottom-right con espaciado adecuado
- [ ] FAB tiene tamaño adecuado (56x56px mínimo)
- [ ] Badge de contador se muestra correctamente
- [ ] Animación de entrada es suave
- [ ] Hide on scroll funciona (opcional)

#### Pull to Refresh
- [ ] Pull to refresh funciona en listas y dashboards
- [ ] Indicador visual es claro
- [ ] Animación es suave
- [ ] Solo visible en móvil

#### Swipe Gestures
- [ ] Swipe desde borde izquierdo abre sidebar
- [ ] Swipe derecho cierra sidebar
- [ ] Swipe left/right en listas muestra acciones
- [ ] Swipe up dismiss en toasts
- [ ] Swipe down dismiss en modales/bottom sheets

### 7. Performance

#### Carga y Rendimiento
- [ ] Tiempo de carga inicial < 3 segundos en 3G
- [ ] Scroll es suave (60fps)
- [ ] No hay lag al interactuar con elementos
- [ ] Animaciones son fluidas (150-200ms)
- [ ] No hay jank o stuttering

#### Cache
- [ ] Datos de dashboard se cachean correctamente
- [ ] Listas se cachean correctamente
- [ ] Cache se invalida cuando es necesario
- [ ] No hay datos obsoletos mostrándose

#### Lazy Loading
- [ ] Imágenes lazy se cargan solo cuando son visibles
- [ ] Placeholder shimmer se muestra mientras carga
- [ ] No hay imágenes que "salten" al cargar
- [ ] Videos lazy se cargan solo cuando son visibles

### 8. Texto y Contraste

#### Tamaños de Fuente
- [ ] Texto normal tiene mínimo 16px (no hay zoom automático en iOS)
- [ ] Títulos son legibles y tienen jerarquía clara
- [ ] Texto pequeño (small) es legible (mínimo 14px)
- [ ] Tamaños de fuente son responsivos con `clamp()`

#### Contraste
- [ ] Contraste mínimo WCAG AA (4.5:1 para texto normal)
- [ ] Contraste preferiblemente AAA (7:1)
- [ ] Texto en botones tiene contraste adecuado
- [ ] Texto en badges tiene contraste adecuado
- [ ] Alto contraste funciona (`prefers-contrast: more`)

#### Espaciado
- [ ] Espaciado mínimo de 8px entre elementos táctiles
- [ ] Padding interno de elementos es adecuado
- [ ] Margins entre secciones son generosos

### 9. Animaciones

#### Transiciones
- [ ] Animaciones son rápidas en móvil (150-200ms)
- [ ] Animaciones usan `transform` y `opacity` (mejor performance)
- [ ] No hay animaciones pesadas que afecten scroll
- [ ] `prefers-reduced-motion` se respeta

#### Feedback Visual
- [ ] Hover effects funcionan en touch (tap)
- [ ] Active states son claros
- [ ] Loading states son visibles
- [ ] Success/error feedback es inmediato

### 10. Imágenes y Videos

#### Imágenes
- [ ] Imágenes tienen `srcset` con tamaños móviles
- [ ] Imágenes tienen `sizes` attribute apropiado
- [ ] WebP con fallback funciona correctamente
- [ ] Lazy loading funciona con Intersection Observer
- [ ] Aspect ratio previene CLS (Cumulative Layout Shift)
- [ ] Placeholder shimmer se muestra mientras carga

#### Videos
- [ ] Videos tienen `aspect-ratio` definido
- [ ] Videos usan `object-fit: cover`
- [ ] Videos embebidos (iframe) son responsivos
- [ ] Lazy loading funciona para videos fuera del viewport

### 11. Orientaciones

#### Vertical (Portrait)
- [ ] Todas las funcionalidades funcionan en vertical
- [ ] Layout se adapta correctamente
- [ ] Navegación es accesible
- [ ] Formularios son usables

#### Horizontal (Landscape)
- [ ] Todas las funcionalidades funcionan en horizontal
- [ ] Layout se adapta correctamente
- [ ] Sidebar puede colapsarse si es necesario
- [ ] Contenido no se corta

### 12. Conexiones

#### 3G (Lenta)
- [ ] Tiempo de carga inicial es aceptable (< 5 segundos)
- [ ] Skeleton screens se muestran mientras carga
- [ ] Lazy loading funciona correctamente
- [ ] Cache reduce llamadas API innecesarias
- [ ] No hay timeouts o errores de conexión

#### WiFi (Rápida)
- [ ] Carga es instantánea
- [ ] Todas las funcionalidades funcionan correctamente
- [ ] Performance es óptima

### 13. Touch Events

#### Elementos Interactivos
- [ ] Todos los botones responden a touch
- [ ] Todos los links responden a touch
- [ ] Todos los inputs responden a touch
- [ ] Área de toque es adecuada (44x44px mínimo)
- [ ] No hay elementos que requieran precisión excesiva

#### Gestos
- [ ] Tap funciona en todos los elementos
- [ ] Long press funciona donde está implementado
- [ ] Swipe gestures funcionan correctamente
- [ ] Pinch to zoom funciona en imágenes (si está implementado)

### 14. Zoom Automático en iOS

#### Prevención
- [ ] No hay zoom automático al hacer focus en inputs
- [ ] Font-size mínimo de 16px en todos los inputs
- [ ] Viewport meta tag está configurado correctamente
- [ ] `user-scalable=no` NO está configurado (debe permitir zoom manual)

### 15. Funcionalidades Específicas por Rol

#### Owner Admin
- [ ] Dashboard de owner funciona en móvil
- [ ] Gestión de tenants es usable
- [ ] Billing y facturación son accesibles
- [ ] Métricas y reportes son legibles

#### Empresa Admin / Planificador
- [ ] Calendario funciona en móvil
- [ ] Solicitudes de personal son creables/editables
- [ ] Constructor de grupos es usable
- [ ] Asignaciones son gestionables
- [ ] Órdenes de trabajo son editables
- [ ] Parte del líder es completable

#### Supervisor
- [ ] Aprobaciones son realizables
- [ ] Lista de partes pendientes es usable
- [ ] Acciones rápidas (aprobar/rechazar) funcionan

#### Empleado
- [ ] Fichaje funciona correctamente
- [ ] Ver turnos es accesible
- [ ] Comentarios son añadibles

#### Temporera Admin / Coordinador
- [ ] Propuestas son gestionables
- [ ] Trabajadores son gestionables
- [ ] Asignaciones son visibles

#### Trabajador
- [ ] Fichaje funciona (si está habilitado)
- [ ] Ver asignaciones es accesible

---

## 🛠️ Scripts de Verificación Automática

### Script 1: Verificar Touch Targets

```javascript
// Ejecutar en consola del navegador
function verifyTouchTargets() {
    const issues = [];
    const interactiveElements = document.querySelectorAll('button, a, input, select, textarea, [role="button"], [tabindex="0"]');
    
    interactiveElements.forEach(el => {
        const rect = el.getBoundingClientRect();
        const minSize = 44; // 44px mínimo
        
        if (rect.width < minSize || rect.height < minSize) {
            issues.push({
                element: el,
                width: rect.width,
                height: rect.height,
                selector: el.tagName + (el.className ? '.' + el.className.split(' ')[0] : '')
            });
        }
    });
    
    if (issues.length > 0) {
        console.warn('Touch targets menores a 44px encontrados:', issues);
        return false;
    }
    
    console.log('✅ Todos los touch targets son >= 44px');
    return true;
}

verifyTouchTargets();
```

### Script 2: Verificar Font Sizes

```javascript
// Ejecutar en consola del navegador
function verifyFontSizes() {
    const issues = [];
    const textElements = document.querySelectorAll('body, p, span, div, label, button, a, input, textarea, select');
    
    textElements.forEach(el => {
        const computed = window.getComputedStyle(el);
        const fontSize = parseFloat(computed.fontSize);
        const minSize = 16; // 16px mínimo para evitar zoom en iOS
        
        // Solo verificar elementos visibles y que tienen texto
        if (el.offsetHeight > 0 && fontSize < minSize && !el.classList.contains('text-xs')) {
            issues.push({
                element: el,
                fontSize: fontSize + 'px',
                selector: el.tagName + (el.className ? '.' + el.className.split(' ')[0] : '')
            });
        }
    });
    
    if (issues.length > 0) {
        console.warn('Font sizes menores a 16px encontrados:', issues);
        return false;
    }
    
    console.log('✅ Todos los font sizes son >= 16px');
    return true;
}

verifyFontSizes();
```

### Script 3: Verificar Espaciado

```javascript
// Ejecutar en consola del navegador
function verifySpacing() {
    const issues = [];
    const interactiveElements = document.querySelectorAll('button, a, input, [role="button"]');
    const minSpacing = 8; // 8px mínimo
    
    interactiveElements.forEach((el, index) => {
        if (index === 0) return; // Saltar el primero
        
        const prevEl = interactiveElements[index - 1];
        const rect1 = prevEl.getBoundingClientRect();
        const rect2 = el.getBoundingClientRect();
        
        // Verificar espaciado vertical
        if (rect2.top - rect1.bottom < minSpacing && rect1.bottom < rect2.top) {
            issues.push({
                element1: prevEl.tagName + (prevEl.className ? '.' + prevEl.className.split(' ')[0] : ''),
                element2: el.tagName + (el.className ? '.' + el.className.split(' ')[0] : ''),
                spacing: (rect2.top - rect1.bottom) + 'px'
            });
        }
    });
    
    if (issues.length > 0) {
        console.warn('Espaciado insuficiente encontrado:', issues);
        return false;
    }
    
    console.log('✅ Espaciado adecuado entre elementos');
    return true;
}

verifySpacing();
```

### Script 4: Verificar Lazy Loading

```javascript
// Ejecutar en consola del navegador
function verifyLazyLoading() {
    const images = document.querySelectorAll('img');
    const issues = [];
    
    images.forEach(img => {
        const isAboveFold = img.offsetTop < window.innerHeight * 2;
        const hasLazy = img.hasAttribute('loading') && img.getAttribute('loading') === 'lazy';
        
        // Imágenes fuera del viewport inicial deben tener lazy loading
        if (!isAboveFold && !hasLazy) {
            issues.push({
                element: img,
                src: img.src.substring(0, 50) + '...',
                offsetTop: img.offsetTop
            });
        }
    });
    
    if (issues.length > 0) {
        console.warn('Imágenes sin lazy loading encontradas:', issues);
        return false;
    }
    
    console.log('✅ Lazy loading implementado correctamente');
    return true;
}

verifyLazyLoading();
```

### Script 5: Verificar Contraste (Básico)

```javascript
// Ejecutar en consola del navegador
// Nota: Este es un check básico, usar herramientas profesionales para verificación completa
function verifyContrast() {
    console.log('⚠️ Verificación de contraste requiere herramientas profesionales');
    console.log('Recomendado: usar Lighthouse, WAVE, o Color Contrast Analyzer');
    console.log('Contraste mínimo requerido: 4.5:1 para texto normal, 3:1 para texto grande');
}

verifyContrast();
```

---

## 📊 Herramientas de Testing

### Chrome DevTools
1. **Device Toolbar** (F12 → Toggle device toolbar)
   - Seleccionar dispositivo específico
   - Cambiar orientación (portrait/landscape)
   - Simular conexión lenta (3G, Slow 3G)

2. **Lighthouse**
   - Auditar performance móvil
   - Verificar accesibilidad
   - Verificar best practices

3. **Network Throttling**
   - Simular 3G, 4G, WiFi
   - Verificar tiempos de carga

### Firefox DevTools
1. **Responsive Design Mode** (Ctrl+Shift+M)
   - Seleccionar dispositivo
   - Cambiar orientación
   - Simular touch events

### Safari (iOS)
1. **Web Inspector**
   - Conectar iPhone/iPad
   - Inspeccionar en tiempo real
   - Debug JavaScript

### Android
1. **Chrome Remote Debugging**
   - Conectar dispositivo Android
   - Inspeccionar en Chrome
   - Ver console logs

---

## 🧪 Testing en Dispositivos Reales

### Proceso Recomendado

1. **Preparación**
   - Limpiar cache del navegador
   - Desactivar extensiones que puedan interferir
   - Configurar conexión (3G para testing lento)

2. **Testing Funcional**
   - Probar cada funcionalidad según checklist
   - Documentar issues encontrados
   - Tomar screenshots de problemas

3. **Testing de Performance**
   - Medir tiempos de carga
   - Verificar scroll performance
   - Verificar animaciones

4. **Testing de Accesibilidad**
   - Probar con screen reader (VoiceOver en iOS, TalkBack en Android)
   - Verificar navegación por teclado
   - Verificar contraste

---

## 📝 Reporte de Issues

Para cada issue encontrado, documentar:

1. **Dispositivo**: iPhone 12, Android Samsung Galaxy S21, etc.
2. **Navegador**: Safari 15, Chrome 95, etc.
3. **Orientación**: Portrait / Landscape
4. **Conexión**: 3G / WiFi
5. **Página/Componente**: Dashboard, Formulario de solicitudes, etc.
6. **Descripción**: Qué no funciona o qué está mal
7. **Pasos para reproducir**: Cómo reproducir el issue
8. **Screenshot**: Captura del problema
9. **Severidad**: Crítico / Alto / Medio / Bajo

---

## ✅ Criterios de Aceptación

- [ ] Todas las funcionalidades funcionan en iOS (iPhone)
- [ ] Todas las funcionalidades funcionan en Android
- [ ] Todas las funcionalidades funcionan en tablet
- [ ] Funciona en orientación vertical y horizontal
- [ ] Funciona con conexión lenta (3G)
- [ ] Touch events funcionan en todos los elementos interactivos
- [ ] Tiempos de carga son aceptables (< 3s en 3G)
- [ ] Scroll performance es óptima (60fps)
- [ ] No hay zoom automático en iOS
- [ ] Contraste cumple WCAG AA mínimo

---

## 🔗 Referencias

- [Web.dev - Mobile-Friendly](https://web.dev/mobile-friendly/)
- [Google Mobile-Friendly Test](https://search.google.com/test/mobile-friendly)
- [Lighthouse Mobile Audit](https://developers.google.com/web/tools/lighthouse)
- [WCAG 2.1 Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)

---

**Última actualización**: 2025-01-XX
**Responsable**: Equipo de Desarrollo Workee

