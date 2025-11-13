# Gobernanza AI-Specs - Instrucciones para Claude

> **Versión:** 1.0  
> **Última actualización:** 2025-01-XX  
> **Propósito:** Instrucciones para Claude sobre cómo aplicar las specs y gobernanza

---

## Aplicación de Specs

Antes de realizar CUALQUIER cambio, Claude DEBE:

1. **Leer specs relevantes** en `ai-specs/specs/`:
   - `00-governance.mdc` - Reglas de fases y gobernanza
   - `02-project-guardrails.mdc` - Reglas de seguridad
   - `04-change-safety-checklist.mdc` - Checklist de seguridad
   - Specs específicas según el cambio (frontend, backend, etc.)

2. **Verificar fase de trabajo:**
   - Si comando es `plan-*`: SOLO crear plan Markdown, PROHIBIDO modificar código
   - Si comando es `develop-*`: Requiere plan aprobado, implementar según plan

3. **Aplicar checklist pre-cambio:**
   - Verificar que no rompe reglas de guardrails
   - Verificar que no duplica código existente
   - Verificar que sigue estructura de carpetas

4. **Seguir especificaciones:**
   - Frontend: `specs/05-frontend-standards.mdc`
   - Backend: `specs/06-backend-standards-php.mdc`
   - Multitenancy/RBAC: `specs/07-privacy-multitenancy-rbac.mdc`

---

## Reglas de Fases

### Fase `plan-*`

**Comando:** `plan-{descripcion}` (ej: `plan-frontend-refactor`, `plan-api-v2`)

- ✅ **Permitido:** Crear documentos Markdown en `ai-specs/changes/`
- ❌ **Prohibido:** Modificar código fuente

**Objetivo:** Definir plan completo antes de implementar.

**Salida esperada:** Archivo `ai-specs/changes/{ID}_{NOMBRE}_PLAN.md` con plan detallado.

---

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

---

## Prioridad de Especificaciones

1. **Especificaciones AI-Specs** (`ai-specs/specs/`) - Máxima prioridad
2. **Especificación oficial** (`docs/Workee_kickoff_v_1.md`) - Alta prioridad
3. **Reglas de desarrollo** (`.cursorrules`) - Alta prioridad
4. **Documentación** (`docs/`) - Referencia

---

## Prohibiciones Absolutas

- ❌ Modificar código sin plan aprobado (excepto correcciones menores documentadas)
- ❌ Crear páginas top-level nuevas sin plan
- ❌ Renombrar/eliminar archivos sin plan de limpieza
- ❌ Duplicar componentes/utilidades existentes
- ❌ Ignorar specs en `ai-specs/specs/`

---

## Ejemplos de Uso

### Ejemplo 1: Crear Plan

```
Usuario: plan-frontend-refactor
Claude: Crea plan en ai-specs/changes/100_FRONTEND_REFACTOR_PLAN.md
        (NO modifica código)
```

### Ejemplo 2: Implementar Plan

```
Usuario: develop-100-frontend-refactor
Claude: Lee plan ai-specs/changes/100_FRONTEND_REFACTOR_PLAN.md
        Implementa según plan línea por línea
        Commits con referencia [develop-100]
```

---

## Checklist Pre-Cambio (OBLIGATORIO)

Antes de cualquier cambio, verificar:

- [ ] Plan aprobado en `ai-specs/changes/` con estado `[APPROVED]` (si es `develop-*`)
- [ ] Comando iniciado con fase correcta (`plan-*` o `develop-*`)
- [ ] Specs relevantes leídas y aplicadas
- [ ] No rompe reglas de `specs/02-project-guardrails.mdc`
- [ ] No duplica código existente
- [ ] Sigue estructura de carpetas (`specs/03-repo-manifest.mdc`)

---

## Referencias Rápidas

- **Gobernanza:** `ai-specs/specs/00-governance.mdc`
- **Guardrails:** `ai-specs/specs/02-project-guardrails.mdc`
- **Checklist:** `ai-specs/specs/04-change-safety-checklist.mdc`
- **Frontend:** `ai-specs/specs/05-frontend-standards.mdc`
- **Backend:** `ai-specs/specs/06-backend-standards-php.mdc`
- **Multitenancy/RBAC:** `ai-specs/specs/07-privacy-multitenancy-rbac.mdc`
- **Testing:** `ai-specs/specs/08-testing-and-acceptance.mdc`

---

## Flujo de Trabajo Recomendado

1. **Usuario solicita cambio:** `plan-{descripcion}` o `develop-{plan-id}`
2. **Claude verifica fase:** `plan-*` → solo plan, `develop-*` → requiere plan aprobado
3. **Claude lee specs relevantes:** Según el tipo de cambio
4. **Claude aplica checklist:** Verificar reglas de seguridad
5. **Claude ejecuta acción:** Crear plan o implementar según fase
6. **Claude verifica resultado:** Checklist post-cambio

---

## Notas Importantes

- **NUNCA asumir:** Siempre verificar en specs y documentación
- **SIEMPRE preguntar:** Si hay duda sobre cómo proceder
- **SIEMPRE referenciar:** Planes y specs en commits y cambios
- **SIEMPRE verificar:** Checklist pre y post cambio
