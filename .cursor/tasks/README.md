# WanderDesa — Cursor Task Index

**Location:** `.cursor/tasks/`  
**Authority:** `CURSOR.md` + `docs/01`–`11`  
**Order:** Execute by Task ID unless a dependency says otherwise.  
**Rule:** One task = one focused feature. Backend authority before clients.

## Priority → Tasks

| Priority | Tasks |
|---|---|
| 1 Foundation | TASK-001 |
| 2 Database | TASK-002 |
| 3 Backend | TASK-003 |
| 4 Business Logic | TASK-005 … TASK-016, TASK-026 |
| 5 API | TASK-020 |
| 6 Authorization | TASK-004 |
| 7 Tests | (each task) + TASK-029 |
| 8 Dashboard | TASK-017, TASK-018 |
| 9 Kiosk | TASK-021, TASK-022 |
| 10 Hardware | TASK-023, TASK-024 |
| 11 Integration | TASK-011, TASK-019 |
| 12 Reporting | TASK-025 |
| 13 Production readiness | TASK-027, TASK-028, TASK-030 |

## Task list

| ID | File | Title | Depends on |
|---|---|---|---|
| 001 | `TASK-001-project-foundation.md` | Project foundation | — |
| 002 | `TASK-002-database-foundation.md` | Database foundation | 001 |
| 003 | `TASK-003-authentication.md` | Authentication | 002 |
| 004 | `TASK-004-rbac.md` | RBAC | 003 |
| 005 | `TASK-005-tourism-master-data.md` | Destinations master data | 004 |
| 006 | `TASK-006-ticket-types.md` | Ticket types | 005 |
| 007 | `TASK-007-pricing.md` | Pricing & quote | 006 |
| 008 | `TASK-008-idempotency.md` | Idempotency infrastructure | 002, 003 |
| 009 | `TASK-009-orders.md` | Orders | 007, 008 |
| 010 | `TASK-010-payments.md` | Payments (initiate/cash/states) | 009 |
| 011 | `TASK-011-payment-webhooks.md` | Payment webhooks & reconcile | 010 |
| 012 | `TASK-012-ticketing.md` | Ticket issuance | 010 |
| 013 | `TASK-013-qr-validation.md` | QR validation | 012 |
| 014 | `TASK-014-check-in.md` | Check-in | 013 |
| 015 | `TASK-015-refunds.md` | Refunds | 010, 012, 014 |
| 016 | `TASK-016-audit-logging.md` | Audit logging | 003, 004 |
| 017 | `TASK-017-assisted-service.md` | Assisted-service sale flow | 009–014, 016 |
| 018 | `TASK-018-dashboard-shell.md` | Dashboard shell & role nav | 004, 016 |
| 019 | `TASK-019-kiosk-device-management.md` | Kiosk device management | 003, 004 |
| 020 | `TASK-020-kiosk-api.md` | Kiosk API surface | 007–014, 019 |
| 021 | `TASK-021-flutter-kiosk-foundation.md` | Flutter kiosk foundation | 020 |
| 022 | `TASK-022-flutter-kiosk-checkout.md` | Flutter kiosk checkout | 020, 021 |
| 023 | `TASK-023-printer.md` | Printer integration | 012, 017, 022 |
| 024 | `TASK-024-scanner.md` | Scanner integration | 013, 014, 017 |
| 025 | `TASK-025-reporting.md` | Reporting | 009–015 |
| 026 | `TASK-026-scheduled-jobs.md` | Expire & reconcile jobs | 009–011, 019 |
| 027 | `TASK-027-ops-notifications.md` | Ops notifications | 019, 011, 026 |
| 028 | `TASK-028-security-hardening.md` | Security hardening | 003–020 |
| 029 | `TASK-029-test-suite.md` | Critical path test suite | 009–015, 004 |
| 030 | `TASK-030-deployment-mvp.md` | Deployment & MVP readiness | 017–029 |

## Execution notes for agents

1. Read `CURSOR.md` before coding any task.  
2. If code would conflict with docs — **STOP** and report conflict.  
3. Do not create Staff Flutter app.  
4. Do not implement critical logic in Flutter/Blade/Livewire/JS.  
5. Prefer shared Actions for kiosk + dashboard.  
6. Mark task progress only when Acceptance Criteria and Definition of Done are met.
