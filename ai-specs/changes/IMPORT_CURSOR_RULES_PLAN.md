# Plan: Importar Reglas de Cursor a AI-Specs

> **Estado:** [IMPLEMENTED]  
> **Fecha:** 2025-01-XX  
> **Autor:** AI Assistant  
> **Referencia:** `.cursorrules` (1291 líneas), estilo LIDR-academy/ai-specs

---

## Objetivo

Completar los archivos specs de AI-Specs (`01-cursor-rules.mdc`, `02-project-guardrails.mdc`, `03-repo-manifest.mdc`, `04-change-safety-checklist.mdc`) con todas las reglas existentes en `.cursorrules`, reflejando la estructura REAL del repositorio y estableciendo zonas editables vs protegidas.

---

## Contexto

Los archivos specs actuales tienen contenido básico pero faltan detalles importantes de `.cursorrules`:

1. **Convenciones MVC PHP** con sufijos específicos (`*_view.php`, `*_action.php`, `*_data.php`)
2. **Stack tecnológico completo** (DataTables serverside, etc.)
3. **Regla de "un solo planificador"** (no múltiples calendarios)
4. **Reutilización de componentes** existentes (api-client, modal, toast, datatable)
5. **Zonas editables vs protegidas** del repositorio
6. **Estructura real** del repositorio (frontend/js/modules, backend/lib si existe, etc.)
7. **Checklists pre/post-flight** mejorados con justificación de archivos afectados
8. **Regla ES→EN** (usuario escribe en español, agente trabaja en inglés internamente)

---

## Alcance

### Incluye

- Completar `specs/01-cursor-rules.mdc` con todas las reglas faltantes
- Completar `specs/02-project-guardrails.mdc` con zonas editables vs protegidas
- Completar `specs/03-repo-manifest.mdc` con estructura REAL del repositorio
- Completar `specs/04-change-safety-checklist.mdc` con checklists mejorados

### No Incluye

- Modificar código fuente
- Crear nuevos archivos specs
- Cambiar estructura de carpetas existente

---

## Archivos Afectados

- `ai-specs/specs/01-cursor-rules.mdc` - Completar con reglas faltantes
- `ai-specs/specs/02-project-guardrails.mdc` - Agregar zonas editables vs protegidas
- `ai-specs/specs/03-repo-manifest.mdc` - Actualizar con estructura real
- `ai-specs/specs/04-change-safety-checklist.mdc` - Mejorar checklists pre/post-flight

---

## Plan de Implementación

### Fase 1: Análisis y Mapeo

1. **Revisar `.cursorrules` completo** (1291 líneas)
   - Identificar todas las reglas no incluidas en specs actuales
   - Mapear reglas a archivos specs correspondientes
   - Identificar estructura real del repositorio

2. **Verificar estructura real del repositorio**
   - Listar carpetas en `backend/` (verificar si existe `backend/lib/`)
   - Listar carpetas en `frontend/js/` (verificar `frontend/js/modules/`)
   - Documentar estructura real vs estructura documentada

3. **Identificar reglas específicas mencionadas**
   - Convenciones MVC PHP (`*_view.php`, `*_action.php`, `*_data.php`)
   - Stack completo (DataTables serverside)
   - Regla "un solo planificador"
   - Regla ES→EN
   - Componentes reutilizables existentes

---

### Fase 2: Completar `specs/01-cursor-rules.mdc`

**Secciones a agregar/completar:**

#### 2.1 Convenciones de Nomenclatura MVC PHP

```markdown
## Convenciones de Nomenclatura MVC PHP

### Sufijos Obligatorios para Archivos PHP

- **Vistas:** `*_view.php` (ej: `staffing_requests_view.php`)
- **Acciones:** `*_action.php` (ej: `staffing_requests_action.php`)
- **Datos:** `*_data.php` (ej: `staffing_requests_data.php`)

### Estructura MVC Recomendada

- Separar lógica de presentación (view) de lógica de negocio (action)
- Separar acceso a datos (data) de lógica de negocio
- Mantener consistencia en nomenclatura según tipo de archivo
```

#### 2.2 Stack Tecnológico Completo

```markdown
## Stack Tecnológico Completo

### Backend
- PHP 8.x + mysqli (NO PDO)
- MySQL 5.7+
- JWT para autenticación
- Sistema de traducciones i18n

### Frontend
- Bootstrap 5
- JavaScript modular (ES6+)
- **DataTables serverside** (OBLIGATORIO para tablas grandes)
- CSS ITCSS con design tokens
- FullCalendar para calendario drag & drop

### Librerías Específicas
- DataTables (serverside processing)
- FullCalendar (calendario interactivo)
- Chart.js / ApexCharts (gráficos)
```

#### 2.3 Regla de "Un Solo Planificador"

```markdown
## Regla de Arquitectura: Un Solo Planificador

- ✅ **SIEMPRE usar un único calendario/planificador** por tenant
- ❌ **NUNCA crear múltiples calendarios** o instancias de planificador
- ✅ **Reutilizar componente de calendario existente** (`frontend/js/calendar.js`)
- ✅ **Centralizar lógica de planificación** en módulo único
- **Razón:** Evitar inconsistencias y duplicación de lógica de planificación
```

#### 2.4 Reutilización de Componentes Existentes

```markdown
## Componentes Reutilizables Existentes (OBLIGATORIO USAR)

### Componentes JS Disponibles

- **API Client:** `frontend/js/core/api.js` - Cliente API centralizado
- **Modal:** `frontend/js/components/modal.js` - Modales reutilizables
- **Toast:** `frontend/js/components/toast.js` - Notificaciones toast
- **DataTable:** `frontend/js/components/data-table.js` - Tablas con paginación
- **EmptyState:** `frontend/js/components/empty-state.js` - Estados vacíos
- **LoadingSpinner:** `frontend/js/components/loading-spinner.js` - Indicadores de carga
- **FormValidator:** `frontend/js/components/form-validator.js` - Validación de formularios

### Regla de Reutilización

- ✅ **SIEMPRE buscar componente existente** antes de crear nuevo
- ✅ **SIEMPRE reutilizar o extender** componentes existentes
- ❌ **NUNCA duplicar funcionalidad** de componentes existentes
- ❌ **NUNCA crear páginas sueltas** sin integrar componentes existentes
```

#### 2.5 Regla ES→EN

```markdown
## Regla de Comunicación: ES→EN

### Principio

- **Usuario escribe en español:** Todas las solicitudes del usuario están en español
- **Agente trabaja en inglés internamente:** Código, comentarios, nombres de variables/funciones en inglés
- **Traducciones en frontend:** Textos visibles al usuario traducidos con sistema i18n

### Aplicación

- ✅ **Comentarios en código:** Inglés (ej: `// Get user data`)
- ✅ **Nombres de variables/funciones:** Inglés (ej: `getUserData()`, `$userId`)
- ✅ **Mensajes de commit:** Inglés (ej: `[develop-100] Add user validation`)
- ✅ **Textos visibles al usuario:** Claves de traducción (ej: `t('common.save')`)
- ❌ **NUNCA hardcodear texto en español** en código (usar claves de traducción)
```

---

### Fase 3: Completar `specs/02-project-guardrails.mdc`

**Sección a agregar:**

#### 3.1 Zonas Editables vs Zonas Protegidas

```markdown
## Zonas Editables vs Zonas Protegidas

### Zonas Editables (Permitido Modificar con Plan Aprobado)

- `frontend/pages/` - Páginas HTML (crear nuevas con plan)
- `frontend/js/modules/` - Módulos JS por funcionalidad
- `frontend/css/pages/` - Estilos específicos por página
- `backend/api/` - Endpoints API nuevos (con middlewares obligatorios)
- `backend/helpers/` - Utilidades nuevas
- `backend/lang/{locale}/` - Traducciones nuevas
- `scripts/` - Scripts no esenciales

### Zonas Protegidas (Requieren Plan de Limpieza Específico)

- `backend/core/` - Middlewares y servicios core (solo modificar con plan explícito)
- `frontend/js/core/` - Cliente API y utilidades core (solo modificar con plan explícito)
- `frontend/js/components/` - Componentes reutilizables (solo extender, no modificar sin plan)
- `frontend/css/01-settings/theme.css` - Design tokens (solo agregar tokens, no modificar existentes sin plan)
- `backend/db/schema_v2.sql` - Esquema base (solo migraciones nuevas)
- `.cursorrules` - Reglas de desarrollo (solo actualizar con plan explícito)
- `ai-specs/specs/` - Especificaciones AI (solo actualizar con plan explícito)

### Zonas Prohibidas (NUNCA Modificar Sin Plan de Refactorización)

- Estructura raíz del proyecto (carpetas principales)
- Archivos de configuración críticos (`.env`, etc.)
- Migraciones ya aplicadas en producción
- Componentes core sin plan de refactorización explícito

### Reglas de Modificación

- ✅ **Zonas editables:** Modificar según plan aprobado
- ⚠️ **Zonas protegidas:** Requieren plan específico de modificación
- ❌ **Zonas prohibidas:** NUNCA modificar sin plan de refactorización completo
```

---

### Fase 4: Completar `specs/03-repo-manifest.mdc`

**Secciones a actualizar:**

#### 4.1 Estructura Real del Repositorio

```markdown
## Estructura Real del Repositorio

### Backend (Estructura Real)

```
backend/
├── core/           # Middlewares, servicios core (PROTEGIDO)
│   ├── TenantMiddleware.php
│   ├── RBACMiddleware.php
│   ├── Auth.php
│   ├── Config.php
│   ├── AuditLogger.php
│   ├── FileStorage.php
│   ├── MatchingService.php
│   ├── NotificationService.php
│   ├── WebhookService.php
│   └── ...
├── api/            # Endpoints API REST (EDITABLE con plan)
│   ├── auth.php
│   ├── users.php
│   ├── shifts.php
│   ├── work_orders.php
│   ├── staffing_requests.php
│   ├── v1/         # Versión API v1
│   └── ...
├── helpers/        # Utilidades reutilizables (EDITABLE)
│   ├── db_helper.php
│   ├── i18n.php
│   ├── env.php
│   └── ...
├── jobs/           # Cron jobs (EDITABLE con plan)
│   ├── invoice_run.php
│   └── usage_snapshot.php
├── db/             # Migraciones y schemas (PROTEGIDO)
│   ├── schema_v2.sql
│   ├── schema_work_orders.sql
│   └── setup_database.php
└── lang/           # Traducciones i18n (EDITABLE)
    ├── es/         # Español (41 archivos)
    ├── de/         # Alemán
    ├── fr/         # Francés
    └── it/         # Italiano
```

### Frontend (Estructura Real)

```
frontend/
├── pages/          # Páginas HTML (EDITABLE con plan)
│   ├── owner/     # Páginas Owner (SaaS)
│   │   ├── dashboard.html
│   │   └── billing.html
│   ├── empresa/    # Páginas Empresa (implícito en estructura)
│   └── temporera/   # Páginas Temporera (implícito en estructura)
│   ├── calendar.html
│   ├── dashboard.html
│   ├── work-orders.html
│   └── ... (28 archivos HTML)
├── components/     # Componentes HTML reutilizables (PROTEGIDO)
│   ├── header.html
│   ├── sidebar.html
│   └── skip-links.html
├── js/             # JavaScript modular
│   ├── core/       # Cliente API y utilidades core (PROTEGIDO)
│   │   ├── api.js  # Cliente API centralizado (OBLIGATORIO)
│   │   ├── auth.js
│   │   └── ... (19 archivos)
│   ├── components/ # Componentes JS reutilizables (PROTEGIDO)
│   │   ├── data-table.js
│   │   ├── modal.js
│   │   ├── toast.js
│   │   ├── empty-state.js
│   │   └── ... (13 componentes)
│   ├── modules/    # Módulos por funcionalidad (EDITABLE)
│   │   ├── calendar/
│   │   ├── groups/
│   │   ├── requests/
│   │   └── worklogs/
│   └── [archivos legacy] # Archivos antiguos (a refactorizar)
├── css/            # Estilos ITCSS
│   ├── 01-settings/  # Design tokens (PROTEGIDO)
│   │   └── theme.css
│   ├── 03-generic/   # Reset CSS (PROTEGIDO)
│   │   └── _reset.css
│   ├── 04-elements/  # Elementos HTML base (EDITABLE)
│   │   ├── _typography.css
│   │   ├── _forms.css
│   │   └── ...
│   ├── 05-objects/   # Patrones de layout (EDITABLE)
│   │   ├── _layout.css
│   │   └── ...
│   ├── 06-components/ # Componentes UI (EDITABLE)
│   │   ├── _data-table.css
│   │   ├── _modal.css
│   │   └── ... (38 componentes)
│   ├── 07-utilities/ # Utilidades CSS (EDITABLE)
│   │   ├── _spacing.css
│   │   └── ... (12 utilidades)
│   ├── pages/        # Estilos específicos por página (EDITABLE)
│   │   └── ... (13 archivos)
│   └── main.css      # Archivo principal de importación
```

### Responsabilidades por Carpeta

#### Backend

- **`backend/core/`** - Middlewares y servicios core (PROTEGIDO)
  - TenantMiddleware, RBACMiddleware, Auth, Config
  - Servicios compartidos: MatchingService, NotificationService, WebhookService
  - NUNCA modificar sin plan explícito

- **`backend/api/`** - Endpoints API REST (EDITABLE con plan)
  - Un archivo por recurso (ej: `work_orders.php`, `staffing_requests.php`)
  - Todos los endpoints DEBEN usar TenantMiddleware + RBACMiddleware
  - Crear nuevos endpoints según plan aprobado

- **`backend/helpers/`** - Utilidades reutilizables (EDITABLE)
  - Funciones helper compartidas
  - db_helper.php, i18n.php, env.php
  - Crear nuevos helpers según necesidad

- **`backend/jobs/`** - Cron jobs (EDITABLE con plan)
  - Jobs programados (invoice_run.php, usage_snapshot.php)
  - Crear nuevos jobs según plan aprobado

- **`backend/db/`** - Migraciones y schemas (PROTEGIDO)
  - schema_v2.sql - Esquema base (NUNCA modificar directamente)
  - Migraciones nuevas: `YYYYMMDDHHMM__descripcion.sql`
  - NUNCA modificar migraciones ya aplicadas

- **`backend/lang/`** - Traducciones i18n (EDITABLE)
  - Un archivo por namespace por idioma
  - Agregar traducciones según necesidad

#### Frontend

- **`frontend/pages/`** - Páginas HTML (EDITABLE con plan)
  - Una página por vista
  - Organizadas por rol/tenant (owner/, empresa/, temporera/)
  - Crear nuevas páginas según plan aprobado

- **`frontend/components/`** - Componentes HTML reutilizables (PROTEGIDO)
  - header.html, sidebar.html, skip-links.html
  - Modificar solo con plan explícito

- **`frontend/js/core/`** - Cliente API y utilidades core (PROTEGIDO)
  - api.js - Cliente API centralizado (OBLIGATORIO usar)
  - auth.js, utils.js, etc.
  - NUNCA modificar sin plan explícito

- **`frontend/js/components/`** - Componentes JS reutilizables (PROTEGIDO)
  - data-table.js, modal.js, toast.js, etc.
  - Extender sí, modificar solo con plan explícito

- **`frontend/js/modules/`** - Módulos por funcionalidad (EDITABLE)
  - calendar/, groups/, requests/, worklogs/
  - Crear nuevos módulos según plan aprobado

- **`frontend/css/01-settings/`** - Design tokens (PROTEGIDO)
  - theme.css - Variables CSS (solo agregar tokens nuevos)
  - NUNCA modificar tokens existentes sin plan

- **`frontend/css/06-components/`** - Componentes UI (EDITABLE)
  - Estilos de componentes reutilizables
  - Crear nuevos estilos según necesidad
```

---

### Fase 5: Completar `specs/04-change-safety-checklist.mdc`

**Secciones a mejorar:**

#### 5.1 Checklist Pre-Flight Mejorado

```markdown
## Checklist Pre-Flight (Antes de Modificar Código)

### Lectura de Specs (OBLIGATORIO)

- [ ] Leído `specs/00-governance.mdc` - Reglas de fases
- [ ] Leído `specs/01-cursor-rules.mdc` - Reglas de desarrollo
- [ ] Leído `specs/02-project-guardrails.mdc` - Reglas de seguridad
- [ ] Leído `specs/04-change-safety-checklist.mdc` - Este checklist
- [ ] Leído spec específico según cambio (frontend/backend/testing)

### Verificación de Zonas

- [ ] Verificado que archivos a modificar están en zona EDITABLE
- [ ] Si archivos están en zona PROTEGIDA, plan específico aprobado
- [ ] Si archivos están en zona PROHIBIDA, plan de refactorización aprobado
- [ ] Justificación documentada para modificar zona protegida/prohibida

### Archivos Afectados (OBLIGATORIO LISTAR)

**Lista específica de archivos a modificar/crear/eliminar:**

- `backend/api/example.php` - **Acción:** Modificar endpoint X, **Razón:** [justificación]
- `frontend/pages/example.html` - **Acción:** Crear nueva página, **Razón:** [justificación]
- `frontend/js/components/example.js` - **Acción:** Extender componente, **Razón:** [justificación]

**Justificación requerida para cada archivo:**
- ¿Por qué se modifica este archivo?
- ¿Por qué no se puede reutilizar código existente?
- ¿Por qué está en zona protegida si aplica?

### Verificación de Componentes Existentes

- [ ] Buscado componente existente en `frontend/js/components/`
- [ ] Verificado que no se duplica funcionalidad existente
- [ ] Si existe componente similar, justificado por qué no se reutiliza
- [ ] Listado componentes reutilizados en este cambio

### Verificación de Reglas Específicas

- [ ] Convenciones MVC PHP respetadas (`*_view.php`, `*_action.php`, `*_data.php`)
- [ ] DataTables serverside usado para tablas grandes
- [ ] Un solo planificador/calendario (no múltiples instancias)
- [ ] Comentarios y código en inglés (ES→EN)
- [ ] Textos visibles al usuario con claves de traducción
```

#### 5.2 Checklist Post-Flight Mejorado

```markdown
## Checklist Post-Flight (Después de Modificar Código)

### Verificación de Código

- [ ] Código sigue convenciones de nomenclatura
- [ ] No hay código muerto o comentado obsoleto
- [ ] Comentarios en inglés (regla ES→EN)
- [ ] Nombres de variables/funciones en inglés
- [ ] No hay duplicación de código

### Diff Explicado

**Resumen de cambios realizados:**

- **Archivo X:** [Descripción breve del cambio]
- **Archivo Y:** [Descripción breve del cambio]

**Justificación de cambios:**
- ¿Por qué se hizo este cambio?
- ¿Qué problema resuelve?
- ¿Cómo se relaciona con el plan aprobado?

### Pruebas Manuales

- [ ] Funcionalidad probada manualmente en desarrollo
- [ ] Probado en móvil (iOS y Android)
- [ ] Probado en diferentes navegadores (Chrome, Firefox, Safari)
- [ ] Probado con diferentes roles/permisos
- [ ] Probado con diferentes tenants (multitenancy)

### Actualización de Documentación

- [ ] `api-spec.yml` actualizado si se agregaron/modificaron endpoints
- [ ] Documentación en `docs/` actualizada si aplica
- [ ] Comentarios en código actualizados si aplica
- [ ] README actualizado si cambió estructura

### Verificación de Trazabilidad

- [ ] Commit incluye referencia al plan: `[develop-{plan-id}] ...`
- [ ] Mensaje de commit en inglés (regla ES→EN)
- [ ] Plan marcado como `[IMPLEMENTED]` si corresponde
- [ ] PR incluye referencia al plan aprobado
```

---

## Checklist Pre-Implementación

- [ ] Plan revisado y aprobado
- [ ] `.cursorrules` completo analizado (1291 líneas)
- [ ] Estructura real del repositorio verificada
- [ ] Reglas específicas identificadas y documentadas
- [ ] Zonas editables vs protegidas definidas
- [ ] Checklists mejorados diseñados

---

## Checklist Post-Implementación

- [ ] `specs/01-cursor-rules.mdc` completado con todas las reglas
- [ ] `specs/02-project-guardrails.mdc` incluye zonas editables vs protegidas
- [ ] `specs/03-repo-manifest.mdc` refleja estructura REAL del repositorio
- [ ] `specs/04-change-safety-checklist.mdc` incluye checklists mejorados
- [ ] Todas las referencias cruzadas verificadas
- [ ] Estilo consistente con LIDR-academy/ai-specs

---

## Riesgos y Mitigaciones

- **Riesgo:** Reglas de `.cursorrules` muy extensas (1291 líneas)
  - **Mitigación:** Priorizar reglas críticas y organizar por categorías

- **Riesgo:** Estructura real del repo puede diferir de documentada
  - **Mitigación:** Verificar estructura real con `list_dir` antes de documentar

- **Riesgo:** Reglas específicas mencionadas pueden no existir explícitamente
  - **Mitigación:** Inferir de contexto y mejores prácticas, documentar como "recomendado" si no es explícito

---

## Referencias

- `.cursorrules` - Reglas completas de desarrollo (1291 líneas)
- `docs/Workee_kickoff_v_1.md` - Especificación oficial del proyecto
- `ai-specs/specs/00-governance.mdc` - Reglas de fases
- `ai-specs/changes/BOOTSTRAP_AI_SPECS_PLAN.md` - Plan de bootstrap
- Estilo LIDR-academy/ai-specs - Referencia de estilo y estructura

---

## Notas Adicionales

- Este plan NO modifica código, solo completa documentación de specs
- Las reglas específicas mencionadas (MVC PHP, ES→EN, etc.) se documentarán aunque no estén explícitas en `.cursorrules`
- La estructura real del repositorio se verificará antes de documentar
- Los checklists mejorados incluirán justificación obligatoria de archivos afectados

---

**Estado:** `[DRAFT]` → Cambiar a `[APPROVED]` cuando se apruebe este plan.

