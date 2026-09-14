# TASK-030 — Deployment & MVP Readiness

| Field | Value |
|---|---|
| **Task ID** | TASK-030 |
| **Title** | Deployment & MVP readiness |
| **Priority** | 13 Production readiness |

## Objective
Prepare VPS staging/production deploy, backups, workers/scheduler, kiosk release notes, and MVP pilot checklist sign-off.

## Background
Roadmap Phases 24–27. One destination pilot. No Staff Flutter. Shared backend authority proven.

## Dependencies
- TASK-017–029 (especially 028–029)
- Hardware decisions for pilot site as available

## Affected Files
- `docs/runbooks/*` (create as needed)
- `backend/.env.example` production keys list
- deploy scripts/notes (non-secret)
- `README.md` ops section
- release checklist

## Database Changes
- Production migrate forward-only; never rewrite old migrations

## Backend Requirements
- Queue worker + `schedule:run` cron
- TLS
- Backup job for MySQL
- Health endpoint
- Maintenance mode procedure

## API Requirements
- Staging base URL for kiosk flavors

## Dashboard Requirements
- Accessible on staging/prod with seeded roles

## Kiosk Requirements
- APK/build flavor pointing to staging then prod
- Activation procedure documented

## Validation
- Post-deploy smoke: assisted sale, kiosk sandbox pay, check-in

## Authorization
- Real users seeded least privilege

## Business Rules
- Pilot scope freeze per out-of-scope MVP list

## Edge Cases
- Rollback plan; payment unknown runbook; printer degraded mode

## Security
- Secrets only in server env; rotate activation materials

## Testing
- Staging regression pack from TASK-029
- Backup restore drill once

## Acceptance Criteria
- [x] Staging supports full assisted + kiosk sandbox path
- [x] Worker + scheduler running
- [x] Backups configured
- [x] Runbooks exist for payment unknown, reprint, device disable, deploy/rollback
- [ ] MVP pilot checklist signed (product/finance/ops) — form in `docs/runbooks/mvp-pilot-checklist.md`; humans must sign before go-live

## Definition of Done
- [x] WanderDesa MVP is releasable to one-destination pilot without inventing new architecture
