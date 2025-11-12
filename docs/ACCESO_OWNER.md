# 👑 Acceso como Owner - Workee SaaS

## ¿Qué es el Owner?

El **Owner** es el administrador del sistema SaaS que gestiona:
- ✅ Todos los tenants (Empresas y Temporeras)
- ✅ Planes y suscripciones
- ✅ Facturación y billing
- ✅ Configuración global del sistema
- ✅ Métricas y reportes del negocio SaaS

## 🔐 Credenciales de Owner

### Crear Usuario Owner

Ejecuta el script para crear el usuario owner:

```bash
php scripts/dev/create_owner_user.php
```

### Credenciales por Defecto

**Email:** `owner@workee.app`  
**Password:** `password`  
**Role:** `owner_admin`  
**Tenant ID:** (ID del tenant especial "Workee System")

## 🌐 Acceso

### 1. Login

**URL:** `http://localhost/SaSS_Workee/login`

1. Ingresa las credenciales:
   - Email: `owner@workee.app`
   - Password: `password`

2. Después del login, serás redirigido al dashboard

### 2. Dashboard Owner

**URL:** `http://localhost/SaSS_Workee/owner/dashboard`

El dashboard muestra:
- Total de tenants
- MRR (Monthly Recurring Revenue)
- Usuarios activos
- Churn rate
- Actividad reciente
- Distribución de tenants por plan

## 📋 Funcionalidades Disponibles para Owner

### Módulos en el Menú

Cuando accedes como Owner, verás en el sidebar:

```
📊 Dashboard
📋 Principal
   🔗 Relaciones
   ⚙️ Configuración
👑 Owner
   👑 Dashboard Owner
```

### APIs Disponibles

#### Crear Tenant
```bash
POST http://localhost/SaSS_Workee/api/v1/tenants
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Nueva Empresa",
  "slug": "nueva-empresa",
  "org_type": "empresa",
  "plan": "basic",
  "currency": "CHF",
  "timezone": "Europe/Zurich",
  "locale": "es"
}
```

#### Listar Todos los Tenants
```bash
GET http://localhost/SaSS_Workee/api/v1/tenants
Authorization: Bearer {token}
```

**Nota:** Actualmente la API de tenants requiere autenticación, pero en producción debería tener validación especial para Owner.

## ⚠️ Notas Importantes

### Sistema de Autenticación Actual

El sistema actual requiere que todos los usuarios (incluido Owner) tengan un `tenant_id` asociado. Para el Owner:

1. Se crea un **tenant especial** llamado "Workee System" (slug: `workee-system`)
2. El usuario Owner se asocia a este tenant con rol `owner_admin`
3. El Owner puede gestionar **todos los tenants** del sistema, no solo el suyo

### Limitaciones Actuales

- ⚠️ La API de tenants aún no valida completamente el rol `owner_admin`
- ⚠️ Algunas funcionalidades de Owner aún no están implementadas (billing, métricas avanzadas)
- ⚠️ El Owner debería poder ver todos los tenants sin restricción de tenant_id

### Mejoras Futuras

En producción, el Owner debería:
- Tener un sistema de autenticación especial (sin requerir tenant_id en algunas operaciones)
- Poder cambiar entre tenants para gestionarlos
- Tener un panel de administración completo con todas las métricas SaaS

## 🔍 Verificar Usuario Owner

### Desde Base de Datos

```sql
-- Ver usuario owner
SELECT u.id, u.email, u.first_name, u.last_name, tu.role, tu.tenant_id, t.name as tenant_name
FROM users u
INNER JOIN tenant_users tu ON u.id = tu.user_id
INNER JOIN tenants t ON tu.tenant_id = t.id
WHERE tu.role = 'owner_admin';
```

### Desde la Aplicación

1. Haz login con `owner@workee.app` / `password`
2. Verifica que en el sidebar aparece la sección "👑 Owner"
3. Verifica que puedes acceder a `/owner/dashboard`

## 🐛 Troubleshooting

### Problema: No puedo hacer login como Owner

**Verificar:**
1. ¿Existe el usuario owner?
   ```sql
   SELECT * FROM users WHERE email = 'owner@workee.app';
   ```

2. ¿Tiene el rol owner_admin?
   ```sql
   SELECT * FROM tenant_users WHERE user_id = (SELECT id FROM users WHERE email = 'owner@workee.app');
   ```

3. Si no existe, ejecuta:
   ```bash
   php scripts/dev/create_owner_user.php
   ```

### Problema: No veo la sección Owner en el menú

**Verificar:**
1. ¿El rol en localStorage es `owner_admin`?
   - Abre consola del navegador (F12)
   - Ejecuta: `localStorage.getItem('user_role')`
   - Debe ser: `owner_admin`

2. ¿El JWT contiene el rol correcto?
   - Verifica que después del login, el token contiene `role: "owner_admin"`

### Problema: No puedo crear tenants

**Verificar:**
1. La API de tenants requiere autenticación
2. El token debe tener el rol `owner_admin`
3. En desarrollo, la validación puede estar relajada

## 📝 Próximos Pasos

Para completar la funcionalidad de Owner:
1. ✅ Usuario Owner creado
2. ✅ Dashboard Owner básico
3. ⏳ Validación completa de permisos en APIs
4. ⏳ Panel de gestión de tenants completo
5. ⏳ Métricas y reportes SaaS
6. ⏳ Gestión de planes y precios
7. ⏳ Facturación y billing

