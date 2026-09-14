# TASK-015 — Refunds

| Field | Value |
|---|---|
| **Task ID** | TASK-015 |
| **Title** | Refunds |
| **Priority** | 4 Business Logic |

## Objective
Implement permissioned full refund flow that updates payment/order/tickets under eligibility rules.

## Background
MVP default: refund only if tickets not USED. Full refund amount calculated by Laravel (`docs/05`, `docs/07`).

## Dependencies
- TASK-010, TASK-012
- TASK-014 (to enforce not-USED rule)

## Affected Files
- `backend/app/Actions/Payments/RefundPayment.php`
- `backend/app/Http/Controllers/Api/V1/PaymentRefundController.php`
- Integration refund method on PaymentGateway
- tests

## Database Changes
- Status transitions to refunded; timestamps

## Backend Requirements
- Server sets refund_amount = payment.amount
- Reject client refund amount
- Mark payment/order refunded; tickets → REFUNDED if eligible
- Provider refund call for digital when configured
- Idempotency-Key required

## API Requirements
- `POST /api/v1/payments/{id}/refund`
- 422 `refund.ineligible` when USED

## Dashboard Requirements
- Finance UI button later/reporting; Action must exist

## Kiosk Requirements
- None

## Validation
- reason required/optional per product; no amount field

## Authorization
- `payments.refund` only (finance/super_admin)

## Business Rules
- Default block if any related ticket USED
- Tickets lose entry eligibility after refund

## Edge Cases
- Already refunded; provider refund failure; mixed ticket states

## Security
- Strong authz + audit `refund.created`

## Testing
- Eligible refund success
- USED ineligible
- Permission deny for ticket_officer
- Idempotent refund

## Acceptance Criteria
- [ ] Eligible PAID refund completes and voids tickets
- [ ] USED tickets block refund by default
- [ ] Unauthorized roles denied

## Definition of Done
- Refund path matches PRD/RBAC defaults
