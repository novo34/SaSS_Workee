# Plan: Auditoría Completa del Repositorio y Backlog

> **Estado:** [DRAFT]  
> **Fecha:** 2025-01-XX  
> **Autor:** AI Assistant  
> **Referencia:** Estructura real del repositorio, `.cursorrules`, `docs/Workee_kickoff_v_1.md`

---

## Objetivo

Auditar TODO el repositorio (sin modificar código) y producir artefactos de especificación que documenten:

1. Mapa completo del frontend (rutas, páginas, módulos, helpers)
2. Mapa completo del backend (endpoints reales, middleware, base de datos)
3. Mapa de datos (tablas y relaciones detectadas)
4. Análisis de reutilización e integración con Planner
5. Riesgos de duplicidad y código muerto
6. Backlog jerárquico estructurado (Épicas → Historias → Tareas)
7. Snapshot JSON de estructura actual
8. Plantillas de prompts por módulo

---

## Contexto

El repositorio tiene:
- **28 páginas HTML** en `frontend/pages/`
- **35 endpoints API** en `backend/api/`
- **39 tablas** en base de datos (según `schema_v2.sql`)
- **62 archivos JavaScript** (core, components, legacy)
- **Estructura legacy** en `frontend/js/` raíz que debería estar en `modules/`
- **Un solo calendario/planificador** (`calendar.js`) que debe integrarse con otras páginas

Necesitamos documentar TODO esto para:
- Detectar duplicidades y código muerto
- Identificar qué debe reutilizarse
- Planificar integración con Planner
- Crear backlog estructurado
- Generar snapshot para detectar cambios no planificados

---

## Alcance

### Incluye

- Auditoría completa sin modificar código
- Generación de artefactos de documentación
- Análisis de estructura actual
- Identificación de riesgos y oportunidades
- Creación de backlog jerárquico
- Snapshot JSON de estructura
- Plantillas de prompts mejoradas

### No Incluye

- Modificar código fuente
- Refactorizar estructura
- Eliminar código muerto
- Implementar mejoras

---

## Archivos a Crear

1. **`ai-specs/changes/000_REPO_AUDIT_AND_BACKLOG.md`**
   - Mapa frontend completo
   - Mapa backend completo
   - Mapa de datos
   - Análisis de reutilización
   - Riesgos de duplicidad/código muerto
   - Backlog jerárquico

2. **`ai-specs/specs/repo-structure.snapshot.json`**
   - Snapshot de estructura actual
   - Hash/checksum de archivos críticos
   - Timestamp de auditoría

3. **`ai-specs/prompts/task-templates.md`** (actualizar)
   - Plantillas por módulo
   - Plantillas respetando reglas AI-Specs

---

## Plan de Implementación

### Fase 1: Auditoría Frontend

#### 1.1 Mapa de Páginas HTML

**Analizar:**
- Listar todas las páginas en `frontend/pages/` (28 archivos detectados)
- Identificar rutas/URLs de cada página
- Identificar rol/tenant de cada página (owner, empresa, temporera)
- Identificar dependencias JS/CSS de cada página
- Identificar si usa Planner/Calendar

**Estructura del mapa:**
```markdown
## Mapa de Páginas Frontend

### Páginas Owner (SaaS Backoffice)
- `/owner/dashboard` → `owner/dashboard.html`
  - JS: `owner-base.js`, `dashboard.js`
  - CSS: `pages/dashboard.css`
  - Planner: NO
  - Estado: [ACTIVO/LEGACY/DEPRECATED]

### Páginas Empresa
- `/calendar` → `calendar.html`
  - JS: `calendar.js` (PLANNER PRINCIPAL)
  - CSS: `pages/calendar.css`
  - Planner: SÍ (componente principal)
  - Estado: ACTIVO
```

#### 1.2 Mapa de Módulos JavaScript

**Analizar:**
- Archivos en `frontend/js/core/` (19 archivos)
- Archivos en `frontend/js/components/` (14 componentes)
- Archivos legacy en `frontend/js/` raíz (29 archivos)
- Identificar dependencias entre módulos
- Identificar duplicidades potenciales

**Estructura del mapa:**
```markdown
## Mapa de Módulos JavaScript

### Core (PROTEGIDO)
- `api.js` - Cliente API centralizado
  - Usado por: [lista de archivos]
  - Dependencias: ninguna
  - Estado: ACTIVO, OBLIGATORIO

### Components (PROTEGIDO)
- `data-table.js` - Tabla de datos serverside
  - Usado por: [lista de páginas]
  - Dependencias: `api.js`
  - Estado: ACTIVO, REUTILIZABLE

### Legacy (A REFACTORIZAR)
- `calendar.js` - Calendario/Planificador
  - Debe integrarse con: [páginas que necesitan planner]
  - Debe moverse a: `modules/calendar/calendar.js`
  - Estado: LEGACY, REFACTORIZAR
```

#### 1.3 Mapa de Helpers y Utilidades

**Analizar:**
- Helpers en `frontend/js/core/`
- Utilidades compartidas
- Funciones reutilizables

---

### Fase 2: Auditoría Backend

#### 2.1 Mapa de Endpoints API

**Analizar:**
- Todos los endpoints en `backend/api/` (35 archivos)
- Métodos HTTP soportados por cada endpoint
- Middlewares usados (TenantMiddleware, RBACMiddleware)
- Parámetros y respuestas
- Endpoints públicos vs privados

**Estructura del mapa:**
```markdown
## Mapa de Endpoints API

### Autenticación
- `POST /api/auth/login`
  - Archivo: `backend/api/auth.php`
  - Middlewares: ninguno (público)
  - Respuesta: JWT token
  - Estado: ACTIVO

### Turnos (Shifts)
- `GET /api/shifts`
  - Archivo: `backend/api/shifts.php`
  - Middlewares: TenantMiddleware, RBACMiddleware
  - Parámetros: page, page_size, filters
  - Respuesta: Lista paginada de shifts
  - Estado: ACTIVO
```

#### 2.2 Mapa de Middleware

**Analizar:**
- Middlewares en `backend/core/`
- Orden de ejecución
- Dependencias entre middlewares
- Configuración por endpoint

#### 2.3 Mapa de Base de Datos

**Analizar:**
- Todas las tablas en `schema_v2.sql` (39 tablas detectadas)
- Relaciones Foreign Key
- Índices y constraints
- Tablas globales vs tablas con tenant_id
- Tablas relacionadas con Planner/Calendar

**Estructura del mapa:**
```markdown
## Mapa de Base de Datos

### Tablas Globales (sin tenant_id)
- `users` - Usuarios globales
- `tenants` - Organizaciones (empresas/temporeras)
- `subscriptions` - Suscripciones
- `invoices` - Facturas
- `audit_logs` - Logs de auditoría

### Tablas de Negocio (con tenant_id)
- `shifts` - Turnos de trabajo
  - FK: `group_id` → `work_groups(id)`
  - FK: `project_id` → `projects(id)`
  - Relacionado con Planner: SÍ (tabla principal)
  
- `work_groups` - Grupos de trabajo
  - FK: `tenant_company_id` → `tenants(id)`
  - FK: `leader_employee_id` → `own_employees(id)`
  - Relacionado con Planner: SÍ
```

---

### Fase 3: Análisis de Reutilización e Integración

#### 3.1 Qué Reutilizar Sí o Sí

**Identificar:**
- Componentes que DEBEN reutilizarse (data-table, modal, toast, etc.)
- Helpers que DEBEN usarse (api.js, auth.js, etc.)
- Funciones compartidas que NO deben duplicarse

**Lista de componentes obligatorios:**
```markdown
## Componentes Obligatorios de Reutilizar

### Frontend JS
- `api.js` - Cliente API (OBLIGATORIO para todas las llamadas API)
- `data-table.js` - Tablas (OBLIGATORIO para tablas >100 registros)
- `modal.js` - Modales (OBLIGATORIO para diálogos)
- `toast.js` - Notificaciones (OBLIGATORIO para feedback)
- `calendar.js` - Planner (OBLIGATORIO, un solo planificador)

### Backend
- `TenantMiddleware` - Extracción de tenant_id (OBLIGATORIO)
- `RBACMiddleware` - Verificación de permisos (OBLIGATORIO)
- `db_helper.php` - Helper de BD con tenant_id (OBLIGATORIO)
```

#### 3.2 Qué Páginas Deben Integrarse al Planner

**Identificar:**
- Páginas que necesitan calendario/planificador
- Páginas que crean/modifican shifts
- Páginas que visualizan turnos

**Lista de integración:**
```markdown
## Páginas que Deben Integrarse al Planner

### Integración Completa (usar calendar.js directamente)
- `calendar.html` - Ya integrada (página principal del planner)
- `work-orders-calendar.html` - Debe integrarse con calendar.js
- `group-builder.html` - Debe integrarse con calendar.js

### Integración Parcial (mostrar turnos relacionados)
- `work-orders.html` - Mostrar turnos de la OT
- `group-shift-report.html` - Mostrar turnos del grupo
- `assignments.html` - Mostrar turnos asignados
```

---

### Fase 4: Riesgos de Duplicidad y Código Muerto

#### 4.1 Archivos a Vigilar por Duplicidad

**Identificar:**
- Archivos con funcionalidad similar
- Código duplicado entre páginas
- Componentes que hacen lo mismo

**Lista de riesgos:**
```markdown
## Riesgos de Duplicidad

### JavaScript
- `calendar.js` vs `work-orders-calendar.js` - Verificar si hay duplicación
- `dashboard.js` vs `dashboard-new.html` - Verificar si dashboard-new reemplaza dashboard
- Múltiples archivos con funciones de validación - Consolidar en form-validator.js

### CSS
- Estilos duplicados en `pages/` que deberían estar en `components/`
- Colores hardcodeados que deberían usar variables CSS

### Backend
- Lógica de validación duplicada entre endpoints
- Queries SQL similares que deberían estar en helpers
```

#### 4.2 Código Muerto Potencial

**Identificar:**
- Archivos no referenciados
- Funciones no utilizadas
- Páginas deprecated
- Endpoints no documentados

**Lista de código muerto:**
```markdown
## Código Muerto Potencial

### Frontend
- `dashboard.html` vs `dashboard-new.html` - Verificar si dashboard.html está deprecated
- `example.php` en backend/api/ - Archivo de ejemplo, verificar si se usa

### Backend
- `backend/api/example.php` - Archivo de ejemplo, posible código muerto
- Funciones en helpers no utilizadas
```

---

### Fase 5: Backlog Jerárquico

#### 5.1 Estructura del Backlog

**Formato:**
- **Épicas** (EPIC-XXX): Agrupaciones de alto nivel
- **Historias** (US-XXX): User Stories dentro de épicas
- **Tareas** (TASK-XXX): Tareas técnicas específicas

**Ejemplo:**
```markdown
## Backlog Jerárquico

### EPIC-001: Refactorización Frontend Legacy
**Descripción:** Refactorizar archivos JS legacy a estructura modular

#### US-001: Mover módulos legacy a estructura modules/
- TASK-001: Crear estructura `frontend/js/modules/calendar/`
- TASK-002: Mover `calendar.js` a `modules/calendar/calendar.js`
- TASK-003: Actualizar referencias en páginas HTML
- TASK-004: Verificar funcionalidad después de mover

#### US-002: Consolidar componentes duplicados
- TASK-005: Identificar componentes duplicados
- TASK-006: Consolidar en componente único
- TASK-007: Actualizar todas las referencias

### EPIC-002: Integración Planner con Todas las Páginas
**Descripción:** Integrar calendar.js (planner) con todas las páginas que necesitan calendario

#### US-003: Integrar planner en work-orders-calendar.html
- TASK-008: Importar calendar.js en work-orders-calendar.html
- TASK-009: Adaptar filtros para mostrar solo turnos de OT
- TASK-010: Probar funcionalidad

### EPIC-003: Eliminación de Código Muerto
**Descripción:** Eliminar archivos y código no utilizado

#### US-004: Eliminar archivos deprecated
- TASK-011: Verificar si dashboard.html está deprecated
- TASK-012: Eliminar dashboard.html si está deprecated
- TASK-013: Eliminar example.php si no se usa
```

---

### Fase 6: Snapshot JSON de Estructura

#### 6.1 Estructura del Snapshot

**Crear JSON con:**
- Estructura de carpetas completa
- Lista de archivos con hash/checksum
- Timestamp de auditoría
- Versión del snapshot

**Formato:**
```json
{
  "version": "1.0",
  "timestamp": "2025-01-XXT00:00:00Z",
  "auditor": "AI Assistant",
  "structure": {
    "frontend": {
      "pages": {
        "count": 28,
        "files": [
          {
            "path": "frontend/pages/calendar.html",
            "hash": "abc123...",
            "size": 12345,
            "last_modified": "2025-01-XX"
          }
        ]
      },
      "js": {
        "core": {
          "count": 19,
          "files": [...]
        },
        "components": {
          "count": 14,
          "files": [...]
        },
        "legacy": {
          "count": 29,
          "files": [...]
        }
      }
    },
    "backend": {
      "api": {
        "count": 35,
        "files": [...]
      },
      "core": {
        "count": 15,
        "files": [...]
      }
    }
  },
  "database": {
    "tables": {
      "count": 39,
      "global": ["users", "tenants", "subscriptions", "invoices", "audit_logs"],
      "with_tenant_id": [...]
    }
  }
}
```

---

### Fase 7: Plantillas de Prompts por Módulo

#### 7.1 Plantillas por Módulo

**Crear plantillas para:**
- Plan/Develop de módulos específicos
- Respetando reglas AI-Specs
- Incluyendo checklist específico del módulo

**Estructura:**
```markdown
## Plantilla: Plan/Develop Módulo Calendar

### Plan Calendar
```
plan-calendar

Objetivo: [Descripción del cambio en módulo Calendar]

Archivos afectados:
- frontend/js/modules/calendar/calendar.js
- frontend/pages/calendar.html
- frontend/css/pages/calendar.css

Requisitos específicos Calendar:
- [ ] Usa FullCalendar (no crear nuevo calendario)
- [ ] Integrado con shifts API
- [ ] Drag & drop funcional
- [ ] Validación de conflictos

Checklist AI-Specs:
- [ ] Plan creado en ai-specs/changes/
- [ ] Verificado zona EDITABLE
- [ ] Componentes reutilizados (api.js, toast.js)
```

### Develop Calendar
```
develop-calendar @PLAN_ID

[Implementar según plan aprobado]
```
```

---

## Checklist Pre-Implementación

- [ ] Plan revisado y aprobado
- [ ] Estructura del repositorio verificada
- [ ] Archivos de referencia leídos (schema_v2.sql, endpoints API)
- [ ] Herramientas de análisis preparadas

---

## Checklist Post-Implementación

- [ ] `000_REPO_AUDIT_AND_BACKLOG.md` creado con todos los mapas
- [ ] `repo-structure.snapshot.json` creado con estructura completa
- [ ] `task-templates.md` actualizado con plantillas por módulo
- [ ] Backlog jerárquico completo (Épicas → Historias → Tareas)
- [ ] Riesgos documentados con lista exacta de archivos
- [ ] Análisis de reutilización completo
- [ ] Plan marcado como `[IMPLEMENTED]`

---

## Riesgos y Mitigaciones

- **Riesgo:** Estructura muy grande, puede haber archivos no detectados
  - **Mitigación:** Usar herramientas de búsqueda exhaustiva (glob, grep)

- **Riesgo:** Algunos archivos pueden estar deprecated pero aún referenciados
  - **Mitigación:** Verificar referencias antes de marcar como código muerto

- **Riesgo:** Relaciones de base de datos complejas
  - **Mitigación:** Analizar schema_v2.sql completo y documentar todas las FKs

---

## Referencias

- `docs/Workee_kickoff_v_1.md` - Especificación oficial del proyecto
- `.cursorrules` - Reglas de desarrollo (1291 líneas)
- `backend/db/schema_v2.sql` - Esquema completo de base de datos
- `ai-specs/specs/03-repo-manifest.mdc` - Manifesto del repositorio
- `ai-specs/specs/01-cursor-rules.mdc` - Reglas de desarrollo

---

## Notas Adicionales

- Este plan NO modifica código, solo crea artefactos de documentación
- El snapshot JSON permitirá detectar cambios no planificados en el futuro
- El backlog jerárquico servirá como guía para futuras implementaciones
- Las plantillas de prompts facilitarán el trabajo futuro respetando AI-Specs

---

**Estado:** `[DRAFT]` → Cambiar a `[APPROVED]` cuando se apruebe este plan.

