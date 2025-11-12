# 🧪 Cómo Probar Sprint 2 - Solicitudes y Propuestas

## 📋 Resumen del Sprint 2

Este sprint implementa el flujo completo de **Solicitudes de Personal** (Empresa → Temporera) y **Propuestas de Candidatos** (Temporera → Empresa), incluyendo:

- ✅ CRUD completo de solicitudes de personal
- ✅ Algoritmo de auto-matching inteligente
- ✅ Sistema de propuestas con aceptación/rechazo
- ✅ UIs completas para Empresa y Temporera

---

## 🎯 Flujo Completo de Prueba

### Paso 1: Preparar Datos de Prueba

Asegúrate de tener:
- ✅ Una **Empresa** creada
- ✅ Una **Temporera** creada
- ✅ Un **vínculo activo** entre Empresa y Temporera
- ✅ **Trabajadores** en la Temporera (para matching)

Si no los tienes, ejecuta:

```bash
php scripts/dev/create_test_data.php
```

O crea manualmente:
1. Empresa y Temporera desde `/onboarding`
2. Vínculo desde `/agency-company-links`
3. Trabajadores desde la UI de Temporera (si existe) o directamente en BD

---

## 🏢 Prueba desde Empresa (Crear Solicitudes)

### 1. Acceder a la UI de Solicitudes

**URL:** `http://localhost/SaSS_Workee/staffing-requests`

**Credenciales:**
- Email: `admin@empresa.com`
- Password: `password`

### 2. Crear Nueva Solicitud

1. Haz clic en **"Nueva Solicitud"**
2. Completa el wizard de 3 pasos:

#### Paso 1: Fechas y Ubicación
- **Fecha y Hora Inicio:** Selecciona una fecha futura
- **Fecha y Hora Fin:** Selecciona una fecha posterior
- **Ubicación:** Ingresa una dirección (ej: "Zúrich, Suiza")
- **Latitud/Longitud:** Opcional (para matching por cercanía)

#### Paso 2: Cantidad y Requisitos
- **Cantidad Necesaria:** Ingresa un número (ej: 3)
- **Skills Requeridos:** Ej: "Construcción, Albañilería"
- **Idiomas:** Ej: "Español, Alemán"
- **Otros Requisitos:** Opcional

#### Paso 3: Proyecto y Destinatarios
- **Proyecto:** Opcional
- **Enviar a Temporeras:** Selecciona las temporeras vinculadas

3. Haz clic en **"Crear Solicitud"**

**Resultado esperado:**
- ✅ Solicitud creada con status `open`
- ✅ Aparece en la lista de solicitudes
- ✅ Si seleccionaste temporeras, se crean registros en `staffing_request_targets`

### 3. Ver Lista de Solicitudes

La lista muestra:
- ID de solicitud
- Status (badge de color)
- Fechas y ubicación
- Cantidad necesaria
- Proyecto (si aplica)
- Botón "Enviar" para enviar a más temporeras

### 4. Enviar Solicitud a Temporeras

1. Haz clic en **"Enviar"** en una solicitud
2. Ingresa los IDs de las temporeras separados por comas (ej: `16`)
3. Confirma

**Resultado esperado:**
- ✅ Se crean registros en `staffing_request_targets` con status `sent`
- ✅ Las temporeras pueden ver la solicitud

### 5. Filtrar Solicitudes

Usa los filtros en la parte superior:
- **Estado:** Abiertas, Parcialmente cubiertas, Cubiertas, Canceladas
- **Buscar:** Búsqueda por texto

### 6. Actualizar Solicitud

**Desde la API:**
```bash
PUT http://localhost/SaSS_Workee/api/v1/staffing-requests/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "qty_needed": 5,
  "location": "Nueva ubicación"
}
```

**Resultado esperado:**
- ✅ Solo se pueden actualizar solicitudes con status `open` o `partially_filled`
- ✅ Solicitudes `filled` o `canceled` no se pueden modificar

### 7. Cancelar Solicitud

**Desde la API:**
```bash
DELETE http://localhost/SaSS_Workee/api/v1/staffing-requests/{id}
Authorization: Bearer {token}
```

**Resultado esperado:**
- ✅ Status cambia a `canceled` (soft delete)

---

## 🏭 Prueba desde Temporera (Crear Propuestas)

### 1. Acceder a la UI de Propuestas

**URL:** `http://localhost/SaSS_Workee/staffing-proposals`

**Credenciales:**
- Email: `admin@temporera.com`
- Password: `password`

### 2. Ver Solicitudes Recibidas

Las solicitudes enviadas a esta temporera aparecen con status `sent` en `staffing_request_targets`.

### 3. Crear Propuesta con Auto-Matching

1. Haz clic en **"Nueva Propuesta"**
2. Selecciona una **Solicitud** de la lista
3. Marca **"Usar Auto-Matching"** (recomendado)
4. Configura opciones:
   - **Distancia Máxima:** Ej: 50 km
5. Haz clic en **"Crear Propuesta"**

**Resultado esperado:**
- ✅ El sistema ejecuta `MatchingService::findMatches()`
- ✅ Encuentra trabajadores que coinciden con:
  - Disponibilidad en las fechas
  - Cercanía (si hay coordenadas)
  - Skills requeridos
  - Rating histórico
  - Sin conflictos de horario
- ✅ Crea propuesta con candidatos ordenados por score
- ✅ Status de `staffing_request_targets` cambia a `responded`

### 4. Ver Propuesta Creada

La propuesta muestra:
- ID de propuesta
- Solicitud asociada
- Fechas y ubicación
- Lista de candidatos con:
  - Nombre y código
  - Score de matching (0-100%)
  - Distancia (si aplica)
  - Detalles (disponibilidad, skills, rating)

### 5. Crear Propuesta Manual

1. Desmarca **"Usar Auto-Matching"**
2. Selecciona trabajadores manualmente (si la UI lo permite)
3. Crea la propuesta

**Nota:** La UI manual está simplificada. En producción, se cargarían todos los trabajadores de la temporera.

### 6. Filtrar Propuestas

Usa los filtros:
- **Estado:** Pendientes, Aceptadas, Rechazadas, Con cambios
- **Solicitud:** Filtrar por ID de solicitud

---

## 🏢 Prueba desde Empresa (Gestionar Propuestas)

### 1. Ver Propuestas Recibidas

Desde la UI de Propuestas (o desde la solicitud), verás las propuestas con status `pending_company`.

### 2. Aceptar Propuesta

1. Haz clic en **"Aceptar"** en una propuesta
2. Confirma

**Resultado esperado:**
- ✅ Status de propuesta cambia a `accepted`
- ✅ Status de solicitud se actualiza:
  - Si se cubre la cantidad completa → `filled`
  - Si es parcial → `partially_filled`

### 3. Rechazar Propuesta

1. Haz clic en **"Rechazar"**
2. Confirma

**Resultado esperado:**
- ✅ Status de propuesta cambia a `rejected`

### 4. Solicitar Cambios

1. Haz clic en **"Cambios"**
2. Confirma

**Resultado esperado:**
- ✅ Status de propuesta cambia a `changed`
- ✅ La temporera puede modificar la propuesta

---

## 🔍 Probar APIs Directamente

### API de Solicitudes

#### Listar Solicitudes
```bash
GET http://localhost/SaSS_Workee/api/v1/staffing-requests?page=1&page_size=20&status=open
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}
```

#### Crear Solicitud
```bash
POST http://localhost/SaSS_Workee/api/v1/staffing-requests
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}
X-User-ID: {user_id}
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

#### Enviar a Temporeras
```bash
POST http://localhost/SaSS_Workee/api/v1/staffing-requests/{id}/send
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}
Content-Type: application/json

{
  "agency_ids": [16, 17]
}
```

### API de Propuestas

#### Listar Propuestas
```bash
GET http://localhost/SaSS_Workee/api/v1/staffing-proposals?page=1&status=pending_company
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}
```

#### Crear Propuesta con Auto-Matching
```bash
POST http://localhost/SaSS_Workee/api/v1/staffing-proposals
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}
X-User-ID: {user_id}
Content-Type: application/json

{
  "request_id": 1,
  "use_auto_matching": true,
  "matching_options": {
    "max_distance_km": 50,
    "weight_availability": 0.3,
    "weight_proximity": 0.2,
    "weight_skills": 0.3,
    "weight_rating": 0.2
  }
}
```

#### Aceptar Propuesta
```bash
PUT http://localhost/SaSS_Workee/api/v1/staffing-proposals/{id}/accept
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}
```

#### Rechazar Propuesta
```bash
PUT http://localhost/SaSS_Workee/api/v1/staffing-proposals/{id}/reject
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}
```

---

## 🧮 Probar Algoritmo de Matching

### 1. Preparar Datos

Crea trabajadores con diferentes características:

```sql
-- Trabajador cerca de la ubicación
INSERT INTO agency_workers (tenant_id, first_name, last_name, latitude, longitude, skills_json, availability_json)
VALUES (16, 'Juan', 'Pérez', 47.3770, 8.5418, '["Construcción", "Albañilería"]', '{"monday": {"available": true, "hours": {"from": "08:00", "to": "18:00"}}}');

-- Trabajador lejos
INSERT INTO agency_workers (tenant_id, first_name, last_name, latitude, longitude, skills_json)
VALUES (16, 'Pedro', 'García', 46.2044, 6.1432, '["Construcción"]');

-- Trabajador sin skills requeridos
INSERT INTO agency_workers (tenant_id, first_name, last_name, latitude, longitude, skills_json)
VALUES (16, 'Luis', 'Martínez', 47.3769, 8.5417, '["Carpintería"]');
```

### 2. Crear Solicitud con Coordenadas

Crea una solicitud con:
- Ubicación: "Zúrich, Suiza"
- Latitude: 47.3769
- Longitude: 8.5417
- Skills requeridos: ["Construcción", "Albañilería"]

### 3. Ejecutar Auto-Matching

Crea una propuesta con auto-matching.

**Resultado esperado:**
- ✅ Juan Pérez debería tener el score más alto (cerca + skills correctos)
- ✅ Pedro García debería tener score medio (skills pero lejos)
- ✅ Luis Martínez debería tener score bajo (sin skills requeridos)

### 4. Verificar Scores

Los scores se calculan con:
- **Disponibilidad:** 0.0 - 1.0 (según availability_json)
- **Cercanía:** 0.0 - 1.0 (según distancia Haversine)
- **Skills:** 0.0 - 1.0 (skills que coinciden / skills requeridos)
- **Rating:** 0.0 - 1.0 (rating histórico con la empresa)
- **Conflictos:** Si hay conflicto, score se reduce a 30%

---

## ✅ Checklist de Prueba Completo

### Desde Empresa
- [ ] Acceder a `/staffing-requests`
- [ ] Crear solicitud con wizard de 3 pasos
- [ ] Ver solicitud en la lista
- [ ] Filtrar solicitudes por estado
- [ ] Enviar solicitud a temporeras
- [ ] Actualizar solicitud (solo si está abierta)
- [ ] Cancelar solicitud
- [ ] Ver propuestas recibidas
- [ ] Aceptar propuesta
- [ ] Rechazar propuesta
- [ ] Solicitar cambios a propuesta

### Desde Temporera
- [ ] Acceder a `/staffing-proposals`
- [ ] Ver solicitudes recibidas
- [ ] Crear propuesta con auto-matching
- [ ] Ver candidatos encontrados con scores
- [ ] Verificar que los candidatos tienen:
  - Disponibilidad correcta
  - Skills requeridos
  - Cercanía adecuada (si hay coordenadas)
- [ ] Crear propuesta manual (si implementado)
- [ ] Filtrar propuestas por estado

### APIs
- [ ] GET `/api/v1/staffing-requests` - Listar con paginación
- [ ] POST `/api/v1/staffing-requests` - Crear
- [ ] GET `/api/v1/staffing-requests/{id}` - Obtener una
- [ ] PUT `/api/v1/staffing-requests/{id}` - Actualizar
- [ ] DELETE `/api/v1/staffing-requests/{id}` - Cancelar
- [ ] POST `/api/v1/staffing-requests/{id}/send` - Enviar
- [ ] GET `/api/v1/staffing-proposals` - Listar
- [ ] POST `/api/v1/staffing-proposals` - Crear con auto-matching
- [ ] PUT `/api/v1/staffing-proposals/{id}/accept` - Aceptar
- [ ] PUT `/api/v1/staffing-proposals/{id}/reject` - Rechazar
- [ ] PUT `/api/v1/staffing-proposals/{id}/change` - Cambios

### Matching
- [ ] Crear trabajadores con diferentes características
- [ ] Crear solicitud con requisitos específicos
- [ ] Ejecutar auto-matching
- [ ] Verificar que los scores son correctos
- [ ] Verificar que trabajadores con conflictos tienen score reducido
- [ ] Verificar que trabajadores sin disponibilidad no aparecen

---

## 🐛 Troubleshooting

### Problema: No aparecen solicitudes en Temporera

**Verificar:**
1. ¿Existe vínculo activo entre Empresa y Temporera?
   ```sql
   SELECT * FROM agency_company_links 
   WHERE tenant_company_id = {empresa_id} 
   AND tenant_agency_id = {temporera_id} 
   AND status = 'active';
   ```

2. ¿Se envió la solicitud a la temporera?
   ```sql
   SELECT * FROM staffing_request_targets 
   WHERE request_id = {request_id} 
   AND tenant_agency_id = {temporera_id};
   ```

### Problema: Auto-matching no encuentra candidatos

**Verificar:**
1. ¿Hay trabajadores activos en la temporera?
   ```sql
   SELECT * FROM agency_workers 
   WHERE tenant_id = {temporera_id} 
   AND is_active = 1;
   ```

2. ¿Los trabajadores tienen disponibilidad en las fechas?
   - Revisar `availability_json`

3. ¿Los trabajadores tienen los skills requeridos?
   - Revisar `skills_json` vs `requirements_json` de la solicitud

### Problema: Scores muy bajos

**Verificar:**
1. ¿Hay coordenadas en solicitud y trabajadores? (para cercanía)
2. ¿Los trabajadores tienen rating histórico? (si no, score neutral 0.5)
3. ¿Hay conflictos de horario?
   ```sql
   SELECT * FROM assignments a
   INNER JOIN shifts s ON a.shift_id = s.id
   WHERE a.worker_id = {worker_id}
   AND s.date_from < '{date_to}'
   AND s.date_to > '{date_from}';
   ```

### Problema: No se puede actualizar solicitud

**Verificar:**
- Solo se pueden actualizar solicitudes con status `open` o `partially_filled`
- Solicitudes `filled` o `canceled` no se pueden modificar

### Problema: Error 403 al crear propuesta

**Verificar:**
- Solo las temporeras pueden crear propuestas
- Verificar que el tenant es de tipo `temporera`

---

## 📊 Verificar en Base de Datos

### Ver Solicitudes
```sql
SELECT sr.*, t.name as company_name
FROM staffing_requests sr
INNER JOIN tenants t ON sr.tenant_company_id = t.id
ORDER BY sr.created_at DESC;
```

### Ver Destinatarios
```sql
SELECT srt.*, t.name as agency_name
FROM staffing_request_targets srt
INNER JOIN tenants t ON srt.tenant_agency_id = t.id
WHERE srt.request_id = {request_id};
```

### Ver Propuestas
```sql
SELECT sp.*, sr.date_from, sr.date_to, t.name as agency_name
FROM staffing_proposals sp
INNER JOIN staffing_requests sr ON sp.request_id = sr.id
INNER JOIN tenants t ON sp.tenant_agency_id = t.id
ORDER BY sp.created_at DESC;
```

### Ver Candidatos en Propuesta
```sql
SELECT sp.id, sp.proposal_json
FROM staffing_proposals sp
WHERE sp.id = {proposal_id};
-- El proposal_json contiene el array de candidatos con scores
```

---

## 🎯 Próximos Pasos

Una vez probado el Sprint 2:
1. ✅ Verificar que el flujo completo funciona
2. ✅ Probar edge cases (sin trabajadores, sin vínculos, etc.)
3. ✅ Verificar que el matching es preciso
4. ✅ Continuar con Sprint 3 (Calendario y grupos)

---

## 📝 Notas Importantes

- **Multitenancy:** Todas las queries filtran por `tenant_id` automáticamente
- **Seguridad:** Solo empresas crean solicitudes, solo temporeras crean propuestas
- **Validaciones:** Fechas, cantidades y estados se validan en backend
- **Auto-matching:** Configurable por tenant (pesos, distancia máxima)
- **Conflictos:** El sistema detecta automáticamente conflictos de horario

