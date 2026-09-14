# TASK-012 — Ticketing (Issuance)

| Field | Value |
|---|---|
| **Task ID** | TASK-012 |
| **Title** | Ticket issuance |
| **Priority** | 4 Business Logic |

## Objective
Issue unique tickets with QR material only after authoritative payment PAID, idempotently.

## Background
No ISSUED without PAID. Channel copied from order. Snapshots retained (`docs/05`–`06`).

## Dependencies
- TASK-010 (PAID path)
- TASK-006 ticket types

## Affected Files
- `backend/app/Actions/Tickets/IssueTickets.php`
- `backend/app/Services/Tickets/TicketStateService.php`
- `backend/app/Services/Qr/QrPayloadGenerator.php`
- `backend/app/Models/Ticket.php`
- `backend/app/Enums/TicketStatus.php`
- `backend/app/Http/Resources/Api/V1/TicketResource.php`
- tests

## Database Changes
- Use `tickets` including qr columns

## Backend Requirements
- Issue N tickets from order items after PAID
- Unique ticket_code + qr_payload_hash
- Status ISSUED/ACTIVE per validity start
- Idempotent: re-run returns existing tickets for order
- Print-payload builder method/DTO
- No public manual mint endpoint

## API Requirements
- Tickets appear on paid order responses / `GET /orders/{id}/tickets`
- `GET /tickets/{code}` scoped
- `GET /tickets/{code}/print-payload` authz

## Dashboard Requirements
- Lookup/print consume later UI

## Kiosk Requirements
- Receives tickets after PAID for display/print

## Validation
- Internal only; payment must be PAID

## Authorization
- Read scoped; print permissions for print-payload

## Business Rules
- Single-entry tickets MVP
- Reprint does not create second ticket (print logs optional)

## Edge Cases
- Partial failure mid-issue → transaction rollback
- Clock validity boundaries

## Security
- QR authenticity strategy server-side; limit payload exposure

## Testing
- Issue only when PAID
- Idempotent issuance
- Unique constraint enforcement
- Count matches quantities

## Acceptance Criteria
- [ ] PAID order yields correct ticket count
- [ ] Retry does not duplicate
- [ ] Unpaid order cannot issue

## Definition of Done
- Issuance hooked from MarkPaymentPaid / ConfirmCashPayment
- Ready for QR validate (TASK-013)
