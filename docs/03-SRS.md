# WanderDesa — Software Requirements Specification (SRS)

**Document ID:** `03-SRS`  
**File:** `docs/03-SRS.md`  
**Status:** Technical requirements (pre-implementation)  
**Based on:** `docs/01-PRODUCT-VALIDATION.md`, `docs/02-PRD.md`  
**Code in this phase:** **None**

---

## 0. Document Control

| Field | Value |
|---|---|
| Product | WanderDesa |
| Document type | Software Requirements Specification |
| Version | 1.0.0 |
| Date | 2026-09-13 |
| Audience | Engineering, Architecture, QA, DevOps |
| Authority principle | Laravel is the authoritative business layer and Single Source of Truth |

### Environment inspection summary

Project workspace currently contains documentation only (`docs/`). No `composer.json` / `pubspec.yaml` application scaffold exists yet. Versions below are taken from **local environment inspection** and **Packagist availability**, not invented from an installed app lockfile.

| Component | Inspected value | SRS constraint |
|---|---|---|
| PHP (CLI, Laragon) | **8.4.25** | PHP **8.4.x** |
| MySQL (Laragon) | **8.4.11** | MySQL **8.4.x** |
| Flutter (local) | **3.44.9** stable · Dart **3.12.2** | Kiosk client target |
| Laravel Framework (Packagist) | **13.x available**; latest inspected **v13.31.0** | Laravel **13.x** (`^13.0`) |
| Laravel Sanctum (Packagist) | **4.x available**; latest inspected **v4.3.3** | Planned API/device auth package; exact pin at install |
| Livewire (Packagist) | **4.x available**; latest inspected **v4.4.4** | Planned dashboard UI; exact pin must be Laravel 13–compatible at install |
| Node / npm (local tooling) | **v24.20.0** / **11.19.0** | Frontend asset tooling when scaffolded |
| Installed project Laravel | **Not installed** | Exact patch confirmed after `composer.lock` exists |
| Payment / Printer / Scanner / Gate / CCTV | **TBD** | Integration adapters; vendor freeze before deep coding |

### Locked technology baseline

| Layer | Technology |
|---|---|
| Backend | Laravel **13.x** |
| PHP | **8.4.x** |
| Database | MySQL **8.4.x** |
| API | Laravel REST API |
| Dashboard UI | Laravel Blade + Livewire + Tailwind CSS |
| Kiosk client | Flutter (physical terminal; not consumer mobile app) |
| Authentication | **Laravel session auth (dashboard) + Laravel Sanctum token auth (API / kiosk devices)** |
| Deployment | **VPS** |
| Queue | Laravel Queue (database driver acceptable for MVP; Redis optional later) |
| Cache | Laravel Cache (file/database MVP; Redis optional later) |

---

## 1. System Overview

WanderDesa is an Integrated Tourism System with:

1. **Flutter Kiosk** — physical self-service terminal client  
2. **Laravel Backend/API** — business authority  
3. **Laravel Blade + Livewire Dashboard** — assisted-service + management  
4. **MySQL** — authoritative persistence  
5. External integrations — payment, printer, scanner, optional gate, optional CCTV/AI  

Clients request operations. Laravel validates, decides, calculates, persists, and returns authoritative results. Critical business logic must not be independently implemented in Flutter, Blade, Livewire, or JavaScript.

---

## 2. System Context

```text
[Visitor]──touch──▶[Flutter Kiosk / Advan A10 class]
                        │ HTTPS REST
                        ▼
                 [Laravel 13 API + Domain]
                        │
        ┌───────────────┼────────────────┐
        ▼               ▼                ▼
   [MySQL 8.4]   [Queue/Jobs]     [Payment Provider TBD]
        ▲               │                │
        │               ▼                ▼
[Staff Browser]──▶[Blade+Livewire Dashboard]
        │
        ├── Scanner TBD ──▶ validate/check-in API
        ├── Printer TBD ◀── print ticket payload
        ├── Gate TBD ◀── after check-in success (optional)
        └── CCTV/AI TBD ──▶ analytics ingest only (optional)
```

### Trust boundaries

| Boundary | Trust rule |
|---|---|
| Kiosk → API | Device-authenticated; never trusted for money/ticket finality |
| Dashboard → Backend | Staff-authenticated; permissions enforced server-side |
| Payment provider → API | Webhook signature/authenticity verified |
| Scanner → API | Authorized actor/device context required |
| CCTV/AI → API | May submit detection events; must not mutate ticket authority |

---

## 3. Technical Objectives

| ID | Objective |
|---|---|
| TO-01 | Keep Laravel as sole authoritative business layer |
| TO-02 | Share domain services between kiosk API and dashboard flows |
| TO-03 | Enforce idempotent critical writes (order, payment, ticket, check-in) |
| TO-04 | Support VPS deployment with operable backups and logs |
| TO-05 | Prefer simple Laravel primitives over unnecessary frameworks/microservices |
| TO-06 | Isolate hardware/payment vendors behind adapters |
| TO-07 | Make status transitions explicit, server-side, auditable |
| TO-08 | Enable recovery for PENDING/PROCESSING payments without local fake success |

---

## 4. Functional Requirements

Technical functional requirements map to PRD `FR-*` and must be realized in Laravel domain services.

| ID | Requirement |
|---|---|
| SRS-FR-01 | System provides REST API for kiosk commerce and validation flows |
| SRS-FR-02 | System provides dashboard assisted-sale, lookup, check-in, admin, reports |
| SRS-FR-03 | Catalog, pricing, order, payment, ticket, QR, check-in are server-authoritative |
| SRS-FR-04 | Channel `kiosk` and `assisted` use the same pricing/issuance/check-in services |
| SRS-FR-05 | Device registry supports register/activate/heartbeat/maintenance/disable |
| SRS-FR-06 | Payment webhooks update payment state idempotently |
| SRS-FR-07 | Ticket issuance occurs only after authoritative `PAID` |
| SRS-FR-08 | Check-in is transactional and concurrency-safe for single-entry MVP tickets |
| SRS-FR-09 | Audit events are written for critical mutations |
| SRS-FR-10 | Reports read authoritative DB state |

---

## 5. Non-Functional Requirements

| ID | Category | Requirement |
|---|---|---|
| SRS-NFR-01 | Correctness | No ISSUED ticket without PAID payment linkage |
| SRS-NFR-02 | Security | All mutating endpoints authorize server-side |
| SRS-NFR-03 | Integrity | Idempotent retries do not duplicate money/tickets |
| SRS-NFR-04 | Performance | Quote/order create p95 < 2s under normal VPS/LAN conditions |
| SRS-NFR-05 | Performance | Validate/check-in p95 < 1.5s |
| SRS-NFR-06 | Availability | Target ≥ 99% during destination operating hours (ops-defined) |
| SRS-NFR-07 | Observability | Structured logs include order/payment/ticket/check-in IDs |
| SRS-NFR-08 | Maintainability | Domain logic concentrated in service/action layer, not controllers/Livewire |
| SRS-NFR-09 | Simplicity | No microservices split for MVP |
| SRS-NFR-10 | Locale/time | Authoritative timestamps UTC; display TZ configurable; currency IDR |

---

## 6. Kiosk Requirements (technical)

| ID | Requirement |
|---|---|
| SRS-KIO-01 | Flutter app is a device client only; no independent business authority |
| SRS-KIO-02 | Communicates exclusively with Laravel REST API for critical operations |
| SRS-KIO-03 | Authenticates as registered kiosk device (Section 24) |
| SRS-KIO-04 | Sends heartbeat with software version and basic health signals |
| SRS-KIO-05 | Uses idempotency keys on order create and payment initiate |
| SRS-KIO-06 | May cache UI/session/retry state locally; must not declare PAID/ISSUED/USED locally |
| SRS-KIO-07 | Targets Advan A10 class Android tablet, landscape, locked fullscreen |
| SRS-KIO-08 | Handles payment pending/failure/timeout UX from server states |
| SRS-KIO-09 | Invokes printer integration after server ISSUED when printer available |
| SRS-KIO-10 | Blocks commerce when API reports maintenance/disabled/inactive |

Flutter version used in local env: **3.44.9**. Project `pubspec.yaml` not present yet — final app SDK constraint set at scaffold.

---

## 7. Backend Requirements

| ID | Requirement |
|---|---|
| SRS-BE-01 | Application built on Laravel **13.x** / PHP **8.4.x** |
| SRS-BE-02 | Single Laravel app serves API + dashboard (monolith) |
| SRS-BE-03 | Domain modules for Catalog, Order, Payment, Ticket, CheckIn, Device, AuthZ, Audit, Reporting |
| SRS-BE-04 | Controllers/Livewire components are thin; call shared actions/services |
| SRS-BE-05 | Eloquent models persist authoritative state in MySQL **8.4.x** |
| SRS-BE-06 | Config via `.env`; secrets never committed |
| SRS-BE-07 | Feature flags optional but not required for MVP complexity |
| SRS-BE-08 | Avoid premature package sprawl; prefer first-party Laravel features |

---

## 8. Dashboard Requirements (technical)

| ID | Requirement |
|---|---|
| SRS-DSH-01 | Blade + Livewire UI with Tailwind CSS |
| SRS-DSH-02 | Session-authenticated staff access |
| SRS-DSH-03 | Assisted sale calls same domain services as kiosk API |
| SRS-DSH-04 | Gate validation/check-in UI calls same validation/check-in services |
| SRS-DSH-05 | Server-side authorization on every Livewire/action mutation |
| SRS-DSH-06 | No critical total/status computation solely in Alpine/JS |
| SRS-DSH-07 | Livewire package version pinned to Laravel 13–compatible release at install (Packagist 4.x currently available) |

---

## 9. Authentication

### Selected approach

**`[AUTHENTICATION]` = dual-mode Laravel authentication:**

| Client | Mechanism |
|---|---|
| Dashboard (staff) | Laravel session authentication (login form / guard `web`) |
| Kiosk device API | Laravel Sanctum token (device credential → API token) |
| Staff API (if any later) | Sanctum optional; **not required for MVP** beyond dashboard session |
| Payment webhooks | Provider signature / shared secret verification (not user auth) |

### Requirements

| ID | Requirement |
|---|---|
| SRS-AUTH-01 | Staff users authenticate with email/username + password (hash via Laravel) |
| SRS-AUTH-02 | Kiosk devices authenticate with device credentials issued at activation |
| SRS-AUTH-03 | Sanctum tokens scoped/limited to kiosk- Permitted abilities |
| SRS-AUTH-04 | Dashboard sessions expire per policy (idle timeout configurable) |
| SRS-AUTH-05 | Failed login attempts logged; lockout strategy configurable |
| SRS-AUTH-06 | No visitor personal accounts required for MVP kiosk purchase |

Sanctum exact installed version: confirm in `composer.lock` after scaffold (Packagist latest inspected **v4.3.3**).

---

## 10. Authorization

| ID | Requirement |
|---|---|
| SRS-AZ-01 | Authorization enforced in Laravel policies/gates/permission checks |
| SRS-AZ-02 | UI hiding is never the only control |
| SRS-AZ-03 | API and dashboard share the same permission vocabulary where overlapping |
| SRS-AZ-04 | Device tokens cannot perform admin/refund/user-management actions |
| SRS-AZ-05 | Deny by default for unknown permissions |

---

## 11. RBAC

Implement role-based access control aligned to PRD roles:

`ticket_officer`, `gate_officer`, `operator`, `manager`, `finance`, `admin`, `auditor`, `sysadmin` (+ optional generic `staff`).

| ID | Requirement |
|---|---|
| SRS-RBAC-01 | Users have one or more roles; MVP may start with single primary role per user |
| SRS-RBAC-02 | Permissions are explicit strings (see PRD Section 29) |
| SRS-RBAC-03 | Role/permission assignments manageable by `admin`/`sysadmin` |
| SRS-RBAC-04 | Auditor is read-oriented for audit/reports |
| SRS-RBAC-05 | Prefer a simple roles/permissions schema (or a well-supported package) without over-modeling |

Keep RBAC simple: roles → permissions → checks. Avoid complex ABAC in MVP.

---

## 12. Session Management

| ID | Requirement |
|---|---|
| SRS-SES-01 | Dashboard uses secure HTTP-only session cookies |
| SRS-SES-02 | Session driver: `database` or `file` for MVP on VPS; Redis optional later |
| SRS-SES-03 | CSRF protection enabled for dashboard web routes |
| SRS-SES-04 | Concurrent session policy: configurable; default allow with logout-all for admin security actions |
| SRS-SES-05 | Kiosk does not use staff browser sessions; uses Sanctum device tokens |
| SRS-SES-06 | Token revocation on device disable/deactivation |

---

## 13. API Requirements

| ID | Requirement |
|---|---|
| SRS-API-01 | Versioned REST API under `/api/...` (e.g. `/api/v1`) |
| SRS-API-02 | JSON request/response |
| SRS-API-03 | Consistent error envelope with machine-readable codes |
| SRS-API-04 | Idempotency-Key support on critical POSTs |
| SRS-API-05 | Authentication via Sanctum Bearer token for kiosk |
| SRS-API-06 | Rate limiting on auth, order, payment, validation endpoints |
| SRS-API-07 | No endpoint accepts client authoritative `price`/`total`/`payment_status`/`ticket_status` |
| SRS-API-08 | Validation and check-in endpoints return stable deny reason codes (PRD) |
| SRS-API-09 | OpenAPI/Swagger optional post-MVP; route list + contract doc sufficient for MVP |

### Minimum API capability groups (conceptual)

- Device: activate/heartbeat/status  
- Catalog/quote  
- Orders create/show  
- Payments initiate/status  
- Tickets show (own order scope)  
- Validate / check-in  
- Webhooks: payment provider  

---

## 14. Database Requirements

| ID | Requirement |
|---|---|
| SRS-DB-01 | MySQL **8.4.x** as system of record |
| SRS-DB-02 | InnoDB engine; FK constraints where practical |
| SRS-DB-03 | Migrations are source of schema truth |
| SRS-DB-04 | Money stored as integer minor units **or** `decimal(15,2)` consistently — pick one project standard and apply everywhere (recommended: integer IDR minor units = whole Rupiah integers) |
| SRS-DB-05 | Unique constraints for ticket codes, idempotency keys, provider payment refs |
| SRS-DB-06 | Indexes on foreign keys, status columns used in ops queries, heartbeat lookups |
| SRS-DB-07 | Soft deletes only where business-safe; do not soft-delete away audit necessity |
| SRS-DB-08 | UTC timestamps (`created_at`/`updated_at` + domain event times) |
| SRS-DB-09 | No dual-write to a second business database in MVP |

### Core entity groups (logical)

Users/Roles/Permissions · Destinations · TicketTypes · Orders/OrderItems · Payments · Tickets · CheckIns · Devices/Kiosks · AuditLogs · IdempotencyRecords · Jobs/FailedJobs

---

## 15. Validation Strategy

| Layer | Responsibility |
|---|---|
| Form Request / API Request validators | Syntax, presence, types, basic ranges |
| Domain services | Business eligibility, state machine, pricing integrity |
| DB constraints | Uniqueness, FK integrity, non-null essentials |

| ID | Requirement |
|---|---|
| SRS-VAL-01 | Never rely on client validation alone |
| SRS-VAL-02 | Re-validate ticket eligibility inside check-in transaction |
| SRS-VAL-03 | Recalculate totals server-side at order create even if quote shown earlier |
| SRS-VAL-04 | Reject stale/unknown ticket types and inactive catalog items |

---

## 16. Business Logic Strategy

| ID | Requirement |
|---|---|
| SRS-BL-01 | All critical rules live in Laravel domain layer |
| SRS-BL-02 | Flutter/Livewire/JS may orchestrate UX only |
| SRS-BL-03 | Pricing, discounts, tax, fees, totals computed in one pricing service |
| SRS-BL-04 | Status transitions centralized (state transition services/policies) |
| SRS-BL-05 | Channel-specific code limited to transport/actor metadata, not divergent money rules |
| SRS-BL-06 | Authorized overrides (e.g. special refunds) are explicit domain operations with audit |

---

## 17. Service / Action Layer

Preferred simple pattern:

```text
HTTP/Livewire → Action / Application Service → Domain Service → Eloquent / DB
```

| ID | Requirement |
|---|---|
| SRS-SVC-01 | One action/service per use case where clarity helps (CreateOrder, InitiatePayment, MarkPaymentPaid, IssueTickets, ValidateTicket, CheckInTicket, RegisterDevice, etc.) |
| SRS-SVC-02 | Controllers/Livewire call actions; do not duplicate logic |
| SRS-SVC-03 | Actions are reusable by API and dashboard |
| SRS-SVC-04 | Avoid deep abstract enterprise layering (no DDD theater) |
| SRS-SVC-05 | Keep payment provider and printer/scanner behind narrow interfaces/adapters |

---

## 18. Transaction Management

| ID | Requirement |
|---|---|
| SRS-TX-01 | Use DB transactions for multi-row critical operations |
| SRS-TX-02 | Check-in: lock ticket row → recheck → insert check-in → set USED → commit |
| SRS-TX-03 | Issue tickets inside transaction after PAID confirmation |
| SRS-TX-04 | Keep transactions short; external HTTP to payment provider outside long locks where possible |
| SRS-TX-05 | Compensating/reconciliation flows for async payment confirmation |
| SRS-TX-06 | Unique constraints backstop race conditions (ticket code, idempotency key, check-in rules) |

---

## 19. Payment Architecture

```text
CreateOrder (authoritative totals)
  → Create Payment PENDING
  → Initiate provider OR cash confirm path → PROCESSING
  → Verified success → PAID (idempotent)
  → IssueTickets
```

| ID | Requirement |
|---|---|
| SRS-PAY-01 | Server is sole authority for payment status |
| SRS-PAY-02 | One MVP digital provider behind `PaymentGateway` adapter (provider **TBD**) |
| SRS-PAY-03 | Assisted cash path creates audited PAID via authorized staff action |
| SRS-PAY-04 | Webhooks verified and idempotent |
| SRS-PAY-05 | Store provider reference IDs for reconciliation |
| SRS-PAY-06 | Partial payments not supported in MVP |
| SRS-PAY-07 | TTL expiry job moves unpaid to EXPIRED |
| SRS-PAY-08 | Refund is explicit domain operation with permissions + ticket impact rules |
| SRS-PAY-09 | Never trust kiosk “payment success” body as final |

Statuses per PRD: `PENDING`, `PROCESSING`, `PAID`, `FAILED`, `EXPIRED`, `CANCELLED`, `REFUNDED`.

---

## 20. Ticket Architecture

| ID | Requirement |
|---|---|
| SRS-TKT-01 | Tickets created/issued only after PAID |
| SRS-TKT-02 | Unique public ticket code + server-verifiable QR payload |
| SRS-TKT-03 | Persist channel, order linkage, price snapshots, validity window |
| SRS-TKT-04 | Status machine per PRD (`PENDING`/`ISSUED`/`ACTIVE`/`USED`/`EXPIRED`/`CANCELLED`/`REFUNDED`) |
| SRS-TKT-05 | MVP single-entry consumption |
| SRS-TKT-06 | Reprint does not create a second active ticket by default |
| SRS-TKT-07 | QR signing/opaque token strategy **TBD** but must be server-verifiable |

---

## 21. QR Validation Architecture

```text
Scan payload → API validate → authenticity + business checks → ALLOW candidate / DENY+code
```

| ID | Requirement |
|---|---|
| SRS-QR-01 | Validation always executed in Laravel |
| SRS-QR-02 | Perform PRD checks (exists, authentic, destination, payment, status, expiry, cancel/refund, reused, authz, replay considerations) |
| SRS-QR-03 | Return stable machine reason codes |
| SRS-QR-04 | Do not open gate from client-only validation |
| SRS-QR-05 | Rate-limit brute-force guessing where applicable |

---

## 22. Check-in Architecture

| ID | Requirement |
|---|---|
| SRS-CI-01 | Validate + check-in can be one coordinated server operation for gate speed |
| SRS-CI-02 | Transactional; concurrency-safe |
| SRS-CI-03 | Persist check-in record with actor/device/context/timestamps |
| SRS-CI-04 | Transition ACTIVE → USED atomically for MVP |
| SRS-CI-05 | Second attempt returns `DENY_ALREADY_USED` |
| SRS-CI-06 | Optional gate command only after commit success |

---

## 23. Device Management

Logical device fields (PRD):  
`device_id`, `terminal_id`, `location_id`, `status`, `last_heartbeat`, `software_version`, `hardware_version`, `maintenance_mode`, `is_active`, `registered_at`, `activated_at`, `deactivated_at`

| ID | Requirement |
|---|---|
| SRS-DEV-01 | Admin/sysadmin can register and activate devices |
| SRS-DEV-02 | Heartbeat updates `last_heartbeat` and reported versions |
| SRS-DEV-03 | Stale heartbeat surfaces OFFLINE in ops views |
| SRS-DEV-04 | Maintenance/disable blocks order creation |
| SRS-DEV-05 | Deactivation revokes device tokens |
| SRS-DEV-06 | Device bound to destination/location |

---

## 24. Kiosk Authentication

| ID | Requirement |
|---|---|
| SRS-KA-01 | Activation exchanges device secret/code for Sanctum token |
| SRS-KA-02 | Token required on all kiosk commerce endpoints |
| SRS-KA-03 | Token tied to device record; invalid if device disabled |
| SRS-KA-04 | Rotate/revoke credentials on compromise or redeploy |
| SRS-KA-05 | Least privilege: catalog read, quote, order, payment initiate/status, own ticket status, heartbeat |
| SRS-KA-06 | Device secrets stored hashed/encrypted at rest as applicable |

---

## 25. Offline Recovery

| ID | Requirement |
|---|---|
| SRS-OFF-01 | No offline authoritative PAID/ISSUED/USED in MVP |
| SRS-OFF-02 | Connectivity loss → kiosk shows unavailable/pending guidance |
| SRS-OFF-03 | Status polling + reconciliation jobs resolve in-flight payments |
| SRS-OFF-04 | Safe retries using idempotency keys |
| SRS-OFF-05 | Runbooks for unknown payment and reprint-after-paid |

---

## 26. Idempotency

| ID | Requirement |
|---|---|
| SRS-IDEM-01 | Require `Idempotency-Key` (or equivalent field) on order create and payment initiate |
| SRS-IDEM-02 | Persist key + request hash + response/resource reference |
| SRS-IDEM-03 | Replay with same key returns original resource |
| SRS-IDEM-04 | Conflict if same key used with different payload |
| SRS-IDEM-05 | Provider webhook event IDs uniquely processed |
| SRS-IDEM-06 | Check-in uniqueness enforced for single-entry tickets |

---

## 27. Queue Architecture

| ID | Requirement |
|---|---|
| SRS-Q-01 | Use Laravel queues for webhooks fan-out, mail, non-critical notifications, reconciliation tasks as needed |
| SRS-Q-02 | MVP driver: `database` acceptable on single VPS; Redis later if needed |
| SRS-Q-03 | Failed jobs inspectable (`failed_jobs`) |
| SRS-Q-04 | Do not put check-in authority solely in async eventual processing for gate path (sync commit required) |
| SRS-Q-05 | Queue workers supervised on VPS (systemd/supervisor) |

---

## 28. Scheduled Jobs

| Job | Purpose |
|---|---|
| Expire unpaid orders/payments | TTL enforcement |
| Mark expired tickets | Validity end |
| Reconcile PENDING/PROCESSING payments | Provider sync |
| Derive offline kiosks | Heartbeat SLA |
| Report/cache warm (optional) | Ops performance |

| ID | Requirement |
|---|---|
| SRS-SCH-01 | Scheduler via `php artisan schedule:run` cron on VPS |
| SRS-SCH-02 | Jobs must be idempotent and safe to re-run |
| SRS-SCH-03 | Critical schedule failures alert via logs/ops notification |

---

## 29. Cache

| ID | Requirement |
|---|---|
| SRS-CACHE-01 | Cache catalog reads optionally; never cache authoritative payment/ticket finality as sole source |
| SRS-CACHE-02 | MVP cache driver: `file` or `database`; Redis optional |
| SRS-CACHE-03 | Invalidate/bust catalog cache on admin price/type changes |
| SRS-CACHE-04 | Do not use client cache as business authority |

---

## 30. Notifications

| ID | Requirement |
|---|---|
| SRS-NTF-01 | MVP focuses on operational notifications (kiosk offline, webhook failures), not visitor marketing |
| SRS-NTF-02 | Prefer dashboard badges + logs first; email optional |
| SRS-NTF-03 | Use Laravel notifications only where they reduce ops pain |

---

## 31. Email

| ID | Requirement |
|---|---|
| SRS-MAIL-01 | Email not required for visitor ticket delivery in MVP |
| SRS-MAIL-02 | Optional admin/ops alert mail via Laravel Mail |
| SRS-MAIL-03 | Mail driver configured per VPS (SMTP); credentials in `.env` |
| SRS-MAIL-04 | Ticket-by-email is post-MVP |

---

## 32. File Storage

| ID | Requirement |
|---|---|
| SRS-FS-01 | Local/public disk sufficient for MVP assets (logos, export files) |
| SRS-FS-02 | Private disk for sensitive exports if needed |
| SRS-FS-03 | Object storage (S3-compatible) optional later |
| SRS-FS-04 | Do not store payment provider secrets in public storage |
| SRS-FS-05 | Printed ticket templates may be code/Blade-driven rather than binary file dependent |

---

## 33. Logging

| ID | Requirement |
|---|---|
| SRS-LOG-01 | Use Laravel logging channels |
| SRS-LOG-02 | Include correlation IDs for order/payment/ticket/check-in where possible |
| SRS-LOG-03 | Log webhook receipts and processing outcomes |
| SRS-LOG-04 | Avoid logging secrets, raw card data, or full device secrets |
| SRS-LOG-05 | Retain logs per VPS disk policy; ship externally later if needed |

---

## 34. Audit Logging

| ID | Requirement |
|---|---|
| SRS-AUD-01 | Dedicated audit log persistence distinct from ephemeral app logs |
| SRS-AUD-02 | Record actor, action, entity, metadata summary, timestamp, IP/device when available |
| SRS-AUD-03 | Cover PRD critical actions (orders, PAID, refunds, cancels, issue, reprint, check-in, permission/catalog/kiosk changes) |
| SRS-AUD-04 | Application has no user-facing edit/delete for audit rows |
| SRS-AUD-05 | Auditor role can read via dashboard |

---

## 35. Error Handling

| ID | Requirement |
|---|---|
| SRS-ERR-01 | Domain exceptions mapped to stable API/dashboard error codes |
| SRS-ERR-02 | Do not leak stack traces to kiosk/public clients in production |
| SRS-ERR-03 | Payment uncertainty remains non-terminal until reconciled |
| SRS-ERR-04 | Printer failures after PAID are operational errors, not payment failures |
| SRS-ERR-05 | Unauthorized → 401/403; validation → 422; conflicts/idempotency → 409 where appropriate |

---

## 36. Webhooks

| ID | Requirement |
|---|---|
| SRS-WH-01 | Payment provider webhooks hit dedicated Laravel routes |
| SRS-WH-02 | Verify authenticity before mutation |
| SRS-WH-03 | Persist/process idempotently by provider event ID |
| SRS-WH-04 | Fast ack pattern acceptable: verify → queue → process, with careful PAID/issue ordering invariants |
| SRS-WH-05 | Replay-safe; never double-issue tickets |
| SRS-WH-06 | Invalid signatures rejected and logged |

---

## 37. Hardware Integration

| ID | Requirement |
|---|---|
| SRS-HW-01 | Hardware access isolated behind adapters/interfaces |
| SRS-HW-02 | Vendor choices TBD until spike: printer, scanner, payment medium |
| SRS-HW-03 | Backend remains authority even when hardware is local to kiosk/gate PC |
| SRS-HW-04 | Failure modes documented (offline peripheral ≠ successful business state) |

Target kiosk compute: Advan A10 class Android tablet.

---

## 38. Printer Integration

| ID | Requirement |
|---|---|
| SRS-PRN-01 | Printer type **TBD** |
| SRS-PRN-02 | Print payload derived from authoritative issued ticket data |
| SRS-PRN-03 | Kiosk/dashboard may trigger print; success/failure does not recreate tickets |
| SRS-PRN-04 | Reprint is permissioned + audited |
| SRS-PRN-05 | Support degraded mode: PAID/ISSUED with on-screen QR if print fails (ops policy) |

---

## 39. Scanner Integration

| ID | Requirement |
|---|---|
| SRS-SCN-01 | Scanner type **TBD** |
| SRS-SCN-02 | Scanner supplies payload to dashboard/gate client → Laravel validate/check-in |
| SRS-SCN-03 | No local ALLOW decision without server response |
| SRS-SCN-04 | Support keyboard-wedge or SDK modes via thin client adapter without changing domain rules |

---

## 40. Payment Terminal Integration

| ID | Requirement |
|---|---|
| SRS-PT-01 | Payment acceptance medium depends on selected provider (**TBD**) |
| SRS-PT-02 | Whether QRIS display, VA, or EDC, finality comes from provider verification + Laravel state |
| SRS-PT-03 | Kiosk may display provider QR/instructions but cannot finalize PAID alone |
| SRS-PT-04 | Adapter interface allows replacing provider without rewriting order/ticket domain |

---

## 41. Gate Integration

| ID | Requirement |
|---|---|
| SRS-GATE-01 | Optional module; not MVP-blocking |
| SRS-GATE-02 | Open command only after successful authoritative check-in |
| SRS-GATE-03 | Never: Scanner → Flutter → Gate open bypassing Laravel |
| SRS-GATE-04 | Offline gate strategy explicitly out of MVP until designed |
| SRS-GATE-05 | Controller vendor **TBD** |

---

## 42. CCTV / AI Integration

| ID | Requirement |
|---|---|
| SRS-AI-01 | Optional; post-MVP |
| SRS-AI-02 | Ingest detection/count events for analytics only |
| SRS-AI-03 | Must not silently modify ticket/payment/check-in records |
| SRS-AI-04 | Future comparisons: sold vs checked-in vs detected counts |
| SRS-AI-05 | System vendor **TBD** |

---

## 43. Reporting

| ID | Requirement |
|---|---|
| SRS-RPT-01 | Reports implemented server-side from MySQL authoritative data |
| SRS-RPT-02 | MVP reports: daily sales by channel; payments by status; tickets issued vs used; refunds/cancels; kiosk health |
| SRS-RPT-03 | Export CSV optional if low-cost; not a blocker |
| SRS-RPT-04 | Permission-gated report access |

---

## 44. Analytics

| ID | Requirement |
|---|---|
| SRS-AN-01 | Lightweight operational metrics in MVP (funnels, denies, offline) |
| SRS-AN-02 | No separate analytics microservice in MVP |
| SRS-AN-03 | Post-MVP AI comparison dashboards only after event ingest exists |
| SRS-AN-04 | Do not block MVP on product analytics tooling |

---

## 45. Security

| ID | Requirement |
|---|---|
| SRS-SEC-01 | TLS for all production client↔VPS traffic |
| SRS-SEC-02 | Hashed passwords; protected device secrets |
| SRS-SEC-03 | CSRF on web; token auth on API |
| SRS-SEC-04 | Mass-assignment protection / explicit request DTOs |
| SRS-SEC-05 | Webhook signature verification |
| SRS-SEC-06 | Principle of least privilege for RBAC and device tokens |
| SRS-SEC-07 | Remote device disable |
| SRS-SEC-08 | Security headers as practical on VPS web server |
| SRS-SEC-09 | Dependency updates within Laravel 13.x / PHP 8.4.x constraints |
| SRS-SEC-10 | Minimal PII collection in MVP |

---

## 46. Backup

| ID | Requirement |
|---|---|
| SRS-BK-01 | Daily automated MySQL backups on VPS |
| SRS-BK-02 | Backup retention policy documented (recommended ≥ 7–30 days; finalize with ops) |
| SRS-BK-03 | Periodic restore test |
| SRS-BK-04 | Application `.env` secrets backed up securely offline from public repo |
| SRS-BK-05 | Backup includes audit tables |

---

## 47. Testing

| ID | Requirement |
|---|---|
| SRS-TEST-01 | Feature/integration tests for order→pay→issue→check-in |
| SRS-TEST-02 | Idempotency tests for order/payment/webhook |
| SRS-TEST-03 | Concurrency test for double check-in |
| SRS-TEST-04 | Permission negative tests |
| SRS-TEST-05 | Pricing consistency tests across channel entry points |
| SRS-TEST-06 | Webhook signature failure tests |
| SRS-TEST-07 | Kiosk client widget/flow tests where practical; device UAT on Advan A10 class |
| SRS-TEST-08 | Prefer Laravel’s built-in testing tools; avoid unnecessary test framework complexity |

---

## 48. Deployment

| ID | Requirement |
|---|---|
| SRS-DEP-01 | Primary deployment target: **VPS** |
| SRS-DEP-02 | Components on VPS: PHP-FPM/web server, MySQL 8.4.x (or managed MySQL), queue worker, scheduler cron |
| SRS-DEP-03 | Environment separation: local / staging / production |
| SRS-DEP-04 | Zero-secret-in-git policy |
| SRS-DEP-05 | Deploy via simple release process (Git pull + composer + migrate + cache + reload); CI optional |
| SRS-DEP-06 | Kiosk APK/distribution process documented separately from VPS app deploy |
| SRS-DEP-07 | Maintenance mode supported for backend upgrades |

Exact production VPS OS/panel: **TBD — Requires Environment Verification** at provisioning time.

---

## 49. Performance

| ID | Requirement |
|---|---|
| SRS-PERF-01 | Meet SRS-NFR-04/05 latency targets under expected single-destination load |
| SRS-PERF-02 | Index hot paths (status, ticket code, device id, provider refs) |
| SRS-PERF-03 | Avoid N+1 in dashboard listings |
| SRS-PERF-04 | Queue non-critical work off request path |
| SRS-PERF-05 | No premature sharding/read replicas for MVP |

---

## 50. Scalability

| ID | Requirement |
|---|---|
| SRS-SCL-01 | MVP sized for one destination / small kiosk fleet |
| SRS-SCL-02 | Vertical scale VPS first |
| SRS-SCL-03 | Introduce Redis queue/cache when metrics justify |
| SRS-SCL-04 | Multi-destination tenancy packaging is post-MVP |
| SRS-SCL-05 | Do not split into microservices for scale theater |

---

## 51. Maintainability

| ID | Requirement |
|---|---|
| SRS-MAIN-01 | Shared domain actions prevent channel drift |
| SRS-MAIN-02 | Clear module boundaries; thin UI layer |
| SRS-MAIN-03 | Adapter interfaces for payment/hardware |
| SRS-MAIN-04 | Coding standards via Laravel Pint (or project standard) after scaffold |
| SRS-MAIN-05 | Document runbooks for payment unknown, reprint, device disable |
| SRS-MAIN-06 | Prefer boring, readable Laravel code over novel architecture |

---

## 52. Upgrade Strategy

| ID | Requirement |
|---|---|
| SRS-UP-01 | Stay on Laravel **13.x** patch/minor updates intentionally |
| SRS-UP-02 | PHP remains **8.4.x** compatible line |
| SRS-UP-03 | Composer lockfile is the installed-version source of truth after scaffold |
| SRS-UP-04 | Test critical money/ticket flows before production framework upgrades |
| SRS-UP-05 | Kiosk app version reported via heartbeat; support forced-upgrade policy later |
| SRS-UP-06 | Database migrations backward-safe during rolling deploys where possible |

Until `composer.lock` exists, exact Laravel patch is **not claimed as installed**. Packagist latest inspected for planning: **v13.31.0**.

---

## 53. Technical Constraints

| ID | Constraint |
|---|---|
| SRS-CON-01 | Laravel remains authoritative business layer |
| SRS-CON-02 | Flutter is physical kiosk client, not consumer app, not staff app (MVP) |
| SRS-CON-03 | No separate business logic engines per channel |
| SRS-CON-04 | No offline authoritative selling in MVP |
| SRS-CON-05 | No gate open without Laravel check-in success |
| SRS-CON-06 | No AI silent mutation of tickets |
| SRS-CON-07 | Avoid unnecessary complexity: monolith, simple queues, simple cache, action/services |
| SRS-CON-08 | Vendor unknowns remain TBD until spike: payment, printer, scanner, gate, CCTV |
| SRS-CON-09 | Stack baseline: Laravel 13.x · PHP 8.4.x · MySQL 8.4.x · Sanctum + session auth · VPS |
| SRS-CON-10 | Do not invent installed patch versions; verify from lockfiles/environment after scaffold |
| SRS-CON-11 | Currency IDR; timestamps UTC authoritative |
| SRS-CON-12 | MVP single-entry tickets; full refunds only by default policy |

---

## Appendix A — Technology decision record (MVP)

| Decision | Choice | Rationale |
|---|---|---|
| App shape | Laravel monolith | Simplicity, shared domain |
| API style | REST | Fits kiosk/dashboard clients |
| Auth | Session + Sanctum | Matches dashboard + device clients |
| Queue/Cache MVP | Database/file acceptable | Single VPS simplicity |
| UI dashboard | Blade + Livewire + Tailwind | Assisted ops without SPA complexity |
| Kiosk | Flutter device app | Touch terminal on Android tablet |
| Architecture style | Action/service layer | Shared rules, low ceremony |

---

## Appendix B — Traceability

| Source | SRS reflection |
|---|---|
| Product validation GO + shared SoT | Sections 1–3, 16–22 |
| PRD dual channel + no staff Flutter | Sections 6–8, 53 |
| PRD status machines | Sections 19–22 |
| PRD idempotency/offline | Sections 25–26 |
| PRD hardware optionalities | Sections 37–42 |

---

## Appendix C — Open technical items before implementation freeze

1. Pin exact Laravel 13.x / Sanctum / Livewire / Tailwind toolchain in `composer.lock` / `package-lock.json` after scaffold  
2. Select `[PAYMENT_PROVIDER]` and webhook verification method  
3. Select `[PRINTER_TYPE]`, `[SCANNER_TYPE]`  
4. Finalize QR token/signing strategy  
5. Confirm VPS OS, web server (Nginx/Apache), SSL termination  
6. Confirm money storage standard (integer Rupiah vs decimal)  
7. Confirm order payment TTL default (PRD suggests starting at 15 minutes)  

---

*End of SRS. No application implementation code in this document.*
