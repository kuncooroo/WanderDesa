# TASK-027 — Ops Notifications

| Field | Value |
|---|---|
| **Task ID** | TASK-027 |
| **Title** | Operational notifications |
| **Priority** | 13 Production readiness |

## Objective
Notify operators/admins of kiosk offline and critical payment webhook failures (no visitor marketing).

## Background
MVP notifications are operational (`docs/03`, Phase 20).

## Dependencies
- TASK-019, TASK-011, TASK-026
- TASK-018 notification inbox slot

## Affected Files
- `backend/app/Notifications/*`
- listeners/jobs dispatching notifications
- Livewire notifications dropdown
- mail config via env
- tests

## Database Changes
- Laravel `notifications` table

## Backend Requirements
- Offline beyond threshold → notify users with `kiosks.view`/operator role
- Webhook processing failure → admin/super_admin
- Database channel first; email optional

## API Requirements
- `GET /notifications`, mark read optional

## Dashboard Requirements
- Bell/inbox UI

## Kiosk Requirements
- None

## Validation
- N/A

## Authorization
- Only intended roles receive/see

## Business Rules
- No secrets in notification payloads

## Edge Cases
- Alert storms — debounce/threshold
- Mail misconfig fallback to DB only

## Security
- Redact provider payloads

## Testing
- Notification created on offline detection
- Unauthorized user doesn’t see others’ admin alerts if scoped

## Acceptance Criteria
- [ ] Offline kiosk creates ops notification
- [ ] Webhook failure notifies admin path
- [ ] Inbox readable in dashboard

## Definition of Done
- Ops can learn critical issues without watching logs only
