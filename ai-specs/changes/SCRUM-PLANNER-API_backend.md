# SCRUM-PLANNER-API_backend

## Goal

Provide unified planner feeds and operations with strict tenant isolation.

## Endpoints

- `GET /api/planner.php?path=events` (range, filters, mode)

- `POST /api/planner.php?path=slot` (create/update/delete slot)

- `POST /api/project-schedule.php` (multi-day creation by weekday selection)

- `GET|POST /api/shifts.php` (list and actions: create, split, merge, reassign, status, bulk)

## Validations

- Overlap check per person for time windows.

- Allow multi-client/day by splitting shifts (no overlap).

- RBAC checks per role.

## OpenAPI

- Update `/ai-specs/specs/api-spec.yml` accordingly.

## Tests

- Basic unit tests for overlap and reassign logic (service-level).

