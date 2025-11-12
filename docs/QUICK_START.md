# 🚀 Inicio Rápido - Workee SaaS

## Paso 1: Verificar que todo está funcionando

### ✅ Verificar Base de Datos
```bash
php verify_db.php
```

Deberías ver: "Total de tablas: 39"

### ✅ Crear Datos de Prueba
```bash
php scripts/dev/create_test_data.php
```

Deberías ver las credenciales creadas.

## Paso 2: Acceder a la Aplicación

### 🌐 Abre en tu navegador:

**Página Principal:**
```
http://localhost/SaSS_Workee/
```

**Login:**
```
http://localhost/SaSS_Workee/login
```

**Credenciales:**
- **Empresa:** `admin@empresa.com` / `password`
- **Temporera:** `admin@temporera.com` / `password`
- **Owner:** `owner@workee.app` / `password` (crear con: `php scripts/dev/create_owner_user.php`)

## Paso 3: Probar Funcionalidades

### 1. Login
1. Ve a `http://localhost/SaSS_Workee/login`
2. Ingresa las credenciales
3. Deberías ser redirigido al dashboard

### 2. Onboarding
1. Ve a `http://localhost/SaSS_Workee/onboarding`
2. Completa el wizard de 4 pasos
3. Crea un nuevo tenant

### 3. Gestión de Relaciones
1. Haz login
2. Ve a `http://localhost/SaSS_Workee/agency-company-links`
3. Crea un vínculo entre empresa y temporera

### 4. Configuración
1. Ve a `http://localhost/SaSS_Workee/settings`
2. Cambia configuraciones
3. Guarda cambios

## 🔍 Si algo no funciona

### Ver logs de Apache
```
C:\xampp\apache\logs\error.log
```

### Verificar que Apache está ejecutándose
- Abre XAMPP Control Panel
- Verifica que Apache está "Running"

### Verificar que PHP está funcionando
Crea un archivo `test.php` en la raíz:
```php
<?php phpinfo(); ?>
```
Accede a: `http://localhost/SaSS_Workee/test.php`

### Verificar rutas
Si ves "Index of /SaSS_Workee", haz clic en `index.php` o accede directamente a `/login`

## 📱 Probar en Móvil

1. Encuentra tu IP local: `ipconfig` (Windows)
2. Accede desde tu móvil: `http://TU_IP/SaSS_Workee/`
3. Verifica que todo se ve bien en móvil

## ✅ Checklist Rápido

- [ ] Base de datos creada (39 tablas)
- [ ] Datos de prueba creados
- [ ] Puedo acceder a `http://localhost/SaSS_Workee/`
- [ ] Puedo hacer login
- [ ] Puedo ver el dashboard
- [ ] Puedo crear un tenant desde onboarding
- [ ] Puedo gestionar relaciones

