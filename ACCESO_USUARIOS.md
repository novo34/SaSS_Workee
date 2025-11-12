# 👥 Acceso por Tipo de Usuario - Workee SaaS

## 📋 Resumen de Roles

El sistema Workee tiene diferentes tipos de usuarios según su rol y tenant. Cada rol tiene permisos y funcionalidades específicas.

---

## 🏢 ROLES DE EMPRESA

### 1. 👔 Empresa Admin (`empresa_admin`)

**Descripción:** Administrador completo de una empresa. Gestiona empleados, proyectos, solicitudes y configuración.

#### 🔐 Credenciales de Prueba

**Email:** `admin@empresa.com`  
**Password:** `password`  
**Role:** `empresa_admin`  
**Tenant:** Empresa Test

#### 🌐 Acceso y Funcionalidades

**URLs Disponibles:**
- Dashboard: `http://localhost/SaSS_Workee/dashboard`
- Solicitudes de Personal: `http://localhost/SaSS_Workee/staffing-requests`
- Relaciones: `http://localhost/SaSS_Workee/agency-company-links`
- Configuración: `http://localhost/SaSS_Workee/settings`

**Menú en Sidebar:**
```
📊 Dashboard
📋 Principal
   🔗 Relaciones
   ⚙️ Configuración
📋 Empresa
   📋 Solicitudes de Personal
```

**Permisos:**
- ✅ Gestionar empleados propios
- ✅ Gestionar clientes y proyectos
- ✅ Crear y gestionar solicitudes de personal
- ✅ Ver y gestionar propuestas recibidas
- ✅ Gestionar grupos de trabajo
- ✅ Gestionar turnos y asignaciones
- ✅ Ver y aprobar work logs
- ✅ Gestionar relaciones con temporeras
- ✅ Invitar usuarios
- ✅ Configuración del tenant

**APIs Disponibles:**
- `GET/POST /api/v1/staffing-requests` - Solicitudes
- `GET/PUT /api/v1/staffing-proposals/{id}/accept|reject` - Propuestas
- `GET/POST /api/v1/agency-company-links` - Relaciones
- `GET/POST /api/v1/users/invite` - Invitar usuarios
- `GET/PUT /api/v1/users` - Gestionar usuarios
- `GET/PUT /api/v1/config` - Configuración

---

### 2. 📅 Planificador (`planificador`)

**Descripción:** Planifica turnos, crea solicitudes y gestiona el calendario.

#### 🔐 Credenciales de Prueba

**Crear usuario:**
```sql
-- Crear usuario planificador
INSERT INTO users (email, password_hash, first_name, last_name, is_active)
VALUES ('planificador@empresa.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Planificador', 'Test', 1);
-- Password: password

-- Asignar a empresa (reemplazar USER_ID y TENANT_ID)
INSERT INTO tenant_users (tenant_id, user_id, role, is_active)
VALUES (TENANT_ID, USER_ID, 'planificador', 1);
```

**Email:** `planificador@empresa.com`  
**Password:** `password`  
**Role:** `planificador`

#### 🌐 Acceso y Funcionalidades

**URLs Disponibles:**
- Dashboard: `http://localhost/SaSS_Workee/dashboard`
- Solicitudes de Personal: `http://localhost/SaSS_Workee/staffing-requests`
- Calendario: `http://localhost/SaSS_Workee/calendar` (cuando esté implementado)

**Menú en Sidebar:**
```
📊 Dashboard
📋 Principal
   🔗 Relaciones
   ⚙️ Configuración
📋 Empresa
   📋 Solicitudes de Personal
```

**Permisos:**
- ✅ Crear y gestionar solicitudes de personal
- ✅ Ver propuestas recibidas
- ✅ Aceptar/rechazar propuestas
- ✅ Gestionar turnos (shifts)
- ✅ Gestionar grupos de trabajo
- ✅ Gestionar órdenes de trabajo (OT)
- ✅ Acceso al calendario con drag & drop

**APIs Disponibles:**
- `GET/POST /api/v1/staffing-requests` - Solicitudes
- `GET/PUT /api/v1/staffing-proposals/{id}/accept|reject` - Propuestas
- `GET/POST /api/v1/shifts` - Turnos
- `GET/POST /api/v1/work-groups` - Grupos
- `GET/POST /api/v1/work-orders` - Órdenes de trabajo

---

### 3. 👨‍💼 Supervisor (`supervisor`)

**Descripción:** Supervisa y aprueba horas trabajadas, revisa partes del líder.

#### 🔐 Credenciales de Prueba

**Crear usuario:**
```sql
INSERT INTO users (email, password_hash, first_name, last_name, is_active)
VALUES ('supervisor@empresa.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Supervisor', 'Test', 1);

INSERT INTO tenant_users (tenant_id, user_id, role, is_active)
VALUES (TENANT_ID, USER_ID, 'supervisor', 1);
```

**Email:** `supervisor@empresa.com`  
**Password:** `password`  
**Role:** `supervisor`

#### 🌐 Acceso y Funcionalidades

**URLs Disponibles:**
- Dashboard: `http://localhost/SaSS_Workee/dashboard`
- Aprobaciones: `http://localhost/SaSS_Workee/approvals` (cuando esté implementado)
- Reportes: `http://localhost/SaSS_Workee/reports` (cuando esté implementado)

**Menú en Sidebar:**
```
📊 Dashboard
📋 Principal
   🔗 Relaciones
   ⚙️ Configuración
```

**Permisos:**
- ✅ Ver work logs
- ✅ Aprobar/rechazar work logs
- ✅ Ver reportes
- ❌ No puede crear solicitudes
- ❌ No puede gestionar empleados

**APIs Disponibles:**
- `GET /api/v1/work-logs` - Ver work logs
- `PUT /api/v1/approvals/{id}/approve|reject` - Aprobar/rechazar
- `GET /api/v1/reports` - Reportes

---

### 4. 👷 Empleado (`empleado`)

**Descripción:** Empleado propio de la empresa. Puede fichar y ver sus turnos.

#### 🔐 Credenciales de Prueba

**Crear usuario:**
```sql
INSERT INTO users (email, password_hash, first_name, last_name, is_active)
VALUES ('empleado@empresa.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Empleado', 'Test', 1);

INSERT INTO tenant_users (tenant_id, user_id, role, is_active)
VALUES (TENANT_ID, USER_ID, 'empleado', 1);
```

**Email:** `empleado@empresa.com`  
**Password:** `password`  
**Role:** `empleado`

#### 🌐 Acceso y Funcionalidades

**URLs Disponibles:**
- Dashboard: `http://localhost/SaSS_Workee/dashboard`
- Mis Turnos: `http://localhost/SaSS_Workee/my-shifts` (cuando esté implementado)
- Fichaje: `http://localhost/SaSS_Workee/timeclock` (cuando esté implementado)

**Menú en Sidebar:**
```
📊 Dashboard
📋 Principal
   🔗 Relaciones
   ⚙️ Configuración
```

**Permisos:**
- ✅ Ver sus propios turnos
- ✅ Crear work logs (fichaje)
- ✅ Ver sus propios work logs
- ❌ No puede ver otros empleados
- ❌ No puede crear solicitudes
- ❌ No puede aprobar horas

**APIs Disponibles:**
- `GET /api/v1/shifts?my_shifts=true` - Ver sus turnos
- `POST /api/v1/work-logs` - Crear fichaje
- `GET /api/v1/work-logs?my_logs=true` - Ver sus work logs

---

## 🏭 ROLES DE TEMPORERA

### 5. 🏢 Temporera Admin (`temporera_admin`)

**Descripción:** Administrador completo de una temporera. Gestiona trabajadores, propuestas y asignaciones.

#### 🔐 Credenciales de Prueba

**Email:** `admin@temporera.com`  
**Password:** `password`  
**Role:** `temporera_admin`  
**Tenant:** Temporera Test

#### 🌐 Acceso y Funcionalidades

**URLs Disponibles:**
- Dashboard: `http://localhost/SaSS_Workee/dashboard`
- Propuestas: `http://localhost/SaSS_Workee/staffing-proposals`
- Relaciones: `http://localhost/SaSS_Workee/agency-company-links`
- Configuración: `http://localhost/SaSS_Workee/settings`

**Menú en Sidebar:**
```
📊 Dashboard
📋 Principal
   🔗 Relaciones
   ⚙️ Configuración
💼 Temporera
   💼 Propuestas de Candidatos
```

**Permisos:**
- ✅ Gestionar trabajadores temporeros
- ✅ Ver solicitudes recibidas
- ✅ Crear propuestas de candidatos
- ✅ Gestionar asignaciones
- ✅ Ver work logs de trabajadores
- ✅ Gestionar relaciones con empresas
- ✅ Invitar usuarios
- ✅ Configuración del tenant

**APIs Disponibles:**
- `GET/POST /api/v1/staffing-proposals` - Propuestas
- `GET /api/v1/staffing-requests` - Ver solicitudes recibidas
- `GET/POST /api/v1/agency-workers` - Trabajadores
- `GET/POST /api/v1/agency-company-links` - Relaciones
- `GET/POST /api/v1/users/invite` - Invitar usuarios

---

### 6. 📋 Coordinador (`coordinador`)

**Descripción:** Coordina trabajadores, crea propuestas y gestiona asignaciones.

#### 🔐 Credenciales de Prueba

**Crear usuario:**
```sql
INSERT INTO users (email, password_hash, first_name, last_name, is_active)
VALUES ('coordinador@temporera.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Coordinador', 'Test', 1);

INSERT INTO tenant_users (tenant_id, user_id, role, is_active)
VALUES (TENANT_ID, USER_ID, 'coordinador', 1);
```

**Email:** `coordinador@temporera.com`  
**Password:** `password`  
**Role:** `coordinador`

#### 🌐 Acceso y Funcionalidades

**URLs Disponibles:**
- Dashboard: `http://localhost/SaSS_Workee/dashboard`
- Propuestas: `http://localhost/SaSS_Workee/staffing-proposals`

**Menú en Sidebar:**
```
📊 Dashboard
📋 Principal
   🔗 Relaciones
   ⚙️ Configuración
💼 Temporera
   💼 Propuestas de Candidatos
```

**Permisos:**
- ✅ Ver trabajadores
- ✅ Ver solicitudes recibidas
- ✅ Crear propuestas (con auto-matching)
- ✅ Ver asignaciones
- ✅ Ver work logs
- ❌ No puede gestionar usuarios
- ❌ No puede cambiar configuración

**APIs Disponibles:**
- `GET/POST /api/v1/staffing-proposals` - Propuestas
- `GET /api/v1/staffing-requests` - Ver solicitudes
- `GET /api/v1/agency-workers` - Ver trabajadores
- `GET /api/v1/assignments` - Ver asignaciones

---

### 7. 👷 Trabajador (`trabajador`)

**Descripción:** Trabajador temporero. Puede fichar y ver sus asignaciones.

#### 🔐 Credenciales de Prueba

**Crear usuario:**
```sql
INSERT INTO users (email, password_hash, first_name, last_name, is_active)
VALUES ('trabajador@temporera.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Trabajador', 'Test', 1);

INSERT INTO tenant_users (tenant_id, user_id, role, is_active)
VALUES (TENANT_ID, USER_ID, 'trabajador', 1);
```

**Email:** `trabajador@temporera.com`  
**Password:** `password`  
**Role:** `trabajador`

#### 🌐 Acceso y Funcionalidades

**URLs Disponibles:**
- Dashboard: `http://localhost/SaSS_Workee/dashboard`
- Mis Asignaciones: `http://localhost/SaSS_Workee/my-assignments` (cuando esté implementado)
- Fichaje: `http://localhost/SaSS_Workee/timeclock` (cuando esté implementado)

**Menú en Sidebar:**
```
📊 Dashboard
📋 Principal
   🔗 Relaciones
   ⚙️ Configuración
```

**Permisos:**
- ✅ Ver sus propias asignaciones
- ✅ Crear work logs (fichaje)
- ✅ Ver sus propios work logs
- ❌ No puede ver otros trabajadores
- ❌ No puede crear propuestas
- ❌ No puede ver solicitudes

**APIs Disponibles:**
- `GET /api/v1/shifts?my_shifts=true` - Ver sus turnos
- `POST /api/v1/work-logs` - Crear fichaje
- `GET /api/v1/work-logs?my_logs=true` - Ver sus work logs

---

## 👑 ROLES ESPECIALES

### 8. 👑 Owner Admin (`owner_admin`)

**Descripción:** Administrador del sistema SaaS. Gestiona todos los tenants, planes y facturación.

#### 🔐 Credenciales de Prueba

**Email:** `owner@workee.app`  
**Password:** `password`  
**Role:** `owner_admin`  
**Tenant:** Workee System (ID: 22)

**Crear usuario:**
```bash
php scripts/dev/create_owner_user.php
```

#### 🌐 Acceso y Funcionalidades

**URLs Disponibles:**
- Dashboard Owner: `http://localhost/SaSS_Workee/owner/dashboard`
- Dashboard: `http://localhost/SaSS_Workee/dashboard`

**Menú en Sidebar:**
```
📊 Dashboard
📋 Principal
   🔗 Relaciones
   ⚙️ Configuración
👑 Owner
   👑 Dashboard Owner
```

**Permisos:**
- ✅ Crear y gestionar todos los tenants
- ✅ Gestionar planes y suscripciones
- ✅ Ver facturación y billing
- ✅ Ver métricas del negocio SaaS
- ✅ Configuración global
- ✅ Ver todos los usuarios del sistema

**APIs Disponibles:**
- `GET/POST /api/v1/tenants` - Gestionar tenants
- `GET /api/v1/subscriptions` - Ver suscripciones
- `GET /api/v1/invoices` - Ver facturas
- `GET /api/v1/users` - Ver todos los usuarios

**Ver documentación completa:** `ACCESO_OWNER.md`

---

## 📝 Script para Crear Todos los Usuarios de Prueba

**Script creado:** `scripts/dev/create_all_test_users.php`

Ejecuta para crear todos los usuarios de prueba:

```bash
php scripts/dev/create_all_test_users.php
```

Este script crea automáticamente:
- ✅ Usuario Planificador (empresa)
- ✅ Usuario Supervisor (empresa)
- ✅ Usuario Empleado (empresa)
- ✅ Usuario Coordinador (temporera)
- ✅ Usuario Trabajador (temporera)

**Nota:** Los usuarios Owner, Empresa Admin y Temporera Admin se crean con otros scripts:
- Owner: `php scripts/dev/create_owner_user.php`
- Empresa/Temporera Admin: `php scripts/dev/create_test_data.php`

---

## 🔍 Verificar Usuarios en Base de Datos

### Ver todos los usuarios y sus roles

```sql
SELECT 
    u.id,
    u.email,
    u.first_name,
    u.last_name,
    tu.role,
    t.name as tenant_name,
    t.org_type
FROM users u
INNER JOIN tenant_users tu ON u.id = tu.user_id
INNER JOIN tenants t ON tu.tenant_id = t.id
WHERE tu.is_active = 1
ORDER BY tu.role, t.name;
```

### Ver usuarios por rol específico

```sql
-- Ver todos los empresa_admin
SELECT u.email, t.name as tenant_name
FROM users u
INNER JOIN tenant_users tu ON u.id = tu.user_id
INNER JOIN tenants t ON tu.tenant_id = t.id
WHERE tu.role = 'empresa_admin' AND tu.is_active = 1;
```

---

## 🎯 Matriz de Permisos por Rol

| Funcionalidad | Owner | Empresa Admin | Planificador | Supervisor | Empleado | Temporera Admin | Coordinador | Trabajador |
|--------------|-------|--------------|--------------|------------|----------|-----------------|-------------|------------|
| **Solicitudes** | ❌ | ✅ Crear/Gestionar | ✅ Crear/Gestionar | ❌ | ❌ | ❌ Ver | ❌ Ver | ❌ |
| **Propuestas** | ❌ | ✅ Ver/Aceptar | ✅ Ver/Aceptar | ❌ | ❌ | ✅ Crear/Gestionar | ✅ Crear | ❌ |
| **Trabajadores** | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ Gestionar | ✅ Ver | ❌ |
| **Empleados** | ❌ | ✅ Gestionar | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Turnos** | ❌ | ✅ Gestionar | ✅ Gestionar | ❌ | ✅ Ver propios | ❌ | ❌ | ✅ Ver propios |
| **Work Logs** | ❌ | ✅ Ver/Aprobar | ❌ | ✅ Ver/Aprobar | ✅ Crear/Ver propios | ✅ Ver | ✅ Ver | ✅ Crear/Ver propios |
| **Grupos** | ❌ | ✅ Gestionar | ✅ Gestionar | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Relaciones** | ❌ | ✅ Gestionar | ❌ | ❌ | ❌ | ✅ Gestionar | ❌ | ❌ |
| **Configuración** | ✅ Global | ✅ Tenant | ❌ | ❌ | ❌ | ✅ Tenant | ❌ | ❌ |
| **Usuarios** | ✅ Todos | ✅ Invitar/Gestionar | ❌ | ❌ | ❌ | ✅ Invitar/Gestionar | ❌ | ❌ |
| **Tenants** | ✅ Crear/Gestionar | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

---

## 🚀 Flujo de Prueba por Rol

### Como Empresa Admin

1. Login con `admin@empresa.com` / `password`
2. Crear solicitud de personal
3. Enviar solicitud a temporeras vinculadas
4. Ver propuestas recibidas
5. Aceptar/rechazar propuestas
6. Gestionar relaciones con temporeras

### Como Temporera Admin

1. Login con `admin@temporera.com` / `password`
2. Ver solicitudes recibidas
3. Crear propuesta con auto-matching
4. Gestionar trabajadores
5. Ver asignaciones

### Como Planificador

1. Login con `planificador@empresa.com` / `password`
2. Crear solicitudes de personal
3. Gestionar turnos en calendario
4. Aceptar propuestas

### Como Owner

1. Login con `owner@workee.app` / `password`
2. Ver dashboard con métricas
3. Crear nuevos tenants
4. Ver todos los tenants del sistema

---

## 📚 Documentación Relacionada

- **Owner:** `ACCESO_OWNER.md`
- **Cómo Probar:** `COMO_PROBAR.md`
- **Sprint 2:** `COMO_PROBAR_SPRINT2.md`
- **Inicio Rápido:** `QUICK_START.md`

