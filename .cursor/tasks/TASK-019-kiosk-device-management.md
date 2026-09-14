# TASK-019 — Kiosk Device Management

| Field | Value |
|---|---|
| **Task ID** | TASK-019 |
| **Title** | Kiosk device management |
| **Priority** | 11 Integration |

## Objective
Implement device register/activate/heartbeat/maintenance/deactivate and fleet health for physical kiosks.

## Background
KIOSK RULE: device identity, activation, heartbeat, remote disable (`docs/08`, Phase 17).

## Dependencies
- TASK-003, TASK-004
- TASK-005 destination binding

## Affected Files
- `backend/app/Models/Device.php`
- `backend/app/Actions/Devices/*`
- `backend/app/Http/Controllers/Api/V1/Kiosk/*`
- `backend/app/Livewire/Kiosks/*`
- Sanctum token abilities
- tests

## Database Changes
- `devices`, `device_heartbeats`

## Backend Requirements
- Register → REGISTERED
- Activate issues one-time code; exchange → Sanctum device token + ACTIVE
- Heartbeat updates last_heartbeat_at + versions
- Maintenance/disable blocks commerce
- Deactivate revokes tokens
- Offline derived from stale heartbeat (job may finalize in TASK-026)

## API Requirements
- Per docs/08 kiosk sections
- Device abilities least privilege

## Dashboard Requirements
- Fleet list, activate modal, maintenance toggle, deactivate confirm

## Kiosk Requirements
- Activation exchange + heartbeat client consumed in Flutter tasks

## Validation
- device_id unique; destination required

## Authorization
- kiosks.* permissions per RBAC

## Business Rules
- Unregistered/disabled/maintenance cannot CreateOrder

## Edge Cases
- Invalid activation code; replayed code; heartbeat from disabled device

## Security
- Hash activation secrets; never audit raw secrets; revoke on deactivate

## Testing
- Activation happy path
- Disabled rejects commerce
- Maintenance blocks orders
- Ability isolation

## Acceptance Criteria
- [ ] Device can activate and heartbeat
- [ ] Maintenance/disable enforced on order create
- [ ] Fleet UI shows status/heartbeat

## Definition of Done
- Device trust model ready for TASK-020/021
