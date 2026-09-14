# TASK-024 — Scanner Integration

| Field | Value |
|---|---|
| **Task ID** | TASK-024 |
| **Title** | Scanner integration |
| **Priority** | 10 Hardware |

## Objective
Integrate QR scanner input into dashboard gate validate/check-in without local ALLOW decisions.

## Background
Scanner is an input peripheral; Laravel decides (`docs/09`, Phase 15). Not on public purchase kiosk by default.

## Dependencies
- TASK-013, TASK-014, TASK-017

## Affected Files
- `backend/app/Livewire/Gate/CheckInPanel.php` (scanner autofocus/HID)
- docs/runbook note optional
- tests with payload fixtures

## Database Changes
- None

## Backend Requirements
- Existing validate/check-in Actions only

## API Requirements
- Existing endpoints

## Dashboard Requirements
- Autofocus scan field; keyboard-wedge support
- Manual code fallback
- Scanner failure messaging
- Large ALLOW/DENY

## Kiosk Requirements
- None (unless future combo mode — out of scope)

## Validation
- Server validation of payload

## Authorization
- Gate officer permissions

## Business Rules
- No offline authoritative entry

## Edge Cases
- Rapid double scan; gibberish input; scanner disconnect

## Security
- No client-side gate open

## Testing
- Feature tests simulate scanned payload
- UAT with hardware when available

## Acceptance Criteria
- [ ] Scan submits to server check-in/validate
- [ ] Manual entry works when scanner fails
- [ ] Double scan yields single USE

## Definition of Done
- Gate desk operable with scanner or manual entry
