# 🧪 Cómo Probar Sprint 3 - Calendario y Preasignación

## 📋 Resumen del Sprint 3

Este sprint implementa:
- ✅ **Calendario interactivo** con FullCalendar (drag & drop)
- ✅ **API de Turnos (Shifts)** con CRUD completo
- ✅ **Validación de conflictos** (solapamientos, límites, skills)
- ✅ **Generación de solicitudes desde huecos** detectados en el calendario

---

## 🚀 Preparación

### 1. Verificar que los datos de prueba existen

```bash
# Verificar que tienes usuarios de prueba
php scripts/dev/create_all_test_users.php
```

**Credenciales recomendadas para pruebas:**
- **Empresa Admin:** `admin@empresa.com` / `password`
- **Planificador:** `planificador@empresa.com` / `password`

### 2. Acceder al Calendario

**URL:** `http://localhost/SaSS_Workee/calendar`

**Requisitos:**
- Debes estar logueado como `empresa_admin` o `planificador`
- El calendario aparece en el menú lateral bajo la sección "Empresa"

---

## 📅 Pruebas del Calendario

### Test 1: Visualizar el Calendario

1. **Acceder al calendario:**
   - Login como `admin@empresa.com` / `password`
   - Clic en "Calendario" en el menú lateral (sección Empresa)

2. **Verificar elementos:**
   - ✅ Calendario se carga correctamente
   - ✅ Botones de navegación (prev, next, today)
   - ✅ Selector de vista (Mes/Semana/Día)
   - ✅ Botón "Nuevo Turno"
   - ✅ Botón "Crear Solicitud"
   - ✅ Filtros (Grupo, Estado, Vista)

3. **Cambiar vista:**
   - Clic en "Semana" → Verifica vista semanal
   - Clic en "Día" → Verifica vista diaria
   - Clic en "Mes" → Verifica vista mensual

### Test 2: Crear un Turno

1. **Crear turno desde botón:**
   - Clic en "Nuevo Turno"
   - Se abre modal de creación

2. **Completar formulario:**
   - **Inicio:** Seleccionar fecha y hora (ej: `2024-01-15 08:00`)
   - **Fin:** Seleccionar fecha y hora (ej: `2024-01-15 16:00`)
   - **Tipo de Empleado:** Seleccionar "Empleado Propio" o "Trabajador Temporero"
   - **Empleado/Trabajador:** Seleccionar (si hay disponibles)
   - **Ubicación:** Opcional (ej: "Obra Central")
   - **Estado:** "Planificado" (por defecto)

3. **Guardar:**
   - Clic en "Guardar"
   - ✅ El turno aparece en el calendario
   - ✅ Color según estado (gris para "planificado")

### Test 3: Crear Turno desde Selección de Fecha

1. **Seleccionar rango en el calendario:**
   - En vista "Semana" o "Día", arrastrar sobre un rango de horas
   - Se abre automáticamente el modal con fechas prellenadas

2. **Completar y guardar:**
   - Completar los campos faltantes
   - Guardar
   - ✅ El turno aparece en el rango seleccionado

### Test 4: Editar un Turno

1. **Abrir turno existente:**
   - Clic en cualquier turno del calendario
   - Se abre modal con datos del turno

2. **Modificar:**
   - Cambiar hora de inicio o fin
   - Cambiar ubicación
   - Cambiar estado
   - Guardar

3. **Verificar:**
   - ✅ El turno se actualiza en el calendario
   - ✅ Los cambios se reflejan inmediatamente

### Test 5: Drag & Drop (Arrastrar Turno)

1. **Arrastrar turno:**
   - En vista "Semana" o "Día", hacer clic y arrastrar un turno a otra hora/día
   - Soltar en la nueva posición

2. **Verificar:**
   - ✅ El turno se mueve a la nueva posición
   - ✅ Se actualiza automáticamente en la base de datos
   - ✅ Si hay conflictos, se muestra advertencia

### Test 6: Redimensionar Turno

1. **Redimensionar:**
   - En vista "Semana" o "Día", hacer clic en el borde inferior de un turno
   - Arrastrar para cambiar la duración

2. **Verificar:**
   - ✅ La duración se actualiza
   - ✅ Se guarda automáticamente

### Test 7: Filtros del Calendario

1. **Filtrar por estado:**
   - Seleccionar "Asignado" en filtro de estado
   - ✅ Solo se muestran turnos con estado "asignado"

2. **Filtrar por grupo:**
   - Seleccionar un grupo (si hay grupos creados)
   - ✅ Solo se muestran turnos de ese grupo

3. **Limpiar filtros:**
   - Clic en "Limpiar"
   - ✅ Se muestran todos los turnos

---

## ⚠️ Pruebas de Validación de Conflictos

### Test 8: Detectar Solapamiento

1. **Crear turno base:**
   - Crear turno: `2024-01-15 08:00` a `2024-01-15 16:00`
   - Asignar a un empleado

2. **Intentar crear turno solapado:**
   - Crear otro turno: `2024-01-15 10:00` a `2024-01-15 18:00`
   - Mismo empleado
   - Guardar

3. **Verificar validación:**
   - ✅ Se muestra alerta de conflicto
   - ✅ El turno NO se crea
   - ✅ Mensaje: "El turno se solapa con otro turno existente"

### Test 9: Detectar Límite Diario

1. **Configurar límite (si aplica):**
   - Por defecto: máximo 2 turnos por día
   - Crear 2 turnos para el mismo empleado en el mismo día

2. **Intentar crear tercer turno:**
   - Crear otro turno el mismo día
   - Guardar

3. **Verificar:**
   - ✅ Se muestra error: "Límite de turnos por día excedido"
   - ✅ El turno NO se crea

### Test 10: Detectar Límite Semanal

1. **Crear múltiples turnos:**
   - Crear turnos que sumen más de 48 horas semanales para un empleado

2. **Verificar:**
   - ✅ Se muestra advertencia al acercarse al límite
   - ✅ Se muestra error si se excede

### Test 11: Validar Skills (si hay grupos con skills requeridas)

1. **Crear turno con grupo que requiere skills:**
   - Seleccionar un grupo que tenga skills requeridas
   - Asignar empleado sin esas skills

2. **Verificar:**
   - ✅ Se muestra error: "El trabajador no tiene las skills requeridas"
   - ✅ Lista de skills faltantes

---

## 🔍 Pruebas de Generación de Solicitudes desde Huecos

### Test 12: Detectar Huecos en el Calendario

1. **Preparar escenario:**
   - Crear varios turnos en diferentes días
   - Dejar algunos días sin turnos o con déficit

2. **Detectar huecos:**
   - Clic en "Crear Solicitud" en el calendario
   - El sistema analiza el rango visible

3. **Verificar:**
   - ✅ Se detectan días sin turnos
   - ✅ Se detectan grupos con déficit de personal
   - ✅ Se muestra información de huecos detectados

### Test 13: Generar Solicitud desde Huecos

1. **Detectar huecos:**
   - Clic en "Crear Solicitud"
   - Si hay huecos, se redirige a página de solicitudes

2. **Verificar datos prellenados:**
   - ✅ Las fechas de los huecos están prellenadas
   - ✅ La cantidad necesaria está calculada
   - ✅ Los grupos afectados están identificados

3. **Completar solicitud:**
   - Completar información faltante (ubicación, skills, etc.)
   - Enviar solicitud

---

## 🧪 Pruebas de API (Postman/Thunder Client)

### Test 14: Listar Turnos

**Request:**
```http
GET http://localhost/SaSS_Workee/api/v1/shifts?from=2024-01-01&to=2024-01-31
Authorization: Bearer {access_token}
X-Tenant-ID: 18
Content-Type: application/json
```

**Response esperado:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "start_at": "2024-01-15 08:00:00",
      "end_at": "2024-01-15 16:00:00",
      "employee_type": "own_employee",
      "status": "planned",
      ...
    }
  ],
  "page": 1,
  "page_size": 20,
  "total": 1
}
```

### Test 15: Crear Turno

**Request:**
```http
POST http://localhost/SaSS_Workee/api/v1/shifts
Authorization: Bearer {access_token}
X-Tenant-ID: 18
Content-Type: application/json

{
  "start_at": "2024-01-20 08:00:00",
  "end_at": "2024-01-20 16:00:00",
  "employee_type": "own_employee",
  "own_employee_id": 1,
  "location": "Obra Central",
  "status": "planned"
}
```

**Response esperado:**
```json
{
  "success": true,
  "data": {
    "id": 2,
    "start_at": "2024-01-20 08:00:00",
    ...
  }
}
```

### Test 16: Actualizar Turno

**Request:**
```http
PUT http://localhost/SaSS_Workee/api/v1/shifts/1
Authorization: Bearer {access_token}
X-Tenant-ID: 18
Content-Type: application/json

{
  "start_at": "2024-01-15 09:00:00",
  "end_at": "2024-01-15 17:00:00",
  "status": "assigned"
}
```

### Test 17: Validar Conflictos

**Request:**
```http
POST http://localhost/SaSS_Workee/api/v1/shifts/1/validate
Authorization: Bearer {access_token}
X-Tenant-ID: 18
Content-Type: application/json
```

**Response esperado:**
```json
{
  "success": true,
  "valid": false,
  "conflicts": [
    {
      "type": "overlap",
      "message": "El turno se solapa con otro turno existente",
      "details": [...]
    }
  ],
  "warnings": []
}
```

### Test 18: Cancelar Turno

**Request:**
```http
DELETE http://localhost/SaSS_Workee/api/v1/shifts/1
Authorization: Bearer {access_token}
X-Tenant-ID: 18
```

**Response esperado:**
```json
{
  "success": true,
  "message": "Turno cancelado correctamente"
}
```

---

## 📱 Pruebas en Móvil

### Test 19: Calendario Responsive

1. **Abrir en móvil:**
   - Acceder desde dispositivo móvil o emulador
   - URL: `http://TU_IP/SaSS_Workee/calendar`

2. **Verificar:**
   - ✅ El calendario se adapta a pantalla pequeña
   - ✅ Los botones son táctiles (mínimo 44x44px)
   - ✅ El modal se abre correctamente
   - ✅ Los filtros son usables

3. **Probar drag & drop en móvil:**
   - ✅ Funciona con gestos táctiles (si está implementado)
   - ✅ O se puede editar desde el modal

---

## ✅ Checklist de Pruebas

### Funcionalidad Básica
- [ ] Calendario carga correctamente
- [ ] Se pueden crear turnos
- [ ] Se pueden editar turnos
- [ ] Se pueden cancelar turnos
- [ ] Drag & drop funciona
- [ ] Redimensionamiento funciona

### Validaciones
- [ ] Detecta solapamientos
- [ ] Detecta límites diarios
- [ ] Detecta límites semanales
- [ ] Detecta skills faltantes
- [ ] Detecta capacidad excedida

### Generación de Solicitudes
- [ ] Detecta huecos en calendario
- [ ] Genera solicitud con datos prellenados
- [ ] Redirige correctamente a página de solicitudes

### API
- [ ] GET /shifts lista correctamente
- [ ] POST /shifts crea correctamente
- [ ] PUT /shifts actualiza correctamente
- [ ] DELETE /shifts cancela correctamente
- [ ] POST /shifts/{id}/validate valida correctamente

### Responsive
- [ ] Funciona en móvil
- [ ] Botones táctiles
- [ ] Modales usables

---

## 🐛 Problemas Comunes y Soluciones

### Problema: El calendario no carga

**Solución:**
1. Verificar que FullCalendar está cargado (consola del navegador)
2. Verificar que la API `/api/v1/shifts` responde correctamente
3. Verificar que el token de autenticación es válido

### Problema: No se pueden crear turnos

**Solución:**
1. Verificar permisos del usuario (debe ser `empresa_admin` o `planificador`)
2. Verificar que hay empleados disponibles
3. Revisar consola del navegador para errores

### Problema: Los conflictos no se detectan

**Solución:**
1. Verificar que `ConflictValidator` está funcionando
2. Revisar logs del servidor
3. Verificar que los turnos tienen fechas válidas

### Problema: Drag & drop no funciona

**Solución:**
1. Verificar que FullCalendar está en modo editable
2. Verificar que no hay errores en consola
3. Probar en otro navegador

---

## 📝 Notas Adicionales

- **Datos de prueba:** Para probar con datos reales, primero necesitas crear empleados y grupos (esto se implementará en sprints posteriores)
- **Validaciones:** Las validaciones de conflictos son estrictas y previenen crear turnos inválidos
- **Performance:** El calendario carga turnos por rango de fechas para optimizar rendimiento

---

## 🔗 Referencias

- **Documentación completa:** `docs/Workee_kickoff_v_1.md`
- **Credenciales:** `docs/RESUMEN_CREDENCIALES.md`
- **Acceso usuarios:** `docs/ACCESO_USUARIOS.md`
- **Sprint 2:** `docs/COMO_PROBAR_SPRINT2.md`

---

**Última actualización:** Sprint 3 - Calendario y Preasignación

