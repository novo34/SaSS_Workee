# Cómo Ejecutar Tests de Accesibilidad de Navegación - Workee

**Guía Rápida para Ejecutar Tests Automatizados**

---

## 🚀 Ejecución Rápida (Recomendado)

### Opción 1: Script Helper (Más Fácil)

1. Abre la aplicación en el navegador (ej: `http://localhost/SaSS_Workee/dashboard`)
2. Abre las herramientas de desarrollador (F12)
3. Ve a la pestaña "Console"
4. Copia y pega este código:

```javascript
// Cargar y ejecutar tests automáticamente
const script = document.createElement('script');
script.src = '/SaSS_Workee/scripts/dev/ejecutar-tests-accesibilidad-navegacion.js';
document.head.appendChild(script);
```

5. Presiona Enter
6. Los tests se ejecutarán automáticamente y mostrarán los resultados en la consola

---

### Opción 2: Cargar Script Manualmente

1. Abre la aplicación en el navegador
2. Abre la consola (F12)
3. Copia y pega:

```javascript
// Cargar script de tests
const script = document.createElement('script');
script.src = '/SaSS_Workee/scripts/dev/navigation-accessibility-tests.js';
script.onload = () => {
    console.log('✅ Script cargado');
    NavigationAccessibilityTests.runAll();
};
document.head.appendChild(script);
```

4. Presiona Enter

---

### Opción 3: Ejecutar Tests Individuales

Si el script ya está cargado:

```javascript
// Ejecutar todos los tests
NavigationAccessibilityTests.runAll();

// O ejecutar tests individuales
NavigationAccessibilityTests.testKeyboardNavigation();
NavigationAccessibilityTests.testAriaLabels();
NavigationAccessibilityTests.testSemanticStructure();
NavigationAccessibilityTests.testFocusManagement();
NavigationAccessibilityTests.testSkipLinks();
NavigationAccessibilityTests.testColorContrast();
NavigationAccessibilityTests.testScreenReader();
```

---

## 📊 Interpretar Resultados

### Resultados Exitosos

```
🚀 Iniciando tests de accesibilidad de navegación...

📋 Test 1: Keyboard Navigation
✅ Keyboard Navigation: 25 elementos verificados correctamente

📋 Test 2: ARIA Labels
✅ ARIA Labels: 30 elementos verificados correctamente

...

📊 RESUMEN DE TESTS DE ACCESIBILIDAD
✅ Tests pasados: 150
❌ Tests fallidos: 0
⚠️ Advertencias: 0

🎉 ¡Todos los tests de accesibilidad pasaron correctamente!
```

### Resultados con Problemas

```
📋 Test 1: Keyboard Navigation
❌ Keyboard Navigation: 2 problemas encontrados
   - .nav-link.disabled: Elemento disabled sin tabindex="-1"
   - #sidebar-toggle: sidebar-toggle no tiene tabindex="-1" en desktop

📋 Test 2: ARIA Labels
⚠️ ARIA Labels: 3 advertencias
   - .nav-link: aria-label muy corto
   - .nav-icon: Icono sin aria-hidden="true"

...

❌ Hay problemas de accesibilidad que deben corregirse.
```

---

## 🔍 Ver Detalles de Problemas

```javascript
// Ver todos los resultados
console.log(window.navigationAccessibilityResults);

// Ver problemas específicos
console.log(window.navigationAccessibilityResults.keyboardNavigation.issues);
console.log(window.navigationAccessibilityResults.ariaLabels.warnings);

// Ver resumen
NavigationAccessibilityTests.printSummary();
```

---

## 📋 Checklist de Verificación Manual

Después de ejecutar los tests automatizados, verifica manualmente:

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
- [ ] Screen reader anuncia estado expandido/colapsado
- [ ] Screen reader puede navegar por breadcrumbs
- [ ] Screen reader ignora iconos decorativos

---

## 🐛 Troubleshooting

### Error: "NavigationAccessibilityTests is not defined"

**Solución:** El script no se cargó correctamente. Verifica la ruta:

```javascript
// Verificar ruta base
console.log(window.BASE_URL || '');

// Cargar con ruta absoluta
const script = document.createElement('script');
script.src = window.location.origin + '/SaSS_Workee/scripts/dev/navigation-accessibility-tests.js';
document.head.appendChild(script);
```

### Error: "Script failed to load"

**Solución:** Verifica que el archivo existe y la ruta es correcta:

```javascript
// Verificar que el archivo existe
fetch('/SaSS_Workee/scripts/dev/navigation-accessibility-tests.js')
    .then(response => {
        if (response.ok) {
            console.log('✅ Archivo encontrado');
        } else {
            console.error('❌ Archivo no encontrado:', response.status);
        }
    });
```

### Los tests no encuentran elementos

**Solución:** Asegúrate de estar en una página que tenga navegación:

- Dashboard
- Cualquier página con sidebar
- Páginas con breadcrumbs

---

## 📚 Referencias

- **Documentación Completa**: `docs/TESTS_ACCESIBILIDAD_NAVEGACION.md`
- **Script de Tests**: `scripts/dev/navigation-accessibility-tests.js`
- **Script Helper**: `scripts/dev/ejecutar-tests-accesibilidad-navegacion.js`
- **Verificación Tab Order**: `scripts/dev/verify-tab-order.js`
- **Documentación Tab Order**: `docs/NAV_132_VERIFICACION_TAB_ORDER.md`

---

**Última actualización**: 2025-01-XX
**Responsable**: Equipo de Desarrollo Workee

