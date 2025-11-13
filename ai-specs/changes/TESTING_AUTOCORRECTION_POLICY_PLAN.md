# Plan: Política de Pruebas Automáticas y Autocorrección

> **Estado:** [IMPLEMENTED]  
> **Fecha:** 2025-01-XX  
> **Autor:** AI Assistant  
> **Referencia:** Ampliar gobernanza AI-Specs para incluir testing automático y autocorrección

---

## Objetivo

Ampliar la gobernanza de AI-Specs para que cada desarrollo incluya pruebas automáticas y autocorrección de fallos. Esto asegura que todo código generado pase pruebas antes de considerarse completado.

---

## Contexto

Actualmente, las especificaciones AI-Specs incluyen:
- Reglas de fases (`plan-*` vs `develop-*`)
- Checklists de seguridad pre/post cambio
- Directrices de testing básicas

**Falta:**
- Obligatoriedad de generar tests automáticos en cada `develop-*`
- Ejecución automática de tests después de generar código
- Autocorrección automática si tests fallan
- Documentación del resultado de tests en el proceso

---

## Alcance

### Incluye

- Actualizar `specs/00-governance.mdc` con sección de testing automático
- Actualizar `specs/04-change-safety-checklist.mdc` con paso obligatorio de tests
- Actualizar `specs/08-testing-and-acceptance.mdc` con directrices de pruebas
- Actualizar `CLAUDE.md` con regla de autocorrección

### No Incluye

- Modificar código fuente existente
- Crear scripts de testing (eso será parte de futuros `develop-*`)
- Cambiar estructura de carpetas

---

## Archivos Afectados

- `ai-specs/specs/00-governance.mdc` - Agregar sección "Automated Testing & Self-Correction"
- `ai-specs/specs/04-change-safety-checklist.mdc` - Agregar paso obligatorio de tests en Post-Flight
- `ai-specs/specs/08-testing-and-acceptance.mdc` - Definir directrices de pruebas detalladas
- `ai-specs/CLAUDE.md` - Actualizar sección "Task Phases Rule"

---

## Plan de Implementación

### Fase 1: Actualizar `specs/00-governance.mdc`

#### 1.1 Ubicación de la Nueva Sección

**Agregar después de la sección "Fase `develop-*`" y antes de "Flujo de Aprobación".**

#### 1.2 Contenido a Agregar

```markdown
---

## Automated Testing & Self-Correction

### Regla Obligatoria

Cada tarea `develop-*` DEBE incluir pruebas automáticas y autocorrección de fallos.

### Proceso Obligatorio

1. **Generar script de pruebas:**
   - Crear script de pruebas (unit o integration) en `/tests/` o `/frontend/tests/` según el módulo
   - Nombre del script: `<module>.test.php` (Backend) o `<module>.test.js` (Frontend)
   - El script debe devolver exit code 0 si todas las pruebas pasan

2. **Ejecutar pruebas inmediatamente:**
   - Ejecutar el script de pruebas inmediatamente después de generar el código
   - No continuar hasta que las pruebas pasen

3. **Autocorrección automática:**
   - Si alguna prueba falla, corregir el código automáticamente
   - Reiterar correcciones hasta que todas las pruebas pasen
   - No preguntar confirmación durante la corrección automática

4. **Documentar resultado:**
   - Documentar el resultado final de las pruebas (✅/❌) en el diff del PR o en la consola
   - Incluir mensaje claro: "Tests Passed: Yes/No"

### Tipos de Pruebas

- **Backend:** PHPUnit o script `php test_runner.php`
- **Frontend:** JS tests (Vitest, Jest o script local)

### Simplicidad de Pruebas

Las pruebas deben ser simples y enfocadas:
- CRUD básico (crear, leer, actualizar, eliminar)
- Validaciones de entrada
- Render de componentes (Frontend)
- Verificación de respuestas API (Backend)

### Exit Code

- Cada test debe devolver **exit code 0** si todo pasa
- Si no pasa, el agente reitera cambios hasta lograr exit code 0

### Excepciones

- Si el plan explícitamente indica "sin tests" o "tests manuales solamente", documentar la razón
- Tests de aislamiento multitenancy siempre obligatorios para endpoints API

---
```

---

### Fase 2: Actualizar `specs/04-change-safety-checklist.mdc`

#### 2.1 Ubicación de la Modificación

**Agregar nueva subsección dentro de "Checklist Post-Flight" después de "Verificación de Código" y antes de "Diff Explicado".**

#### 2.2 Contenido a Agregar

```markdown
### Run Tests and Self-Fix if Failing

- [ ] Script de pruebas generado en ubicación correcta (`/tests/` o `/frontend/tests/`)
- [ ] Script de pruebas ejecutado inmediatamente después de generar código
- [ ] Todas las pruebas pasan (exit code 0)
- [ ] Si pruebas fallaron, código corregido automáticamente hasta que pasen
- [ ] Resultado documentado: **Tests Passed: Yes/No**
- [ ] Mensaje de resultado incluido en diff o consola
```

**También actualizar la sección "Linter y Tests" existente:**

```markdown
### Linter y Tests

- [ ] Linter sin errores críticos
- [ ] Tests pasando (OBLIGATORIO - exit code 0)
- [ ] Script de pruebas generado y ejecutado
- [ ] Autocorrección aplicada si tests fallaron
- [ ] Resultado de tests documentado (✅/❌)
```

---

### Fase 3: Actualizar `specs/08-testing-and-acceptance.mdc`

#### 3.1 Ubicación de las Nuevas Secciones

**Agregar después de "Tests de Aislamiento" y antes de "Tests Funcionales".**

#### 3.2 Contenido a Agregar

```markdown
---

## Directrices de Pruebas Automáticas

### Tipos de Pruebas por Stack

#### Backend (PHP)

- **Framework:** PHPUnit o script `php test_runner.php`
- **Ubicación:** `/tests/` o `backend/tests/`
- **Nomenclatura:** `<module>.test.php` (ej: `shifts.test.php`, `users.test.php`)
- **Ejecución:** `php tests/<module>.test.php` o `php test_runner.php tests/<module>.test.php`
- **Exit code:** 0 si todas pasan, != 0 si alguna falla

**Ejemplo de estructura:**
```php
<?php
// tests/shifts.test.php
require_once __DIR__ . '/../backend/api/shifts.php';
require_once __DIR__ . '/../backend/core/TenantMiddleware.php';

// Test básico CRUD
function testCreateShift() {
    // ... código de prueba
    return true; // o false si falla
}

// Ejecutar tests
$allPassed = testCreateShift() && testUpdateShift() && testDeleteShift();
exit($allPassed ? 0 : 1);
```

#### Frontend (JavaScript)

- **Framework:** Vitest, Jest o script local
- **Ubicación:** `/frontend/tests/` o `tests/frontend/`
- **Nomenclatura:** `<module>.test.js` (ej: `calendar.test.js`, `data-table.test.js`)
- **Ejecución:** `node tests/<module>.test.js` o `npm test tests/<module>.test.js`
- **Exit code:** 0 si todas pasan, != 0 si alguna falla

**Ejemplo de estructura:**
```javascript
// frontend/tests/calendar.test.js
// Test básico de render de componente
function testCalendarRenders() {
    // ... código de prueba
    return true; // o false si falla
}

// Ejecutar tests
const allPassed = testCalendarRenders() && testCalendarDragDrop();
process.exit(allPassed ? 0 : 1);
```

### Simplicidad de Pruebas

Las pruebas deben ser **simples y enfocadas**:

- ✅ **CRUD básico:** Crear, leer, actualizar, eliminar recursos
- ✅ **Validaciones:** Verificar que validaciones funcionan correctamente
- ✅ **Render de componentes:** Verificar que componentes se renderizan (Frontend)
- ✅ **Respuestas API:** Verificar formato JSON y códigos HTTP (Backend)
- ❌ **NO pruebas complejas:** Evitar pruebas de integración complejas en esta fase
- ❌ **NO pruebas E2E:** Las pruebas E2E son manuales y están fuera del scope automático

### Nomenclatura de Scripts

- **Backend:** `<module>.test.php` (ej: `shifts.test.php`, `work_orders.test.php`)
- **Frontend:** `<module>.test.js` (ej: `calendar.test.js`, `data-table.test.js`)
- **Ubicación:** Mismo nombre que el archivo testado, con sufijo `.test`

### Exit Code Obligatorio

- Cada script de prueba DEBE devolver **exit code 0** si todas las pruebas pasan
- Si alguna prueba falla, el script DEBE devolver exit code != 0
- El agente DEBE verificar el exit code y reiterar correcciones hasta lograr 0

### Autocorrección Automática

- Si el exit code != 0, el agente DEBE:
  1. Analizar el error de la prueba
  2. Corregir el código automáticamente
  3. Re-ejecutar la prueba
  4. Repetir hasta lograr exit code 0
  5. NO preguntar confirmación durante la corrección

### Documentación de Resultados

- Después de ejecutar pruebas, documentar:
  - **Tests Passed: Yes/No**
  - Número de pruebas ejecutadas
  - Número de pruebas que pasaron
  - Si hubo correcciones automáticas, mencionarlas

**Formato de documentación:**
```
Tests: ✅ Passed (5/5)
- testCreateShift: ✅
- testUpdateShift: ✅
- testDeleteShift: ✅
- testValidation: ✅
- testTenantIsolation: ✅
```

O si falló:
```
Tests: ❌ Failed (3/5)
- testCreateShift: ✅
- testUpdateShift: ❌ (corregido automáticamente)
- testDeleteShift: ✅
- testValidation: ❌ (corregido automáticamente)
- testTenantIsolation: ✅

Correcciones aplicadas:
- Corregido validación de fecha en testUpdateShift
- Corregido formato de respuesta en testValidation
```

---
```

---

### Fase 4: Actualizar `CLAUDE.md`

#### 4.1 Ubicación de la Modificación

**Actualizar la sección "### Fase `develop-*`" agregando el nuevo requisito después de "Objetivo:" y antes de "Salida esperada:".**

#### 4.2 Contenido a Agregar

**En la sección "### Fase `develop-*`":**

```markdown
### Fase `develop-*`

**Comando:** `develop-{plan-id}` (ej: `develop-100-planner-v2`, `develop-200-backend-crm`)

- ✅ **Permitido:** Modificar código según plan aprobado
- ❌ **Prohibido:** Desviarse del plan sin crear nuevo plan primero

**Requisito previo:** Plan aprobado en `ai-specs/changes/` con estado `[APPROVED]`.

**Objetivo:** Implementar plan línea por línea.

**Testing Automático Obligatorio:**
- Después de cada `develop-*`, automáticamente:
  1. Generar script de pruebas en ubicación correcta (`/tests/` o `/frontend/tests/`)
  2. Ejecutar el script de pruebas inmediatamente
  3. Si pruebas fallan, corregir código automáticamente hasta que todas pasen
  4. No preguntar confirmación durante la corrección automática
  5. Documentar resultado final (✅/❌) en diff o consola

**Salida esperada:** Código implementado según plan, commits con referencia `[develop-{plan-id}]`, tests pasando (exit code 0).
```

**También actualizar la sección "## Reglas de Fases" si existe:**

```markdown
## Reglas de Fases

### Fase `plan-*`
[... contenido existente ...]

### Fase `develop-*`
[... contenido existente ...]

**Testing Automático:**
- After every `develop-*`, automatically run generated tests and self-fix until all pass.
- No confirmation required during automatic correction.
- Document test results (✅/❌) in diff or console.
```

---

## Checklist Pre-Implementación

- [ ] Plan revisado y aprobado
- [ ] Archivos actuales leídos y estructura entendida
- [ ] Ubicaciones de nuevas secciones identificadas
- [ ] Contenido de nuevas secciones preparado

---

## Checklist Post-Implementación

- [ ] `specs/00-governance.mdc` actualizado con sección "Automated Testing & Self-Correction"
- [ ] `specs/04-change-safety-checklist.mdc` actualizado con paso obligatorio de tests
- [ ] `specs/08-testing-and-acceptance.mdc` actualizado con directrices detalladas
- [ ] `CLAUDE.md` actualizado con regla de autocorrección en fase `develop-*`
- [ ] Todas las referencias cruzadas verificadas
- [ ] Estilo consistente mantenido
- [ ] Plan marcado como `[IMPLEMENTED]`

---

## Ejemplos de Uso

### Ejemplo 1: Develop con Tests Automáticos

```
Usuario: develop-100-shifts-api
Claude: 
1. Lee plan ai-specs/changes/100_SHIFTS_API_PLAN.md
2. Implementa código según plan
3. Genera tests/shifts.test.php
4. Ejecuta: php tests/shifts.test.php
5. Si falla, corrige automáticamente
6. Re-ejecuta hasta exit code 0
7. Documenta: "Tests: ✅ Passed (5/5)"
```

### Ejemplo 2: Develop Frontend con Tests

```
Usuario: develop-200-calendar-component
Claude:
1. Lee plan ai-specs/changes/200_CALENDAR_COMPONENT_PLAN.md
2. Implementa código según plan
3. Genera frontend/tests/calendar.test.js
4. Ejecuta: node frontend/tests/calendar.test.js
5. Si falla, corrige automáticamente
6. Re-ejecuta hasta exit code 0
7. Documenta: "Tests: ✅ Passed (3/3)"
```

---

## Riesgos y Mitigaciones

- **Riesgo:** Tests pueden ser demasiado complejos y fallar por razones no relacionadas con el código
  - **Mitigación:** Enfatizar simplicidad de pruebas (CRUD, validaciones básicas)

- **Riesgo:** Autocorrección puede entrar en loop infinito
  - **Mitigación:** Limitar número de iteraciones (máximo 5 intentos), luego reportar error

- **Riesgo:** Tests pueden requerir configuración adicional (BD, servicios externos)
  - **Mitigación:** Tests deben ser independientes y no requerir configuración compleja

---

## Referencias

- `specs/00-governance.mdc` - Gobernanza actual
- `specs/04-change-safety-checklist.mdc` - Checklist actual
- `specs/08-testing-and-acceptance.mdc` - Testing actual
- `CLAUDE.md` - Instrucciones para Claude actuales

---

## Notas Adicionales

- Esta política se aplica a TODOS los `develop-*` futuros
- Los tests deben ser simples y rápidos de ejecutar
- La autocorrección es automática, sin confirmación del usuario
- Los resultados de tests deben ser siempre documentados
- Si un plan explícitamente indica "sin tests", documentar la razón

---

**Estado:** `[DRAFT]` → Cambiar a `[APPROVED]` cuando se apruebe este plan.

