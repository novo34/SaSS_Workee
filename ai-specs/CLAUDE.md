# AI Specs Wrapper for Cursor/Claude

Always load and follow the specs in `/ai-specs/specs/*`.

- Work in **small, verifiable steps** (TDD-first when possible).

- Do **not** create new pages if a hub view exists. Prefer toggles/tabs/drawers.

- Enforce **multitenancy**: every backend call must be tenant-scoped. The server derives `tenant_id` from session/JWT; client must never send it.

- Enforce **RBAC**: UI must hide routes/components the role cannot use; backend must re-check.

- No dead code, no duplication. Reuse existing core components (`api-client`, `modal`, `toast`, `datatable`).

Spanish → English rule:

- The human will type high-level tasks in Spanish. You must translate to English internally and implement/spec accordingly.

Always update `/ai-specs/specs/api-spec.yml` when endpoints change, and include diffs in PRs.

