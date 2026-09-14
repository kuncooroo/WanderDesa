# MVP pilot checklist (one destination)

**Product:** WanderDesa  
**Scope freeze:** PRD §22 — no Staff Flutter, no offline authoritative selling, no AI ticket mutation, no multi-provider mesh, no multi-entry tickets.  
**Authority:** Laravel. Kiosk and dashboard are clients.

This form is the Phase 27 sign-off. **Do not pre-sign in git.** Named humans date the rows below when the evidence is true.

## A. Engineering evidence (already in repo)

| Gate | Evidence |
|---|---|
| Critical path tests | `cd backend && php artisan test` and `composer test:critical` |
| Security | `docs/12-SECURITY-CHECKLIST.md` |
| Health | `GET /up`; stays up during `php artisan down` |
| Worker + scheduler | cron `schedule:run` + `queue:work` ([deploy-rollback.md](deploy-rollback.md)) |
| Backups | [backups.md](backups.md) restore drill dated below |
| Runbooks | [README.md](README.md) |

Staging SHA promoted: _________________  
Production tag/SHA: _________________

## B. Staging UAT (PRD AC-01…AC-14)

| ID | Criterion | Pass? |
|---|---|---|
| AC-01 | Kiosk purchase → issued ticket, server totals | ☐ |
| AC-02 | Assisted purchase, same pricing engine | ☐ |
| AC-03 | Digital PAID only via server verification/webhook | ☐ |
| AC-04 | Cash assisted payment audited with staff id | ☐ |
| AC-05 | QR ALLOW once, then DENY already used | ☐ |
| AC-06 | Invalid/expired/cancelled/refunded DENY codes | ☐ |
| AC-07 | Idempotent retries do not duplicate | ☐ |
| AC-08 | Disabled/maintenance kiosk cannot sell | ☐ |
| AC-09 | Manager daily sales by channel | ☐ |
| AC-10 | Finance payments by status for a day | ☐ |
| AC-11 | Auditor sees refund/check-in audit | ☐ |
| AC-12 | No Staff Flutter app in this repo | ☐ |
| AC-13 | API rejects client money authority fields | ☐ |
| AC-14 | Check-in works without gate; gate only after ALLOW | ☐ |

## C. Operations

| Item | Pass? |
|---|---|
| TLS on dashboard + kiosk API | ☐ |
| `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` | ☐ |
| Worker + scheduler confirmed on the VPS | ☐ |
| MySQL backup off-box + restore drill date: __________ | ☐ |
| Least-privilege users (not Super Admin at the counter) | ☐ |
| Catalog/prices entered from the destination’s real list (not invented) | ☐ |
| Kiosk production APK HTTPS + real signing keystore | ☐ |
| Activation codes issued once and stored hashed | ☐ |
| Ticket Officer / Gate Officer / Operator trained | ☐ |
| Payment unknown + reprint + disable runbooks printed at the desk | ☐ |

## D. Sign-off

| Role | Name | Date | Signature |
|---|---|---|---|
| Product | | | |
| Finance | | | |
| Operations | | | |

Pilot is **not** releasable until all three rows are signed and section B is complete. Hardware (tablet, printer, scanner, payment medium) remains a site decision; missing hardware uses degraded QR + cash assisted paths already in the product.
