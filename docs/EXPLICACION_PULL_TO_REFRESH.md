# 📱 Explicación: Pull to Refresh en Workee

## 🔍 ¿Qué es Pull to Refresh?

**Pull to Refresh** es un gesto móvil donde el usuario desliza hacia abajo desde la parte superior de la pantalla para actualizar el contenido, similar a aplicaciones móviles nativas.

## 🎯 Dónde está implementado

### 1. **Dashboard** (`dashboard.js`)
- **Elemento:** `#main-content` (todo el contenido de la página)
- **Cuándo aparece:** Solo en móvil (ancho < 992px)
- **Qué hace:** Recarga todos los datos del dashboard:
  - Información del usuario
  - Información del tenant
  - Solicitudes activas
  - Datos de engagement

```javascript
// En dashboard.js (línea 152-168)
PullToRefresh.init(mainContent, {
  onRefresh: async () => {
    loadUserInfo();
    loadTenantInfo();
    loadActiveRequests();
    initializeEngagement();
  },
  threshold: 80,  // Distancia mínima: 80px
  resistance: 2.5  // Resistencia: el pull se siente más difícil
});
```

### 2. **DataTable** (`data-table.js`)
- **Elemento:** `.data-table__container` (solo el contenedor de la tabla)
- **Cuándo aparece:** Solo en móvil (ancho < 992px) y solo si `pullToRefresh: true` en opciones
- **Qué hace:** Recarga los datos de la tabla (vuelve a página 1)

```javascript
// En data-table.js (línea 170-186)
PullToRefresh.init(tableContainerEl, {
  onRefresh: () => {
    this.currentPage = 1;
    return this.loadData();
  },
  threshold: 80,
  resistance: 2.5
});
```

## ⚠️ Problema: Dos Pull-to-Refresh

### Pull-to-Refresh NATIVO del navegador
- **Qué es:** El navegador móvil (Chrome, Safari, etc.) tiene su propio pull-to-refresh
- **Cuándo aparece:** Cuando deslizas desde la parte superior y el scroll está en `window` o `body`
- **Qué hace:** Recarga toda la página (F5 completo)
- **Problema:** Puede interferir con nuestro pull-to-refresh personalizado

### Pull-to-Refresh PERSONALIZADO
- **Qué es:** El que implementamos en JavaScript
- **Cuándo aparece:** Cuando deslizas desde la parte superior y el scroll está en el elemento configurado
- **Qué hace:** Solo recarga los datos específicos (sin recargar toda la página)
- **Ventaja:** Más rápido, no pierde el estado de la aplicación

## 🔧 Cómo funciona técnicamente

### Condiciones para activarse:
1. ✅ **Solo en móvil:** `window.innerWidth < 992`
2. ✅ **Scroll en la parte superior:** `scrollTop === 0` o `window.scrollY === 0`
3. ✅ **Touch dentro del elemento:** El touch debe estar dentro del área del elemento
4. ✅ **Movimiento hacia abajo:** `deltaY > 10px` (threshold mínimo)
5. ✅ **Distancia suficiente:** `pullDistance >= 80px` (threshold)

### Proceso:
1. **Touch Start:** Detecta cuando empiezas a tocar
2. **Touch Move:** Mide la distancia del deslizamiento
3. **Aplicar resistencia:** `pullDistance = deltaY / 2.5` (hace que se sienta más difícil)
4. **Mostrar indicador:** Aparece el spinner y el texto
5. **Touch End:** Si alcanzaste 80px, ejecuta `onRefresh()`

## 🐛 Por qué a veces aparece uno y otras veces otro

### Escenario 1: Pull-to-Refresh NATIVO del navegador
- **Cuándo:** Cuando el scroll está en `window` o `body` (no en un elemento específico)
- **Por qué:** El navegador detecta el gesto antes que nuestro código
- **Resultado:** Recarga toda la página

### Escenario 2: Pull-to-Refresh PERSONALIZADO
- **Cuándo:** Cuando el scroll está en `#main-content` o `.data-table__container`
- **Por qué:** Nuestro código detecta el gesto y previene el comportamiento nativo
- **Resultado:** Solo recarga los datos específicos

## ✅ Solución: Prevenir Pull-to-Refresh Nativo

Para evitar que aparezca el pull-to-refresh nativo del navegador, debemos:

1. **Agregar CSS:** `overscroll-behavior-y: contain` en el body/html
2. **Prevenir en JavaScript:** `e.preventDefault()` cuando detectamos nuestro pull-to-refresh

## 📊 Resumen Visual

```
┌─────────────────────────────────────┐
│  DASHBOARD (main-content)          │
│  ┌───────────────────────────────┐ │
│  │ Pull-to-Refresh Personalizado │ │ ← Solo recarga datos
│  │ (si scroll en main-content)   │ │
│  └───────────────────────────────┘ │
│                                     │
│  ┌───────────────────────────────┐ │
│  │ DATA TABLE                     │ │
│  │ ┌───────────────────────────┐ │ │
│  │ │ Pull-to-Refresh Personalizado│ │ │ ← Solo recarga tabla
│  │ │ (si scroll en container)    │ │ │
│  │ └───────────────────────────┘ │ │
│  └───────────────────────────────┘ │
└─────────────────────────────────────┘
         ↓ (si scroll en window/body)
┌─────────────────────────────────────┐
│  Pull-to-Refresh NATIVO navegador   │ ← Recarga toda la página
└─────────────────────────────────────┘
```

## 🎯 Recomendación

Para una experiencia consistente:
- ✅ Usar pull-to-refresh personalizado en elementos específicos
- ✅ Prevenir el pull-to-refresh nativo del navegador
- ✅ Solo activar en móvil (< 992px)

