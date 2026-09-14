# TASK-008 — Idempotency Infrastructure

| Field | Value |
|---|---|
| **Task ID** | TASK-008 |
| **Title** | Idempotency infrastructure |
| **Priority** | 4 Business Logic |

## Objective
Provide reusable idempotency key handling for critical POSTs (orders, payments, check-in, refunds).

## Background
Offline/retry safety requires idempotency (`docs/05`, `docs/06`, `docs/08`). Local kiosk retries must not duplicate money/tickets.

## Dependencies
- TASK-002, TASK-003

## Affected Files
- `backend/app/Support/Idempotency/IdempotencyManager.php` (name flexible)
- `backend/app/Http/Middleware/EnsureIdempotencyKey.php` (optional)
- `backend/app/Models/IdempotencyKey.php`
- tests

## Database Changes
- Use `idempotency_keys` table

## Backend Requirements
- Begin/commit/replay helpers scoped by principal + action
- Same key + same payload hash → return original resource
- Same key + different payload → 409 conflict
- Store response resource type/id

## API Requirements
- Support `Idempotency-Key` header
- Document required endpoints (orders, payments, check-ins, refunds)

## Dashboard Requirements
- Assisted flows should send keys for critical posts (via Livewire later)

## Kiosk Requirements
- Will generate/store keys in Flutter later

## Validation
- Key length ≤ 128; required when middleware applied

## Authorization
- Scoped per actor (user id or device id)

## Business Rules
- Never create duplicate orders/payments on replay

## Edge Cases
- Concurrent same key; in-progress lock

## Security
- Do not leak other actors’ idempotent resources

## Testing
- Replay returns same; conflict 409; cross-actor isolation

## Acceptance Criteria
- [ ] Helper usable by CreateOrder/InitiatePayment
- [ ] Tests prove no duplicate on replay

## Definition of Done
- Ready for TASK-009 orders to require Idempotency-Key
