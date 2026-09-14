# TASK-026 — Scheduled Jobs

| Field | Value |
|---|---|
| **Task ID** | TASK-026 |
| **Title** | Scheduled expire & reconcile jobs |
| **Priority** | 4 Business Logic |

## Objective
Implement scheduler jobs for unpaid expiry, ticket expiry, payment reconcile, and offline device detection.

## Background
TTL and reconciliation protect ledger integrity (`docs/04`, `docs/05`, Phase 8/17).

## Dependencies
- TASK-009–011, TASK-012, TASK-019

## Affected Files
- `backend/app/Jobs/*` or `app/Console/Commands/*`
- `backend/routes/console.php` schedule
- tests

## Database Changes
- Status timestamp fields updates only

## Backend Requirements
- Expire unpaid orders/payments past expires_at
- Expire unused tickets past valid_end_at
- Reconcile open digital payments (calls provider adapter)
- Mark/detect offline kiosks from heartbeat SLA
- Jobs idempotent and safe to re-run

## API Requirements
- None

## Dashboard Requirements
- Offline badges rely on job/heartbeat fields

## Kiosk Requirements
- Benefits from reconcile after reconnect

## Validation
- N/A

## Authorization
- System actor; audit where state changes

## Business Rules
- Expired unpaid never issues tickets
- Expired tickets DENY at gate

## Edge Cases
- Clock skew; already terminal states; provider down during reconcile

## Security
- Jobs run only on trusted workers; no public trigger without auth

## Testing
- Freeze time tests for expiry
- Reconcile idempotency
- Offline detection threshold

## Acceptance Criteria
- [ ] Unpaid past TTL → EXPIRED
- [ ] Tickets past validity → EXPIRED when job runs / on validate
- [ ] Schedule registered in console

## Definition of Done
- Documented cron `schedule:run` requirement for deploy
