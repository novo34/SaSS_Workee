# Guía de Pruebas - Workee SaaS

## 🚀 Cómo Probar el Sistema

### 1. Acceso a la Aplicación

**URL Base:** `http://localhost/SaSS_Workee/`

#### Páginas Disponibles:

- **Inicio:** `http://localhost/SaSS_Workee/`
- **Login:** `http://localhost/SaSS_Workee/login`
- **Onboarding:** `http://localhost/SaSS_Workee/onboarding`
- **Dashboard Owner:** `http://localhost/SaSS_Workee/owner/dashboard`
- **Relaciones:** `http://localhost/SaSS_Workee/agency-company-links`
- **Configuración:** `http://localhost/SaSS_Workee/settings`

### 2. Probar APIs

#### Endpoints Disponibles:

**Autenticación:**
- `POST /api/auth/login` - Login
- `POST /api/auth/refresh` - Refresh token
- `POST /api/auth/logout` - Logout

**Tenants (Owner):**
- `POST /api/tenants` - Crear tenant
- `GET /api/tenants` - Listar tenants

**Usuarios:**
- `POST /api/users/invite` - Invitar usuario
- `GET /api/users` - Listar usuarios
- `PUT /api/users/{id}` - Actualizar usuario
- `DELETE /api/users/{id}` - Desactivar usuario

**Relaciones:**
- `POST /api/agency-company-links` - Crear vínculo
- `GET /api/agency-company-links` - Listar vínculos
- `PUT /api/agency-company-links/{id}` - Actualizar vínculo
- `DELETE /api/agency-company-links/{id}` - Eliminar vínculo

### 3. Flujo de Prueba Recomendado

#### Paso 1: Crear un Tenant (Onboarding)

1. Ir a: `http://localhost/SaSS_Workee/onboarding`
2. Completar el wizard:
   - Paso 1: Seleccionar tipo (Empresa o Temporera)
   - Paso 2: Ingresar nombre, timezone, idioma
   - Paso 3: Seleccionar plan
   - Paso 4: Revisar y crear
3. Verificar que se crea correctamente

#### Paso 2: Crear Usuario y Login

**Opción A: Desde la API (para testing rápido)**

```bash
# Crear usuario directamente en BD
mysql -u root -p workee
```

```sql
INSERT INTO users (email, password_hash, first_name, last_name) 
VALUES ('admin@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Test');
-- Password: password

# Obtener ID del tenant creado
SELECT id FROM tenants LIMIT 1;

# Asignar usuario al tenant (reemplazar TENANT_ID y USER_ID)
INSERT INTO tenant_users (tenant_id, user_id, role) 
VALUES (TENANT_ID, USER_ID, 'empresa_admin');
```

**Opción B: Usar la API de invitación (requiere autenticación)**

1. Primero crear usuario manualmente en BD (como arriba)
2. Luego usar API para invitar más usuarios

#### Paso 3: Probar Login

1. Ir a: `http://localhost/SaSS_Workee/login`
2. Ingresar credenciales:
   - Email: `admin@test.com`
   - Password: `password`
3. Verificar que redirige al dashboard

#### Paso 4: Probar APIs con Postman/Thunder Client

**Headers requeridos:**
```
Authorization: Bearer {access_token}
Content-Type: application/json
X-Tenant-ID: {tenant_id} (si no viene en JWT)
```

**Ejemplo de Login:**
```json
POST http://localhost/SaSS_Workee/api/auth/login
Content-Type: application/json

{
  "email": "admin@test.com",
  "password": "password"
}
```

**Ejemplo de Crear Vínculo:**
```json
POST http://localhost/SaSS_Workee/api/agency-company-links
Authorization: Bearer {token}
Content-Type: application/json

{
  "tenant_company_id": 1,
  "tenant_agency_id": 2,
  "status": "active"
}
```

### 4. Scripts de Prueba Rápida

#### Crear Datos de Prueba

Crear archivo `scripts/dev/create_test_data.php`:

```php
<?php
require_once __DIR__ . '/../../backend/helpers/db_helper.php';

$conn = DBHelper::getConnection();

// Crear tenant empresa
$stmt = $conn->prepare("INSERT INTO tenants (name, slug, org_type, plan) VALUES (?, ?, 'empresa', 'basic')");
$name = 'Empresa Test';
$slug = 'empresa-test';
$stmt->bind_param('ss', $name, $slug);
$stmt->execute();
$companyId = $conn->insert_id;
$stmt->close();

// Crear tenant temporera
$stmt = $conn->prepare("INSERT INTO tenants (name, slug, org_type, plan) VALUES (?, ?, 'temporera', 'basic')");
$name = 'Temporera Test';
$slug = 'temporera-test';
$stmt->bind_param('ss', $name, $slug);
$stmt->execute();
$agencyId = $conn->insert_id;
$stmt->close();

// Crear usuario
$passwordHash = password_hash('password', PASSWORD_BCRYPT);
$stmt = $conn->prepare("INSERT INTO users (email, password_hash, first_name, last_name) VALUES (?, ?, ?, ?)");
$email = 'admin@test.com';
$firstName = 'Admin';
$lastName = 'Test';
$stmt->bind_param('ssss', $email, $passwordHash, $firstName, $lastName);
$stmt->execute();
$userId = $conn->insert_id;
$stmt->close();

// Asignar usuario a empresa
$stmt = $conn->prepare("INSERT INTO tenant_users (tenant_id, user_id, role) VALUES (?, ?, 'empresa_admin')");
$stmt->bind_param('ii', $companyId, $userId);
$stmt->execute();
$stmt->close();

echo "✅ Datos de prueba creados:\n";
echo "  - Empresa ID: {$companyId}\n";
echo "  - Temporera ID: {$agencyId}\n";
echo "  - Usuario ID: {$userId}\n";
echo "  - Email: admin@test.com\n";
echo "  - Password: password\n";
```

Ejecutar: `php scripts/dev/create_test_data.php`

### 5. Verificar Base de Datos

```bash
# Conectar a MySQL
mysql -u root -p workee

# Verificar tenants
SELECT * FROM tenants;

# Verificar usuarios
SELECT u.email, tu.role, t.name as tenant_name 
FROM users u 
INNER JOIN tenant_users tu ON u.id = tu.user_id 
INNER JOIN tenants t ON tu.tenant_id = t.id;
```

### 6. Troubleshooting

**Problema: Página en blanco**
- Verificar que Apache está ejecutándose
- Verificar que PHP está habilitado
- Revisar logs de Apache: `C:\xampp\apache\logs\error.log`

**Problema: Error 404 en APIs**
- Verificar que `.htaccess` está en la raíz
- Verificar que `mod_rewrite` está habilitado en Apache
- Verificar rutas en `index.php`

**Problema: Error de conexión a BD**
- Verificar que MySQL está ejecutándose
- Verificar credenciales en `.env`
- Ejecutar `php verify_db.php`

**Problema: CORS en APIs**
- Agregar headers CORS si es necesario (para desarrollo)

### 7. Próximos Pasos

Una vez que puedas acceder a las páginas:
1. Probar el wizard de onboarding
2. Crear usuarios de prueba
3. Probar login
4. Probar creación de vínculos
5. Verificar que todo funciona correctamente

