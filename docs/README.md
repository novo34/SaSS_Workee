# Documentación del Proyecto Workee

Esta carpeta contiene toda la documentación del proyecto Workee SaaS.

## 📋 Índice de Documentación

### Guías de Inicio
- **QUICK_START.md** - Guía rápida de inicio y configuración inicial
- **ACCESO_OWNER.md** - Credenciales y acceso para usuarios Owner/Admin
- **ACCESO_USUARIOS.md** - Credenciales y acceso para usuarios de prueba

### Guías de Pruebas
- **COMO_PROBAR.md** - Guía general de pruebas del sistema
- **COMO_PROBAR_SPRINT2.md** - Guía específica para probar el Sprint 2 (Solicitudes y Propuestas)
- **TEST_GUIDE.md** - Guía de testing y casos de prueba

### Arquitectura CSS y Frontend
- **CSS_ARCHITECTURE_GUIDE.md** - Guía completa de arquitectura ITCSS, BEM y convenciones CSS
- **DESIGN_TOKENS_GUIDE.md** - Guía de uso de design tokens (variables CSS)
- **GUIA_IMAGENES_RESPONSIVAS.md** - Guía de imágenes responsivas y optimización
- **FRONTEND_ARCHITECTURE.md** - Arquitectura frontend moderna (componentes, módulos, API client)
- **AUDITORIA_CSS_CORPORATIVO.md** - Auditoría del sistema de diseño CSS corporativo
- **CSS_DUPLICATE_ANALYSIS.md** - Análisis de código CSS duplicado
- **CSS_UNUSED_ANALYSIS.md** - Análisis de código CSS muerto (no usado)
- **CSS_CLEANUP_CHECKLIST.md** - Checklist de verificación antes de eliminar código CSS
- **CSS_DUPLICATE_CLEANUP_LOG.md** - Log de eliminación de código duplicado
- **CSS_UNUSED_CLEANUP_LOG.md** - Log de eliminación de código muerto
- **CSS_VALIDATION_FINAL_REPORT.md** - Reporte de validación final post-eliminación

### Especificación y Diseño
- **Workee_kickoff_v_1.md** - Especificación completa del proyecto (documento principal)
- **ANALISIS_DISENO_IMPLEMENTADO.md** - Análisis del diseño implementado
- **RESUMEN_IMPLEMENTACION_DISENO.md** - Resumen de la implementación del diseño

### Resúmenes y Reportes
- **RESUMEN_CREDENCIALES.md** - Resumen de credenciales de acceso
- **RESUMEN_SPRINT2_VERIFICACION.md** - Resumen de verificación del Sprint 2
- **TODO_ROADMAP_COMPLETO.md** - Roadmap completo y tareas pendientes

## 📖 Documento Principal

**Workee_kickoff_v_1.md** es el documento de especificación oficial del proyecto. Debe ser consultado antes de desarrollar cualquier funcionalidad nueva.

## 🎨 Arquitectura CSS

El sistema usa **ITCSS (Inverted Triangle CSS)** y **Design Tokens** para una arquitectura CSS escalable y mantenible.

- **Guía de Arquitectura CSS**: Ver `CSS_ARCHITECTURE_GUIDE.md`
- **Guía de Design Tokens**: Ver `DESIGN_TOKENS_GUIDE.md`
- **Guía de Imágenes Responsivas**: Ver `GUIA_IMAGENES_RESPONSIVAS.md`
- **Análisis de Código Duplicado**: Ver `CSS_DUPLICATE_ANALYSIS.md`
- **Análisis de Código Muerto**: Ver `CSS_UNUSED_ANALYSIS.md`
- **Checklist de Limpieza CSS**: Ver `CSS_CLEANUP_CHECKLIST.md`
- **Logs de Limpieza**: Ver `CSS_DUPLICATE_CLEANUP_LOG.md`, `CSS_UNUSED_CLEANUP_LOG.md`
- **Validación Final**: Ver `CSS_VALIDATION_FINAL_REPORT.md`

### Estructura ITCSS

```
frontend/css/
├── 01-settings/    # Design tokens (theme.css)
├── 03-generic/     # Reset CSS
├── 04-elements/    # Elementos HTML base
├── 05-objects/     # Patrones de layout
├── 06-components/  # Componentes UI
├── 07-utilities/   # Utilidades CSS
└── pages/          # Estilos específicos por página
```

**Importar en HTML:**
```html
<link rel="stylesheet" href="/frontend/css/main.css">
```

## 🔍 Búsqueda Rápida

- **¿Cómo empezar?** → `QUICK_START.md`
- **¿Cómo probar el sistema?** → `COMO_PROBAR.md`
- **¿Cuáles son las credenciales?** → `ACCESO_OWNER.md` o `ACCESO_USUARIOS.md`
- **¿Qué funcionalidades están pendientes?** → `TODO_ROADMAP_COMPLETO.md`
- **¿Cuál es la especificación completa?** → `Workee_kickoff_v_1.md`
- **¿Cómo funciona el CSS?** → `CSS_ARCHITECTURE_GUIDE.md`
- **¿Cómo usar design tokens?** → `DESIGN_TOKENS_GUIDE.md`
- **¿Hay código CSS duplicado?** → `CSS_DUPLICATE_ANALYSIS.md`
- **¿Hay código CSS muerto?** → `CSS_UNUSED_ANALYSIS.md`
- **¿Cómo eliminar código CSS de forma segura?** → `CSS_CLEANUP_CHECKLIST.md`
- **¿Qué se eliminó y cómo?** → `CSS_DUPLICATE_CLEANUP_LOG.md`, `CSS_UNUSED_CLEANUP_LOG.md`
- **¿La validación final pasó?** → `CSS_VALIDATION_FINAL_REPORT.md`

