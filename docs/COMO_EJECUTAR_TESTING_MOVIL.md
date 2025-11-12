# Cómo Ejecutar el Script de Testing Móvil - Workee

**MOB-148: Testing Móvil Completo**

Esta guía explica cómo ejecutar el script de verificación automática de testing móvil.

---

## 📋 Métodos de Ejecución

### Método 1: Automático (Recomendado)

El script se carga **automáticamente** cuando estás en desarrollo (localhost o 127.0.0.1).

**Pasos:**

1. Abre la aplicación en el navegador: `http://localhost/SaSS_Workee/` (o tu URL de desarrollo)
2. Abre las **DevTools** (F12 o Clic derecho → Inspeccionar)
3. Ve a la pestaña **Console**
4. Deberías ver el mensaje: `📱 Mobile Testing Verification cargado. Ejecutar: MobileTestingVerification.runAll()`
5. Ejecuta en la consola:
   ```javascript
   MobileTestingVerification.runAll()
   ```

**Resultado:** Verás un reporte completo en la consola con todos los checks realizados.

---

### Método 2: Manual - Cargar Script desde Consola

Si el script no se carga automáticamente, puedes cargarlo manualmente:

**Pasos:**

1. Abre la aplicación en el navegador
2. Abre las **DevTools** (F12)
3. Ve a la pestaña **Console**
4. Ejecuta este código para cargar el script:
   ```javascript
   const script = document.createElement('script');
   script.src = '/scripts/dev/mobile-testing-verification.js';
   script.onload = () => {
       console.log('✅ Script cargado. Ejecutando verificación...');
       MobileTestingVerification.runAll();
   };
   document.head.appendChild(script);
   ```

**Nota:** Ajusta la ruta `/scripts/dev/mobile-testing-verification.js` según tu estructura de carpetas.

---

### Método 3: Desde el Checklist HTML

El checklist HTML tiene un botón para ejecutar el script automáticamente.

**Pasos:**

1. Abre el archivo: `scripts/dev/mobile-testing-checklist.html` en tu navegador
2. Haz clic en el botón **"🔍 Ejecutar Verificación Automática"**
3. El script se ejecutará y mostrará los resultados en la consola

---

### Método 4: Verificaciones Individuales

Puedes ejecutar verificaciones específicas en lugar de todas:

```javascript
// Verificar solo touch targets
MobileTestingVerification.verifyTouchTargets()

// Verificar solo font sizes
MobileTestingVerification.verifyFontSizes()

// Verificar solo spacing
MobileTestingVerification.verifySpacing()

// Verificar solo lazy loading
MobileTestingVerification.verifyLazyLoading()

// Verificar solo imágenes responsivas
MobileTestingVerification.verifyResponsiveImages()

// Verificar solo animaciones
MobileTestingVerification.verifyAnimations()

// Verificar solo accesibilidad
MobileTestingVerification.verifyAccessibility()
```

---

## 📱 Modo Móvil en Chrome DevTools

Para obtener resultados precisos, ejecuta el script en **modo móvil**:

**Pasos:**

1. Abre Chrome DevTools (F12)
2. Haz clic en el botón **Toggle device toolbar** (Ctrl+Shift+M o el ícono de dispositivo)
3. Selecciona un dispositivo móvil (ej: iPhone 12, Samsung Galaxy S21)
4. Asegúrate de que el viewport sea móvil (< 992px)
5. Ejecuta el script: `MobileTestingVerification.runAll()`

---

## 📊 Interpretar los Resultados

### Resultado Exitoso

```
✅ Todos los touch targets son >= 44px (25 elementos verificados)
✅ Todos los font sizes son >= 16px (150 elementos verificados)
✅ Espaciado adecuado entre elementos (25 elementos verificados)
✅ Lazy loading implementado correctamente (10 imágenes verificadas)
✅ Imágenes responsivas verificadas (10 imágenes verificadas)
✅ Animaciones verificadas (50 elementos verificados)
✅ Accesibilidad básica verificada

📊 RESUMEN DE VERIFICACIÓN:
========================
✅ touchTargets: PASSED
✅ fontSizes: PASSED
✅ spacing: PASSED
✅ lazyLoading: PASSED
✅ responsiveImages: PASSED
✅ animations: PASSED
✅ accessibility: PASSED

✅ VERIFICACIÓN GENERAL: PASSED
```

### Resultado con Issues

```
❌ 3 touch targets menores a 44px encontrados: [...]
⚠️ 5 font sizes menores a 16px encontrados: [...]
✅ Espaciado adecuado entre elementos (25 elementos verificados)
...

📊 RESUMEN DE VERIFICACIÓN:
========================
❌ touchTargets: FAILED
❌ fontSizes: FAILED
✅ spacing: PASSED
...

❌ VERIFICACIÓN GENERAL: FAILED
```

### Acceder a Resultados Detallados

Los resultados detallados se guardan en `window.mobileTestingResults`:

```javascript
// Ver todos los resultados
console.log(window.mobileTestingResults)

// Ver issues específicos
console.log(window.mobileTestingResults.touchTargets.issues)
console.log(window.mobileTestingResults.fontSizes.issues)
```

---

## 🔧 Solución de Problemas

### El script no se carga automáticamente

**Causa:** No estás en localhost o el archivo no existe.

**Solución:**
1. Verifica que estés en `localhost` o `127.0.0.1`
2. Verifica que el archivo existe en: `scripts/dev/mobile-testing-verification.js`
3. Usa el Método 2 para cargar manualmente

### Error: "MobileTestingVerification is not defined"

**Causa:** El script no se ha cargado aún.

**Solución:**
1. Espera a que aparezca el mensaje en consola: `📱 Mobile Testing Verification cargado`
2. O carga el script manualmente (Método 2)

### Los resultados no son precisos

**Causa:** Estás ejecutando en modo desktop.

**Solución:**
1. Activa el modo móvil en Chrome DevTools (Ctrl+Shift+M)
2. Selecciona un dispositivo móvil
3. Asegúrate de que el viewport sea < 992px
4. Recarga la página y ejecuta el script nuevamente

---

## 📝 Ejemplo Completo

```javascript
// 1. Cargar el script (si no está cargado automáticamente)
const script = document.createElement('script');
script.src = '/scripts/dev/mobile-testing-verification.js';
script.onload = () => {
    console.log('✅ Script cargado');
    
    // 2. Ejecutar todas las verificaciones
    MobileTestingVerification.runAll();
    
    // 3. Esperar un momento y ver resultados detallados
    setTimeout(() => {
        console.log('📊 Resultados detallados:', window.mobileTestingResults);
        
        // 4. Ver issues específicos
        if (window.mobileTestingResults.touchTargets.issues.length > 0) {
            console.warn('⚠️ Touch targets con problemas:');
            window.mobileTestingResults.touchTargets.issues.forEach(issue => {
                console.log(`  - ${issue.selector}: ${issue.width}x${issue.height}px`);
            });
        }
    }, 1000);
};
document.head.appendChild(script);
```

---

## 🎯 Mejores Prácticas

1. **Ejecuta en modo móvil**: Siempre usa Chrome DevTools en modo móvil para resultados precisos
2. **Ejecuta en diferentes páginas**: Verifica todas las páginas principales (Dashboard, Solicitudes, Calendario, etc.)
3. **Ejecuta después de cambios**: Ejecuta el script después de hacer cambios en CSS o HTML
4. **Revisa los issues**: No ignores los warnings, corrígelos para mejorar la experiencia móvil
5. **Combina con testing manual**: El script verifica aspectos técnicos, pero también haz testing manual en dispositivos reales

---

## 📚 Referencias

- **Guía completa de testing**: `docs/TESTING_MOVIL_COMPLETO.md`
- **Checklist HTML**: `scripts/dev/mobile-testing-checklist.html`
- **Script de verificación**: `scripts/dev/mobile-testing-verification.js`

---

**Última actualización**: 2025-01-XX
**Responsable**: Equipo de Desarrollo Workee

