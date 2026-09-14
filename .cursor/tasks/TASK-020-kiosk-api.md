# TASK-020 — Kiosk API

| Field | Value |
|---|---|
| **Task ID** | TASK-020 |
| **Title** | Kiosk API surface |
| **Priority** | 5 API |

## Objective
Stabilize and complete `/api/v1` endpoints required by the Flutter kiosk against the API contract, with device authz and money rejection.

## Background
Flutter consumes only Laravel API. Freeze Resource shapes for kiosk client (`docs/08`, Phase 12).

## Dependencies
- TASK-007–014, TASK-019

## Affected Files
- `backend/routes/api.php`
- `backend/app/Http/Controllers/Api/V1/*`
- `backend/app/Http/Resources/Api/V1/*`
- `backend/app/Http/Middleware/*`
- contract smoke tests

## Database Changes
- None expected

## Backend Requirements
- Ensure all kiosk-critical routes wired: config, catalog, quote, orders, payments, tickets, heartbeat
- Consistent success/error envelopes
- Rate limits
- Device scoping IDOR-safe

## API Requirements
- Match `docs/08-API-CONTRACT.md` for kiosk paths
- Idempotency on critical POSTs
- Reject client money fields everywhere applicable

## Dashboard Requirements
- None

## Kiosk Requirements
- API sufficient for checkout feature task

## Validation
- Form Requests complete

## Authorization
- Device abilities only; no refund/admin

## Business Rules
- Same Actions as dashboard channel

## Edge Cases
- Maintenance responses; expired orders; payment pending poll

## Security
- Least privilege tokens; rate limits; no secrets

## Testing
- Contract smoke: quote→order→pay(sandbox)→tickets
- Device cannot refund
- Money field rejection suite

## Acceptance Criteria
- [ ] Contract smoke green with device token
- [ ] Error envelope consistent
- [ ] Resource JSON stable enough for Flutter

## Definition of Done
- Ready for Flutter foundation/checkout tasks
