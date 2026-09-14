# TASK-009 — Orders

| Field | Value |
|---|---|
| **Task ID** | TASK-009 |
| **Title** | Orders |
| **Priority** | 4 Business Logic |

## Objective
Implement authoritative order create, get, cancel, and expiry fields using shared Actions for both channels.

## Background
Orders are channel-tagged (`kiosk`|`assisted`) with server totals and snapshots (`docs/05`, `docs/08`).

## Dependencies
- TASK-007, TASK-008
- TASK-004 for permissions

## Affected Files
- `backend/app/Actions/Orders/CreateOrder.php`
- `backend/app/Actions/Orders/CancelOrder.php`
- `backend/app/Models/Order.php`, `OrderItem.php`
- `backend/app/Http/Controllers/Api/V1/OrderController.php`
- `backend/app/Enums/OrderStatus.php`
- tests

## Database Changes
- Use orders/order_items; set expires_at from settings TTL

## Backend Requirements
- CreateOrder recalculates via PricingService
- Channel from principal (not client forge to escalate)
- Cancel only pending_payment
- Unique order_number
- Item snapshots persisted

## API Requirements
- `POST /orders` (Idempotency-Key required)
- `GET /orders/{id}` scoped
- `POST /orders/{id}/cancel`
- Reject client money fields

## Dashboard Requirements
- Actions only; UI in TASK-017

## Kiosk Requirements
- API usable; device sellability check (maintenance/disabled — full device TASK-019; stub if needed)

## Validation
- items, destination, quantities

## Authorization
- device orders.create ability or `orders.create`
- view scoping / `orders.view`
- cancel permission/owner rules

## Business Rules
- No tickets on create
- Channel immutable
- Shared logic for both channels

## Edge Cases
- Idempotent replay; cancel after paid rejected; expired flag set by job later (TASK-026)

## Security
- IDOR prevention on GET
- Device cannot access others’ orders

## Testing
- Create totals match quote
- Idempotency
- Cancel rules
- Permission denials

## Acceptance Criteria
- [ ] Order created PENDING_PAYMENT with authoritative totals
- [ ] Idempotent create safe
- [ ] Cancel unpaid works; paid cancel blocked

## Definition of Done
- Orders ready for TASK-010 payments
