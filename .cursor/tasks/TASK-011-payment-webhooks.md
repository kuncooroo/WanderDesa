# TASK-011 — Payment Webhooks & Reconciliation

| Field | Value |
|---|---|
| **Task ID** | TASK-011 |
| **Title** | Payment webhooks & reconciliation |
| **Priority** | 11 Integration |

## Objective
Secure, idempotent payment webhook processing and reconciliation for stuck PENDING/PROCESSING payments.

## Background
PAYMENT RULE: server-side verification; duplicate callbacks must not double-issue tickets (`docs/08`, Phase 8).

## Dependencies
- TASK-010
- TASK-012 ideally in same PR train for PAID→Issue (webhook calls MarkPaymentPaid which issues)

## Affected Files
- `backend/app/Http/Controllers/Api/V1/Webhooks/PaymentWebhookController.php`
- `backend/app/Actions/Payments/MarkPaymentPaid.php`
- `backend/app/Jobs/ProcessPaymentWebhook.php`
- `backend/app/Jobs/ReconcileOpenPayments.php`
- `backend/app/Models/PaymentWebhookEvent.php`
- `backend/app/Integrations/Payments/*`
- tests

## Database Changes
- Use `payment_webhook_events`

## Backend Requirements
- Verify provider signature/secret before mutation
- Unique (provider, event_id) idempotency
- On paid → MarkPaymentPaid → IssueTickets
- Reconcile job queries provider for open payments
- Never trust unsigned client “paid” callbacks as user routes

## API Requirements
- `POST /api/v1/webhooks/payments/{provider}`
- Fast 200 after accept; processing idempotent

## Dashboard Requirements
- None required (finance sees statuses via payments UI later)

## Kiosk Requirements
- Polls payment status while webhook settles

## Validation
- Signature headers per provider TBD; adapter validates

## Authorization
- Signature auth only (not user Bearer)

## Business Rules
- Duplicate event = no duplicate tickets
- FAILED/EXPIRED aligned via reconcile when provider says so

## Edge Cases
- Out-of-order events; replay; unknown payment id; provider downtime

## Security
- Reject invalid signatures (401)
- Do not log raw secrets; redact payloads in logs

## Testing
- Invalid signature
- Duplicate event_id
- Paid webhook issues tickets once
- Reconcile marks paid when provider paid

## Acceptance Criteria
- [ ] Valid paid webhook → PAID + tickets once
- [ ] Duplicate webhook safe
- [ ] Invalid signature rejected
- [ ] Reconcile job safe to re-run

## Definition of Done
- Digital payment finality path production-shaped (provider adapter fillable)
