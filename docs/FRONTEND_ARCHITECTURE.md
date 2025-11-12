# Arquitectura Frontend - Workee SaaS

## Estructura de Directorios

```
frontend/
├── pages/              # Páginas HTML (una por vista)
│   ├── owner/
│   ├── empresa/
│   └── temporera/
├── js/
│   ├── core/           # Utilidades core, helpers, servicios
│   │   ├── api.js      # Cliente API centralizado (OBLIGATORIO)
│   │   └── components-loader.js
│   ├── components/     # Componentes JS reutilizables
│   │   ├── data-table.js
│   │   ├── modal.js
│   │   ├── toast.js
│   │   ├── empty-state.js
│   │   ├── loading-spinner.js
│   │   └── form-validator.js
│   ├── modules/         # Módulos por funcionalidad
│   │   ├── calendar/
│   │   ├── groups/
│   │   ├── requests/
│   │   ├── worklogs/
│   │   └── work-orders/
│   └── [legacy]/        # Archivos antiguos (a refactorizar)
├── css/
│   ├── theme.css        # Variables CSS y base (OBLIGATORIO)
│   ├── components/      # Estilos de componentes
│   │   ├── components.css
│   │   ├── toast.css
│   │   ├── modal.css
│   │   ├── data-table.css
│   │   └── ...
│   ├── layout/          # Estilos de layout
│   └── pages/           # Estilos específicos por página (mínimos)
```

## Componentes Reutilizables

### 1. API Client (`js/core/api.js`)

**OBLIGATORIO**: Usar para TODAS las llamadas API. NUNCA usar `fetch()` directamente.

```javascript
// ✅ CORRECTO
const response = await API.get('/work-orders', { page: 1 });
if (response && response.success) {
    // Usar response.data
}

// ❌ INCORRECTO
const response = await fetch('/api/v1/work-orders');
```

**Características:**
- Autenticación automática (JWT + refresh token)
- Manejo de errores global
- Traducción automática de claves de error
- Respuestas JSON consistentes

### 2. DataTable (`js/components/data-table.js`)

Componente reutilizable para todas las tablas con paginación, ordenamiento y filtros.

```javascript
const table = new DataTable(container, {
    columns: [
        { key: 'name', label: 'Nombre', sortable: true },
        { key: 'actions', label: 'Acciones', render: (row) => `<button>Editar</button>` }
    ],
    fetchData: async (page, pageSize, sortBy, sortDir, filters) => {
        const response = await API.get('/endpoint', { page, page_size: pageSize, ...filters });
        return { data: response.data, total: response.total };
    },
    pageSize: 20
});
```

### 3. Toast (`js/components/toast.js`)

Sistema de notificaciones toast. Reemplaza `alert()` y mensajes inline.

```javascript
// ✅ CORRECTO
Toast.success('Operación exitosa');
Toast.error('Error al guardar');
Toast.warning('Advertencia');
Toast.info('Información');

// ❌ INCORRECTO
alert('Operación exitosa');
```

### 4. Modal (`js/components/modal.js`)

Modal reutilizable. Wrapper sobre Bootstrap Modal con API simplificada.

```javascript
// Modal simple
const modal = new Modal({
    title: 'Título',
    body: '<p>Contenido</p>',
    footer: '<button>OK</button>'
});
modal.show();

// Confirmación rápida
const confirmed = await Modal.confirm({
    title: 'Confirmar',
    message: '¿Está seguro?'
});
```

### 5. EmptyState (`js/components/empty-state.js`)

Estado vacío con mensaje y acción opcional.

```javascript
const emptyState = new EmptyState(container, {
    icon: '📭',
    title: 'No hay datos',
    message: 'No se encontraron elementos',
    action: {
        label: 'Crear nuevo',
        callback: () => { /* ... */ }
    }
});
emptyState.show();
```

### 6. LoadingSpinner (`js/components/loading-spinner.js`)

Indicador de carga reutilizable.

```javascript
// Instancia
const spinner = new LoadingSpinner(container);
spinner.show('Cargando...');
spinner.hide();

// Estático
LoadingSpinner.show(container, 'Cargando...');
LoadingSpinner.hide(container);
```

### 7. FormValidator (`js/components/form-validator.js`)

Validador de formularios con validación en tiempo real.

```javascript
const validator = new FormValidator(form, {
    rules: {
        email: { required: true, email: true },
        password: { required: true, minLength: 8 }
    },
    messages: {
        email: { required: 'El email es requerido' }
    },
    onSubmit: (data) => {
        // Procesar formulario
    }
});
```

## Carga de Componentes

### En HTML

```html
<!-- CSS -->
<link rel="stylesheet" href="/frontend/css/theme.css">
<link rel="stylesheet" href="/frontend/css/components/components.css">

<!-- JS - Cargar componentes primero -->
<script src="/frontend/js/core/components-loader.js"></script>
<!-- Luego el JS específico de la página -->
<script src="/frontend/js/modules/work-orders/work-orders-list.js"></script>
```

### Carga Dinámica

Los componentes se cargan automáticamente al incluir `components-loader.js`.

## Patrones de Uso

### 1. Llamadas API

```javascript
// Siempre usar API client
const response = await API.get('/work-orders', { page: 1 });

if (response && response.success) {
    // Procesar datos
    const workOrders = response.data;
} else {
    // Mostrar error con Toast
    Toast.error(response?.message || 'Error desconocido');
}
```

### 2. Tablas de Datos

```javascript
// Usar DataTable en lugar de renderizar manualmente
const table = new DataTable(container, {
    columns: [...],
    fetchData: async (...) => {
        const response = await API.get('/endpoint', params);
        return { data: response.data, total: response.total };
    }
});
```

### 3. Notificaciones

```javascript
// Usar Toast en lugar de alert()
Toast.success('Guardado correctamente');
Toast.error('Error al guardar');
```

### 4. Confirmaciones

```javascript
// Usar Modal.confirm() en lugar de confirm()
const confirmed = await Modal.confirm({
    title: 'Confirmar',
    message: '¿Está seguro?'
});
```

### 5. Estados Vacíos

```javascript
// Usar EmptyState en lugar de mensajes inline
if (data.length === 0) {
    EmptyState.show(container, {
        title: 'No hay datos',
        message: 'No se encontraron elementos'
    });
}
```

## Refactorización de Páginas Existentes

### Antes (Código Antiguo)

```javascript
// ❌ INCORRECTO
async function loadData() {
    const token = localStorage.getItem('access_token');
    const response = await fetch('/api/v1/endpoint', {
        headers: { 'Authorization': `Bearer ${token}` }
    });
    const data = await response.json();
    // Renderizar manualmente...
}

function showError(message) {
    alert(message);
}
```

### Después (Código Refactorizado)

```javascript
// ✅ CORRECTO
async function loadData() {
    const response = await API.get('/endpoint');
    if (response && response.success) {
        // Usar DataTable o renderizar con componentes
    } else {
        Toast.error(response?.message || 'Error desconocido');
    }
}
```

## Mejoras de Performance

1. **Lazy Loading**: Cargar componentes solo cuando se necesiten
2. **Debounce**: En búsquedas y filtros (300ms mínimo)
3. **Virtual Scrolling**: Para listas grandes (>100 items)
4. **Paginación Server-side**: Para >1000 registros
5. **Cache**: Usar localStorage/sessionStorage para datos que cambian poco

## Mejoras de Accesibilidad

1. **ARIA Labels**: Todos los elementos interactivos deben tener `aria-label`
2. **Navegación por Teclado**: Tab, Enter, flechas funcionan correctamente
3. **Focus Visible**: Indicadores de foco claros
4. **Contraste**: Mínimo WCAG AA
5. **Screen Readers**: Textos alternativos y roles ARIA apropiados

## Testing de Componentes

Cada componente debe tener tests que validen:
- Estados: loading, error, empty, success
- Interacciones: click, hover, focus
- Integración con APIs: mockear respuestas
- Compatibilidad: diferentes navegadores

## Migración Gradual

1. **Fase 1**: Crear componentes reutilizables ✅
2. **Fase 2**: Refactorizar páginas críticas (en progreso)
3. **Fase 3**: Migrar todas las páginas
4. **Fase 4**: Eliminar código legacy

## Referencias

- `frontend/js/core/api.js` - Cliente API
- `frontend/js/components/` - Componentes reutilizables
- `frontend/js/modules/work-orders/work-orders-list.js` - Ejemplo de refactorización

