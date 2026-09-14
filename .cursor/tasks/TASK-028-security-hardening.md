# TASK-028 — Security Hardening

| Field | Value |
|---|---|
| **Task ID** | TASK-028 |
| **Title** | Security hardening |
| **Priority** | 13 Production readiness |

## Objective
Perform a focused security pass: IDOR, webhook auth, rate limits, secrets, privilege escalation, headers, and device least privilege.

## Background
SECURITY section of `CURSOR.md` and Phase 21 must pass before pilot.

## Dependencies
- TASK-003–020 (features exist to harden)

## Affected Files
- middleware rate limiters
- policies gaps
- webhook verifiers
- `.env.example` secret inventory
- security tests
- optional security checklist doc under `docs/`

## Database Changes
- None expected

## Backend Requirements
- Close IDOR on orders/tickets/payments
- Confirm webhook signature enforcement
- Confirm device cannot hit refund/admin
- Confirm roles.assign only super_admin
- Production session/cookie secure flags documented
- Upload validation if any upload exists

## API Requirements
- 401/403 correct; rate limit 429

## Dashboard Requirements
- 403 pages; no privileged Livewire mutators without authorize

## Kiosk Requirements
- Token storage remains secure; no secrets in logs

## Validation
- Server-side always

## Authorization
- Re-verify matrix critical cells

## Business Rules
- No bypass of money/ticket invariants

## Edge Cases
- Token reuse after deactivate; horizontal access between destinations if scoped later

## Security
- This task is the security gate

## Testing
- Automated IDOR attempts
- Invalid webhook
- Escalation attempts
- Device privilege tests

## Acceptance Criteria
- [ ] Critical IDOR tests pass
- [ ] Webhook rejects bad signatures
- [ ] Device cannot refund/admin
- [ ] No secrets in repo
- [ ] Rate limits active on auth/payment/validate

## Definition of Done
- Security checklist signed for MVP pilot readiness
