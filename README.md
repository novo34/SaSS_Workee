# Workee SaaS - Sistema Multitenant

SaaS multitenant que conecta Empresas (planifican trabajo) con Temporeras (suministran trabajadores).

## 🚀 Inicio Rápido

### Requisitos

- PHP 8.x
- MySQL 5.7+
- Composer (opcional, para dependencias futuras)
- Servidor web (Apache/Nginx) o PHP built-in server

### Instalación

1. **Clonar repositorio**
```bash
git clone <repo-url>
cd SaSS_Workee
```

2. **Configurar base de datos**
```bash
# Crear base de datos
mysql -u root -p -e "CREATE DATABASE workee CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Importar esquema
mysql -u root -p workee < backend/db/schema_v2.sql
```

3. **Configurar entorno**
```bash
# Copiar archivo de ejemplo
cp .env.example .env

# Editar .env con tus credenciales
nano .env
```

4. **Configurar servidor web**

**Apache**: Configurar VirtualHost apuntando a `public/` o raíz del proyecto.

**PHP Built-in Server** (desarrollo):
```bash
php -S localhost:8000 -t .
```

## 📁 Estructura del Proyecto

```
SaSS_Workee/
├── backend/
│   ├── core/           # Middlewares, servicios core
│   │   ├── TenantMiddleware.php
│   │   ├── RBACMiddleware.php
│   │   ├── Auth.php
│   │   └── Config.php
│   ├── api/            # Endpoints API
│   ├── models/         # Modelos de datos
│   ├── helpers/        # Helpers y utilidades
│   │   ├── db_helper.php
│   │   └── i18n.php
│   ├── jobs/           # Cron jobs
│   ├── db/             # Migraciones y schemas
│   │   └── schema_v2.sql
│   └── lang/           # Traducciones
│       ├── es/
│       ├── de/
│       ├── fr/
│       └── it/
├── frontend/
│   ├── pages/          # Páginas HTML
│   │   └── owner/
│   ├── js/             # JavaScript modular
│   └── css/            # Estilos
├── tests/              # Tests
└── .env.example        # Ejemplo de configuración
```

## 🔐 Seguridad y Multitenancy

### Middlewares Obligatorios

Todas las rutas de API DEBEN usar:

1. **TenantMiddleware**: Extrae y valida `tenant_id`
2. **RBACMiddleware**: Verifica permisos por rol

Ejemplo de uso:
```php
<?php
require_once __DIR__ . '/core/TenantMiddleware.php';
require_once __DIR__ . '/core/RBACMiddleware.php';

// Procesar tenant
$context = TenantMiddleware::process();
$tenantId = $context['tenant_id'];
$userId = $context['user_id'];

// Verificar permiso
RBACMiddleware::requirePermission('shifts.create');
```

### Reglas Críticas

- ✅ TODAS las tablas de negocio tienen `tenant_id`
- ✅ TODAS las queries incluyen `tenant_id` en WHERE
- ✅ NUNCA hacer queries cross-tenant sin autorización
- ✅ Usar `DBHelper` que fuerza `tenant_id` en todas las operaciones

## 🌍 Internacionalización (i18n)

**NUNCA hardcodear texto visible al usuario**

Usar función `t()`:
```php
echo t('common.save');
echo t('users.welcome', ['name' => 'Juan']);
```

En JavaScript:
```javascript
const text = t('common.save');
```

## 📱 Mobile-First

Todas las interfaces están diseñadas mobile-first:
- Botones mínimo 44x44px
- Espaciado adecuado entre elementos
- Navegación hamburguesa en móvil
- Bootstrap 5 responsive

## 🧪 Testing

Ejecutar tests de aislamiento:
```bash
php tests/tenancy_isolation.test.php
```

## 📚 Documentación

Toda la documentación se encuentra en la carpeta `docs/`:

- `docs/Workee_kickoff_v_1.md` - Especificación completa del proyecto
- `docs/TODO_ROADMAP_COMPLETO.md` - Roadmap y tareas pendientes
- `docs/QUICK_START.md` - Guía de inicio rápido
- `docs/COMO_PROBAR.md` - Guía de pruebas general
- `docs/COMO_PROBAR_SPRINT2.md` - Guía de pruebas Sprint 2
- `docs/ACCESO_OWNER.md` - Acceso y credenciales Owner
- `docs/ACCESO_USUARIOS.md` - Acceso y credenciales de usuarios
- Y más documentación en `docs/`

- `.cursorrules` - Reglas de desarrollo (en raíz)

## 🔄 Roadmap

### Fase 0 - Fundaciones (Sprints 0-2) ✅ En progreso
- [x] Esquema DB v2
- [x] TenantMiddleware
- [x] RBACMiddleware
- [x] Autenticación JWT
- [x] Helper SQL
- [x] Sistema de configuración
- [x] Sistema i18n base
- [ ] Layout base Owner
- [ ] Tests de aislamiento

### Fase 1 - Núcleo Operativo (Sprints 3-6)
- Solicitudes y propuestas
- Calendario drag & drop
- Grupos y asignaciones
- Fichaje y aprobaciones
- Disputas

### Fase 2 - Monetización (Sprints 7-10)
- Billing y facturación
- APIs públicas
- Webhooks
- Notificaciones
- Reportes avanzados

## 📝 Licencia

Proprietary - Todos los derechos reservados

