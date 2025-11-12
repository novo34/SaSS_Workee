# HorasPro — SaaS Multitenant (Empresas ↔ Temporeras) — Especificación v2

> Rediseño integral para operar como **SaaS multitenant** que sirve a **Empresas** (clientes finales que planifican y ejecutan trabajo) y **Temporeras** (agencias de personal que suministran trabajadores). Incluye **modelo de cobro fijo + variable por empleado activo**, calendario drag & drop, gestión de grupos mixtos (propios/temporeros), reportes, facturación y APIs.

---

## 1) Visión y objetivos
- **Unificar la planificación y control de horas** entre Empresas y Temporeras, con asignación flexible de personal (propio y externo), y **cálculo confiable** de horas normales/extra/recargos.
- **Automatizar la relación Empresa ↔ Temporera**: solicitud de personal, propuesta de candidatos, confirmación/cambios, y seguimiento del desempeño.
- **SaaS escalable** con **multitenancy segura**, **billing mensual** (fijo + variable por empleado activo) y **integraciones** contables/nomina.

**KPI clave**: adopción (nº empresas/temporeras), tiempo de confirmación de grupos, exactitud de horas exportadas, MRR/Churn.

---

## 2) Modelo Multitenant
### 2.1 Tipos de organización (tenant)
- **Empresa**: planifica, asigna, ejecuta, aprueba y reporta.
- **Temporera**: gestiona cartera de trabajadores, responde solicitudes, propone candidatos, administra contratos con Empresas.
- **Owner (SaaS)**: backoffice global (planes, precios, facturación, auditoría, soporte).

### 2.2 Aislamiento y seguridad
- **Single DB, single schema con `tenant_id`** (y `org_type`), aplicado en todas las tablas de negocio.
- **RBAC con scoping por tenant** + **ownership cruzado controlado** para relaciones Empresa↔Temporera (tablas puente con permisos explícitos).
- **Auditoría** completa (quién, cuándo, qué) y **soft-delete** donde aplique.
- **Cumplimiento** GDPR/LPD Suiza; retención configurables y exportabilidad de datos.

---

## 3) Roles y permisos (resumen)
**Empresa**
- **Empresa-Admin**: configura cuenta, empleados propios, clientes, proyectos, reglas, integra contabilidad, ve y descarga todo.
- **Planificador**: gestiona calendario y solicitudes a temporeras; arma grupos; confirma candidatos.
- **Supervisor**: aprueba horas de su equipo.
- **Empleado**: ficha horas, ve turnos, comentarios.
- **Cliente-Viewer (opcional)**: lectura de partes y entregables.

**Temporera**
- **Temporera-Admin**: gestiona trabajadores, contratos con empresas, precios específicos, facturación a empresas si aplica.
- **Coordinador**: recibe solicitudes, propone candidatos, gestiona disponibilidad.
- **Trabajador**: ficha horas (si la temporera lo habilita), ve asignaciones.

**Owner (SaaS)**
- **Owner-Admin**: planes/precios, alta/baja tenants, métricas, soporte, auditoría global, gestión de cobros.

> Matriz detallada RBAC se definirá como `role_page_permissions` con granularidad por módulo/acción.

---

## 4) Facturación (Billing) — Modelo y reglas
### 4.1 Precios
- **Cuota fija mensual por tenant** (diferenciada por tipo: Empresa vs Temporera) según plan (Basic/Pro/Enterprise).
- **Variable por empleado activo**:
  - **Empresa**: cuenta **empleados propios activos** en el mes.
  - **Temporera**: cuenta **trabajadores activos** (sean o no asignados), con regla de **facturar solo desde el mes siguiente si se desactiva**.
- **Opcional**: suplemento por **slots de calendario** masivos, almacenamiento de adjuntos, o **módulos premium** (APIs, webhooks, ELM/contabilidad avanzada).

### 4.2 Conteo de activos
- **Snapshot diario** de estado activo; **promediado mensual** o **pico mensual**, configurable por plan.
- Prorrateo si alta/baja de tenant a mitad de ciclo.

### 4.3 Emisión y cobro
- Ciclo **mensual** (1–31) con **pre-billing** de fijo + **post-billing** del variable (o consolidado el 1 del mes siguiente).
- **Pasarela**: Stripe (suscripciones + usage records) / alternativa local. Dunning & reintentos.
- **Documentos**: factura PDF + detalle de uso (CSV/JSON) descargable.

### 4.4 Backoffice Owner
- Definición de **planes**, precios por país/moneda, **impuestos** (IVA), cupones, cancelaciones y créditos.
- Panel de **MRR/ARR**, churn, usuarios activos, incidencias de cobro.

---

## 5) Flujos críticos Empresa ↔ Temporera
### 5.1 Solicitud de personal (Empresa → Temporera)
1) Empresa crea **Solicitud**: fecha(s), horas, ubicación, requisitos (idioma, certificaciones, experiencia), cantidad.
2) Se envía a una o varias Temporeras seleccionadas (o marketplace privado).
3) Temporera responde con **Propuesta de candidatos** (lista con perfiles, disponibilidad, tarifa si aplica).
4) Empresa **Confirma** por candidato (total o parcial) o **Solicita cambios**.
5) Al confirmar, se genera **Asignación** y se integran al **calendario** (drag & drop posible para ajustar grupos/horarios).

### 5.2 Gestión de grupos
- Un **Grupo de trabajo** se compone de: 1 **Jefe de grupo** (empleado propio) + `N` trabajadores (propios y/o temporeros).
- **Escenarios soportados**: solo propios, solo temporeros, mixto.
- Reglas de capacidad: límite de tamaño, ratio jefe:miembros, skills obligatorias.

### 5.3 Aprobaciones y horas
- Fichaje por cada miembro (origen: app web móvil/responsive). O bien **parte del líder** que certifica horas para el grupo.
- **Supervisor Empresa** aprueba; si trabajador es de Temporera, se puede configurar **co-aprobación**.
- Exportación a **nómina (Empresa)** y/o **liquidación (Temporera)**.

---

## 6) Módulos por tipo de tenant
### 6.1 Empresa — Gestión
- **Calendario drag & drop** (semanal/mensual): turnos, grupos, asignaciones, conflictos, ausencias.
- **Clientes & Proyectos**: contratos, tarifas, ubicaciones.
- **Empleados propios**: altas/bajas, skills, documentos.
- **Solicitudes a Temporeras**: creación, seguimiento, comunicación.
- **Grupos**: armado, responsable, rotaciones.
- **Fichaje & Aprobaciones**: panel por equipo/proyecto.
- **Reportes**: por cliente, por proyecto, por empleado, por período; KPIs: cumplimiento de turnos, extras, ausencias, coste hora.
- **Historial por cliente**: trabajos realizados, incidencias, satisfacción, anexos.
- **Import/Export**: CSV/XLSX de empleados, turnos, horas.
- **Integraciones**: contabilidad/facturación (p. ej., export CSV estándar; conectores opcionales), SSO opcional.

### 6.2 Temporera — Gestión
- **Catálogo de trabajadores**: perfiles, disponibilidad, documentos, skills, estatus **activo/inactivo** (impacta billing).
- **Matching**: búsqueda por requisitos, filtros rápidos.
- **Propuestas** a solicitudes de Empresas.
- **Asignaciones**: calendario propio, seguimiento de performance.
- **Fichaje (opcional)**: habilitar a trabajadores para fichar directo (con permisos y visibilidad de Empresa asociada).
- **Relaciones con Empresas**: contratos, tarifas, SLAs, KPIs.
- **Reportes**: colocaciones por período, fill-rate, cancelaciones, horas confirmadas vs propuestas.
- **Facturación**: a Empresas (si el acuerdo lo requiere) y recibos internos a trabajadores; exportes a sistemas externos.
- **Import/Export**: masivo de trabajadores y documentos.

### 6.3 Owner — Backoffice SaaS
- **Tenants**: alta/baja, estado, plan, dominios.
- **Planes & Precios**: fijo/variable, moneda, límites, features.
- **Billing**: suscripciones, usage records, facturas, fallos de cobro, créditos.
- **Soporte**: impersonar (con auditoría), tickets.
- **Métricas**: MRR/ARR, DAU/WAU, retención, health por tenant.

---

## 7) UX clave
- **Calendario**: drag & drop con validaciones (choques, límites diarios/semanales, skills faltantes). Multiselección y duplicación de turnos.
- **Constructor de grupos**: sidebar de candidatos (propios/temporeros), filtros por skill/disponibilidad, arrastrar para conformar grupo.
- **Wizard de Solicitud**: requisitos → horarios → cantidad → temporeras destino → resumen.
- **Panel de aprobación**: vista por empleado/grupo con totales y alertas (faltan pausas, excede umbral, horario nocturno, festivo).

---

## 8) Modelo de datos (ampliado)
> MySQL/mysqli, todas con `tenant_id` (excepto tablas globales de owner). Campos comunes: `created_at`, `updated_at`, `created_by`, `updated_by`.

```sql
-- Tenants y suscripciones (Owner)
CREATE TABLE tenants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  org_type ENUM('empresa','temporera') NOT NULL,
  plan ENUM('basic','pro','enterprise') NOT NULL DEFAULT 'basic',
  currency CHAR(3) NOT NULL DEFAULT 'CHF',
  timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Zurich',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  external_id VARCHAR(120) NULL, -- Stripe sub id
  billing_cycle_anchor DATE NOT NULL,
  status ENUM('active','past_due','canceled') NOT NULL DEFAULT 'active',
  fixed_price DECIMAL(10,2) NOT NULL,
  variable_price_per_employee DECIMAL(10,2) NOT NULL,
  measure ENUM('monthly_avg','monthly_peak','daily') NOT NULL DEFAULT 'monthly_avg',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE usage_employee_snapshots (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  date DATE NOT NULL,
  active_count INT NOT NULL,
  org_type ENUM('empresa','temporera') NOT NULL,
  UNIQUE KEY uq_tenant_date (tenant_id, date),
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  period_from DATE NOT NULL,
  period_to DATE NOT NULL,
  fixed_amount DECIMAL(10,2) NOT NULL,
  variable_units INT NOT NULL,
  variable_rate DECIMAL(10,2) NOT NULL,
  total_amount DECIMAL(10,2) NOT NULL,
  pdf_path VARCHAR(255) NULL,
  status ENUM('draft','issued','paid','failed','canceled') NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

**Organización y usuarios (por tenant)**
```sql
CREATE TABLE tenant_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  user_id INT NOT NULL,
  role ENUM('owner_admin','empresa_admin','planificador','supervisor','empleado','cliente_viewer','temporera_admin','coordinador','trabajador') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tenant_user (tenant_id, user_id),
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

**Relaciones Empresa ↔ Temporera**
```sql
CREATE TABLE agency_company_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_company_id INT NOT NULL, -- Empresa
  tenant_agency_id INT NOT NULL,  -- Temporera
  status ENUM('active','paused','blocked') NOT NULL DEFAULT 'active',
  terms_json JSON NULL, -- tarifas/SLA/condiciones particulares
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_link (tenant_company_id, tenant_agency_id),
  FOREIGN KEY (tenant_company_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (tenant_agency_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

**Solicitudes y propuestas**
```sql
CREATE TABLE staffing_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_company_id INT NOT NULL,
  created_by_user INT NOT NULL,
  project_id INT NULL,
  date_from DATETIME NOT NULL,
  date_to DATETIME NOT NULL,
  location VARCHAR(160) NULL,
  qty_needed INT NOT NULL,
  requirements_json JSON NULL, -- idioma, skills, ropa, herramientas
  status ENUM('open','partially_filled','filled','canceled') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE staffing_request_targets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  tenant_agency_id INT NOT NULL,
  status ENUM('sent','responded','declined') NOT NULL DEFAULT 'sent',
  FOREIGN KEY (request_id) REFERENCES staffing_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE staffing_proposals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  tenant_agency_id INT NOT NULL,
  proposed_by_user INT NOT NULL,
  proposal_json JSON NOT NULL, -- lista de candidatos, tarifas
  status ENUM('pending_company','accepted','rejected','changed') NOT NULL DEFAULT 'pending_company',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (request_id) REFERENCES staffing_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

**Grupos y asignaciones**
```sql
CREATE TABLE work_groups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_company_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  leader_employee_id INT NULL, -- empleado propio
  project_id INT NULL,
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE group_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_id INT NOT NULL,
  member_type ENUM('own_employee','agency_worker') NOT NULL,
  own_employee_id INT NULL,
  agency_worker_id INT NULL,
  tenant_agency_id INT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NULL,
  UNIQUE KEY uq_member_span (group_id, member_type, COALESCE(own_employee_id,0), COALESCE(agency_worker_id,0), start_at),
  FOREIGN KEY (group_id) REFERENCES work_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

**Calendario y fichajes**
```sql
CREATE TABLE shifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  group_id INT NULL,
  employee_type ENUM('own_employee','agency_worker') NOT NULL,
  own_employee_id INT NULL,
  agency_worker_id INT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  location VARCHAR(160) NULL,
  status ENUM('planned','assigned','in_progress','done','canceled') NOT NULL DEFAULT 'planned',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE work_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  employee_type ENUM('own_employee','agency_worker') NOT NULL,
  own_employee_id INT NULL,
  agency_worker_id INT NULL,
  project_id INT NULL,
  date DATE NOT NULL,
  clock_in DATETIME NOT NULL,
  clock_out DATETIME NULL,
  breaks_minutes INT NOT NULL DEFAULT 0,
  status ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  approved_by INT NULL,
  approved_at DATETIME NULL,
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (tenant_id, date)
) ENGINE=InnoDB;
```

---

## 9) Integraciones & APIs
- **API pública REST** (`/api/v1`) con API Keys por tenant y scoping. Endpoints para: empleados/trabajadores, calendario, grupos, solicitudes, propuestas, fichajes, reportes.
- **Webhooks**: `request.created`, `proposal.submitted`, `proposal.accepted`, `shift.assigned`, `worklog.approved`, `invoice.issued`.
- **Conectores** (fase 2): export contable (CSV estándar) y adaptadores a ERPs suizos populares; export nómina (CSV genérico; ampliable a estándares locales si se requiere), almacenamiento S3-compatible.

---

## 10) Reportes (ambos tipos de tenant)
- **Horas**: por empleado/trabajador, por grupo, por cliente/proyecto, por período, por tipo (normal, extra, noche, festivo).
- **Planificación**: cumplimiento de turnos, cancelaciones, rellenos de última hora.
- **Relación Empresa↔Temporera**: solicitudes, tasa de fill, tiempos de respuesta, desempeño por trabajador, incidencias.
- **Costes**: coste estimado vs real (tarifa × horas), margen por proyecto (si se ingresan tarifas internas/externas).
- **Descargas**: CSV/XLSX/PDF.

---

## 11) Importación/Exportación
- **Plantillas CSV/XLSX** para empleados/trabajadores, clientes, proyectos, turnos.
- **Validaciones** (duplicados por email/teléfono/código, formatos, fechas), vista previa y confirmación.
- **Export masivo** de horas y asignaciones por rango.

---

## 12) Seguridad, cumplimiento y auditoría
- **Tenancy enforcement** en todas las consultas (`tenant_id`), middlewares de autorización.
- **Logs de auditoría** por tabla/acción con diffs.
- **Retención configurable** y **export data** por tenant.
- **Protección**: CSRF, rate limiting, 2FA opcional, hashing `bcrypt`, roles granulares.

---

## 13) Arquitectura técnica
- **Backend**: PHP 8.x (`mysqli`), MVC ligero, endpoints JSON, JWT/sesiones.
- **Frontend**: Bootstrap 5 + JS modular; calendario con librería (p. ej., FullCalendar) en modo drag & drop.
- **Almacenamiento**: MySQL; archivos en S3-compatible.
- **Jobs**: cron para snapshots de activos y generación de facturas.
- **Observabilidad**: logs estructurados, métricas de uso (por endpoint y acción).

---

## 14) Backlog funcional (detallado)
1. **Tenancy base**: tablas `tenants`, `tenant_users`, middleware de scoping.
2. **Onboarding**: alta de tenant (Empresa/Temporera), invitación de usuarios, selección de plan.
3. **Billing**: subscripciones, snapshots, cálculo variable, emisión de facturas.
4. **Relaciones Empresa↔Temporera**: `agency_company_links` y UI para conectar.
5. **Solicitudes & Propuestas**: creación, envío a agencias, recepción y respuesta.
6. **Calendario**: drag & drop, creación de turnos, conflictos y reglas.
7. **Grupos**: builder de grupos y asignaciones mixtas.
8. **Fichaje & Aprobaciones**: flujo completo y co-aprobación opcional.
9. **Reportes**: módulos y descargas.
10. **Import/Export**: plantillas y validación.
11. **APIs & Webhooks**: claves, scopes, eventos principales.
12. **Backoffice Owner**: planes, precios, métricas, soporte.

---

## 15) Roadmap de desarrollo (3 fases)
### Fase 0 — Fundaciones (2–3 semanas)
- Tenancy + RBAC + autenticación.
- Esquema DB completo (v2) e importadores.
- Onboarding tenants y UI básica de owner.

### Fase 1 — Núcleo Operativo (4–6 semanas)
- Relaciones Empresa↔Temporera.
- Solicitudes, propuestas y confirmaciones.
- Calendario drag & drop + builder de grupos.
- Fichaje y aprobaciones.
- Reportes esenciales.

### Fase 2 — Monetización y Escala (3–5 semanas)
- Billing fijo+variable con facturas PDF y pasarela.
- APIs públicas + webhooks.
- Integraciones (export contable/nómina).
- Observabilidad, auditoría avanzada y hardening seguridad.

> Hitos: **MVP operativo (Fase 1)** → **Go-to-market con billing (Fase 2)**.

---

## 16) Definiciones de Done (DoD)
- Cobertura de validaciones y errores JSON consistentes.
- Tenancy y permisos verificados por pruebas funcionales.
- Calendario: arrastrar/soltar con reglas de conflicto y duplicación.
- Facturas: cálculo reproducible con snapshot de activos.
- Auditoría activada para operaciones sensibles.

---

## 17) Configuración dinámica (Owner / Empresa / Temporera)
> Todo debe ser configurable por rol con herencia: **Owner** (valores por defecto globales) → **Tenant** (Empresa o Temporera) → **Proyecto/Cliente** → **Grupo**.

**Matriz de configuración (ejemplos clave)**
- **Fichaje**: obligatorio / opcional / sólo líder; tolerancia de llegada/salida; mínimo de pausa; redondeo.
- **Aprobaciones**: 1 etapa (Empresa) o 2 etapas (Empresa + Temporera); auto‑aprobación bajo umbral; bloqueo por incidencias.
- **Disputas**: ventana de reclamación (ej. 72h), evidencias requeridas (comentario, foto), escalado a admins.
- **Matching temporeros**: ponderación de criterios (disponibilidad, cercanía, skills, idioma, rating histórico); radio máximo km.
- **Calendario**: límites por día/semana, tamaño máximo de grupo, ratios líder:miembros.
- **Notificaciones**: qué eventos, por qué canal (email/SMS/WhatsApp/WebPush), plantillas y horarios «quiet hours».
- **Billing**: medida del variable (avg/peak), fecha de corte, moneda, impuestos.
- **Privacidad**: visibilidad de datos de contacto entre Empresa y Temporera (completo / enmascarado / sólo líder), retención.
- **Feature flags**: activar/desactivar módulos (webhooks, APIs públicas, OCR fichajes, etc.).

---

## 18) Parte del líder (reporte por grupo) + Disputas
### 18.1 Flujo base
1) **Líder de grupo** (empleado propio de la Empresa) registra el **parte** del turno: asistentes, horas reales, incidencias por persona (llegó tarde/no llegó/enviado a casa), pausas y notas.
2) El sistema **genera work_logs** para **todos los miembros** (propios y temporeros) con el mismo rango de horas (ajustables por persona si hubo diferencias).
3) **Empresa** revisa y **aprueba**; si hay trabajadores de Temporera y está activada la co‑aprobación, la **Temporera revisa**.
4) **Disputas**: la Temporera (o un trabajador habilitado) puede **reclamar** dentro de la ventana; Estados: `sin_disputa → disputado → resuelto (a favor empresa/temporera)`. Registro de evidencias.

### 18.2 UI del parte
- Tabla de miembros del grupo con controles rápidos: presente/tarde/ausente, ajuste de horas, causa, comentarios.
- Botón **Aplicar a todos** (p.ej. fin de turno 18:00) y editables por fila.
- Indicadores de validación: supera umbral diario/semana, pausa insuficiente, tramo nocturno/festivo.

### 18.3 Datos adicionales (modelo)
- `group_shift_reports` (cabecera) y `group_shift_report_lines` (por miembro, con estado y evidencias).
- `disputes` enlazadas a `work_logs`/líneas de parte con historial y resolución.

---

## 19) 5.1 Solicitud de personal — Preasignación + UX Drag & Drop
### 19.1 Preasignación
- El planificador **arma primero su plantilla** con **empleados propios** (calendario). Al detectar **déficit de personal**, pulsa **“Solicitar temporeros”** y el sistema crea la **Solicitud** con los huecos exactos (fechas, horas, ubicación, skills), cantidad por franja.

### 19.2 Respuesta de la Temporera (automática + filtros)
- Motor de **auto‑matching** propone candidatos priorizando: **disponibilidad**, **cercanía** (geocodificación por dirección), **skills/idiomas**, **rating histórico** con esa Empresa y **conflictos** (turnos superpuestos).
- El coordinador puede filtrar/ajustar (sliders de ponderación, checkboxes de skills, radio km) y **enviar propuesta**.

### 19.3 Confirmación y grupos
- La Empresa **acepta/ajusta** candidatos (drag & drop al grupo/turno), puede **solicitar cambios parciales**. Al confirmar, se **generan asignaciones** y se notifica a todos.

### 19.4 Valoraciones
- Tras el turno, la Empresa **valora** a cada temporero (1–5, tags: puntualidad, calidad, comunicación). Las valoraciones alimentan el **matching**.

---

## 20) Notificaciones & Automatización
- **Eventos**: solicitud creada, propuesta recibida, confirmación, cambio de horario, parte enviado, disputa creada, disputa resuelta, factura emitida.
- **Canales**: Email (SMTP), SMS (Twilio/u otro), WhatsApp (oficial/API partner), WebPush. Integración **n8n** vía **webhooks**.
- **Plantillas** multi‑idioma con variables (tenant, proyecto, horarios, enlaces seguros con token temporal).
- **Preferencias** por tenant y por usuario (opt‑in/out por canal, quiet hours, resumen diario/instantáneo).

---

## 21) Seguridad y multitenancy (profundizado)
- **Tenant scoping** obligatorio en backend (middleware + helpers SQL). Tests de aislamiento.
- **Listas cruzadas** Empresa↔Temporera sólo a través de `agency_company_links` y objetos compartidos (`staffing_requests`, `proposals`, `assignments`) con **ACL** explícitas.
- **Datos personales**: minimización, enmascaramiento configurable, logging de accesos, export/erase (GDPR/LPD).
- **Secretos y claves**: gestión en `.env`, rotación, hashing `bcrypt`, JWT con expiración corta y refresh.
- **Auditoría**: `audit_logs` con diffs y huella (IP/UA).

---

## 22) Roadmap (detallado por sprints)
### Fase 0 — Fundaciones (Sprints 0–2)
- **Sprint 0**: Esquema DB v2 extendido (partes y disputas), tenancy middleware, RBAC, seeds; páginas base Owner.
- **Sprint 1**: Onboarding tenants, agency_company_links, UI de relaciones, configuraciones base, feature flags.
- **Sprint 2**: Solicitudes (empresa) + Propuestas (temporera) + auto‑matching mínimo viable.

### Fase 1 — Núcleo Operativo (Sprints 3–6)
- **Sprint 3**: Calendario drag & drop + preasignación + generación de solicitud desde huecos.
- **Sprint 4**: Builder de grupos + confirmación de candidatos + asignaciones.
- **Sprint 5**: Parte del líder + generación de work_logs masivos + aprobaciones.
- **Sprint 6**: Disputas con evidencias + co‑aprobación temporera + valoraciones.

### Fase 2 — Monetización e Integraciones (Sprints 7–10)
- **Sprint 7**: Snapshots de activos + facturación fija/variable + facturas PDF.
- **Sprint 8**: Reportes avanzados + export contable/nómina + APIs públicas + webhooks.
- **Sprint 9**: Notificaciones multi‑canal + preferencias + plantillas.
- **Sprint 10**: Hardening de seguridad, auditoría avanzada, performance y escalado.

---

## 23) Reglas para Cursor (cursorrules.json)
> Objetivo: evitar refactors no pedidos, mantener multitenancy seguro y módulos aislados.

```json
{
  "noRefactorOutside": [
    "backend/core/*", "backend/api/*", "backend/models/*", "frontend/js/*", "frontend/pages/*"
  ],
  "codingStandards": {
    "php": {
      "db": "mysqli prepared",
      "noPDO": true,
      "strict_types": false,
      "auth": "JWT or PHP sessions",
      "output": "JSON consistent {success,error,data}"
    },
    "js": {"module": true, "noInlineHTML": true}
  },
  "tenancy": {
    "enforceTenantId": true,
    "requiredTablesWithTenantId": "*",
    "requireMiddleware": ["TenantMiddleware", "RBACMiddleware"],
    "forbidCrossTenantQueries": true
  },
  "dataIntegrity": {
    "requireFK": true,
    "onDelete": {"default": "RESTRICT", "attachments": "CASCADE"}
  },
  "security": {
    "csrf": true,
    "rateLimit": ["/auth/login", "/api/*"],
    "logging": ["auth", "billing", "sharing"],
    "maskPII": true
  },
  "i18n": {"keysOnlyForUI": true, "noDataTranslation": true},
  "featureFlags": ["webhooks", "apis_public", "ocr_fichajes"],
  "build": {"keepFiles": [".env.example", "docs/*"]}
}
```

---

## 24) To‑Do para Cursor (issues ejecutables)
> Formato: **ID – Título – Ruta/Archivo – Criterios de aceptación**.

1. **BE-001 – TenantMiddleware** – `backend/core/Tenant.php` – Extrae `tenant_id` de JWT/sesión/cabecera y lo inyecta en contexto; tests con aislamiento.
2. **BE-002 – RBACMiddleware** – `backend/core/RBAC.php` – Verifica permisos por rol/acción; matriz inicial.
3. **DB-003 – Migraciones v2** – `backend/db/schema_v2.sql` – Añade tablas: `group_shift_reports`, `group_shift_report_lines`, `disputes`, `audit_logs`.
4. **BE-004 – API Solicitudes** – `backend/api/staffing_requests.php` – CRUD + envío a temporeras vinculadas.
5. **BE-005 – MatchingService** – `backend/core/MatchingService.php` – Disponibilidad, proximidad (Haversine), skills, rating (ponderable); retorna ranking.
6. **BE-006 – API Propuestas** – `backend/api/staffing_proposals.php` – Crear/leer/aceptar/rechazar; auditar cambios.
7. **FE-007 – Calendario** – `frontend/js/calendar.js` + `pages/calendar.html` – FullCalendar drag&drop; crea huecos/turnos/preasignación.
8. **FE-008 – Builder de grupos** – `frontend/js/group-builder.js` – UI con sidebar candidatos (propios/temporeros) + DnD.
9. **BE-009 – API Grupos & Asignaciones** – `backend/api/groups.php` / `backend/api/shifts.php` – Endpoints y validaciones de conflictos.
10. **BE-010 – Parte del líder** – `backend/api/group_reports.php` – Crear parte, generar work_logs masivos.
11. **BE-011 – Aprobaciones & Co‑aprobación** – `backend/api/approvals.php` – Flujo 1/2 etapas configurable.
12. **BE-012 – Disputas** – `backend/api/disputes.php` – Crear, adjuntar evidencias, resolver; notificar.
13. **BE-013 – Valoraciones** – `backend/api/ratings.php` – Puntuar temporeros; afecta MatchingService.
14. **BE-014 – Snapshots activos** – `backend/jobs/usage_snapshot.php` – Cuenta activos/día por tenant.
15. **BE-015 – Facturación** – `backend/jobs/invoice_run.php` + `api/invoices.php` – Calcula fijo+variable; genera PDF.
16. **BE-016 – Notificaciones** – `backend/core/Notifications.php` – Email/SMS/WhatsApp; plantillas; webhooks a n8n.
17. **FE-017 – Preferencias** – `frontend/js/settings-notifications.js` – UI por usuario/tenant; quiet hours.
18. **SEC-018 – Auditoría** – `backend/core/Audit.php` – Registra diffs; endpoints consultables por owner.
19. **INT-019 – Export contable/nómina** – `backend/api/exports.php` – CSV estándar; hooks para adaptadores.
20. **OPS-020 – Tests de aislamiento** – `tests/tenancy_isolation.test.php` – Confirma que no hay fugas cross‑tenant.

---

## 25) Contratos API clave (resumen)
- `POST /staffing-requests` {date_from, date_to, qty_needed, requirements, targets[]} → {request_id}
- `POST /staffing-proposals` {request_id, candidates:[{worker_id, score, distance_km, skills[], available:true}]}
- `POST /group-reports` {group_id, shift_id, lines:[{member_id,type,attended,clock_in,clock_out,breaks,notes}]} → genera work_logs
- `POST /disputes` {work_log_id, reason, evidence_urls[]} → {dispute_id}
- `POST /ratings` {worker_id, score(1-5), tags[]} → afecta matching
- `POST /notifications/test` {channel, template, to, variables}

---

## 26) Próximos pasos inmediatos (Sprint 0 actualizado)
1. Aplicar **migraciones v2 extendidas** (partes/disputas/ratings/auditoría).
2. Implementar **TenantMiddleware** + **RBACMiddleware** con tests.
3. Esqueleto de **Solicitudes/Propuestas** + **MatchingService** mínimo.
4. Prototipo **Calendario** con preasignación y creación de solicitud desde huecos.
5. Servicio de **Notificaciones** con plantillas y webhook n8n.

---
¿Quieres que empiece generando los archivos base de **middlewares, endpoints y jobs** (IDs BE-001 a BE-006) directamente para que los integres en tu repo?


---

## 27) Órdenes de Trabajo (OT) — multi‑día / multi‑mes
> Las empresas crean **Órdenes de Trabajo** para que los empleados (propios y temporeros asignados) visualicen, ejecuten y reporten el avance. Se conectan con calendario, grupos, partes y facturación.

### 27.1 Objetivos de OT
- Unificar **alcance**, **tareas**, **materiales**, **checklists**, **riesgos/seguridad**, **horarios**, **ubicaciones** y **entregables**.
- Permitir **duración** de 1 día a varios meses, con **rotación** de personal y **historial** por día.
- Facilitar **parte del líder** y la generación automática de `work_logs`.

### 27.2 Flujo general
1) **Empresa** crea OT (desde cero o desde plantilla), define alcance, cliente/proyecto, fechas estimadas, hitos y adjunta documentación (planos, fotos, instrucciones).
2) Planificador **agenda** los turnos en el calendario (propios) y solicita **temporeros** si faltan recursos.
3) Al **confirmar candidatos** de la temporera, el sistema **asigna** miembros a la OT (grupos o individuales).
4) Cada día, el **líder** genera **parte** (asistencia/horas/incidencias/materiales/fotos), lo que crea `work_logs` y avanza el **progreso** de la OT.
5) **Aprobaciones**: Empresa (y Temporera si aplica). Posibles **disputas**.
6) **Cierre de OT**: checklist final, **firma del cliente** (digital), cargas de evidencias y exportables (PDF/CSV).

### 27.3 Modelo de datos (nuevo)
```sql
CREATE TABLE work_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  code VARCHAR(50) NULL,
  title VARCHAR(180) NOT NULL,
  client_id INT NULL,
  project_id INT NULL,
  location VARCHAR(200) NULL,
  start_planned DATE NULL,
  end_planned DATE NULL,
  start_actual DATE NULL,
  end_actual DATE NULL,
  status ENUM('draft','planned','in_progress','on_hold','completed','canceled') NOT NULL DEFAULT 'draft',
  scope_text TEXT NULL,
  safety_notes TEXT NULL,
  attachments_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE work_order_tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  work_order_id INT NOT NULL,
  pos INT NOT NULL DEFAULT 1,
  name VARCHAR(160) NOT NULL,
  description TEXT NULL,
  estimated_hours DECIMAL(6,2) NULL,
  required_skills JSON NULL,
  FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE work_order_checklists (
  id INT AUTO_INCREMENT PRIMARY KEY,
  work_order_id INT NOT NULL,
  item VARCHAR(200) NOT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE work_order_materials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  work_order_id INT NOT NULL,
  name VARCHAR(160) NOT NULL,
  unit VARCHAR(20) NULL,
  qty_planned DECIMAL(10,2) NULL,
  qty_used DECIMAL(10,2) NULL,
  FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE work_order_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  work_order_id INT NOT NULL,
  group_id INT NULL,
  employee_type ENUM('own_employee','agency_worker') NOT NULL,
  own_employee_id INT NULL,
  agency_worker_id INT NULL,
  tenant_agency_id INT NULL,
  start_at DATETIME NULL,
  end_at DATETIME NULL,
  FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE work_order_daily_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  work_order_id INT NOT NULL,
  date DATE NOT NULL,
  group_id INT NULL,
  leader_user_id INT NOT NULL,
  notes TEXT NULL,
  progress_pct DECIMAL(5,2) NULL,
  attachments_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wo_date_group (work_order_id, date, COALESCE(group_id,0)),
  FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE work_order_daily_lines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  daily_report_id INT NOT NULL,
  member_type ENUM('own_employee','agency_worker') NOT NULL,
  own_employee_id INT NULL,
  agency_worker_id INT NULL,
  attended TINYINT(1) NOT NULL DEFAULT 1,
  clock_in DATETIME NULL,
  clock_out DATETIME NULL,
  breaks_minutes INT NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  FOREIGN KEY (daily_report_id) REFERENCES work_order_daily_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE work_order_signoffs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  work_order_id INT NOT NULL,
  signed_by VARCHAR(160) NULL,
  role ENUM('client','company_leader','agency_coordinator') NOT NULL,
  signed_at DATETIME NULL,
  signature_url VARCHAR(255) NULL,
  FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE work_order_changes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  work_order_id INT NOT NULL,
  change_type ENUM('scope','schedule','resources') NOT NULL,
  description TEXT NOT NULL,
  requested_by INT NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

> Notas: las OT se vinculan a **calendario** mediante `work_order_assignments` y `shifts`; el **parte del líder** puede generarse como `work_order_daily_reports` (equivalente extendido a `group_shift_reports`).

### 27.4 UX de OT
- **Editor de OT** con: datos del cliente/proyecto, alcance, tareas, checklists, materiales, seguridad, adjuntos.
- **Vista calendario** de la OT (Gantt ligero): planificación por días/semanas/meses.
- **Tablero de progreso**: % completado, horas planificadas vs registradas, incidencias.
- **Documento PDF** (o impresión) con QR de acceso rápido para el equipo in situ.

### 27.5 Integraciones y firmas
- **Firma digital** del cliente al cierre o por hito.
- **Fotos** y anotaciones (antes/después) — compatibilidad móvil.
- **Exportes**: horas/materiales por OT para facturación/costeo.

### 27.6 Notificaciones
- Publicación de OT, cambios de alcance/horario, recordatorios diarios, solicitud de firma.

---

## 28) API – Endpoints adicionales (OT)
- `POST /work-orders` (crear), `GET /work-orders?status&from&to&client_id`.
- `POST /work-orders/{id}/assignments` (añadir/quitar miembros o grupos; fechas).
- `POST /work-orders/{id}/daily-reports` + `.../lines` (parte diario + líneas) → genera `work_logs`.
- `POST /work-orders/{id}/signoff` (firma cliente/empresa/temporera).
- `POST /work-orders/{id}/changes` (solicitudes de cambio) → workflow aprobación.

---

## 29) To‑Do para Cursor (ampliación OT)
21. **DB-021 – Esquema OT** – `backend/db/schema_work_orders.sql` – Tablas de 27.3 con FKs y claves únicas.
22. **BE-022 – API WorkOrders** – `backend/api/work_orders.php` – CRUD + búsqueda + publicación.
23. **BE-023 – API Assignments** – `backend/api/work_order_assignments.php` – Alta/baja/actualización; validaciones de conflicto.
24. **BE-024 – API Daily Reports** – `backend/api/work_order_daily_reports.php` – Parte diario + líneas → creación de `work_logs`.
25. **BE-025 – API Signoffs** – `backend/api/work_order_signoffs.php` – Captura de firma (imagen/base64) + registro.
26. **BE-026 – API Changes** – `backend/api/work_order_changes.php` – Solicitudes con aprobación.
27. **FE-027 – UI Editor OT** – `frontend/pages/work_orders_edit.html` + `js/work_orders_edit.js` – CRUD, tareas, checklists, materiales.
28. **FE-028 – UI OT Calendar/Gantt** – `frontend/js/work_orders_calendar.js` – Vista por días/semanas/meses.
29. **FE-029 – UI Daily Report** – `frontend/js/work_orders_daily.js` – Parte del líder para OT; adjuntos y validaciones.
30. **FE-030 – PDF OT** – `backend/helpers/pdf_work_order.php` – Generación PDF con QR.

---

## 30) Seguridad y privacidad (OT)
- **Scope** por `tenant_id` en todas las tablas nuevas.
- **ACL**: sólo grupos/miembros asignados ven la OT; la Temporera sólo ve datos necesarios (enmascaramiento si se configura).
- **Auditoría**: cambios en alcance, firmas, materiales y partes.

---

## 31) Roadmap actualizado (añadiendo OT)
- **Sprint 3.5** (entre 3 y 4): **Esquema OT + Editor OT** (DB-021, BE-022, FE-027).
- **Sprint 4.5** (entre 4 y 5): **Assignments + Gantt** (BE-023, FE-028).
- **Sprint 5.5** (entre 5 y 6): **Daily Reports + Logs** (BE-024, FE-029) y **PDF + Firmas** (BE-025, FE-030).

---

