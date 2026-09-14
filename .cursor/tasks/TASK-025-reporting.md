# TASK-025 — Reporting

| Field | Value |
|---|---|
| **Task ID** | TASK-025 |
| **Title** | Reporting |
| **Priority** | 12 Reporting |

## Objective
Deliver MVP operational/finance reports from authoritative MySQL with RBAC.

## Background
Reports never use client caches as authority (`docs/02` metrics, Phase 18).

## Dependencies
- TASK-009–015 data paths
- TASK-018 shell
- TASK-004 permissions

## Affected Files
- `backend/app/Services/Reporting/*`
- `backend/app/Livewire/Reports/*`
- optional report API controllers
- tests with fixtures

## Database Changes
- None (indexes already from TASK-002; add only if proven)

## Backend Requirements
- Daily sales by channel
- Payments by status
- Tickets issued vs used
- Refunds/cancels list
- Optional CSV export with `reports.export`

## API Requirements
- Optional `GET /api/v1/reports/...` per contract

## Dashboard Requirements
- Filterable report pages for manager/finance/admin/auditor

## Kiosk Requirements
- None

## Validation
- Date range validation

## Authorization
- `reports.view` / `reports.export`

## Business Rules
- Numbers from DB status fields only

## Edge Cases
- Empty filters; timezone display vs UTC storage

## Security
- Export audited if sensitive; no PII over-share

## Testing
- Fixture assertions for known totals
- Permission denials

## Acceptance Criteria
- [ ] EOD sales by channel available
- [ ] Unauthorized roles denied
- [ ] Export permission gated

## Definition of Done
- Finance/manager can reconcile a day without raw SQL
