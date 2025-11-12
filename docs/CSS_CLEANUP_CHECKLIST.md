# Checklist de Verificación Antes de Eliminar Código CSS

**⚠️ OBLIGATORIO: Este checklist DEBE completarse antes de eliminar cualquier código CSS**

## Propósito

Este checklist asegura que la eliminación de código CSS duplicado o muerto no rompa funcionalidad existente. **NUNCA eliminar código sin completar este checklist.**

## Checklist de Verificación

### 1. Identificación del Código a Eliminar

- [ ] **Identificar archivo(s) CSS** que contienen el código a eliminar
- [ ] **Identificar clases/reglas específicas** a eliminar
- [ ] **Documentar propósito original** del código (si es conocido)
- [ ] **Verificar si es código duplicado o muerto** usando scripts de análisis
- [ ] **Revisar reportes de análisis** (`CSS_DUPLICATE_ANALYSIS.md`, `CSS_UNUSED_ANALYSIS.md`)

### 2. Búsqueda de Uso en Código

- [ ] **Buscar en HTML:** `grep -r "class-name" frontend/pages/ frontend/components/`
- [ ] **Buscar en JavaScript:** `grep -r "class-name" frontend/js/`
- [ ] **Buscar en templates dinámicos:** Revisar código que genera HTML dinámicamente
- [ ] **Buscar en librerías externas:** Verificar si FullCalendar, SortableJS, Bootstrap usan la clase
- [ ] **Buscar en comentarios:** Verificar si hay referencias en comentarios del código

### 3. Verificación de Páginas Afectadas

- [ ] **Listar todas las páginas** que importan el archivo CSS a modificar
- [ ] **Verificar rutas en `index.php`** para identificar páginas afectadas
- [ ] **Revisar `main.css`** para ver qué archivos se importan
- [ ] **Documentar páginas afectadas** antes de eliminar

### 4. Verificación Visual (Antes de Eliminar)

#### 4.1. Navegadores

- [ ] **Chrome** (última versión)
  - [ ] Verificar que todas las páginas afectadas se ven correctamente
  - [ ] Verificar que no hay errores en consola
  - [ ] Verificar que no hay regresiones visuales

- [ ] **Firefox** (última versión)
  - [ ] Verificar que todas las páginas afectadas se ven correctamente
  - [ ] Verificar que no hay errores en consola
  - [ ] Verificar que no hay regresiones visuales

- [ ] **Safari** (si está disponible)
  - [ ] Verificar que todas las páginas afectadas se ven correctamente
  - [ ] Verificar que no hay errores en consola
  - [ ] Verificar que no hay regresiones visuales

#### 4.2. Tamaños de Pantalla (Responsive)

- [ ] **Mobile (< 576px)**
  - [ ] Verificar layout en móvil
  - [ ] Verificar que elementos interactivos son táctiles (mínimo 44x44px)
  - [ ] Verificar que no hay overflow horizontal
  - [ ] Verificar que menús y navegación funcionan

- [ ] **Tablet (576px - 991px)**
  - [ ] Verificar layout en tablet
  - [ ] Verificar que grid se adapta correctamente
  - [ ] Verificar que sidebar se comporta correctamente

- [ ] **Desktop (≥ 992px)**
  - [ ] Verificar layout en desktop
  - [ ] Verificar que todos los elementos están visibles
  - [ ] Verificar que hover effects funcionan

#### 4.3. Estados Interactivos

- [ ] **Hover**
  - [ ] Verificar hover en botones
  - [ ] Verificar hover en cards
  - [ ] Verificar hover en enlaces
  - [ ] Verificar hover en elementos de navegación

- [ ] **Focus**
  - [ ] Verificar focus visible en todos los elementos interactivos
  - [ ] Verificar navegación por teclado (Tab, Enter, flechas)
  - [ ] Verificar que focus ring es visible

- [ ] **Active**
  - [ ] Verificar estados active en botones
  - [ ] Verificar estados active en elementos de navegación

- [ ] **Disabled**
  - [ ] Verificar estados disabled en botones
  - [ ] Verificar estados disabled en inputs
  - [ ] Verificar opacidad y cursor en elementos disabled

#### 4.4. Componentes Dinámicos

- [ ] **Modales**
  - [ ] Abrir y cerrar modales
  - [ ] Verificar backdrop
  - [ ] Verificar animaciones de entrada/salida
  - [ ] Verificar que focus se maneja correctamente

- [ ] **Dropdowns**
  - [ ] Abrir y cerrar dropdowns
  - [ ] Verificar posicionamiento
  - [ ] Verificar que se cierran al hacer click fuera

- [ ] **Tooltips**
  - [ ] Verificar que tooltips aparecen
  - [ ] Verificar posicionamiento
  - [ ] Verificar que desaparecen correctamente

- [ ] **Toasts/Notificaciones**
  - [ ] Verificar que toasts aparecen
  - [ ] Verificar animaciones
  - [ ] Verificar auto-dismiss
  - [ ] Verificar diferentes tipos (success, error, warning, info)

- [ ] **Tabs**
  - [ ] Cambiar entre tabs
  - [ ] Verificar estados activos
  - [ ] Verificar contenido de cada tab

- [ ] **Accordions**
  - [ ] Expandir y colapsar
  - [ ] Verificar animaciones
  - [ ] Verificar estados activos

#### 4.5. Componentes Específicos del Sistema

- [ ] **DataTable**
  - [ ] Verificar paginación
  - [ ] Verificar ordenamiento
  - [ ] Verificar filtros
  - [ ] Verificar selección de filas
  - [ ] Verificar acciones por fila

- [ ] **Calendario (FullCalendar)**
  - [ ] Verificar eventos se muestran correctamente
  - [ ] Verificar drag & drop
  - [ ] Verificar redimensionamiento
  - [ ] Verificar colores por estado
  - [ ] Verificar vistas (mes, semana, día)

- [ ] **Group Builder (SortableJS)**
  - [ ] Verificar drag & drop
  - [ ] Verificar estados de arrastre
  - [ ] Verificar feedback visual

- [ ] **Formularios**
  - [ ] Verificar validación en tiempo real
  - [ ] Verificar mensajes de error
  - [ ] Verificar estados de éxito
  - [ ] Verificar campos requeridos

- [ ] **Dashboards**
  - [ ] Verificar KPI cards
  - [ ] Verificar grid layout
  - [ ] Verificar gráficos (si aplica)
  - [ ] Verificar filtros

### 5. Tests Funcionales

- [ ] **Login/Logout**
  - [ ] Verificar que login funciona
  - [ ] Verificar que logout funciona
  - [ ] Verificar redirección después de login

- [ ] **Navegación**
  - [ ] Verificar que todos los enlaces funcionan
  - [ ] Verificar breadcrumbs
  - [ ] Verificar menú lateral
  - [ ] Verificar que items activos se marcan correctamente

- [ ] **Formularios**
  - [ ] Crear registro (ej: nueva solicitud, nuevo grupo)
  - [ ] Editar registro existente
  - [ ] Eliminar registro
  - [ ] Verificar validaciones

- [ ] **Listados**
  - [ ] Verificar que listas se cargan
  - [ ] Verificar paginación
  - [ ] Verificar filtros
  - [ ] Verificar búsqueda

- [ ] **Acciones**
  - [ ] Verificar botones de acción
  - [ ] Verificar confirmaciones
  - [ ] Verificar feedback (toasts)

### 6. Verificación de Accesibilidad

- [ ] **Contraste de colores**
  - [ ] Verificar contraste WCAG AA mínimo
  - [ ] Usar herramienta (WAVE, axe DevTools)

- [ ] **Navegación por teclado**
  - [ ] Verificar que todos los elementos interactivos son accesibles por teclado
  - [ ] Verificar orden de tab
  - [ ] Verificar que skip links funcionan

- [ ] **Screen readers**
  - [ ] Verificar ARIA labels
  - [ ] Verificar que elementos decorativos tienen `aria-hidden="true"`
  - [ ] Verificar que elementos interactivos tienen labels descriptivos

- [ ] **Focus visible**
  - [ ] Verificar que todos los elementos tienen focus visible
  - [ ] Verificar que outline es visible y contrastado

### 7. Verificación de Performance

- [ ] **Tiempo de carga**
  - [ ] Verificar que no hay regresión en tiempo de carga
  - [ ] Verificar que CSS se carga correctamente
  - [ ] Verificar que no hay errores 404 de recursos CSS

- [ ] **Renderizado**
  - [ ] Verificar que no hay CLS (Cumulative Layout Shift)
  - [ ] Verificar que imágenes se cargan correctamente
  - [ ] Verificar que no hay FOUC (Flash of Unstyled Content)

### 8. Screenshots/Tests Visuales (Recomendado)

- [ ] **Tomar screenshots ANTES de eliminar**
  - [ ] Screenshot de cada página afectada
  - [ ] Screenshot en diferentes tamaños de pantalla
  - [ ] Screenshot de estados interactivos (hover, focus, active)
  - [ ] Screenshot de componentes dinámicos (modales, dropdowns)

- [ ] **Guardar screenshots** en carpeta `docs/screenshots/before/`

- [ ] **Tomar screenshots DESPUÉS de eliminar**
  - [ ] Mismas páginas y estados que antes
  - [ ] Comparar visualmente con screenshots anteriores

- [ ] **Guardar screenshots** en carpeta `docs/screenshots/after/`

### 9. Verificación de Consola del Navegador

- [ ] **Abrir DevTools** en todas las páginas afectadas
- [ ] **Verificar que no hay errores** en consola
- [ ] **Verificar que no hay warnings** relevantes
- [ ] **Verificar que no hay 404** de recursos CSS
- [ ] **Verificar que no hay errores de JavaScript** relacionados

### 10. Verificación de Código Relacionado

- [ ] **Revisar JavaScript relacionado**
  - [ ] Verificar que no hay código JS que dependa de las clases a eliminar
  - [ ] Verificar que no hay `querySelector` o `getElementsByClassName` que usen las clases

- [ ] **Revisar HTML relacionado**
  - [ ] Verificar que no hay HTML que use las clases directamente
  - [ ] Verificar que no hay templates que generen las clases

- [ ] **Revisar documentación**
  - [ ] Verificar que no hay documentación que mencione las clases
  - [ ] Actualizar documentación si es necesario

## Proceso de Eliminación Segura

### Paso 1: Preparación

1. Completar checklist de verificación (secciones 1-10)
2. Tomar screenshots antes (sección 8)
3. Crear branch de Git para la eliminación
4. Documentar qué se va a eliminar y por qué

### Paso 2: Eliminación Incremental

1. **Eliminar una clase/regla a la vez** (NO eliminar todo de una vez)
2. **Verificar después de cada eliminación:**
   - [ ] Probar página afectada
   - [ ] Verificar consola del navegador
   - [ ] Verificar que no hay regresiones visuales
3. **Si algo se rompe:**
   - [ ] Revertir cambio inmediatamente
   - [ ] Documentar por qué se rompió
   - [ ] Revisar si la clase realmente no se usa o si hay dependencia oculta

### Paso 3: Verificación Post-Eliminación

1. Completar checklist de verificación nuevamente (secciones 4-10)
2. Tomar screenshots después (sección 8)
3. Comparar screenshots antes/después
4. Ejecutar tests funcionales completos

### Paso 4: Documentación

1. Actualizar `CSS_DUPLICATE_ANALYSIS.md` o `CSS_UNUSED_ANALYSIS.md` con lo eliminado
2. Documentar en commit message qué se eliminó y por qué
3. Actualizar documentación si es necesario

## Excepciones y Casos Especiales

### Clases que NUNCA deben eliminarse sin verificación exhaustiva

- ✅ Clases de Bootstrap 5 (todas las clases de Bootstrap)
- ✅ Clases de FullCalendar (todas las `.fc-*`)
- ✅ Clases de SortableJS (`.dragging`, `.sortable-ghost`, etc.)
- ✅ Clases de utilidades (`.u-*`)
- ✅ Clases generadas dinámicamente por JavaScript
- ✅ Clases de componentes reutilizables (`.toast`, `.modal`, `.data-table`, etc.)

### Código comentado

- Si hay código CSS comentado, **NO eliminarlo** sin verificar:
  - [ ] ¿Por qué está comentado?
  - [ ] ¿Se usa en algún lugar?
  - [ ] ¿Es código legacy que podría necesitarse?
  - [ ] Si se elimina, documentar en commit message

### Código con comentarios "TODO" o "FIXME"

- **NO eliminar** código con comentarios "TODO" o "FIXME" sin revisar:
  - [ ] Leer el comentario
  - [ ] Verificar si el problema sigue existiendo
  - [ ] Si se elimina, crear issue/ticket para el problema mencionado

## Herramientas Recomendadas

### Para Verificación Visual

- **Chrome DevTools** - Para inspeccionar elementos y verificar estilos
- **Firefox DevTools** - Para verificar compatibilidad
- **Responsive Design Mode** - Para probar diferentes tamaños de pantalla
- **Lighthouse** - Para verificar performance y accesibilidad

### Para Verificación de Accesibilidad

- **WAVE** (Web Accessibility Evaluation Tool) - Extensión de navegador
- **axe DevTools** - Extensión de navegador
- **Contrast Checker** - Para verificar contraste de colores

### Para Screenshots

- **Screenshot tools** nativos del navegador
- **Responsively App** - Para screenshots en múltiples dispositivos
- **BrowserStack** - Para pruebas en diferentes navegadores (si está disponible)

## Checklist Rápido (Resumen)

Antes de eliminar cualquier código CSS, verificar:

- [ ] ✅ Buscado en HTML/JS
- [ ] ✅ Verificado en Chrome
- [ ] ✅ Verificado en Firefox
- [ ] ✅ Verificado en móvil
- [ ] ✅ Verificado en tablet
- [ ] ✅ Verificado en desktop
- [ ] ✅ Verificado hover/focus/active
- [ ] ✅ Verificado componentes dinámicos
- [ ] ✅ Verificado accesibilidad
- [ ] ✅ Verificado consola (sin errores)
- [ ] ✅ Tomado screenshots antes
- [ ] ✅ Creado branch de Git
- [ ] ✅ Documentado qué se elimina

**Solo después de completar este checklist, proceder con la eliminación incremental.**

## Referencias

- `docs/CSS_DUPLICATE_ANALYSIS.md` - Análisis de código duplicado
- `docs/CSS_UNUSED_ANALYSIS.md` - Análisis de código muerto
- `docs/CSS_ARCHITECTURE_GUIDE.md` - Guía de arquitectura CSS
- `docs/DESIGN_TOKENS_GUIDE.md` - Guía de design tokens

