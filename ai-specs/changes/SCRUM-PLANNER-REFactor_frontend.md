# SCRUM-PLANNER-REFactor_frontend

## Goal

Unify existing calendar-related pages into a single **Planner** with drawer and two modes.

## Scope

- Create:
  - `frontend/pages/planner.html`
  - `frontend/js/planner-state.js`
  - `frontend/js/planner-api.js`
  - `frontend/js/planner-drawer.js`
  - `frontend/js/planner-page.js`

- Remove from menu (keep as auxiliary only if needed):
  - `calendar.html`, `assignments.html`, `staffing-requests.html`, `staffing-proposals.html`

## Acceptance Criteria

- One calendar page renders Work Orders, Staffing Requests, Assignments, Shifts (via toggles/sources).

- Drawer tabs: Summary, Requests, Proposals, Assignments, Incidents.

- Mode toggle: PLANNING vs ASSIGNMENTS.

- No duplicated components or dead files.

## Manual Tests

- Create slot, send staffing request, accept proposal, assign, substitute, split/merge.

- Check role guards and tenant isolation (no leakage).

