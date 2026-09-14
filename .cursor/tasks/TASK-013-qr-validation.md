# TASK-013 — QR Validation

| Field | Value |
|---|---|
| **Task ID** | TASK-013 |
| **Title** | QR validation |
| **Priority** | 4 Business Logic |

## Objective
Implement server-side ticket QR validation returning ALLOW candidate or DENY with stable reason codes — without consuming the ticket.

## Background
Validation checks authenticity and business rules (`docs/05`, `docs/08`). Check-in consumption is TASK-014.

## Dependencies
- TASK-012

## Affected Files
- `backend/app/Actions/Tickets/ValidateTicket.php`
- `backend/app/Http/Controllers/Api/V1/TicketValidationController.php`
- `backend/app/Http/Requests/Api/V1/ValidateTicketRequest.php`
- tests

## Database Changes
- None required (optional validation attempt log later)

## Backend Requirements
- Verify payload authenticity + ticket exists
- Checks: destination, payment valid, status ACTIVE, not expired/cancelled/refunded/used, actor authorized
- HTTP 200 with result ALLOW|DENY + reason_code (per API contract choice)

## API Requirements
- `POST /api/v1/tickets/validate`
- Rate limited

## Dashboard Requirements
- Gate UI will call this / check-in

## Kiosk Requirements
- Not on public purchase kiosk by default

## Validation
- qr_payload, destination_id required; gate_id optional

## Authorization
- `tickets.validate` or `checkins.create`

## Business Rules
- Validate-only does not set USED
- Reason codes match business flow list

## Edge Cases
- Forged payload; wrong destination; expired at boundary

## Security
- Rate limit brute force; no leakage of signing secrets

## Testing
- Each major DENY code
- ALLOW for valid ACTIVE ticket
- Authz denial

## Acceptance Criteria
- [ ] Valid ticket → ALLOW without status change
- [ ] Used/cancelled/refunded/expired → correct DENY
- [ ] Forged → DENY_INVALID_AUTH or NOT_FOUND

## Definition of Done
- Validate Action reusable by CheckIn (TASK-014)
