# Plantillas de Tareas - Workee SaaS

> **Versión:** 1.0  
> **Última actualización:** 2025-01-XX  
> **Propósito:** Plantillas para tareas comunes de desarrollo

---

## Plantilla: Crear Nuevo Endpoint API

```
Objetivo: Crear endpoint API para [DESCRIPCIÓN]

Plan de referencia: [PLAN_ID] si aplica

Archivos a crear/modificar:
- backend/api/[nombre_endpoint].php - Crear endpoint
- backend/core/[middleware].php - Modificar si aplica

Requisitos:
- [ ] Middleware TenantMiddleware presente
- [ ] Middleware RBACMiddleware presente
- [ ] Prepared statements en queries SQL
- [ ] Respuesta JSON con formato estándar
- [ ] Claves de traducción para errores
- [ ] Validación de entradas
- [ ] Documentación en api-spec.yml

Testing:
- [ ] Test de aislamiento actualizado
- [ ] Probado manualmente
- [ ] Verificado en móvil
```

---

## Plantilla: Crear Nueva Página Frontend

```
Objetivo: Crear página [NOMBRE_PÁGINA] para [ROL/TENANT]

Plan de referencia: [PLAN_ID] si aplica

Archivos a crear/modificar:
- frontend/pages/[rol]/[nombre_pagina].html - Crear página
- frontend/js/modules/[modulo]/[nombre].js - Crear módulo JS si aplica
- frontend/css/pages/[nombre_pagina].css - Crear estilos si aplica

Requisitos:
- [ ] Importar theme.css
- [ ] Usar cliente API centralizado (no fetch directo)
- [ ] Usar componente DataTable para tablas
- [ ] Diseño mobile-first
- [ ] Componentes reutilizables usados
- [ ] Variables CSS (no colores hardcodeados)
- [ ] Traducciones con función t()

Testing:
- [ ] Probado en móvil (iOS y Android)
- [ ] Accesibilidad verificada
- [ ] Navegación por teclado funcional
```

---

## Plantilla: Crear Nuevo Componente Reutilizable

```
Objetivo: Crear componente [NOMBRE_COMPONENTE]

Plan de referencia: [PLAN_ID] si aplica

Archivos a crear:
- frontend/js/components/[nombre-componente].js - Componente JS
- frontend/css/06-components/[nombre-componente].css - Estilos CSS

Requisitos:
- [ ] Componente independiente y configurable
- [ ] Documentar props/parámetros
- [ ] Usar variables CSS
- [ ] Convención BEM en CSS
- [ ] Mobile-first responsive
- [ ] Accesibilidad WCAG AA

Uso:
```javascript
const component = new ComponentName(container, {
    // props documentadas
});
```

Testing:
- [ ] Probado en diferentes contextos
- [ ] Probado en móvil
- [ ] Accesibilidad verificada
```

---

## Plantilla: Crear Migración de Base de Datos

```
Objetivo: Crear migración para [DESCRIPCIÓN]

Plan de referencia: [PLAN_ID] si aplica

Archivo a crear:
- backend/db/YYYYMMDDHHMM__[descripcion].sql

Requisitos:
- [ ] Convención de nombre: YYYYMMDDHHMM__descripcion.sql
- [ ] Tablas nuevas incluyen tenant_id (excepto globales)
- [ ] Foreign Keys definidas apropiadamente
- [ ] Índices en campos de búsqueda frecuente
- [ ] Campos estándar: created_at, updated_at
- [ ] Script de rollback incluido si aplica

Validación:
- [ ] No modifica migración ya aplicada
- [ ] Sigue estructura de tablas existente
- [ ] Prueba de migración en desarrollo
```

---

## Plantilla: Refactorizar Código Existente

```
Objetivo: Refactorizar [MÓDULO/FUNCIONALIDAD]

Plan de referencia: [PLAN_ID] - REQUERIDO para refactorización

Archivos afectados:
- [lista de archivos]

Motivo:
- [Descripción del motivo de la refactorización]

Cambios propuestos:
- [Lista de cambios específicos]

Requisitos:
- [ ] No rompe funcionalidad existente
- [ ] Dependencias verificadas
- [ ] Tests actualizados
- [ ] Documentación actualizada

Riesgos:
- [Lista de riesgos potenciales]
- [Mitigaciones propuestas]
```

---

## Plantilla: Agregar Traducciones

```
Objetivo: Agregar traducciones para [MÓDULO/FUNCIONALIDAD]

Archivos a modificar:
- backend/lang/es/[namespace].php
- backend/lang/de/[namespace].php
- backend/lang/fr/[namespace].php
- backend/lang/it/[namespace].php

Claves a agregar:
- namespace.clave1: "Traducción"
- namespace.clave2: "Traducción con {variable}"

Requisitos:
- [ ] Formato: namespace.modulo.clave
- [ ] Todas las traducciones en todos los idiomas
- [ ] Variables documentadas
- [ ] Uso de función t() en código
```

---

## Referencias

- `specs/00-governance.mdc` - Reglas de fases
- `specs/04-change-safety-checklist.mdc` - Checklist de seguridad
- `specs/05-frontend-standards.mdc` - Estándares frontend
- `specs/06-backend-standards-php.mdc` - Estándares backend

