# TASK-016 — Audit Logging

| Field | Value |
|---|---|
| **Task ID** | TASK-016 |
| **Title** | Audit logging |
| **Priority** | 4 Business Logic |

## Objective
Provide append-only audit logging for critical business and security events.

## Background
Auditors need evidence; audit ≠ technical logs (`docs/05`, `docs/06`, `docs/07`).

## Dependencies
- TASK-003, TASK-004
- Integrate into domain Actions as they land (backfill hooks)

## Affected Files
- `backend/app/Support/Audit/AuditWriter.php`
- `backend/app/Models/AuditLog.php`
- `backend/app/Livewire/Audit/AuditLogIndex.php` (or later with shell)
- tests

## Database Changes
- Use `audit_logs` (no update/delete UI)

## Backend Requirements
- Writer API: actor_type/id, action, entity, before/after/meta, ip, ua
- Call from critical Actions (order/payment/ticket/check-in/refund/device/rbac/price)
- Prefer fail-closed or alert on audit write failure for money actions (document choice)

## API Requirements
- Optional read API with `audit_logs.view`; dashboard may be enough

## Dashboard Requirements
- Read-only list/filter for auditor/admin

## Kiosk Requirements
- Device actions audited as actor_type=device

## Validation
- N/A

## Authorization
- `audit_logs.view` read-only

## Business Rules
- Append-only; no staff edit/delete

## Edge Cases
- System actor for jobs; redact secrets in meta

## Security
- Never store raw passwords/activation secrets in audit

## Testing
- Creating order writes audit
- Refund writes audit
- Viewer cannot PATCH audit rows (no route)

## Acceptance Criteria
- [ ] Critical money/ticket events produce audit rows
- [ ] Auditor can view; cannot mutate
- [ ] Secrets redacted

## Definition of Done
- AuditWriter used by existing Actions; checklist of required events covered or tracked
