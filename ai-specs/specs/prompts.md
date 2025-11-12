# Cursor Task Templates (Spanish → English)

## How to use

- You (human) write the high-level task in Spanish between <<<ES ... ES>>>.

- The agent translates to English and produces the plan/code per our specs.

### plan-frontend

Given <<<ES
[TAREA EN ESPAÑOL]
ES>>>

Produce a step-by-step plan to implement or refactor the FRONTEND according to:

- `/ai-specs/specs/frontend-standards.mdc`

- Target files only (list them).

- Include acceptance criteria and manual test notes.

### develop-frontend

Follow the plan at [path to changes/SCRUM-*.md] and implement:

- Create/update only the listed files.

- Respect multitenancy, RBAC, and no-duplication rules.

- Provide runnable code blocks.

### plan-backend

Given <<<ES
[TAREA EN ESPAÑOL]
ES>>>

Design endpoints, DTOs, validations, overlap rules, and OpenAPI diffs according to:

- `/ai-specs/specs/backend-standards-php.mdc`

- Output a single markdown plan.

### develop-backend

Follow the plan at [path to changes/SCRUM-*.md] and implement:

- Controllers (api/*.php), middleware usage, prepared queries, and tests outline.

- Update `/ai-specs/specs/api-spec.yml`.

