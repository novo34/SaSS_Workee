# 📋 Resumen de Verificación Sprint 2

## ✅ Tareas Completadas Automáticamente

### 1. **Actualización de Páginas HTML** ✅
- ✅ `frontend/pages/staffing-requests.html` actualizada para usar nuevo layout (sidebar/header)
- ✅ `frontend/pages/staffing-proposals.html` actualizada para usar nuevo layout (sidebar/header)
- ✅ Eliminado navbar antiguo
- ✅ Agregado `layout.css` y `layout.js`
- ✅ Estructura consistente con otras páginas del sistema

### 2. **Verificación de Archivos** ✅
- ✅ Páginas HTML existen: `staffing-requests.html`, `staffing-proposals.html`
- ✅ JavaScript existe: `staffing-requests.js`, `staffing-proposals.js`
- ✅ Endpoints API existen: `staffing_requests.php`, `staffing_proposals.php`
- ✅ Rutas configuradas en `index.php`
- ✅ JavaScript usa `BASE_URL` correctamente

### 3. **Script de Verificación** ✅
- ✅ Creado `scripts/dev/verify_sprint2_data.php`
- ✅ Verifica tablas de BD
- ✅ Verifica Empresa y Temporera
- ✅ Verifica vínculo entre ellas
- ✅ Verifica trabajadores activos
- ✅ Verifica MatchingService

---

## 📝 Pasos Manuales que DEBES Realizar

### Paso 1: Verificar Datos de Prueba

Ejecuta el script de verificación:

```bash
php scripts/dev/verify_sprint2_data.php
```

**Si falta algo:**
- Ejecuta: `php scripts/dev/create_test_data.php` para crear datos básicos
- Crea vínculo desde: `http://localhost/SaSS_Workee/agency-company-links`
- Crea trabajadores si es necesario (ver instrucciones abajo)

### Paso 2: Probar Login y Acceso

#### Desde Empresa:
1. **URL:** `http://localhost/SaSS_Workee/login`
2. **Credenciales:**
   - Email: `admin@empresa.com`
   - Password: `password`
3. **Verificar:**
   - ✅ Login exitoso
   - ✅ Redirección a `/dashboard`
   - ✅ Sidebar visible con navegación
   - ✅ Acceso a `/staffing-requests`

#### Desde Temporera:
1. **URL:** `http://localhost/SaSS_Workee/login`
2. **Credenciales:**
   - Email: `admin@temporera.com`
   - Password: `password`
3. **Verificar:**
   - ✅ Login exitoso
   - ✅ Redirección a `/dashboard`
   - ✅ Acceso a `/staffing-proposals`

### Paso 3: Crear Vínculo (si no existe)

1. Accede como Empresa: `http://localhost/SaSS_Workee/agency-company-links`
2. Haz clic en **"Crear Vínculo"**
3. Selecciona la Temporera
4. Confirma

**Verificar en BD:**
```sql
SELECT * FROM agency_company_links 
WHERE tenant_company_id = {empresa_id} 
AND tenant_agency_id = {temporera_id} 
AND status = 'active';
```

### Paso 4: Crear Trabajadores (si no existen)

**Opción A: Desde la UI** (si existe)
- Accede como Temporera
- Ve a la sección de Trabajadores
- Crea trabajadores con:
  - Nombre y apellido
  - Skills (ej: "Construcción, Albañilería")
  - Disponibilidad
  - Coordenadas (opcional, para matching por cercanía)

**Opción B: Directamente en BD:**
```sql
-- Trabajador cerca de Zúrich (para matching por cercanía)
INSERT INTO agency_workers (tenant_id, first_name, last_name, latitude, longitude, skills_json, availability_json, is_active)
VALUES (
    {temporera_id},
    'Juan',
    'Pérez',
    47.3770,
    8.5418,
    '["Construcción", "Albañilería"]',
    '{"monday": {"available": true, "hours": {"from": "08:00", "to": "18:00"}}, "tuesday": {"available": true, "hours": {"from": "08:00", "to": "18:00"}}, "wednesday": {"available": true, "hours": {"from": "08:00", "to": "18:00"}}, "thursday": {"available": true, "hours": {"from": "08:00", "to": "18:00"}}, "friday": {"available": true, "hours": {"from": "08:00", "to": "18:00"}}}',
    1
);
```

### Paso 5: Probar Flujo Completo

#### 5.1. Crear Solicitud (Empresa)

1. Accede como Empresa: `http://localhost/SaSS_Workee/staffing-requests`
2. Haz clic en **"Nueva Solicitud"**
3. Completa el wizard de 3 pasos:
   - **Paso 1:** Fechas, ubicación, coordenadas
   - **Paso 2:** Cantidad, skills, idiomas
   - **Paso 3:** Proyecto (opcional), seleccionar temporeras
4. Haz clic en **"Crear Solicitud"**

**Verificar:**
- ✅ Solicitud aparece en la lista
- ✅ Status es `open`
- ✅ Si seleccionaste temporeras, se crearon registros en `staffing_request_targets`

#### 5.2. Crear Propuesta con Auto-Matching (Temporera)

1. Accede como Temporera: `http://localhost/SaSS_Workee/staffing-proposals`
2. Haz clic en **"Nueva Propuesta"**
3. Selecciona la solicitud recibida
4. Marca **"Usar Auto-Matching"**
5. Configura distancia máxima (ej: 50 km)
6. Haz clic en **"Crear Propuesta"**

**Verificar:**
- ✅ Propuesta creada con candidatos
- ✅ Candidatos ordenados por score
- ✅ Scores visibles (0-100%)
- ✅ Detalles de matching (disponibilidad, skills, distancia)

#### 5.3. Aceptar Propuesta (Empresa)

1. Accede como Empresa
2. Ve a la propuesta recibida
3. Haz clic en **"Aceptar"**

**Verificar:**
- ✅ Status de propuesta cambia a `accepted`
- ✅ Status de solicitud se actualiza (`filled` o `partially_filled`)

---

## 🔍 Verificaciones Adicionales

### Verificar en Base de Datos

```sql
-- Ver solicitudes
SELECT sr.*, t.name as company_name
FROM staffing_requests sr
INNER JOIN tenants t ON sr.tenant_company_id = t.id
ORDER BY sr.created_at DESC;

-- Ver propuestas
SELECT sp.*, sr.date_from, sr.date_to, t.name as agency_name
FROM staffing_proposals sp
INNER JOIN staffing_requests sr ON sp.request_id = sr.id
INNER JOIN tenants t ON sp.tenant_agency_id = t.id
ORDER BY sp.created_at DESC;

-- Ver candidatos en propuesta
SELECT id, proposal_json
FROM staffing_proposals
WHERE id = {proposal_id};
-- El proposal_json contiene el array de candidatos con scores
```

### Verificar APIs

Usa Postman o curl para probar los endpoints:

```bash
# Listar solicitudes
GET http://localhost/SaSS_Workee/api/v1/staffing-requests
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}

# Crear solicitud
POST http://localhost/SaSS_Workee/api/v1/staffing-requests
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}
Content-Type: application/json

{
  "date_from": "2024-12-20 08:00:00",
  "date_to": "2024-12-20 18:00:00",
  "location": "Zúrich, Suiza",
  "latitude": 47.3769,
  "longitude": 8.5417,
  "qty_needed": 3,
  "requirements": {
    "skills": ["Construcción", "Albañilería"],
    "languages": ["Español", "Alemán"]
  },
  "agency_ids": [16]
}
```

---

## ⚠️ Problemas Comunes

### Problema: No aparecen solicitudes en Temporera

**Solución:**
1. Verifica que existe vínculo activo
2. Verifica que la solicitud se envió a la temporera
3. Revisa `staffing_request_targets` en BD

### Problema: Auto-matching no encuentra candidatos

**Solución:**
1. Verifica que hay trabajadores activos
2. Verifica que tienen disponibilidad en las fechas
3. Verifica que tienen los skills requeridos
4. Verifica que no hay conflictos de horario

### Problema: Error 403 al crear propuesta

**Solución:**
- Solo las temporeras pueden crear propuestas
- Verifica que el tenant es de tipo `temporera`

---

## 📊 Checklist Final

- [ ] Script de verificación ejecutado sin errores
- [ ] Login funciona (Empresa y Temporera)
- [ ] Vínculo creado y activo
- [ ] Trabajadores creados (al menos 1)
- [ ] Solicitud creada desde Empresa
- [ ] Propuesta creada con auto-matching desde Temporera
- [ ] Candidatos aparecen con scores
- [ ] Propuesta aceptada desde Empresa
- [ ] Status de solicitud se actualiza correctamente

---

## 🎯 Próximos Pasos

Una vez completado el Sprint 2:
1. ✅ Verificar que el flujo completo funciona
2. ✅ Probar edge cases (sin trabajadores, sin vínculos, etc.)
3. ✅ Verificar que el matching es preciso
4. ✅ Continuar con Sprint 3 (Calendario y grupos)

---

**Última actualización:** 2024-01-XX

