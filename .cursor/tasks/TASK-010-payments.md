# TASK-010 — Payments

| Field | Value |
|---|---|
| **Task ID** | TASK-010 |
| **Title** | Payments (initiate, cash confirm, states) |
| **Priority** | 4 Business Logic |

## Objective
Implement payment records, initiate flow, cash confirmation, and status machine — without trusting client amounts or PAID claims.

## Background
Payment amount always equals order.grand_total. Digital provider adapter may be null/sandbox until TASK-011/vendor.

## Dependencies
- TASK-009
- TASK-004

## Affected Files
- `backend/app/Actions/Payments/InitiatePayment.php`
- `backend/app/Actions/Payments/ConfirmCashPayment.php`
- `backend/app/Services/Payments/PaymentStateService.php`
- `backend/app/Integrations/Payments/PaymentGateway.php` (+ Null/Sandbox)
- `backend/app/Http/Controllers/Api/V1/PaymentController.php`
- `backend/app/Enums/PaymentStatus.php`
- tests

## Database Changes
- Use `payments` table

## Backend Requirements
- Initiate → PENDING/PROCESSING; amount server-set
- Cash confirm (staff) → PAID then trigger issuance hook (TASK-012 can complete issuance)
- Failed path sets FAILED
- Reject client `amount`
- PaymentGateway interface for digital next_action

## API Requirements
- `POST /orders/{id}/payments` Idempotency-Key required
- `GET /payments/{id}`
- `POST /payments/{id}/confirm-cash`
- Error codes per contract

## Dashboard Requirements
- Cash confirm used by assisted sale (TASK-017)

## Kiosk Requirements
- Digital initiate + status poll only

## Validation
- method digital|cash; cash staff-only; order pending_payment

## Authorization
- `payments.create` / device `payments.initiate`
- Cash confirm staff only

## Business Rules
- No partial pay
- Client cannot force PAID
- One paid payment per order MVP (enforce in domain)

## Edge Cases
- Initiate on expired order; double initiate idempotent; cash on digital-only kiosk denied

## Security
- Audit payment.initiated / payment.paid (cash) via TASK-016 hooks
- No secrets in responses

## Testing
- Amount equals grand_total
- Reject client amount
- Cash permission
- Idempotent initiate

## Acceptance Criteria
- [ ] Initiate creates processing payment with server amount
- [ ] Cash confirm marks PAID when authorized
- [ ] Digital next_action returned from adapter (even null/sandbox)

## Definition of Done
- Payment states ready; webhook (TASK-011) and issuance (TASK-012) can attach
