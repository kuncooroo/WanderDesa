# TASK-014 — Check-in

| Field | Value |
|---|---|
| **Task ID** | TASK-014 |
| **Title** | Check-in |
| **Priority** | 4 Business Logic |

## Objective
Transactional check-in that consumes an ACTIVE ticket exactly once and records check-in.

## Background
CHECK-IN RULE: Laravel only; prevent duplicate check-in; gate open only after success (`CURSOR.md`).

## Dependencies
- TASK-013
- TASK-004 (`checkins.create`)

## Affected Files
- `backend/app/Actions/CheckIns/CheckInTicket.php`
- `backend/app/Models/CheckIn.php`
- `backend/app/Http/Controllers/Api/V1/CheckInController.php`
- optional Gate adapter stub
- tests including concurrency

## Database Changes
- Use `check_ins` with UNIQUE ticket_id

## Backend Requirements
- Begin tx → lock ticket → re-validate → insert check_in → ACTIVE→USED → commit
- Idempotency-Key required
- Optional GateOpen stub after commit
- No reverse endpoint

## API Requirements
- `POST /api/v1/check-ins`
- Response ALLOW/DENY with reason_code

## Dashboard Requirements
- Gate Livewire in TASK-017/018 uses Action

## Kiosk Requirements
- None (purchase kiosk)

## Validation
- qr_payload, destination_id; gate_id optional

## Authorization
- `checkins.create`

## Business Rules
- Exactly one success under concurrency
- DENY_ALREADY_USED on second

## Edge Cases
- Parallel double scan; validate race; unauthorized actor

## Security
- No local client ALLOW; audit checked_in

## Testing
- Parallel double check-in → one ALLOW
- Second sequential DENY_ALREADY_USED
- Permission deny

## Acceptance Criteria
- [ ] First check-in succeeds and sets USED
- [ ] Second fails with DENY_ALREADY_USED
- [ ] Unique DB constraint backs the rule

## Definition of Done
- Check-in production-ready for gate desk UI
