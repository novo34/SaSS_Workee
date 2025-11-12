# 🔐 Resumen de Credenciales - Workee SaaS

## 📋 Credenciales de Acceso Rápido

**Todos los usuarios tienen la misma contraseña:** `password`

---

## 👑 OWNER (Administrador del Sistema)

| Email | Password | Role | Tenant ID |
|-------|----------|------|-----------|
| `owner@workee.app` | `password` | `owner_admin` | 22 |

**Crear:** `php scripts/dev/create_owner_user.php`

**Acceso:**
- Dashboard Owner: `http://localhost/SaSS_Workee/owner/dashboard`
- Puede gestionar todos los tenants del sistema

---

## 🏢 EMPRESA (Tenant ID: 18)

| Email | Password | Role | Funcionalidad Principal |
|-------|----------|------|-------------------------|
| `admin@empresa.com` | `password` | `empresa_admin` | Administración completa |
| `planificador@empresa.com` | `password` | `planificador` | Planificación y solicitudes |
| `supervisor@empresa.com` | `password` | `supervisor` | Aprobación de horas |
| `empleado@empresa.com` | `password` | `empleado` | Fichaje y ver turnos |

**Crear todos:** `php scripts/dev/create_all_test_users.php`

---

## 🏭 TEMPORERA (Tenant ID: 19)

| Email | Password | Role | Funcionalidad Principal |
|-------|----------|------|-------------------------|
| `admin@temporera.com` | `password` | `temporera_admin` | Administración completa |
| `coordinador@temporera.com` | `password` | `coordinador` | Propuestas y coordinación |
| `trabajador@temporera.com` | `password` | `trabajador` | Fichaje y ver asignaciones |

**Crear todos:** `php scripts/dev/create_all_test_users.php`

---

## 🚀 Crear Todos los Usuarios de Prueba

### Opción 1: Script Completo (Recomendado)

```bash
# 1. Crear Owner
php scripts/dev/create_owner_user.php

# 2. Crear Empresa y Temporera Admin
php scripts/dev/create_test_data.php

# 3. Crear todos los demás usuarios
php scripts/dev/create_all_test_users.php
```

### Opción 2: Script Individual

```bash
# Solo crear usuarios adicionales (si ya tienes Owner, Empresa y Temporera)
php scripts/dev/create_all_test_users.php
```

---

## 📊 Matriz de Acceso Rápido

| Rol | Email | Puede Acceder a |
|-----|-------|-----------------|
| **Owner** | `owner@workee.app` | Dashboard Owner, Gestión de tenants |
| **Empresa Admin** | `admin@empresa.com` | Todo de empresa + Solicitudes + Relaciones |
| **Planificador** | `planificador@empresa.com` | Solicitudes + Turnos + Calendario |
| **Supervisor** | `supervisor@empresa.com` | Aprobaciones + Reportes |
| **Empleado** | `empleado@empresa.com` | Fichaje + Ver turnos propios |
| **Temporera Admin** | `admin@temporera.com` | Todo de temporera + Propuestas + Relaciones |
| **Coordinador** | `coordinador@temporera.com` | Propuestas + Ver trabajadores |
| **Trabajador** | `trabajador@temporera.com` | Fichaje + Ver asignaciones propias |

---

## 🔍 Verificar Usuarios Creados

### Desde Base de Datos

```sql
SELECT 
    u.email,
    tu.role,
    t.name as tenant_name,
    t.org_type
FROM users u
INNER JOIN tenant_users tu ON u.id = tu.user_id
INNER JOIN tenants t ON tu.tenant_id = t.id
WHERE tu.is_active = 1
ORDER BY tu.role, t.name;
```

### Desde la Aplicación

1. Haz login con cualquier credencial
2. Verifica que el menú muestra las secciones correctas según tu rol
3. Verifica que puedes acceder a las funcionalidades permitidas

---

## 📚 Documentación Completa

- **Todos los Usuarios:** `ACCESO_USUARIOS.md` (detalles completos por rol)
- **Owner:** `ACCESO_OWNER.md` (funcionalidades específicas de Owner)
- **Cómo Probar:** `COMO_PROBAR.md`
- **Sprint 2:** `COMO_PROBAR_SPRINT2.md`

---

## ⚠️ Notas de Seguridad

- ⚠️ Estas credenciales son **SOLO PARA DESARROLLO**
- ⚠️ En producción, cambiar todas las contraseñas
- ⚠️ El Owner debe tener una contraseña fuerte
- ⚠️ Implementar 2FA para roles administrativos

