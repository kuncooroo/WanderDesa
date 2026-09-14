# TASK-017 — Assisted-Service

| Field | Value |
|---|---|
| **Task ID** | TASK-017 |
| **Title** | Assisted-service sale & gate ops UI |
| **Priority** | 8 Dashboard |

## Objective
Build Livewire assisted-service sale flow and gate validate/check-in UI that call shared Laravel Actions only.

## Background
Dashboard is POS + ops, not CRUD-only. Same domain as kiosk (`docs/09`, Phase 11).

## Dependencies
- TASK-009–014, TASK-016
- TASK-018 may provide shell — if shell missing, embed in temporary layout

## Affected Files
- `backend/app/Livewire/AssistedSale/*`
- `backend/app/Livewire/Gate/CheckInPanel.php`
- `backend/resources/views/livewire/...`
- tests (Livewire/feature)

## Database Changes
- None

## Backend Requirements
- Livewire calls CreateOrder, InitiatePayment/ConfirmCash, Issue (via pay), print payload fetch
- Check-in panel calls Validate/CheckIn Actions
- No pricing math in Livewire beyond displaying Action results

## API Requirements
- Uses Actions directly (preferred) rather than HTTP loopback

## Dashboard Requirements
- Assisted sale wizard per UX doc
- Cash confirm modal
- Success + print/reprint entry points
- Gate ALLOW/DENY large result UX
- Role-gated pages (`orders.create`, `checkins.create`)

## Kiosk Requirements
- None

## Validation
- Server Form Requests / Action validation still authoritative

## Authorization
- Server-side authorize on every Livewire mutator

## Business Rules
- Channel=assisted
- Same totals as API for identical inputs

## Edge Cases
- Payment fail retry; print fail after paid; deny already used

## Security
- 403 on unauthorized Livewire calls; CSRF

## Testing
- Ticket officer completes cash sale feature test
- Gate officer check-in once
- Ticket officer cannot open refund

## Acceptance Criteria
- [ ] Assisted cash sale → PAID → tickets
- [ ] Gate check-in ALLOW then DENY_ALREADY_USED
- [ ] No business totals invented in Livewire

## Definition of Done
- Staff channel operational for MVP sales and entry
