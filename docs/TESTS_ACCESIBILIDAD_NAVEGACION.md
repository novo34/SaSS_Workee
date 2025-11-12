# Tests de Accesibilidad de Navegación - Workee

**Sprint 5.9: Tests Automatizados Completos de Accesibilidad**

Esta guía documenta los tests automatizados de accesibilidad para la navegación del sistema Workee.

---

## 📋 Tests Implementados

### 1. Keyboard Navigation (Navegación por Teclado)

**Verifica:**
- ✅ Todos los nav-links son focusables
- ✅ Botones de subsecciones son focusables
- ✅ Elementos ocultos no están en tab order
- ✅ No hay tabindex > 0 (debería evitarse)
- ✅ Tab order lógico (de arriba a abajo)

**Criterios de éxito:**
- Todos los elementos visibles son focusables
- Elementos disabled tienen `tabindex="-1"`
- Elementos ocultos tienen `tabindex="-1"`

---

### 2. ARIA Labels

**Verifica:**
- ✅ Todos los nav-links tienen `aria-label`
- ✅ Botones de subsecciones tienen `aria-label` y `aria-expanded`
- ✅ Iconos tienen `aria-hidden="true"`
- ✅ Sidebar tiene `role="navigation"` y `aria-label`

**Criterios de éxito:**
- Todos los elementos interactivos tienen `aria-label` descriptivo
- `aria-label` tiene al menos 3 caracteres
- Iconos decorativos tienen `aria-hidden="true"`

---

### 3. Semantic Structure (Estructura Semántica)

**Verifica:**
- ✅ Sidebar usa `<nav>` o `<aside>` con `role="navigation"`
- ✅ Nav-links son `<a>` o `<button>`
- ✅ Breadcrumbs usan estructura semántica (`<nav>`, `<ol>`)
- ✅ Items activos tienen `aria-current="page"`

**Criterios de éxito:**
- Estructura HTML semántica correcta
- Roles ARIA apropiados
- Items activos marcados con `aria-current`

---

### 4. Focus Management (Gestión de Focus)

**Verifica:**
- ✅ Elementos tienen `:focus-visible` styles
- ✅ `sidebar-toggle` tiene `tabindex` correcto según viewport
- ✅ Elementos disabled tienen `tabindex="-1"`

**Criterios de éxito:**
- Focus visible en todos los elementos interactivos
- `sidebar-toggle` accesible en móvil, oculto en desktop
- Elementos disabled no están en tab order

---

### 5. Skip Links

**Verifica:**
- ✅ Skip link existe
- ✅ Skip link tiene `tabindex="-1"` por defecto
- ✅ Skip link tiene `href` válido
- ✅ Target del skip link existe

**Criterios de éxito:**
- Skip link implementado correctamente
- Skip link se activa con focus
- Target existe en el DOM

---

### 6. Color Contrast (Contraste de Colores)

**Verifica:**
- ✅ Contraste de texto en nav-links >= 4.5:1 (WCAG AA)
- ✅ Contraste en estados hover/active

**Criterios de éxito:**
- Contraste mínimo 4.5:1 para texto normal
- Contraste mínimo 3:1 para texto grande

---

### 7. Screen Reader Support (Soporte para Lectores de Pantalla)

**Verifica:**
- ✅ Elementos ocultos tienen `aria-hidden="true"`
- ✅ Iconos decorativos tienen `aria-hidden="true"`
- ✅ Breadcrumbs tienen `aria-label` apropiado
- ✅ Items activos tienen `aria-current="page"`

**Criterios de éxito:**
- Todos los elementos decorativos ocultos para screen readers
- Estructura semántica navegable por screen readers
- Items activos identificables por screen readers

---

## 🚀 Cómo Ejecutar los Tests

### Opción 1: Cargar Script Manualmente

```javascript
// En la consola del navegador
const script = document.createElement('script');
script.src = '/SaSS_Workee/scripts/dev/navigation-accessibility-tests.js';
script.onload = () => {
    console.log('✅ Script cargado');
    NavigationAccessibilityTests.runAll();
};
document.head.appendChild(script);
```

### Opción 2: Ejecutar Directamente

Si el script ya está cargado:

```javascript
NavigationAccessibilityTests.runAll();
```

### Opción 3: Ejecutar Tests Individuales

```javascript
// Test específico
NavigationAccessibilityTests.testKeyboardNavigation();
NavigationAccessibilityTests.testAriaLabels();
NavigationAccessibilityTests.testSemanticStructure();
NavigationAccessibilityTests.testFocusManagement();
NavigationAccessibilityTests.testSkipLinks();
NavigationAccessibilityTests.testColorContrast();
NavigationAccessibilityTests.testScreenReader();
```

---

## 📊 Interpretación de Resultados

### Resultados Exitosos

```
✅ Keyboard Navigation: 25 elementos verificados correctamente
✅ ARIA Labels: 30 elementos verificados correctamente
✅ Semantic Structure: 15 elementos verificados correctamente
✅ Focus Management: 20 elementos verificados correctamente
✅ Skip Links: 4 elementos verificados correctamente
✅ Color Contrast: 25 elementos verificados correctamente
✅ Screen Reader Support: 30 elementos verificados correctamente

🎉 ¡Todos los tests de accesibilidad pasaron correctamente!
```

### Resultados con Problemas

```
❌ Keyboard Navigation: 2 problemas encontrados
   - .nav-link.disabled: Elemento disabled sin tabindex="-1"
   - #sidebar-toggle: sidebar-toggle no tiene tabindex="-1" en desktop

⚠️ ARIA Labels: 3 advertencias
   - .nav-link: aria-label muy corto
   - .nav-icon: Icono sin aria-hidden="true"

❌ Hay problemas de accesibilidad que deben corregirse.
```

### Acceder a Resultados Detallados

```javascript
// Ver resultados completos
console.log(window.navigationAccessibilityResults);

// Ver problemas específicos
console.log(window.navigationAccessibilityResults.keyboardNavigation.issues);
console.log(window.navigationAccessibilityResults.ariaLabels.warnings);

// Ver resumen
NavigationAccessibilityTests.printSummary();
```

---

## 🔧 Corrección de Problemas Comunes

### Problema: Elemento disabled sin tabindex="-1"

**Solución:**
```javascript
// En layout.js o en el código que deshabilita elementos
element.classList.add('disabled');
element.setAttribute('tabindex', '-1');
element.setAttribute('aria-disabled', 'true');
```

### Problema: Nav link sin aria-label

**Solución:**
```html
<a href="/page" 
   class="nav-link" 
   data-page="page-name"
   aria-label="Ir a Nombre de Página">
    <span class="nav-icon icon icon-md" data-icon="iconName" aria-hidden="true"></span>
    <span class="nav-text" data-i18n="nav.page_name">Nombre Página</span>
</a>
```

### Problema: Icono sin aria-hidden="true"

**Solución:**
```html
<span class="nav-icon icon icon-md" data-icon="iconName" aria-hidden="true"></span>
```

### Problema: Item activo sin aria-current="page"

**Solución:**
```javascript
// En layout.js, función setActiveNavItem()
link.classList.add('active');
link.setAttribute('aria-current', 'page');
```

### Problema: Contraste bajo

**Solución:**
```css
/* Asegurar contraste adecuado */
.nav-link {
    color: var(--color-text); /* Contraste >= 4.5:1 con fondo */
}

.nav-link:hover {
    color: var(--color-primary); /* Contraste >= 4.5:1 */
}
```

---

## 📋 Checklist de Verificación Manual

Además de los tests automatizados, verifica manualmente:

### Keyboard Navigation
- [ ] Tab navega por todos los elementos en orden lógico
- [ ] ArrowDown/ArrowUp navegan entre items del sidebar
- [ ] Home/End van al primer/último item
- [ ] Enter/Space activan botones de subsecciones
- [ ] Esc cierra sidebar en móvil
- [ ] Skip link se activa con tecla 'S'

### Screen Reader
- [ ] Screen reader anuncia todos los items de navegación
- [ ] Screen reader anuncia estado activo correctamente
- [ ] Screen reader anuncia estado expandido/colapsado de subsecciones
- [ ] Screen reader puede navegar por breadcrumbs
- [ ] Screen reader ignora iconos decorativos

### Focus Visible
- [ ] Focus es claramente visible en todos los elementos
- [ ] Focus no se oculta con `outline: none` sin reemplazo
- [ ] Focus tiene contraste adecuado con el fondo

### Contraste
- [ ] Texto tiene contraste >= 4.5:1 con fondo
- [ ] Estados hover/active tienen contraste adecuado
- [ ] Badges tienen contraste adecuado

---

## 🔍 Tests Adicionales Recomendados

### Test Manual con Screen Reader

1. **NVDA (Windows)** o **VoiceOver (Mac)**:
   - Activar screen reader
   - Navegar por la página con teclado
   - Verificar que todos los items se anuncian correctamente
   - Verificar que el estado activo se anuncia

2. **Navegación por teclado sin mouse**:
   - Desconectar mouse
   - Navegar completamente por la aplicación solo con teclado
   - Verificar que todas las funcionalidades son accesibles

### Test con Herramientas Externas

1. **axe DevTools** (extensión de navegador):
   - Instalar extensión
   - Ejecutar análisis de accesibilidad
   - Revisar problemas encontrados

2. **WAVE** (Web Accessibility Evaluation Tool):
   - Visitar https://wave.webaim.org/
   - Ingresar URL de la aplicación
   - Revisar reporte de accesibilidad

3. **Lighthouse** (Chrome DevTools):
   - Abrir Chrome DevTools
   - Ir a pestaña "Lighthouse"
   - Ejecutar auditoría de accesibilidad
   - Revisar puntuación y problemas

---

## 📚 Archivos Relacionados

- **Script de Tests**: `scripts/dev/navigation-accessibility-tests.js`
- **Script de Verificación Tab Order**: `scripts/dev/verify-tab-order.js`
- **Documentación Tab Order**: `docs/NAV_132_VERIFICACION_TAB_ORDER.md`
- **Componente Sidebar**: `frontend/components/sidebar.html`
- **JavaScript Layout**: `frontend/js/layout.js`
- **Estilos CSS**: `frontend/css/05-objects/_layout.css`
- **Estilos Accesibilidad**: `frontend/css/04-elements/_accessibility.css`

---

## 🎯 Integración en CI/CD (Futuro)

Para integrar estos tests en CI/CD:

```javascript
// Ejemplo de integración con Puppeteer
const puppeteer = require('puppeteer');

async function runAccessibilityTests() {
    const browser = await puppeteer.launch();
    const page = await browser.newPage();
    await page.goto('http://localhost/SaSS_Workee/dashboard');
    
    // Cargar script de tests
    await page.addScriptTag({
        path: './scripts/dev/navigation-accessibility-tests.js'
    });
    
    // Ejecutar tests
    const results = await page.evaluate(() => {
        return NavigationAccessibilityTests.runAll();
    });
    
    // Verificar resultados
    const totalFailed = Object.values(results).reduce((sum, result) => sum + result.failed, 0);
    if (totalFailed > 0) {
        throw new Error(`Accessibility tests failed: ${totalFailed} issues found`);
    }
    
    await browser.close();
}
```

---

## 📝 Notas

- Los tests verifican aspectos técnicos de accesibilidad, pero **no reemplazan** pruebas manuales con screen readers reales.
- Algunos aspectos (como contraste de colores) son aproximaciones. Para verificación precisa, usar herramientas especializadas.
- Los tests se ejecutan en el contexto del navegador actual. Verificar en diferentes navegadores y dispositivos.

---

**Última actualización**: 2025-01-XX
**Responsable**: Equipo de Desarrollo Workee

