# TASK-006 — Ticket Types

| Field | Value |
|---|---|
| **Task ID** | TASK-006 |
| **Title** | Ticket types |
| **Priority** | 4 Business Logic |

## Objective
Implement ticket types bound to destinations, including catalog price fields storage (calculation in TASK-007).

## Background
Sellable products with validity rules and max_per_order (`docs/06`, PRD ticketing).

## Dependencies
- TASK-005

## Affected Files
- `backend/app/Models/TicketType.php`
- `backend/app/Actions/Catalog/TicketTypes/*`
- `backend/app/Http/Controllers/Api/V1/TicketTypeController.php`
- `backend/app/Policies/TicketTypePolicy.php`
- tests

## Database Changes
- Use `ticket_types`; ensure money fields BIGINT

## Backend Requirements
- CRUD/deactivate ticket types
- Unique (destination_id, code)
- Store unit_price, tax_amount, service_fee_amount, validity fields

## API Requirements
- `GET /destinations/{id}/ticket-types` active only for clients
- Manage endpoints for admin

## Dashboard Requirements
- Actions ready; UI can be minimal

## Kiosk Requirements
- Can fetch active types for a destination

## Validation
- qty max_per_order ≥ 1; prices ≥ 0; destination exists

## Authorization
- `ticket_types.view` / `ticket_types.manage`

## Business Rules
- Inactive types not returned to sell flows
- Price changes audited (TASK-016 integration point)

## Edge Cases
- Soft-deleted/inactive exclusion from kiosk list

## Security
- Staff-only manage; no public write

## Testing
- Unique constraint; active filter; permission denials

## Acceptance Criteria
- [ ] Ticket types CRUD works with authz
- [ ] Kiosk/staff can list active types with server prices in response

## Definition of Done
- Ready for PricingService quote (TASK-007)
