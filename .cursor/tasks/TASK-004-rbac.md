# TASK-004 — RBAC

| Field | Value |
|---|---|
| **Task ID** | TASK-004 |
| **Title** | RBAC |
| **Priority** | 6 Authorization |

## Objective
Seed MVP roles/permissions and enforce server-side authorization policies per `docs/07-RBAC.md`.

## Background
Client UI checks are UX only. Laravel Policies/Gates/Action checks are authority. No STAFF generic role.

## Dependencies
- TASK-003

## Affected Files
- `backend/database/seeders/RolePermissionSeeder.php`
- `backend/app/Policies/*`
- `backend/app/Enums` or permission constants
- `backend/app/Support/Authorization/*` (optional)
- `backend/tests/Feature/Rbac/*`

## Database Changes
- Seed `roles`, `permissions`, pivots
- Optional assign first super_admin via secure seeder

## Backend Requirements
- Roles: super_admin, admin, manager, finance, ticket_officer, gate_officer, operator, auditor
- Permission catalog from docs/07
- Policies for User, Order, Payment, Ticket, Device, etc. (as resources appear — stub OK)
- `roles.assign` only super_admin
- Deny by default helpers for Actions

## API Requirements
- Authenticated endpoints must authorize
- 403 envelope per API contract

## Dashboard Requirements
- Permission helper available for nav (consumed in TASK-018)

## Kiosk Requirements
- Document device abilities ≠ staff permissions (implemented TASK-019/020)

## Validation
- N/A beyond seed integrity

## Authorization
- Full matrix defaults from docs/07
- tickets.create / checkins.reverse / transactions mutations not human grants

## Business Rules
- Least privilege; no escalation via mass assignment

## Edge Cases
- Admin cannot self-assign super_admin
- Auditor read-only

## Security
- Prevent privilege escalation; audit role assignment (hooks with TASK-016)

## Testing
- Ticket officer cannot refund
- Auditor cannot create order
- Gate officer cannot manage catalog
- Super admin can assign roles

## Acceptance Criteria
- [ ] Seeds install cleanly
- [ ] Critical deny/allow tests pass
- [ ] Matrix matches docs/07 defaults

## Definition of Done
- RBAC usable by subsequent domain Actions
- Documented how to check permissions in Actions
