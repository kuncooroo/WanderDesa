# TASK-005 — Tourism Master Data (Destinations)

| Field | Value |
|---|---|
| **Task ID** | TASK-005 |
| **Title** | Tourism master data — destinations |
| **Priority** | 4 Business Logic |

## Objective
Implement destination catalog as master data with admin manage + read APIs.

## Background
Destinations bound ticket types, devices, orders, check-ins (`docs/06`, Phase 5).

## Dependencies
- TASK-004

## Affected Files
- `backend/app/Models/Destination.php`
- `backend/app/Actions/Catalog/*`
- `backend/app/Http/Controllers/Api/V1/DestinationController.php`
- `backend/app/Policies/DestinationPolicy.php`
- `backend/tests/Feature/Catalog/DestinationTest.php`

## Database Changes
- Use existing `destinations` table; no destructive change

## Backend Requirements
- Actions: Create/Update/Deactivate destination
- Soft delete or is_active per schema
- Code unique

## API Requirements
- `GET /api/v1/destinations`
- Staff manage POST/PATCH with `destinations.manage`

## Dashboard Requirements
- Minimal manage UI optional here; full UI may wait TASK-018 (API/Actions must exist)

## Kiosk Requirements
- List active destinations for browse (via API)

## Validation
- code, name, timezone required; code unique

## Authorization
- view vs manage per RBAC

## Business Rules
- Inactive destinations not sellable later

## Edge Cases
- Duplicate code; deactivate with existing ticket types (RESTRICT/soft)

## Security
- No IDOR on manage; authz required

## Testing
- Create/list/update/deactivate permission tests

## Acceptance Criteria
- [ ] Active destinations listable via API
- [ ] Unauthorized manage denied
- [ ] Unique code enforced

## Definition of Done
- Destination master data ready for ticket types (TASK-006)
