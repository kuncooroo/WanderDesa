# TASK-029 — Critical Path Test Suite

| Field | Value |
|---|---|
| **Task ID** | TASK-029 |
| **Title** | Critical path test suite |
| **Priority** | 7 Tests |

## Objective
Consolidate automated tests proving money/ticket integrity, idempotency, check-in uniqueness, pricing consistency, and RBAC denies.

## Background
Phase 22 / CURSOR testing rules. Prefer integrity over vanity coverage %.

## Dependencies
- TASK-004, TASK-009–015 primarily
- Flutter unit tests from 021–022 as available

## Affected Files
- `backend/tests/Feature/**`
- `backend/tests/Unit/**`
- `kiosk/test/**`
- CI config optional
- README test instructions

## Database Changes
- None

## Backend Requirements
- Suite includes: quote money rejection; order idempotency; payment amount authority; webhook duplicate; issue idempotency; double check-in; refund eligibility; RBAC matrix samples; channel pricing parity entrypoints

## API Requirements
- Contract smoke pack

## Dashboard Requirements
- Assisted sale / check-in feature tests if not already

## Kiosk Requirements
- Retry/offline state unit tests

## Validation
- N/A

## Authorization
- Covered by deny tests

## Business Rules
- Assert docs invariants

## Edge Cases
- Stabilize concurrency tests

## Security
- Include IDOR samples from TASK-028

## Testing
- This task is testing

## Acceptance Criteria
- [x] `php artisan test` (or project runner) green on fresh migrate
- [x] Critical paths listed above covered
- [x] How-to-run documented in README

## Definition of Done
- [x] Regression pack usable before every release candidate
