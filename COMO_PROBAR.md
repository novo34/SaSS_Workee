# 🧪 Cómo Probar el Sistema Workee

## ✅ Datos de Prueba Creados

Se han creado automáticamente:
- **Empresa Test** (ID: 18)
- **Temporera Test** (ID: 19)
- **Workee System** (ID: 22) - Tenant para Owner
- **Usuarios de prueba** con contraseña: `password`

**Para crear todos los usuarios:**
```bash
php scripts/dev/create_all_test_users.php
```

**Ver resumen completo:** `RESUMEN_CREDENCIALES.md`

## 🌐 Acceso a las Páginas

### 1. Página Principal
**URL:** `http://localhost/SaSS_Workee/`

Deberías ver la página de inicio con:
- Hero section con botones
- Enlaces a funcionalidades
- Accesos rápidos

### 2. Login
**URL:** `http://localhost/SaSS_Workee/login`

**Credenciales de prueba:**

**Empresa:**
- Email: `admin@empresa.com`
- Password: `password`

**Temporera:**
- Email: `admin@temporera.com`
- Password: `password`

**Owner (Administrador del Sistema SaaS):**
- Email: `owner@workee.app`
- Password: `password`
- **Nota:** Para crear el usuario Owner, ejecuta: `php scripts/dev/create_owner_user.php`

**Otros Usuarios de Prueba:**
- Planificador: `planificador@empresa.com` / `password`
- Supervisor: `supervisor@empresa.com` / `password`
- Empleado: `empleado@empresa.com` / `password`
- Coordinador: `coordinador@temporera.com` / `password`
- Trabajador: `trabajador@temporera.com` / `password`

**Ver documentación completa:** `ACCESO_USUARIOS.md`

### 3. Onboarding (Wizard)
**URL:** `http://localhost/SaSS_Workee/onboarding`

Flujo completo de creación de tenant:
1. Seleccionar tipo (Empresa/Temporera)
2. Información básica
3. Seleccionar plan
4. Resumen y crear

### 4. Dashboard Owner
**URL:** `http://localhost/SaSS_Workee/owner/dashboard`

Panel de administración (requiere login como owner_admin)

### 5. Gestión de Relaciones
**URL:** `http://localhost/SaSS_Workee/agency-company-links`

Lista y gestión de vínculos Empresa ↔ Temporera

### 6. Configuración
**URL:** `http://localhost/SaSS_Workee/settings`

Panel de configuración del tenant

## 🔌 Probar APIs

### Opción 1: Usar el navegador (GET requests)
Abre la consola del navegador (F12) y ejecuta:

```javascript
// Probar login
fetch('/api/v1/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        email: 'admin@empresa.com',
        password: 'password'
    })
})
.then(r => r.json())
.then(data => {
    console.log('Login:', data);
    localStorage.setItem('access_token', data.data.access_token);
    localStorage.setItem('tenant_id', data.data.tenant_id);
});
```

### Opción 2: Usar Postman o Thunder Client

**Base URL:** `http://localhost/SaSS_Workee/api/v1`

**Ejemplo de Login:**
```
POST http://localhost/SaSS_Workee/api/v1/auth/login
Content-Type: application/json

{
  "email": "admin@empresa.com",
  "password": "password"
}
```

**Ejemplo de Listar Usuarios (después de login):**
```
GET http://localhost/SaSS_Workee/api/v1/users
Authorization: Bearer {access_token}
X-Tenant-ID: 15
```

## 🐛 Troubleshooting

### Si ves "Index of /SaSS_Workee"
- ✅ **Normal**: Es el listado de directorios de Apache
- Haz clic en `index.php` o accede directamente a `/login`

### Si ves error 404
1. Verifica que Apache está ejecutándose
2. Verifica que `mod_rewrite` está habilitado
3. Verifica que `.htaccess` existe en la raíz

### Si el login no funciona
1. Verifica que los datos de prueba existen:
   ```bash
   php scripts/dev/create_test_data.php
   ```
2. Verifica la base de datos:
   ```bash
   php verify_db.php
   ```

### Si las APIs dan error
1. Abre la consola del navegador (F12) → Network
2. Revisa los errores en la pestaña Console
3. Verifica que el endpoint existe en `backend/api/`

## 📝 Checklist de Prueba

- [ ] Acceder a página principal: `http://localhost/SaSS_Workee/`
- [ ] Ver página de login: `http://localhost/SaSS_Workee/login`
- [ ] Probar wizard de onboarding: `http://localhost/SaSS_Workee/onboarding`
- [ ] Hacer login con `admin@empresa.com` / `password`
- [ ] Verificar que redirige después del login
- [ ] Probar API de login desde consola del navegador
- [ ] Probar listar usuarios (después de login)
- [ ] Probar crear vínculo Empresa ↔ Temporera

## 🎯 Próximos Pasos

Una vez que puedas acceder a las páginas:
1. Probar el flujo completo de onboarding
2. Crear más usuarios desde la UI
3. Probar la gestión de relaciones
4. Verificar que todo funciona en móvil (responsive)

