# TASK-022 — Flutter Kiosk Checkout

| Field | Value |
|---|---|
| **Task ID** | TASK-022 |
| **Title** | Flutter kiosk checkout flow |
| **Priority** | 9 Kiosk |

## Objective
Implement self-service browse → select → summary → pay → success/fail → QR display flow using server authority and idempotent retries.

## Background
UX flows A1–A12, A15–A21 from docs/09. FINANCIAL/PAYMENT/OFFLINE rules apply.

## Dependencies
- TASK-020, TASK-021
- TASK-011 for digital finality (sandbox OK)

## Affected Files
- `kiosk/lib/features/catalog/**`
- `kiosk/lib/features/checkout/**`
- `kiosk/lib/features/tickets/**`
- `kiosk/lib/features/recovery/**`
- `kiosk/lib/payment/**`
- tests

## Database Changes
- None

## Backend Requirements
- None new (API must already support)

## API Requirements
- quote, orders, payments, payment status, tickets, print-payload

## Dashboard Requirements
- None

## Kiosk Requirements
- Destination browse (or skip)
- Ticket selection with server prices
- Order summary server totals only
- Payment processing wait + poll
- Success only after server PAID+tickets
- Failure/retry/cancel UX
- QR display
- Session timeout + recovery screen
- Idempotency keys persisted for retries
- Network error honest UX

## Validation
- Display-only; server rejects bad carts

## Authorization
- Device token

## Business Rules
- Never mark success from local cache alone
- Changing cart = new idempotency key

## Edge Cases
- Mid-pay disconnect; payment pending after restart; order expired

## Security
- No client amount fields sent

## Testing
- State machine tests for pending/success/fail
- Manual UAT against staging API

## Acceptance Criteria
- [ ] Happy path purchase works against API
- [ ] Kill network mid-pay → no fake PAID
- [ ] Totals match server quote
- [ ] Multi-ticket QR navigable

## Definition of Done
- Self-service channel usable for MVP (print may be degraded until TASK-023)
