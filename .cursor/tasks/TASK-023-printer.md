# TASK-023 — Printer Integration

| Field | Value |
|---|---|
| **Task ID** | TASK-023 |
| **Title** | Printer integration |
| **Priority** | 10 Hardware |

## Objective
Print authoritative ticket print-payloads on kiosk and support audited dashboard reprint, with safe failure UX.

## Background
Print failure must not void PAID/ISSUED. Printer type TBD — adapter pattern (`docs/05`, Phase 14).

## Dependencies
- TASK-012
- TASK-017 (dashboard reprint)
- TASK-022 (kiosk print trigger)

## Affected Files
- `kiosk/lib/printer/**`
- `backend/app/Actions/Tickets/BuildPrintPayload.php` (if not done)
- optional `ticket_print_logs`
- dashboard reprint Livewire action
- tests/mocks

## Database Changes
- Optional print log table usage

## Backend Requirements
- print-payload endpoint stable
- reprint permission + audit
- no second ticket on reprint

## API Requirements
- `GET /tickets/{code}/print-payload`
- optional print-ack

## Dashboard Requirements
- Reprint button for permitted roles

## Kiosk Requirements
- After ISSUED, attempt print
- On failure: keep success, show QR, guide to loket
- Printer error screen

## Validation
- N/A hardware

## Authorization
- `tickets.print` / `tickets.reprint` / device own print

## Business Rules
- Print ≠ payment state
- Audited reprint

## Edge Cases
- Partial multi-ticket print failure; offline printer

## Security
- Do not expose unrelated tickets to device

## Testing
- Mock printer adapter success/fail
- UAT on real device when hardware available

## Acceptance Criteria
- [ ] Successful print path works with mock or real printer
- [ ] Failure preserves tickets and shows QR
- [ ] Reprint does not mint new ticket

## Definition of Done
- Degraded mode acceptable if vendor delayed; adapter interface stable
