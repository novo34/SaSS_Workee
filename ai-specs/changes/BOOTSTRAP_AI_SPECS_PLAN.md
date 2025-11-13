# Plan de Bootstrap: Arquitectura AI-Specs y Gobernanza de Cambios

> **Estado:** [IMPLEMENTED] - Estructura completa creada  
> **Fecha:** 2025-01-XX  
> **Autor:** AI Assistant  
> **Referencia:** Estilo LIDR-academy/ai-specs  
> **Implementado:** 2025-01-XX

---

## 📋 Objetivo

Crear desde cero la arquitectura **AI-Specs** y la **gobernanza de cambios** para el repositorio Workee SaaS, estableciendo un sistema de especificaciones modulares, trazabilidad de cambios, y reglas estrictas de fases (`plan-*` vs `develop-*`) para prevenir modificaciones no autorizadas del código.

---

## 🏗️ 1. Árbol de Estructura AI-Specs Propuesto

```
ai-specs/
├── specs/                          # Especificaciones modulares (fuente de verdad)
│   ├── 00-governance.mdc          # Gobernanza y reglas de fases
│   ├── 01-cursor-rules.mdc        # Reglas de desarrollo (.cursorrules)
│   ├── 02-project-guardrails.mdc  # Reglas de seguridad y multitenancy
│   ├── 03-repo-manifest.mdc       # Manifesto del repositorio y estructura
│   ├── 04-change-safety-checklist.mdc  # Checklist de seguridad antes de cambios
│   ├── 05-frontend-standards.mdc   # Estándares frontend (CSS ITCSS, JS modular)
│   ├── 06-backend-standards-php.mdc # Estándares backend (PHP 8.x, mysqli)
│   ├── 07-privacy-multitenancy-rbac.mdc # Privacidad, multitenancy, RBAC
│   ├── 08-testing-and-acceptance.mdc   # Testing y criterios de aceptación
│   └── api-spec.yml                # Especificación OpenAPI/Swagger
│
├── changes/                        # Planes de cambio trazables (solo Markdown)
│   ├── 000_REPO_AUDIT_AND_BACKLOG.md      # Auditoría inicial y backlog
│   ├── 100_PLANNER_V2_REFACTOR_PLAN.md    # Plan de refactorización Planner v2
│   ├── 200_BACKEND_CRM_AND_HISTORY_PLAN.md # Plan CRM backend e historial
│   ├── 300_PLANNER_FEED_PLAN.md           # Plan de feed del planner
│   ├── 400_DAY_OPERATIONS_PLAN.md         # Plan de operaciones diarias
│   ├── 500_CLIENT_DASHBOARD_PLAN.md       # Plan de dashboard cliente
│   ├── 900_CLEANUP_HARDENING_PLAN.md      # Plan de limpieza y hardening
│   └── BOOTSTRAP_AI_SPECS_PLAN.md         # Este archivo (plan de bootstrap)
│
├── prompts/                        # Plantillas de prompts y tareas
│   └── task-templates.md          # Plantillas para tareas comunes
│
└── CLAUDE.md                       # Gobernanza y aplicación de specs (instrucciones para Claude)

```

---

## 📄 2. Contenido Esperado de Cada Archivo Spec

### 2.1 `specs/00-governance.mdc`

**Propósito:** Define las reglas de gobernanza, fases de trabajo, y flujo de aprobación.

**Secciones obligatorias:**

```markdown
# Gobernanza AI-Specs - Workee SaaS

## Fases de Trabajo

### Fase `plan-*`
- **Prohibido:** Modificar código fuente, crear archivos de implementación, ejecutar migraciones
- **Permitido:** Crear documentos Markdown en `ai-specs/changes/`, analizar código existente, leer documentación
- **Objetivo:** Definir plan de cambio completo antes de implementar
- **Formato:** `plan-{descripcion}` (ej: `plan-frontend-refactor`, `plan-api-v2`)

### Fase `develop-*`
- **Requisito:** Plan aprobado en `ai-specs/changes/` con referencia explícita
- **Permitido:** Modificar código según plan aprobado, crear archivos, ejecutar migraciones
- **Prohibido:** Desviarse del plan sin crear nuevo plan primero
- **Formato:** `develop-{plan-id}` (ej: `develop-100-planner-v2`)

## Flujo de Aprobación

1. **Crear plan:** `plan-{descripcion}` → genera `ai-specs/changes/{ID}_{NOMBRE}_PLAN.md`
2. **Revisar plan:** Verificar que no rompe reglas de `specs/02-project-guardrails.mdc`
3. **Aprobar plan:** Marcar como `[APPROVED]` en el header del plan
4. **Implementar:** `develop-{plan-id}` → seguir plan línea por línea

## Reglas de Trazabilidad

- Cada cambio DEBE referenciar un plan aprobado
- Cada plan DEBE listar archivos afectados
- Cada plan DEBE incluir checklist de verificación pre/post
- Commits DEBEN incluir referencia al plan: `[develop-100] Implementar...`

## Prohibiciones Absolutas

- ❌ Modificar código sin plan aprobado
- ❌ Crear páginas top-level nuevas sin plan
- ❌ Renombrar/eliminar archivos sin plan de limpieza
- ❌ Duplicar componentes/utilidades existentes
- ❌ Ignorar specs en `ai-specs/specs/`
```

**Estilo:** Directo, imperativo, con ejemplos claros de qué hacer y qué no hacer.

---

### 2.2 `specs/01-cursor-rules.mdc`

**Propósito:** Espejo estructurado de `.cursorrules` para referencia rápida.

**Secciones obligatorias:**

```markdown
# Cursor Rules - Workee SaaS

## Stack Tecnológico
- PHP 8.x + mysqli (NO PDO)
- MySQL 5.7+
- Bootstrap 5
- JavaScript modular
- CSS ITCSS (Inverted Triangle CSS)

## Reglas Críticas de Multitenancy
- TODAS las tablas de negocio DEBEN tener `tenant_id`
- TODAS las queries DEBEN filtrar por `tenant_id`
- Middleware TenantMiddleware obligatorio en todas las rutas API

## Reglas de Internacionalización
- NUNCA hardcodear texto visible al usuario
- SIEMPRE usar función `t()` con claves de traducción
- Formato: `namespace.modulo.clave` (ej: `common.save`)

## Reglas de Frontend
- Mobile-first obligatorio
- Componentes reutilizables en `frontend/js/components/`
- Cliente API centralizado en `frontend/js/core/api.js`
- CSS según ITCSS con design tokens

## Reglas de Backend
- Prepared statements SIEMPRE (prevenir SQL injection)
- Validación y sanitización de todas las entradas
- Respuestas JSON consistentes con formato estándar

[... resto de reglas críticas del .cursorrules]
```

**Estilo:** Resumen ejecutivo, referencias a `.cursorrules` completo, ejemplos de código correcto/incorrecto.

---

### 2.3 `specs/02-project-guardrails.mdc`

**Propósito:** Reglas de seguridad para NO romper el proyecto.

**Secciones obligatorias:**

```markdown
# Project Guardrails - Workee SaaS

## Reglas de Seguridad Absolutas

### Multitenancy
- ❌ NUNCA permitir queries cross-tenant sin autorización explícita
- ❌ NUNCA confiar en `tenant_id` del cliente (siempre desde middleware)
- ✅ SIEMPRE validar `tenant_id` en cada request
- ✅ SIEMPRE incluir `tenant_id` en WHERE de todas las queries

### RBAC
- ❌ NUNCA crear endpoints sin RBACMiddleware
- ❌ NUNCA asumir permisos sin verificar en backend
- ✅ SIEMPRE verificar permisos antes de ejecutar acciones

### Base de Datos
- ❌ NUNCA modificar migraciones ya aplicadas en producción
- ❌ NUNCA crear tablas sin `tenant_id` (excepto tablas globales)
- ❌ NUNCA hacer DELETE físico sin verificar dependencias
- ✅ SIEMPRE usar Foreign Keys con ON DELETE apropiado

### Código Existente
- ❌ NUNCA refactorizar sin solicitud explícita
- ❌ NUNCA eliminar código sin verificar dependencias
- ❌ NUNCA duplicar funcionalidad existente
- ✅ SIEMPRE buscar código existente antes de crear nuevo
- ✅ SIEMPRE reutilizar componentes/utilidades existentes

### Archivos y Estructura
- ❌ NUNCA crear páginas top-level nuevas sin plan aprobado
- ❌ NUNCA renombrar/eliminar archivos sin plan de limpieza
- ❌ NUNCA crear scripts en raíz o carpetas de ejecución (usar `scripts/`)
- ✅ SIEMPRE seguir estructura de carpetas definida

### Frontend
- ❌ NUNCA hardcodear colores en CSS (usar variables CSS)
- ❌ NUNCA crear componentes específicos por página sin intentar reutilizar
- ❌ NUNCA hacer llamadas API directamente (usar cliente centralizado)
- ✅ SIEMPRE importar `theme.css` en todas las páginas
- ✅ SIEMPRE usar componente DataTable para tablas

### Backend
- ❌ NUNCA usar PDO (solo mysqli)
- ❌ NUNCA concatenar variables en SQL (usar prepared statements)
- ❌ NUNCA retornar mensajes de error hardcodeados
- ✅ SIEMPRE retornar claves de traducción en errores
- ✅ SIEMPRE validar y sanitizar entradas

## Checklist Pre-Cambio

Antes de cualquier cambio, verificar:
- [ ] Plan aprobado en `ai-specs/changes/`
- [ ] No rompe reglas de multitenancy
- [ ] No rompe reglas de RBAC
- [ ] No duplica código existente
- [ ] Sigue estructura de carpetas
- [ ] Usa componentes/utilidades existentes
- [ ] Incluye validaciones de seguridad
- [ ] Incluye manejo de errores apropiado
```

**Estilo:** Listas de prohibiciones y obligaciones claras, con checklists verificables.

---

### 2.4 `specs/03-repo-manifest.mdc`

**Propósito:** Manifesto del repositorio: estructura, convenciones, y arquitectura.

**Secciones obligatorias:**

```markdown
# Repositorio Manifesto - Workee SaaS

## Visión del Proyecto
SaaS multitenant que conecta Empresas (planifican trabajo) con Temporeras (suministran trabajadores).

## Estructura de Carpetas

```
SaSS_Workee/
├── backend/
│   ├── core/           # Middlewares, servicios core
│   ├── api/            # Endpoints API REST
│   ├── models/         # Modelos de datos
│   ├── helpers/         # Utilidades reutilizables
│   ├── jobs/            # Cron jobs
│   ├── db/              # Migraciones y schemas
│   └── lang/            # Traducciones i18n
├── frontend/
│   ├── pages/           # Páginas HTML (una por vista)
│   ├── js/              # JavaScript modular
│   │   ├── core/        # Cliente API, auth, utils
│   │   ├── components/  # Componentes reutilizables
│   │   └── modules/      # Módulos por funcionalidad
│   └── css/             # Estilos ITCSS
│       ├── 01-settings/  # Design tokens
│       ├── 03-generic/   # Reset CSS
│       ├── 04-elements/  # Elementos HTML base
│       ├── 05-objects/   # Patrones de layout
│       ├── 06-components/ # Componentes UI
│       └── 07-utilities/ # Utilidades CSS
├── docs/                # Documentación del proyecto
├── tests/               # Tests de aislamiento y funcionales
├── scripts/             # Scripts no esenciales (utilidad, dev, mantenimiento)
└── ai-specs/            # Especificaciones AI y gobernanza
```

## Convenciones de Nomenclatura

- **Archivos PHP:** `snake_case.php` (ej: `staffing_requests.php`)
- **Archivos JS:** `kebab-case.js` (ej: `group-builder.js`)
- **Clases PHP:** `PascalCase` (ej: `TenantMiddleware`)
- **Funciones PHP:** `camelCase` (ej: `getTenantId()`)
- **Tablas DB:** `snake_case` (ej: `work_groups`)
- **Archivos CSS:** `kebab-case.css` (ej: `data-table.css`)

## Arquitectura

- **Backend:** PHP 8.x + mysqli, arquitectura por capas (API → Core → Models → DB)
- **Frontend:** HTML5 + Bootstrap 5 + JS modular, arquitectura por componentes
- **Base de Datos:** MySQL 5.7+, single schema con multitenancy por `tenant_id`
- **Seguridad:** JWT + refresh tokens, RBAC por rol, middleware obligatorio

## Documentación Principal

- `docs/Workee_kickoff_v_1.md` - Especificación oficial del proyecto
- `.cursorrules` - Reglas de desarrollo completas
- `README.md` - Guía de inicio rápido
- `docs/TODO_ROADMAP_COMPLETO.md` - Roadmap y tareas pendientes
```

**Estilo:** Descriptivo, con estructura clara y referencias a documentación.

---

### 2.5 `specs/04-change-safety-checklist.mdc`

**Propósito:** Checklist de seguridad antes de realizar cualquier cambio.

**Secciones obligatorias:**

```markdown
# Change Safety Checklist - Workee SaaS

## Checklist Pre-Cambio (OBLIGATORIO)

Antes de modificar código, verificar:

### Gobernanza
- [ ] Plan aprobado en `ai-specs/changes/` con estado `[APPROVED]`
- [ ] Comando iniciado con `develop-{plan-id}` (no `plan-*`)
- [ ] Plan referencia archivos específicos a modificar

### Multitenancy
- [ ] Cambios incluyen `tenant_id` en queries si aplica
- [ ] No se expone información cross-tenant
- [ ] Middleware TenantMiddleware presente en rutas nuevas/modificadas

### RBAC
- [ ] Permisos verificados en backend (no solo frontend)
- [ ] RBACMiddleware presente en rutas nuevas/modificadas
- [ ] Roles y permisos documentados en plan

### Base de Datos
- [ ] Migraciones siguen convención `YYYYMMDDHHMM__descripcion.sql`
- [ ] Migraciones incluyen rollback si aplica
- [ ] No se modifica migración ya aplicada en producción
- [ ] Foreign Keys definidas apropiadamente

### Código Existente
- [ ] Búsqueda realizada para código similar existente
- [ ] No se duplica funcionalidad existente
- [ ] Componentes/utilidades existentes reutilizados
- [ ] Dependencias verificadas antes de eliminar código

### Frontend
- [ ] CSS usa variables CSS (no colores hardcodeados)
- [ ] `theme.css` importado en páginas nuevas
- [ ] Componente DataTable usado para tablas
- [ ] Cliente API centralizado usado para llamadas API
- [ ] Diseño mobile-first verificado

### Backend
- [ ] Prepared statements usados (no concatenación SQL)
- [ ] Validación y sanitización de entradas
- [ ] Respuestas JSON con formato estándar
- [ ] Claves de traducción usadas (no texto hardcodeado)

### Internacionalización
- [ ] Todos los textos usan función `t()` con claves
- [ ] Traducciones creadas en todos los idiomas soportados
- [ ] No hay texto hardcodeado en código

### Testing
- [ ] Tests de aislamiento actualizados si aplica
- [ ] Funcionalidad probada en móvil
- [ ] Accesibilidad verificada (WCAG AA mínimo)

## Checklist Post-Cambio

Después de modificar código, verificar:

- [ ] Código sigue convenciones de nomenclatura
- [ ] No hay código muerto o comentado obsoleto
- [ ] Documentación actualizada si aplica
- [ ] Linter sin errores críticos
- [ ] Funcionalidad probada manualmente
- [ ] Commit incluye referencia al plan: `[develop-{plan-id}] ...`
```

**Estilo:** Checklist verificable, con casillas y referencias claras.

---

### 2.6 `specs/05-frontend-standards.mdc`

**Propósito:** Estándares frontend específicos (CSS ITCSS, JS modular, componentes).

**Secciones obligatorias:**

```markdown
# Frontend Standards - Workee SaaS

## Arquitectura CSS (ITCSS)

### Estructura Obligatoria
```
frontend/css/
├── 01-settings/    # Design tokens (theme.css)
├── 03-generic/     # Reset CSS
├── 04-elements/    # Elementos HTML base
├── 05-objects/     # Patrones de layout
├── 06-components/  # Componentes UI
└── 07-utilities/   # Utilidades CSS
```

### Design Tokens
- TODOS los valores DEBEN usar variables CSS
- NUNCA hardcodear colores, espaciados, sombras, transiciones
- Variables definidas en `frontend/css/01-settings/theme.css`

### Componentes CSS
- Convención BEM: `.block__element--modifier`
- Archivos pequeños y enfocados (máx. 300-400 líneas)
- Un archivo por componente

## Arquitectura JavaScript

### Estructura Obligatoria
```
frontend/js/
├── core/           # Cliente API, auth, utils
├── components/     # Componentes reutilizables
└── modules/        # Módulos por funcionalidad
```

### Componentes Obligatorios
- **DataTable:** Todas las tablas DEBEN usar `data-table.js`
- **Modal:** Modales reutilizables con `modal.js`
- **Toast:** Notificaciones con `toast.js`
- **EmptyState:** Estados vacíos con `empty-state.js`
- **LoadingSpinner:** Indicadores de carga con `loading-spinner.js`

### Cliente API
- OBLIGATORIO usar `frontend/js/core/api.js` para TODAS las llamadas API
- NUNCA usar `fetch()` directamente
- Manejo automático de autenticación y errores

## Mobile-First Obligatorio

- Todas las interfaces diseñadas primero para móvil
- Botones mínimo 44x44px
- Espaciado adecuado entre elementos
- Navegación hamburguesa en móvil
- Bootstrap 5 responsive

## Componentes Reutilizables

Antes de crear componente específico por página:
1. Buscar componente existente en `frontend/js/components/`
2. Si existe, reutilizar o extender
3. Si no existe, crear componente reutilizable
4. Documentar props/parámetros del componente
```

**Estilo:** Técnico, con ejemplos de código y estructura clara.

---

### 2.7 `specs/06-backend-standards-php.mdc`

**Propósito:** Estándares backend específicos (PHP 8.x, mysqli, arquitectura).

**Secciones obligatorias:**

```markdown
# Backend Standards PHP - Workee SaaS

## Stack Tecnológico

- **PHP:** 8.x (requerido)
- **Base de Datos:** MySQL 5.7+ con mysqli (NO PDO)
- **Arquitectura:** Por capas (API → Core → Models → DB)

## Estructura de Carpetas

```
backend/
├── core/           # Middlewares, servicios core
├── api/            # Endpoints API REST
├── models/         # Modelos de datos
├── helpers/        # Utilidades reutilizables
├── jobs/           # Cron jobs
├── db/             # Migraciones y schemas
└── lang/           # Traducciones i18n
```

## Seguridad SQL

### Prepared Statements Obligatorios
```php
// ✅ CORRECTO
$stmt = $conn->prepare("SELECT * FROM users WHERE tenant_id = ? AND id = ?");
$stmt->bind_param("ii", $tenant_id, $user_id);
$stmt->execute();

// ❌ INCORRECTO
$query = "SELECT * FROM users WHERE tenant_id = $tenant_id";
```

### Multitenancy Obligatorio
- TODAS las queries DEBEN incluir `tenant_id` en WHERE
- NUNCA confiar en `tenant_id` del cliente
- SIEMPRE obtener `tenant_id` desde TenantMiddleware

## Respuestas API

### Formato Estándar Obligatorio
```php
// ✅ CORRECTO
return json_encode([
    'success' => true,
    'data' => $result,
    'error' => null
]);

// ❌ INCORRECTO
return json_encode($result);
```

### Manejo de Errores
- Retornar claves de traducción (no mensajes hardcodeados)
- Códigos HTTP apropiados (400, 401, 403, 404, 500)
- Formato: `{"error": "validation.required", "error_params": {"field": "email"}}`

## Middlewares Obligatorios

Todas las rutas API DEBEN usar:
1. **TenantMiddleware:** Extrae y valida `tenant_id`
2. **RBACMiddleware:** Verifica permisos por rol

## Internacionalización

- NUNCA hardcodear texto visible al usuario
- SIEMPRE usar función `t()` con claves de traducción
- Formato: `namespace.modulo.clave` (ej: `common.save`)
```

**Estilo:** Técnico, con ejemplos de código correcto/incorrecto.

---

### 2.8 `specs/07-privacy-multitenancy-rbac.mdc`

**Propósito:** Reglas específicas de privacidad, multitenancy y RBAC.

**Secciones obligatorias:**

```markdown
# Privacidad, Multitenancy y RBAC - Workee SaaS

## Multitenancy

### Aislamiento de Datos
- Single DB, single schema con `tenant_id` en todas las tablas de negocio
- NUNCA permitir queries cross-tenant sin autorización explícita
- Relaciones Empresa ↔ Temporera SOLO a través de `agency_company_links` con permisos verificados

### Middleware Obligatorio
- TenantMiddleware DEBE extraer `tenant_id` de JWT/sesión/cabecera
- TenantMiddleware DEBE validar `tenant_id` antes de cualquier query
- NUNCA confiar en que el usuario no puede cambiar `tenant_id` en el frontend

## RBAC (Role-Based Access Control)

### Estructura de Roles
- **Empresa:** Admin, Planificador, Supervisor, Empleado, Cliente-Viewer
- **Temporera:** Admin, Coordinador, Trabajador
- **Owner:** Admin

### Permisos Granulares
- Matriz `role_page_permissions` con granularidad por módulo/acción
- Verificación obligatoria en backend (nunca solo en frontend)

### Middleware Obligatorio
- RBACMiddleware DEBE verificar permisos por rol/acción antes de ejecutar
- NUNCA crear endpoints sin RBACMiddleware

## Privacidad

### Visibilidad PII Configurable
- Configuración por tenant de visibilidad de PII (teléfono/email) del trabajador temporero
- Opciones: `oculto`, `visible_al_confirmar`, `oculto_tras_cierre`

### Logs de Acceso a PII
- Registrar TODOS los accesos a datos PII
- Campos: quién (user_id, tenant_id), cuándo (timestamp), qué datos (tipo), propósito

### Enmascaramiento
- Enmascarar datos según configuración (ej: `+41 ** *** 12 34` para teléfono)
- Reportes de acceso a PII disponibles para Owner y Temporera-Admin

### Cumplimiento GDPR/LPD Suiza
- Retención configurables
- Exportabilidad de datos
- Derecho al olvido (soft-delete donde aplique)
```

**Estilo:** Reglas estrictas, con ejemplos de configuración y cumplimiento.

---

### 2.9 `specs/08-testing-and-acceptance.mdc`

**Propósito:** Criterios de testing y aceptación.

**Secciones obligatorias:**

```markdown
# Testing y Aceptación - Workee SaaS

## Tests de Aislamiento (Obligatorios)

### Objetivo
Verificar que no hay fugas cross-tenant.

### Requisitos
- Tests DEBEN verificar que queries sin `tenant_id` fallan
- Tests DEBEN verificar que `tenant_id` incorrecto no devuelve datos
- Tests DEBEN ejecutarse antes de cada release

### Ubicación
- `tests/tenancy_isolation.test.php`

## Tests Funcionales

### Flujos Críticos
- Solicitud → Propuesta → Confirmación → Asignación
- Billing: snapshots, cálculos, facturas
- Permisos RBAC por rol

## Criterios de Aceptación

### Definición de Done
- [ ] Especificación consultada (`docs/Workee_kickoff_v_1.md`)
- [ ] Sin parches temporales (solución desde la raíz)
- [ ] Código revisado y sin duplicación
- [ ] Validaciones y errores JSON consistentes
- [ ] Tenancy y permisos verificados
- [ ] Tests de aislamiento pasando
- [ ] Auditoría activada para operaciones sensibles
- [ ] Todas las traducciones creadas en todos los idiomas
- [ ] Interfaz probada y funcional en móvil (iOS y Android)
- [ ] Diseño mobile-first implementado
- [ ] Sistema de diseño CSS verificado (`theme.css` importado, variables CSS usadas)
- [ ] Arquitectura frontend verificada (componentes reutilizables, cliente API centralizado)

### Testing Móvil Obligatorio
- Probar en dispositivos reales (iOS y Android)
- Probar en diferentes tamaños de pantalla
- Probar con conexión lenta (3G simulation)
- Probar orientación vertical y horizontal

### Testing de Accesibilidad
- Contraste WCAG AA mínimo
- Navegación por teclado funcional
- ARIA labels donde sea necesario
- Screen readers compatibles
```

**Estilo:** Criterios verificables, con checklists y requisitos claros.

---

### 2.10 `specs/api-spec.yml`

**Propósito:** Especificación OpenAPI/Swagger de la API REST.

**Contenido esperado:**

```yaml
openapi: 3.0.0
info:
  title: Workee SaaS API
  version: 1.0.0
  description: API REST para SaaS multitenant Workee

servers:
  - url: /api/v1
    description: API v1

paths:
  /auth/login:
    post:
      summary: Autenticación de usuario
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                email: {type: string, format: email}
                password: {type: string}
      responses:
        200:
          description: Login exitoso
          content:
            application/json:
              schema:
                type: object
                properties:
                  success: {type: boolean}
                  data:
                    type: object
                    properties:
                      token: {type: string}
                      refresh_token: {type: string}
  # ... más endpoints

components:
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
```

**Estilo:** Especificación OpenAPI estándar, completa y actualizada.

---

## 📝 3. Contenido Esperado de Archivos Changes

### 3.1 Formato Estándar de Plan

Cada plan en `ai-specs/changes/` DEBE seguir este formato:

```markdown
# {ID}_{NOMBRE}_PLAN.md

> **Estado:** [DRAFT | APPROVED | IMPLEMENTED | DEPRECATED]  
> **Fecha:** YYYY-MM-DD  
> **Autor:** Nombre/AI  
> **Referencia:** Plan relacionado (si aplica)

## Objetivo

Descripción clara del objetivo del plan.

## Contexto

Por qué se necesita este cambio, qué problema resuelve.

## Alcance

Qué se incluye y qué NO se incluye en este plan.

## Archivos Afectados

Lista específica de archivos a modificar/crear/eliminar:
- `backend/api/example.php` - Modificar endpoint X
- `frontend/pages/example.html` - Crear nueva página
- `frontend/js/components/example.js` - Crear componente nuevo

## Plan de Implementación

### Fase 1: Preparación
1. Paso específico
2. Paso específico

### Fase 2: Implementación
1. Paso específico
2. Paso específico

### Fase 3: Verificación
1. Checklist de verificación
2. Tests a ejecutar

## Checklist Pre-Implementación

- [ ] Verificar que no rompe reglas de `specs/02-project-guardrails.mdc`
- [ ] Verificar que no duplica código existente
- [ ] Verificar que sigue estructura de carpetas
- [ ] Verificar que incluye validaciones de seguridad

## Checklist Post-Implementación

- [ ] Código sigue convenciones
- [ ] Tests pasando
- [ ] Documentación actualizada
- [ ] Funcionalidad probada manualmente

## Riesgos y Mitigaciones

- **Riesgo:** Descripción
  - **Mitigación:** Cómo mitigar

## Referencias

- `specs/XX-spec-name.mdc` - Especificación relacionada
- `docs/Workee_kickoff_v_1.md` - Especificación oficial
```

**Estilo:** Estructurado, con objetivos claros, alcance definido, y checklists verificables.

---

## 🔒 4. Reglas de Fases

### 4.1 Fase `plan-*`

**Comando:** `plan-{descripcion}` (ej: `plan-frontend-refactor`, `plan-api-v2`)

**Reglas:**

- ✅ **PERMITIDO:**
  - Crear documentos Markdown en `ai-specs/changes/`
  - Leer y analizar código existente
  - Leer documentación (`docs/`, `.cursorrules`, `ai-specs/specs/`)
  - Buscar código con `codebase_search`, `grep`, `read_file`
  - Crear plan detallado con estructura, archivos afectados, pasos de implementación

- ❌ **PROHIBIDO:**
  - Modificar código fuente (`.php`, `.js`, `.html`, `.css`, `.sql`)
  - Crear archivos de implementación
  - Ejecutar migraciones
  - Modificar configuración (`.env`, etc.)
  - Ejecutar comandos que modifiquen el sistema

**Objetivo:** Definir plan completo antes de implementar.

**Salida esperada:** Archivo `ai-specs/changes/{ID}_{NOMBRE}_PLAN.md` con plan detallado.

---

### 4.2 Fase `develop-*`

**Comando:** `develop-{plan-id}` (ej: `develop-100-planner-v2`, `develop-200-backend-crm`)

**Reglas:**

- ✅ **PERMITIDO:**
  - Modificar código según plan aprobado
  - Crear archivos según plan aprobado
  - Ejecutar migraciones según plan aprobado
  - Modificar configuración según plan aprobado

- ❌ **PROHIBIDO:**
  - Desviarse del plan sin crear nuevo plan primero
  - Modificar archivos no listados en el plan
  - Implementar funcionalidad no descrita en el plan
  - Saltarse pasos del plan

**Requisito previo:** Plan aprobado en `ai-specs/changes/` con estado `[APPROVED]`.

**Objetivo:** Implementar plan línea por línea.

**Salida esperada:** Código implementado según plan, commits con referencia `[develop-{plan-id}]`.

---

## 🛡️ 5. Reglas para NO Romper el Proyecto

### 5.1 Reglas Absolutas

#### Multitenancy
- ❌ **NUNCA** permitir queries cross-tenant sin autorización explícita
- ❌ **NUNCA** confiar en `tenant_id` del cliente (siempre desde middleware)
- ✅ **SIEMPRE** validar `tenant_id` en cada request
- ✅ **SIEMPRE** incluir `tenant_id` en WHERE de todas las queries

#### RBAC
- ❌ **NUNCA** crear endpoints sin RBACMiddleware
- ❌ **NUNCA** asumir permisos sin verificar en backend
- ✅ **SIEMPRE** verificar permisos antes de ejecutar acciones

#### Base de Datos
- ❌ **NUNCA** modificar migraciones ya aplicadas en producción
- ❌ **NUNCA** crear tablas sin `tenant_id` (excepto tablas globales)
- ❌ **NUNCA** hacer DELETE físico sin verificar dependencias
- ✅ **SIEMPRE** usar Foreign Keys con ON DELETE apropiado

#### Código Existente
- ❌ **NUNCA** refactorizar sin solicitud explícita
- ❌ **NUNCA** eliminar código sin verificar dependencias
- ❌ **NUNCA** duplicar funcionalidad existente
- ✅ **SIEMPRE** buscar código existente antes de crear nuevo
- ✅ **SIEMPRE** reutilizar componentes/utilidades existentes

#### Archivos y Estructura
- ❌ **NUNCA** crear páginas top-level nuevas sin plan aprobado
- ❌ **NUNCA** renombrar/eliminar archivos sin plan de limpieza
- ❌ **NUNCA** crear scripts en raíz o carpetas de ejecución (usar `scripts/`)
- ✅ **SIEMPRE** seguir estructura de carpetas definida

#### Frontend
- ❌ **NUNCA** hardcodear colores en CSS (usar variables CSS)
- ❌ **NUNCA** crear componentes específicos por página sin intentar reutilizar
- ❌ **NUNCA** hacer llamadas API directamente (usar cliente centralizado)
- ✅ **SIEMPRE** importar `theme.css` en todas las páginas
- ✅ **SIEMPRE** usar componente DataTable para tablas

#### Backend
- ❌ **NUNCA** usar PDO (solo mysqli)
- ❌ **NUNCA** concatenar variables en SQL (usar prepared statements)
- ❌ **NUNCA** retornar mensajes de error hardcodeados
- ✅ **SIEMPRE** retornar claves de traducción en errores
- ✅ **SIEMPRE** validar y sanitizar entradas

---

### 5.2 Checklist Pre-Cambio Obligatorio

Antes de cualquier cambio, verificar:

- [ ] Plan aprobado en `ai-specs/changes/` con estado `[APPROVED]`
- [ ] Comando iniciado con `develop-{plan-id}` (no `plan-*`)
- [ ] Plan referencia archivos específicos a modificar
- [ ] No rompe reglas de multitenancy (`specs/02-project-guardrails.mdc`)
- [ ] No rompe reglas de RBAC (`specs/07-privacy-multitenancy-rbac.mdc`)
- [ ] No duplica código existente (búsqueda realizada)
- [ ] Sigue estructura de carpetas (`specs/03-repo-manifest.mdc`)
- [ ] Usa componentes/utilidades existentes
- [ ] Incluye validaciones de seguridad
- [ ] Incluye manejo de errores apropiado

---

## 📋 6. Plantilla de PR y Checklists

### 6.1 Plantilla de Pull Request

```markdown
## Descripción

Breve descripción del cambio realizado.

## Plan de Referencia

- Plan: `ai-specs/changes/{ID}_{NOMBRE}_PLAN.md`
- Estado del plan: `[APPROVED]`
- Comando usado: `develop-{plan-id}`

## Archivos Modificados

- `backend/api/example.php` - Descripción del cambio
- `frontend/pages/example.html` - Descripción del cambio

## Checklist Pre-Flight

- [ ] Plan aprobado y referenciado
- [ ] No rompe reglas de multitenancy
- [ ] No rompe reglas de RBAC
- [ ] No duplica código existente
- [ ] Sigue estructura de carpetas
- [ ] Usa componentes/utilidades existentes
- [ ] Incluye validaciones de seguridad
- [ ] Incluye manejo de errores apropiado

## Checklist Post-Flight

- [ ] Código sigue convenciones de nomenclatura
- [ ] No hay código muerto o comentado obsoleto
- [ ] Documentación actualizada si aplica
- [ ] Linter sin errores críticos
- [ ] Funcionalidad probada manualmente
- [ ] Tests pasando (si aplica)
- [ ] Commit incluye referencia al plan: `[develop-{plan-id}] ...`

## Testing

- [ ] Probado en desarrollo local
- [ ] Probado en móvil (iOS/Android)
- [ ] Tests de aislamiento pasando (si aplica)
- [ ] Accesibilidad verificada (si aplica)

## Screenshots (si aplica)

[Agregar screenshots si hay cambios visuales]

## Notas Adicionales

[Notas adicionales si aplica]
```

---

### 6.2 Checklist Pre-Flight (Antes de Crear PR)

- [ ] Plan aprobado en `ai-specs/changes/` con estado `[APPROVED]`
- [ ] Comando usado fue `develop-{plan-id}` (no `plan-*`)
- [ ] Plan referencia archivos específicos modificados
- [ ] No rompe reglas de `specs/02-project-guardrails.mdc`
- [ ] No rompe reglas de `specs/07-privacy-multitenancy-rbac.mdc`
- [ ] No duplica código existente (búsqueda realizada)
- [ ] Sigue estructura de carpetas (`specs/03-repo-manifest.mdc`)
- [ ] Usa componentes/utilidades existentes
- [ ] Incluye validaciones de seguridad
- [ ] Incluye manejo de errores apropiado
- [ ] Código sigue convenciones de nomenclatura
- [ ] No hay código muerto o comentado obsoleto
- [ ] Documentación actualizada si aplica
- [ ] Linter sin errores críticos
- [ ] Funcionalidad probada manualmente
- [ ] Tests pasando (si aplica)
- [ ] Commit incluye referencia al plan: `[develop-{plan-id}] ...`

---

### 6.3 Checklist Post-Flight (Después de Merge)

- [ ] Funcionalidad verificada en producción (si aplica)
- [ ] Monitoreo de errores activado (si aplica)
- [ ] Documentación actualizada en `docs/` si aplica
- [ ] Plan marcado como `[IMPLEMENTED]` en `ai-specs/changes/`
- [ ] Notas de release actualizadas si aplica

---

## 📚 7. Archivo CLAUDE.md

**Propósito:** Instrucciones para Claude sobre cómo aplicar las specs y gobernanza.

**Contenido esperado:**

```markdown
# Gobernanza AI-Specs - Instrucciones para Claude

## Aplicación de Specs

Antes de realizar CUALQUIER cambio, Claude DEBE:

1. **Leer specs relevantes** en `ai-specs/specs/`:
   - `00-governance.mdc` - Reglas de fases y gobernanza
   - `02-project-guardrails.mdc` - Reglas de seguridad
   - `04-change-safety-checklist.mdc` - Checklist de seguridad
   - Specs específicas según el cambio (frontend, backend, etc.)

2. **Verificar fase de trabajo:**
   - Si comando es `plan-*`: SOLO crear plan Markdown, PROHIBIDO modificar código
   - Si comando es `develop-*`: Requiere plan aprobado, implementar según plan

3. **Aplicar checklist pre-cambio:**
   - Verificar que no rompe reglas de guardrails
   - Verificar que no duplica código existente
   - Verificar que sigue estructura de carpetas

4. **Seguir especificaciones:**
   - Frontend: `specs/05-frontend-standards.mdc`
   - Backend: `specs/06-backend-standards-php.mdc`
   - Multitenancy/RBAC: `specs/07-privacy-multitenancy-rbac.mdc`

## Reglas de Fases

### Fase `plan-*`
- **Prohibido:** Modificar código fuente
- **Permitido:** Crear documentos Markdown en `ai-specs/changes/`
- **Objetivo:** Definir plan completo antes de implementar

### Fase `develop-*`
- **Requisito:** Plan aprobado en `ai-specs/changes/`
- **Permitido:** Modificar código según plan aprobado
- **Prohibido:** Desviarse del plan sin crear nuevo plan primero

## Prioridad de Especificaciones

1. **Especificaciones AI-Specs** (`ai-specs/specs/`) - Máxima prioridad
2. **Especificación oficial** (`docs/Workee_kickoff_v_1.md`) - Alta prioridad
3. **Reglas de desarrollo** (`.cursorrules`) - Alta prioridad
4. **Documentación** (`docs/`) - Referencia

## Prohibiciones Absolutas

- ❌ Modificar código sin plan aprobado (excepto correcciones menores documentadas)
- ❌ Crear páginas top-level nuevas sin plan
- ❌ Renombrar/eliminar archivos sin plan de limpieza
- ❌ Duplicar componentes/utilidades existentes
- ❌ Ignorar specs en `ai-specs/specs/`

## Ejemplos de Uso

### Ejemplo 1: Crear Plan
```
Usuario: plan-frontend-refactor
Claude: Crea plan en ai-specs/changes/100_FRONTEND_REFACTOR_PLAN.md
        (NO modifica código)
```

### Ejemplo 2: Implementar Plan
```
Usuario: develop-100-frontend-refactor
Claude: Lee plan ai-specs/changes/100_FRONTEND_REFACTOR_PLAN.md
        Implementa según plan línea por línea
        Commits con referencia [develop-100]
```

## Referencias Rápidas

- **Gobernanza:** `ai-specs/specs/00-governance.mdc`
- **Guardrails:** `ai-specs/specs/02-project-guardrails.mdc`
- **Checklist:** `ai-specs/specs/04-change-safety-checklist.mdc`
- **Frontend:** `ai-specs/specs/05-frontend-standards.mdc`
- **Backend:** `ai-specs/specs/06-backend-standards-php.mdc`
```

**Estilo:** Instrucciones claras y directas para Claude, con ejemplos y referencias.

---

## 🎯 8. Resumen de Entregables

### Estructura Completa

```
ai-specs/
├── specs/
│   ├── 00-governance.mdc
│   ├── 01-cursor-rules.mdc
│   ├── 02-project-guardrails.mdc
│   ├── 03-repo-manifest.mdc
│   ├── 04-change-safety-checklist.mdc
│   ├── 05-frontend-standards.mdc
│   ├── 06-backend-standards-php.mdc
│   ├── 07-privacy-multitenancy-rbac.mdc
│   ├── 08-testing-and-acceptance.mdc
│   └── api-spec.yml
├── changes/
│   ├── 000_REPO_AUDIT_AND_BACKLOG.md
│   ├── 100_PLANNER_V2_REFACTOR_PLAN.md
│   ├── 200_BACKEND_CRM_AND_HISTORY_PLAN.md
│   ├── 300_PLANNER_FEED_PLAN.md
│   ├── 400_DAY_OPERATIONS_PLAN.md
│   ├── 500_CLIENT_DASHBOARD_PLAN.md
│   ├── 900_CLEANUP_HARDENING_PLAN.md
│   └── BOOTSTRAP_AI_SPECS_PLAN.md (este archivo)
├── prompts/
│   └── task-templates.md
└── CLAUDE.md
```

### Próximos Pasos

1. **Aprobar este plan** (marcar como `[APPROVED]` en el header)
2. **Implementar estructura** con comando `develop-bootstrap-ai-specs`
3. **Crear archivos specs** según contenido esperado definido
4. **Migrar documentación existente** a formato AI-Specs si aplica
5. **Aplicar gobernanza** en todos los cambios futuros

---

## ✅ Checklist de Aprobación

- [ ] Plan revisado y aprobado
- [ ] Estructura de carpetas validada
- [ ] Contenido esperado de cada archivo definido
- [ ] Reglas de fases claras y verificables
- [ ] Reglas de seguridad definidas
- [ ] Plantillas de PR y checklists creadas
- [ ] Instrucciones para Claude definidas

---

**Estado:** `[IMPLEMENTED]` - Estructura completa de AI-Specs creada exitosamente.

## ✅ Implementación Completada

Todos los archivos especificados en el plan han sido creados:

- ✅ `specs/00-governance.mdc` - Gobernanza y reglas de fases
- ✅ `specs/01-cursor-rules.mdc` - Resumen de reglas de desarrollo
- ✅ `specs/02-project-guardrails.mdc` - Reglas de seguridad
- ✅ `specs/03-repo-manifest.mdc` - Manifesto del repositorio
- ✅ `specs/04-change-safety-checklist.mdc` - Checklist de seguridad
- ✅ `specs/05-frontend-standards.mdc` - Estándares frontend
- ✅ `specs/06-backend-standards-php.mdc` - Estándares backend
- ✅ `specs/07-privacy-multitenancy-rbac.mdc` - Privacidad y RBAC
- ✅ `specs/08-testing-and-acceptance.mdc` - Testing y aceptación
- ✅ `specs/api-spec.yml` - Especificación OpenAPI
- ✅ `prompts/task-templates.md` - Plantillas de tareas
- ✅ `CLAUDE.md` - Instrucciones para Claude

La arquitectura AI-Specs está lista para uso.

