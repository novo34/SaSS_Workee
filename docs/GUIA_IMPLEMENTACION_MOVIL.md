# Guía Práctica de Implementación Móvil - Workee

**Sprint 5.10: Ejemplos Prácticos de Uso de Componentes Móviles**

Esta guía proporciona ejemplos prácticos y casos de uso reales para implementar los componentes móviles en el sistema Workee.

---

## 📱 Casos de Uso Comunes

### 1. Implementar Bottom Sheet en un Formulario

**Escenario:** Formulario de creación de solicitud que debe ser bottom sheet en móvil.

```javascript
// En tu archivo JS (ej: staffing-requests.js)
async function showCreateRequestForm() {
    // Detectar si estamos en móvil
    const isMobile = window.innerWidth < 992;
    
    if (isMobile && typeof BottomSheet !== 'undefined') {
        // Usar Bottom Sheet en móvil
        const bottomSheet = new BottomSheet({
            title: 'Nueva Solicitud de Personal',
            body: `
                <form id="create-request-form">
                    <div class="form-group">
                        <label for="position">Posición</label>
                        <input type="text" id="position" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="quantity">Cantidad</label>
                        <input type="number" id="quantity" class="form-control" required>
                    </div>
                    <!-- Más campos... -->
                </form>
            `,
            footer: `
                <button type="button" class="btn btn-secondary" onclick="bottomSheet.hide()">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="submitRequest()">Crear Solicitud</button>
            `,
            fullscreen: false,
            onClose: () => {
                // Limpiar formulario si es necesario
            }
        });
        
        bottomSheet.show();
    } else {
        // Usar Modal estándar en desktop
        if (typeof Modal !== 'undefined') {
            Modal.confirm({
                title: 'Nueva Solicitud de Personal',
                body: '...',
                onConfirm: () => submitRequest()
            });
        }
    }
}
```

---

### 2. Agregar FAB para Acción Principal

**Escenario:** Página de solicitudes que necesita un botón flotante para "Nueva Solicitud" en móvil.

```javascript
// En staffing-requests.js
let fab = null;

function initFloatingActionButton() {
    // Solo en móvil
    if (window.innerWidth >= 992) return;
    
    if (typeof FloatingActionButton !== 'undefined') {
        fab = new FloatingActionButton({
            icon: 'plus',
            label: 'Nueva Solicitud',
            onClick: () => {
                showCreateRequestForm();
            },
            position: 'bottom-right',
            hideOnScroll: true
        });
        
        fab.show();
    }
}

// Inicializar cuando la página carga
document.addEventListener('DOMContentLoaded', () => {
    initFloatingActionButton();
    
    // Ocultar FAB cuando se abre el formulario
    // Mostrar FAB cuando se cierra
});

// Limpiar al salir de la página
window.addEventListener('beforeunload', () => {
    if (fab) {
        fab.destroy();
    }
});
```

---

### 3. Implementar Pull to Refresh en DataTable

**Escenario:** Tabla de solicitudes que debe recargarse con pull-to-refresh en móvil.

```javascript
// En staffing-requests.js
let dataTable = null;

function initRequestsTable() {
    const container = document.getElementById('requests-table-container');
    
    if (!container) return;
    
    dataTable = new DataTable(container, {
        endpoint: '/api/v1/staffing-requests',
        columns: [
            { key: 'id', label: 'ID', sortable: true },
            { key: 'position', label: 'Posición', sortable: true },
            { key: 'status', label: 'Estado', sortable: true },
            // Más columnas...
        ],
        // Pull to refresh activado automáticamente en móvil
        pullToRefresh: true, // Default: true en móvil
        swipeActions: true, // Swipe para acciones rápidas
        onRefresh: async () => {
            // Recargar datos
            await dataTable.loadData();
        }
    });
}
```

---

### 4. Agregar Swipe Actions a Filas de Tabla

**Escenario:** Permitir swipe left/right en filas para acciones rápidas (editar, eliminar).

```javascript
// En staffing-requests.js
function initSwipeActions() {
    // DataTable ya tiene soporte integrado para swipe actions
    // Solo necesitas configurar las acciones en las columnas
    
    dataTable = new DataTable(container, {
        // ... otras opciones
        swipeActions: {
            left: [
                {
                    label: 'Editar',
                    icon: 'edit',
                    color: 'primary',
                    action: (row) => {
                        editRequest(row.id);
                    }
                }
            ],
            right: [
                {
                    label: 'Eliminar',
                    icon: 'trash',
                    color: 'danger',
                    action: (row) => {
                        deleteRequest(row.id);
                    }
                }
            ]
        }
    });
}
```

---

### 5. Optimizar Formularios para Móvil

**Escenario:** Formulario largo que necesita botones sticky en bottom en móvil.

```html
<!-- En tu HTML -->
<form id="long-form" class="form-modern">
    <!-- Campos del formulario -->
    <div class="form-group">
        <label for="field1">Campo 1</label>
        <input type="text" id="field1" class="form-control">
    </div>
    <!-- Más campos... -->
    
    <!-- Botones de acción (se vuelven sticky automáticamente en móvil) -->
    <div class="form-actions form-actions-mobile">
        <button type="button" class="btn btn-secondary">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
    </div>
</form>
```

```javascript
// El FormMobileOptimizer se inicializa automáticamente
// Pero puedes forzar optimización manual si es necesario:
if (typeof FormMobileOptimizer !== 'undefined') {
    FormMobileOptimizer.optimizeForm(document.getElementById('long-form'));
}
```

---

### 6. Implementar Lazy Loading de Imágenes

**Escenario:** Galería de imágenes que debe cargarse progresivamente.

```html
<!-- En tu HTML -->
<div class="image-gallery">
    <img src="/images/photo1.jpg" alt="Foto 1" loading="lazy">
    <img src="/images/photo2.jpg" alt="Foto 2" loading="lazy">
    <!-- Más imágenes... -->
</div>
```

```javascript
// El ImageMobileOptimizer se inicializa automáticamente
// Pero puedes optimizar manualmente:
if (typeof ImageMobileOptimizer !== 'undefined') {
    const images = document.querySelectorAll('.image-gallery img');
    images.forEach(img => {
        ImageMobileOptimizer.optimizeImage(img);
    });
}
```

---

### 7. Optimizar Dashboard para Móvil

**Escenario:** Dashboard con múltiples KPI cards y gráficos.

```html
<!-- En dashboard.html -->
<div class="dashboard-grid">
    <!-- KPI Cards (se apilan automáticamente en móvil) -->
    <div class="kpi-card">
        <div class="kpi-card__value">150</div>
        <div class="kpi-card__label">Solicitudes Activas</div>
    </div>
    <!-- Más KPI cards... -->
    
    <!-- Gráficos (scroll horizontal en móvil) -->
    <div class="dashboard-chart-container">
        <canvas id="requests-chart"></canvas>
    </div>
</div>
```

```javascript
// En dashboard.js
// El dashboard ya está optimizado automáticamente
// Pero puedes forzar lazy loading de gráficos secundarios:
if (typeof PerformanceMobile !== 'undefined') {
    // Cargar gráficos secundarios solo cuando sean visibles
    const secondaryCharts = document.querySelectorAll('.dashboard-chart-container:not(:first-child)');
    secondaryCharts.forEach(chart => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    loadChart(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { rootMargin: '100px' });
        
        observer.observe(chart);
    });
}
```

---

### 8. Implementar Virtual Scrolling en Lista Larga

**Escenario:** Lista de más de 50 items que necesita virtual scrolling.

```javascript
// En tu archivo JS
function initVirtualScrollList() {
    const container = document.getElementById('long-list-container');
    const items = [/* array de items */];
    
    if (typeof VirtualScroll !== 'undefined' && items.length > 50) {
        VirtualScroll.init(container, {
            items: items,
            renderItem: (item) => {
                return `
                    <div class="list-item">
                        <h3>${item.title}</h3>
                        <p>${item.description}</p>
                    </div>
                `;
            },
            itemHeight: 80, // Altura estimada de cada item
            buffer: 5 // Items extra a renderizar fuera del viewport
        });
    } else {
        // Renderizar normalmente si hay pocos items
        renderListNormally(items);
    }
}
```

---

### 9. Usar Cache para Mejorar Performance

**Escenario:** Datos que no cambian frecuentemente y pueden cachearse.

```javascript
// En dashboard.js o cualquier página
async function loadDashboardData() {
    if (typeof PerformanceMobile !== 'undefined') {
        // Usar cache con TTL de 2 minutos
        const data = await PerformanceMobile.getCachedData('dashboard', async () => {
            // Función para obtener datos si no están en cache
            const response = await API.get('/dashboard/stats');
            return response.data;
        }, 120000); // 2 minutos
        
        renderDashboard(data);
    } else {
        // Fallback sin cache
        const response = await API.get('/dashboard/stats');
        renderDashboard(response.data);
    }
}
```

---

### 10. Optimizar Performance de Scroll

**Escenario:** Página con scroll events que necesita throttling.

```javascript
// En tu archivo JS
function initScrollOptimization() {
    const scrollableElement = document.getElementById('scrollable-content');
    
    if (typeof PerformanceMobile !== 'undefined') {
        // Throttle en scroll events (100ms)
        const throttledScroll = PerformanceMobile.throttle(() => {
            // Tu lógica de scroll
            updateStickyHeader();
            loadMoreContentIfNeeded();
        }, 100);
        
        scrollableElement.addEventListener('scroll', throttledScroll);
    } else {
        // Fallback sin throttle (menos eficiente)
        scrollableElement.addEventListener('scroll', () => {
            updateStickyHeader();
            loadMoreContentIfNeeded();
        });
    }
}
```

---

## 🔧 Integración Automática

La mayoría de los componentes se inicializan automáticamente:

1. **FormMobileOptimizer**: Se inicializa en `UIInitializer.init()`
2. **ImageMobileOptimizer**: Se inicializa en `UIInitializer.init()`
3. **PullToRefresh**: Se activa automáticamente en DataTable si `pullToRefresh: true`
4. **SwipeGestures**: Se integra automáticamente en DataTable, Toast y Modal
5. **BottomSheet**: Modal se convierte automáticamente en bottom sheet en móvil

---

## 📝 Checklist de Implementación

Al implementar una nueva página móvil, verifica:

- [ ] Formularios usan `form-modern` class
- [ ] Botones de acción tienen `form-actions-mobile` class
- [ ] Imágenes tienen `loading="lazy"` o se optimizan con `ImageMobileOptimizer`
- [ ] DataTable tiene `pullToRefresh: true` (default)
- [ ] Modales se convierten en bottom sheet en móvil (automático)
- [ ] FAB agregado para acción principal (si aplica)
- [ ] Cache usado para datos que no cambian frecuentemente
- [ ] Virtual scrolling en listas > 50 items
- [ ] Throttle/debounce en eventos frecuentes (scroll, resize, input)

---

## 🐛 Troubleshooting

### El Bottom Sheet no aparece en móvil

**Solución:** Verifica que `Modal` esté usando `mobileMode: 'bottom-sheet'` (default) o que `BottomSheet` esté cargado.

### El FAB no se muestra

**Solución:** Verifica que `window.innerWidth < 992` y que `FloatingActionButton` esté cargado.

### Pull to Refresh no funciona

**Solución:** Verifica que `PullToRefresh` esté cargado y que el contenedor tenga altura suficiente para hacer scroll.

### Swipe actions no funcionan

**Solución:** Verifica que `SwipeGestures` esté cargado y que `swipeActions: true` esté configurado en DataTable.

---

## 📚 Referencias

- **Guía Completa**: `docs/MOBILE_OPTIMIZATION_GUIDE.md`
- **Testing Móvil**: `docs/TESTING_MOVIL_COMPLETO.md`
- **Cómo Ejecutar Testing**: `docs/COMO_EJECUTAR_TESTING_MOVIL.md`
- **Roadmap**: `docs/TODO_ROADMAP_COMPLETO.md`

---

**Última actualización**: 2025-01-XX
**Responsable**: Equipo de Desarrollo Workee

