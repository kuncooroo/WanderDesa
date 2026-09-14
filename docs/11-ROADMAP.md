# WanderDesa — Development Roadmap

**Document ID:** `11-ROADMAP`  
**File:** `docs/11-ROADMAP.md`  
**Status:** Delivery plan (no implementation code)  
**Based on:** `docs/01`–`10`, `CURSOR.md`  
**Principle:** **Backend / business authority before clients.** Kiosk and Dashboard consume shared Laravel Actions — they do not invent rules.

---

## 0. How to use this roadmap

| Rule | Detail |
|---|---|
| Order | Respect phase dependencies; do not start Flutter commerce before Orders/Payments/QR APIs exist |
| Authority | Every commerce phase lands domain Actions + tests before UI polish |
| Docs hierarchy | Follow `CURSOR.md` Source of Truth Hierarchy on conflicts |
| MVP cut line | Phases 0–18 + 21–22 + 24 + 27 form the core MVP path; 19–20, 23, 25–26 deepen production readiness |
| Parallelism | Docs/security hygiene can overlap lightly; never parallelize divergent pricing engines |

### Milestone summary

```text
M0 Foundation     → Phases 0–4
M1 Commerce Core  → Phases 5–10
M2 Staff Channel  → Phase 11
M3 Kiosk Channel  → Phases 12–17
M4 Ops Hardening  → Phases 18–26
M5 Release        → Phase 27
```

---

## Phase 0 — Environment & Repository Foundation

### Objectives
Establish monorepo skeleton, tooling, and engineering guardrails without inventing business behavior.

### Dependencies
- Product decision GO (`docs/01`)
- Local toolchain available (PHP 8.4.x, Composer, MySQL 8.4.x, Flutter, Node for assets)

### Deliverables
- Repo layout: `backend/`, `kiosk/` (placeholder OK), `docs/`, `.cursor/`, `CURSOR.md`, `README.md`
- Git ignore for secrets; `.env.example` strategy noted
- Cursor rules aligned to architecture (optional but recommended)

### Tasks
1. Create top-level structure per `docs/10-PROJECT-STRUCTURE.md`
2. Verify PHP/MySQL/Flutter/Composer versions; record in README (no invented patches)
3. Initialize git (if needed) with safe ignores
4. Link normative docs index in README
5. Define branch naming / PR expectations (lightweight)

### Acceptance Criteria
- [ ] Monorepo paths exist and match project structure doc
- [ ] `CURSOR.md` present and referenced
- [ ] No secrets committed
- [ ] Environment versions documented as inspected or TBD

### Testing Requirements
- Smoke: clone/checkout works; docs readable

### Risks
- Starting Flutter UI before backend → rework; mitigate by phase order discipline

---

## Phase 1 — Laravel Backend Foundation

### Objectives
Scaffold Laravel **13.x** modular monolith app ready for domain work.

### Dependencies
- Phase 0

### Deliverables
- `backend/` Laravel application (API + web capable)
- Base config, logging, queue/cache drivers suitable for local + VPS later
- Coding standards baseline (Pint when available)
- Empty `Actions/`, `Services/`, `Integrations/` directories per structure doc

### Tasks
1. Create Laravel 13.x project in `backend/`
2. Configure `APP_*`, logging channels, timezone UTC policy
3. Set up Tailwind/Vite pipeline for dashboard later
4. Add Livewire package (Laravel 13–compatible pin from Composer)
5. Wire folder conventions; document in short backend README

### Acceptance Criteria
- [ ] `php artisan` runs
- [ ] Health/web route responds locally
- [ ] Composer lockfile records exact Laravel patch
- [ ] Structure matches agreed conventions

### Testing Requirements
- Feature smoke test that app boots

### Risks
- Wrong Livewire/Sanctum major versions → pin carefully against Laravel 13
- Over-packaging → prefer Laravel-native first

---

## Phase 2 — Database Foundation

### Objectives
Implement authoritative schema from `docs/06-DATABASE.md` (new migrations only).

### Dependencies
- Phase 1
- MySQL 8.4.x available

### Deliverables
- Migrations for identity, catalog, orders, payments, tickets, check-ins, devices, audit, settings, integrations, files, idempotency, webhook events
- Factories/seed stubs for core tables
- Money standard enforced as BIGINT IDR

### Tasks
1. Translate ERD to migrations with FK/unique/indexes
2. Add Laravel framework tables (sessions/jobs/cache) as needed
3. Seed empty roles/permissions placeholders (filled in Phase 4)
4. Document migration run order in README

### Acceptance Criteria
- [ ] `migrate:fresh` succeeds on clean MySQL 8.4
- [ ] Uniques exist for ticket_code, payment_number, order_number, device_id, idempotency, webhook event, check_ins.ticket_id
- [ ] No soft deletes on financial/audit tables

### Testing Requirements
- Migration CI/local fresh migrate
- Constraint tests (unique violations)

### Risks
- Over-normalized pricing tables → stick to snapshots on order_items
- Changing old migrations later → always new migrations after share/deploy

---

## Phase 3 — Authentication

### Objectives
Staff session auth + device token foundation (Sanctum).

### Dependencies
- Phase 2

### Deliverables
- Staff login/logout (web)
- Sanctum installed; user token login endpoints optional; device token model ready
- Inactive user blocked
- Basic auth audit events (login success/fail)

### Tasks
1. Implement web guard session auth for dashboard
2. Install/configure Sanctum
3. User `is_active` gate
4. API `auth/login`, `auth/logout`, `auth/me` per contract (staff)
5. Password hashing and session security settings

### Acceptance Criteria
- [ ] Staff can log in/out via dashboard
- [ ] Inactive users cannot authenticate
- [ ] Device token issuance hooks exist (completed in Phase 17 activation flow)
- [ ] Failed login audited/logged

### Testing Requirements
- Feature tests: login success/fail/inactive
- CSRF on web forms

### Risks
- Mixing device and user guards incorrectly → separate abilities clearly

---

## Phase 4 — RBAC

### Objectives
Server-side roles/permissions per `docs/07-RBAC.md`.

### Dependencies
- Phase 3

### Deliverables
- Seeded MVP roles: super_admin, admin, manager, finance, ticket_officer, gate_officer, operator, auditor
- Permission catalog + role matrix
- Policies/middleware enforcement patterns
- Super Admin cannot be assigned by Admin (`roles.assign` only Super Admin)

### Tasks
1. Seed permissions and role_permission map
2. Implement Policies for key resources
3. Deny-by-default helpers for Actions
4. Navigation permission mapping prep for Livewire
5. Negative tests for escalation

### Acceptance Criteria
- [ ] Matrix matches `docs/07-RBAC.md` defaults
- [ ] Ticket Officer cannot refund
- [ ] Auditor cannot mutate commerce
- [ ] Device abilities distinct from staff roles

### Testing Requirements
- Allow/deny feature tests per critical cells
- Escalation tests for `roles.assign`

### Risks
- UI-only hiding mistaken for security → always Policy/Action checks

---

## Phase 5 — Tourism Master Data

### Objectives
Destinations, ticket types, and server pricing configuration.

### Dependencies
- Phase 4

### Deliverables
- CRUD (permissioned) for destinations & ticket types
- Pricing fields (unit_price, tax, service_fee) as IDR integers
- `PricingService` + `POST /pricing/quote`
- Active/inactive catalog rules

### Tasks
1. Models + Actions for catalog manage
2. Quote endpoint rejects client money fields
3. Admin Livewire pages (can be minimal; polish in Phase 11)
4. Audit price changes

### Acceptance Criteria
- [ ] Quote returns authoritative totals
- [ ] Inactive types not quotable/sellable
- [ ] Client money fields → 422

### Testing Requirements
- Unit: PricingService
- Feature: quote validation and money rejection

### Risks
- Embedding promo engine too early → keep discount_total=0 unless rules exist

---

## Phase 6 — Ticketing (domain core)

### Objectives
Ticket entity lifecycle services **ready to issue after PAID** (issuance wired in Phase 8).

### Dependencies
- Phase 5

### Deliverables
- Ticket model/status enums
- `IssueTickets` Action (callable when payment PAID)
- Unique ticket_code + qr_payload_hash generation strategy (crypto details TBD but interface fixed)
- Print-payload DTO builder

### Tasks
1. Implement ticket status transitions service
2. Idempotent issuance (PAID order → same tickets on retry)
3. Validity window calculation from ticket type
4. Deny manual public “create ticket” API

### Acceptance Criteria
- [ ] Cannot issue without PAID payment linkage
- [ ] Unique codes/hashes enforced
- [ ] Re-issue retry does not duplicate tickets

### Testing Requirements
- Unit/feature issuance idempotency
- Validity window tests

### Risks
- QR crypto undecided → define opaque token interface early; swap signing later

---

## Phase 7 — Orders

### Objectives
Authoritative order create/cancel/expire with idempotency and channel metadata.

### Dependencies
- Phase 5 (catalog); Phase 6 ticket types exist; Phase 4 authz

### Deliverables
- `CreateOrder`, `CancelOrder` Actions
- `POST /orders`, `GET /orders/{id}`, `POST /orders/{id}/cancel`
- Idempotency keys table usage
- Order TTL field + expire job stub

### Tasks
1. Recalculate totals server-side on create
2. Set channel from principal (device→kiosk, staff→assisted)
3. Snapshot order_items
4. Reject client money fields
5. Device sellability checks (active, not maintenance) — full device mgmt in Phase 17; stub flags OK

### Acceptance Criteria
- [ ] Identical inputs → identical totals
- [ ] Idempotent create returns same order
- [ ] Cancel only when pending_payment

### Testing Requirements
- Idempotency conflict 409
- Permission tests for assisted create
- Channel immutability

### Risks
- Creating separate kiosk/assisted order tables → forbidden

---

## Phase 8 — Payments

### Objectives
Payment state machine, cash confirm, provider adapter + webhook, issue tickets on PAID.

### Dependencies
- Phase 7, Phase 6

### Deliverables
- `InitiatePayment`, `ConfirmCashPayment`, `MarkPaymentPaid`, `RefundPayment` Actions
- Payment webhook endpoint + idempotent event store
- One MVP provider adapter (provider TBD) **or** sandbox/null adapter with webhook simulator until vendor chosen
- Ticket issuance on PAID
- Expire unpaid job

### Tasks
1. Initiate payment amount = order.grand_total only
2. Secure webhook verification interface
3. Idempotent PAID → IssueTickets
4. Refund eligibility (default not USED)
5. Reconciliation job skeleton

### Acceptance Criteria
- [ ] Client cannot force PAID
- [ ] Duplicate webhook does not double-issue
- [ ] Cash PAID audited with staff id
- [ ] FAILED/EXPIRED do not issue tickets

### Testing Requirements
- Webhook signature fail
- Duplicate event id
- Cash confirm permission
- Refund ineligible USED

### Risks
- Provider TBD delay → use adapter + simulator; do not hardcode fake PAID in clients
- Partial payments scope creep → out of MVP

---

## Phase 9 — QR

### Objectives
Server-verifiable QR payload on issued tickets + validate endpoint.

### Dependencies
- Phase 6–8 (issued tickets exist)

### Deliverables
- QR generation at issuance
- `POST /tickets/validate` decision API (`ALLOW`/`DENY` + reason codes)
- Print-payload includes QR

### Tasks
1. Finalize payload format (opaque/signed)
2. Validation checks per business flow
3. Rate limit validation
4. Audit validate denials (recommended)

### Acceptance Criteria
- [ ] Forged/unknown → DENY
- [ ] Wrong destination/status/expiry → correct reason codes
- [ ] No USED transition on validate-only

### Testing Requirements
- Matrix of deny codes
- Authz for validate

### Risks
- Exposing QR widely → scope print/own flows carefully

---

## Phase 10 — Check-in

### Objectives
Transactional check-in with double-entry prevention; optional gate hook stub.

### Dependencies
- Phase 9

### Deliverables
- `CheckInTicket` Action + `POST /check-ins`
- Unique `check_ins.ticket_id`
- ACTIVE→USED atomic transition
- Gate open interface stub (no hardware required)

### Tasks
1. Lock row + re-validate + insert + status update
2. Idempotency-Key support
3. Concurrent double scan tests
4. No reverse endpoint in MVP

### Acceptance Criteria
- [ ] First ALLOW succeeds; second DENY_ALREADY_USED
- [ ] Unauthorized actor denied
- [ ] Gate stub called only after commit (if enabled)

### Testing Requirements
- Parallel check-in race test
- Permission `checkins.create`

### Risks
- Async check-in → forbidden for gate path; keep sync

---

## Phase 11 — Assisted-Service Dashboard

### Objectives
Staff channel UX: login shell, assisted sale, lookups, gate UI, basic admin catalog, RBAC nav.

### Dependencies
- Phases 3–10 (domain ready)

### Deliverables
- Blade layout + Livewire pages per `docs/09-UI-UX.md` (dashboard sections prioritized)
- Assisted sale wizard calling shared Actions
- Gate validation/check-in UI
- Orders/Payments/Tickets lists
- Catalog admin pages
- Role-filtered navigation

### Tasks
1. App shell (sidebar/top nav)
2. AssistedSale Livewire → CreateOrder/Initiate/ConfirmCash/Print
3. CheckIn Livewire
4. Users admin (Admin/Super Admin)
5. Empty/loading/error patterns
6. Ensure no pricing logic in Livewire beyond displaying Action results

### Acceptance Criteria
- [ ] Ticket Officer completes assisted cash sale to ISSUED
- [ ] Gate Officer check-in ALLOW once
- [ ] Unauthorized modules hidden and server-denied
- [ ] Same totals as API quote for identical inputs

### Testing Requirements
- Livewire feature tests or HTTP feature tests on actions
- RBAC navigation/permission denials
- UAT assisted sale ≤ 90s target (observational)

### Risks
- Fat Livewire components → split flows; keep Actions thin entry

---

## Phase 12 — Kiosk API

### Objectives
Harden/complete device-facing API surface for commerce + catalog + payment poll (device auth may be temporary test tokens until Phase 17).

### Dependencies
- Phases 5–10; Phase 3 Sanctum

### Deliverables
- Full kiosk-critical routes per `docs/08-API-CONTRACT.md`
- Consistent error envelope
- Idempotency + rate limits
- Device ability middleware (even if activation UX lands in 17)

### Tasks
1. Align controllers to contract
2. Money rejection tests on all write endpoints
3. Order/payment/ticket scoping for device principal
4. API Resource DTOs stable for Flutter

### Acceptance Criteria
- [ ] Contract smoke suite passes for quote→order→pay→tickets
- [ ] Device cannot access admin/refund routes
- [ ] Idempotency behavior documented and tested

### Testing Requirements
- API feature suite as contract checklist
- Rate limit smoke (optional)

### Risks
- Building Flutter against unstable payloads → freeze Resource shapes here

---

## Phase 13 — Flutter Kiosk

### Objectives
Physical terminal client UX for self-service happy path on Advan A10 class landscape device.

### Dependencies
- Phase 12 (API stable)
- Phase 17 strongly recommended before production; can start UI against staging devices in parallel late Phase 12

### Deliverables
- Flutter app structure per docs 09–10
- Flows: Idle → Home → Select → Summary → Pay → Success → QR/Print → Idle
- Error: network, payment fail, maintenance, timeout, recovery
- No local authoritative PAID/ISSUED/USED

### Tasks
1. Theme/touch targets/l10n ID
2. Network client + interceptors
3. Checkout feature with idempotency keys
4. Heartbeat client loop
5. Locked fullscreen / landscape configuration
6. Device UAT on target tablet profile

### Acceptance Criteria
- [ ] Visitor completes purchase against real API
- [ ] Kill network mid-pay → no fake success; recovery/poll works
- [ ] Maintenance status blocks sales
- [ ] Totals match server

### Testing Requirements
- Widget/flow tests for state machines
- Manual device UAT checklist from UI/UX doc

### Risks
- Treating kiosk as mobile portrait app → violate UX spec
- Implementing pricing in Dart → STOP

---

## Phase 14 — Printer Integration

### Objectives
Print server print-payload on kiosk and dashboard reprint path.

### Dependencies
- Phase 8–9 (tickets); Phase 11/13 clients
- Printer type TBD spike

### Deliverables
- Printer adapter on kiosk; dashboard reprint using same payload
- Printer failure UX (keep PAID; show QR)
- Audited reprint

### Tasks
1. Spike chosen printer protocol
2. Map print DTO to hardware commands
3. Failure + retry UX
4. Ops runbook snippet

### Acceptance Criteria
- [ ] Successful print of issued ticket
- [ ] Failure does not alter payment/ticket money state
- [ ] Reprint does not mint second active ticket

### Testing Requirements
- Integration test with mock printer port
- UAT jam/offline printer

### Risks
- Vendor lock delay → screen QR remains acceptable degraded mode

---

## Phase 15 — Scanner Integration

### Objectives
Gate/counter scanner feeds Dashboard validate/check-in (not local ALLOW).

### Dependencies
- Phase 10–11
- Scanner type TBD spike

### Deliverables
- Scanner input → Livewire autofocus field / HID wedge support
- Manual code fallback
- Scanner failure messaging

### Tasks
1. Spike scanner mode (keyboard wedge vs SDK)
2. Wire to validate/check-in Actions
3. Deny reason display UX

### Acceptance Criteria
- [ ] Scan triggers server decision
- [ ] No offline local gate open
- [ ] Manual entry works when scanner fails

### Testing Requirements
- Feature tests with payload fixtures
- UAT mis-scan / double-scan

### Risks
- Building scan into public purchase kiosk unnecessarily → keep on dashboard/gate desk

---

## Phase 16 — Payment Terminal Integration

### Objectives
Production digital payment medium for kiosk (QRIS/EDC/etc. per provider).

### Dependencies
- Phase 8 adapter; Phase 13 kiosk payment UI
- `[PAYMENT_PROVIDER]` decision

### Deliverables
- Provider-specific adapter implementation
- Kiosk next_action display (QR/instructions)
- Webhook + reconcile verified in staging
- Finance refs visible in dashboard

### Tasks
1. Select provider; implement signature verify
2. Sandbox E2E then production keys via env
3. Failure/expired UX alignment
4. Runbook for unknown payment state

### Acceptance Criteria
- [ ] Real sandbox payment reaches PAID and issues tickets
- [ ] Duplicate webhook safe
- [ ] Reconciliation resolves stuck PROCESSING

### Testing Requirements
- Contract tests with recorded fixtures
- Staging UAT with finance sign-off

### Risks
- MDR/settlement misunderstandings → involve finance early
- Hardcoding provider in domain → keep behind interface

---

## Phase 17 — Kiosk Device Management

### Objectives
Full device lifecycle: register, activate, heartbeat, maintenance, deactivate, fleet health UI.

### Dependencies
- Phase 3 Sanctum; Phase 12 API; Phase 11 dashboard shell

### Deliverables
- Device Actions + API + Livewire fleet pages
- Activation code exchange → device token
- Heartbeat + offline detection job
- Token revoke on deactivate
- Kiosk respects maintenance/disable from heartbeat/config

### Tasks
1. Implement register/activate/deactivate/maintenance
2. Heartbeat sampling + `last_heartbeat_at`
3. Ops alerts when stale (Phase 20 can notify)
4. Audit device lifecycle events

### Acceptance Criteria
- [ ] Unregistered device cannot sell
- [ ] Disabled device tokens fail
- [ ] Maintenance blocks CreateOrder
- [ ] Fleet health view accurate

### Testing Requirements
- Device ability tests
- Heartbeat stale job test
- Activation invalid code tests

### Risks
- Shipping kiosk to field without activation security → treat as blocker for pilot

---

## Phase 18 — Reporting

### Objectives
MVP operational/finance reports from authoritative MySQL.

### Dependencies
- Phases 7–10 data flowing; Phase 11 shell; RBAC

### Deliverables
- Daily sales by channel
- Payments by status
- Tickets issued vs used
- Refunds/cancels list
- Optional CSV export (`reports.export`)

### Tasks
1. Query services (no client cache authority)
2. Livewire report pages + filters
3. Optional report API
4. Audit exports if sensitive

### Acceptance Criteria
- [ ] Manager/Finance can produce EOD sales in ≤ 5 minutes (PRD metric)
- [ ] Numbers match DB for sample fixtures
- [ ] Unauthorized roles denied

### Testing Requirements
- Fixture-based report assertions
- Permission tests

### Risks
- Premature data warehouse → avoid; SQL on OLTP is enough for MVP

---

## Phase 19 — Analytics

### Objectives
Lightweight funnels and deny/offline metrics (not full BI).

### Dependencies
- Phase 18; sufficient production-like data

### Deliverables
- Kiosk funnel metrics (start→order→paid→issued) if instrumented
- Deny reason distribution
- Offline kiosk counts
- Manager analytics views

### Tasks
1. Define minimal event/metric sources (orders/payments/check-ins/devices)
2. Simple charts/widgets
3. Ensure AI/CCTV not required

### Acceptance Criteria
- [ ] Analytics read-only and permissioned
- [ ] No impact on transactional latency

### Testing Requirements
- Basic aggregation tests

### Risks
- Scope creep into product analytics platform → keep lightweight

---

## Phase 20 — Notifications

### Objectives
Operational notifications (not visitor marketing).

### Dependencies
- Phase 17 offline detection; Phase 8 webhook failures; mail config

### Deliverables
- Database notifications for ops roles
- Optional email for critical alerts
- Notification inbox on dashboard

### Tasks
1. Kiosk offline beyond threshold notify Operator
2. Webhook processing failure notify Admin/SysAdmin
3. Prefer badges + logs first

### Acceptance Criteria
- [ ] Offline kiosk creates actionable notification
- [ ] No secrets in notification payloads

### Testing Requirements
- Notification dispatch feature tests

### Risks
- Alert fatigue → threshold carefully; severity levels

---

## Phase 21 — Security

### Objectives
Hardening pass across authz, webhooks, secrets, headers, rate limits, IDOR.

### Dependencies
- Core features exist (Phases 3–17)

### Deliverables
- Security checklist signed against `CURSOR.md` / SRS
- Rate limits verified
- Webhook auth verified
- IDOR tests on orders/tickets
- Secrets only in env
- Role assignment controls verified

### Tasks
1. Threat pass on kiosk public surface
2. Pen-test lite / checklist
3. Fix gaps before pilot
4. Ensure AI cannot mutate tickets (if any stub exists)

### Acceptance Criteria
- [ ] No critical IDOR on ticket/order read
- [ ] Webhook rejects bad signatures
- [ ] Device token cannot refund/admin
- [ ] No secrets in repo

### Testing Requirements
- Security-focused automated tests
- Manual authz bypass attempts

### Risks
- Deferring security to “later” → Phase 21 is required before Phase 27 pilot

---

## Phase 22 — Testing

### Objectives
Consolidate automated suite for money/ticket integrity and RBAC.

### Dependencies
- Phases 5–18 primarily

### Deliverables
- CI-ready test suite (local minimum; CI optional but recommended)
- Test data factories
- Documented how to run tests

### Tasks
1. Fill gaps from earlier phases
2. Contract smoke pack for `/api/v1`
3. Concurrency check-in test stabilized
4. Flutter critical unit tests for retry/offline UX logic

### Acceptance Criteria
- [ ] Critical path suite green on clean migrate
- [ ] Idempotency + double check-in + money rejection covered

### Testing Requirements
- Meta: coverage of critical paths (not vanity % only)

### Risks
- Flaky concurrency tests → isolate DB transactions carefully

---

## Phase 23 — Performance

### Objectives
Meet SRS latency targets under single-destination load.

### Dependencies
- Phase 22 baseline correctness

### Deliverables
- Indexes validated
- N+1 fixes on dashboard lists
- p95 notes for quote/order/check-in
- Queue non-critical work off request path

### Tasks
1. Explain slow queries; add missing indexes if proven
2. Load smoke on validate/check-in
3. Avoid premature Redis unless needed

### Acceptance Criteria
- [ ] Quote/order p95 < 2s under agreed lab conditions
- [ ] Validate/check-in p95 < 1.5s
- [ ] No N+1 on primary list pages

### Testing Requirements
- Simple load script results attached to release notes

### Risks
- Premature microservices for speed → rejected

---

## Phase 24 — Deployment

### Objectives
VPS deployment of Laravel monolith + MySQL + worker + scheduler; kiosk APK distribution process documented.

### Dependencies
- Phases 1–18 minimum; 21–22 strongly recommended

### Deliverables
- Staging + production env templates
- Deploy runbook (migrate, cache, reload, worker, cron)
- TLS termination
- Backup job for MySQL
- Kiosk release channel notes

### Tasks
1. Provision VPS (OS TBD at provision time)
2. Configure PHP-FPM, web server, MySQL 8.4, supervisor
3. Secrets via env
4. Staging E2E before prod

### Acceptance Criteria
- [ ] Staging supports full assisted + kiosk sandbox payment flow
- [ ] Backups configured
- [ ] Rollback steps documented

### Testing Requirements
- Post-deploy smoke checklist
- Backup restore drill (at least once)

### Risks
- Deploying without worker/scheduler → unpaid expire/reconcile broken

---

## Phase 25 — Monitoring

### Objectives
Observe app health, queues, failed jobs, device fleet, error rates.

### Dependencies
- Phase 24

### Deliverables
- Log aggregation approach (even if file-based initially)
- Alerts for failed jobs / 5xx spike / kiosk mass offline
- Uptime check on API health endpoint

### Tasks
1. Health endpoint
2. Failed job dashboard/process
3. Define on-call lite checklist for pilot

### Acceptance Criteria
- [ ] Ops can detect API down and queue stuck
- [ ] Correlation ids present on critical logs

### Testing Requirements
- Chaos: stop worker → alert/detect

### Risks
- No monitoring during pilot weekends → revenue blind spots

---

## Phase 26 — Documentation

### Objectives
Keep docs aligned with shipped behavior; add runbooks.

### Dependencies
- Ongoing; formalize before Phase 27

### Deliverables
- Updated docs if behavior changed (hierarchy conflict protocol)
- Runbooks: payment unknown, reprint, device disable, deploy/rollback
- API/changelog notes for kiosk app versioning

### Tasks
1. Diff implementation vs docs; resolve STOP conflicts properly
2. Write ops runbooks under `docs/runbooks/` (optional folder)
3. README quickstart for backend + kiosk

### Acceptance Criteria
- [ ] No known silent doc/code conflicts
- [ ] Pilot ops can follow runbooks without engineer tribal knowledge

### Testing Requirements
- Doc review checklist signed by PM/tech lead

### Risks
- Docs drift → violates CURSOR constitution

---

## Phase 27 — Release

### Objectives
MVP production pilot at one destination.

### Dependencies
- Phases 0–18, 21–22, 24–26 (19–20, 23, 25 as readiness allows)
- Hardware: tablet + printer + scanner + payment medium decided for pilot site

### Deliverables
- Production release tag
- Pilot checklist sign-off (product, finance, ops)
- Monitoring during first peak period
- Rollback plan ready

### Tasks
1. Freeze scope; no Staff Flutter, no offline sell, no AI ticket mutation
2. Seed real catalog/prices/users/devices
3. Train Ticket Officers / Gate Officers / Operators
4. Soft launch → peak observation → hotfix channel
5. Capture metrics vs PRD success metrics

### Acceptance Criteria
- [ ] AC from PRD MVP (kiosk buy, assisted buy, check-in once, reconcile day, no duplicate under retry tests in staging)
- [ ] Finance accepts payment reconciliation process
- [ ] Ops can disable a kiosk remotely
- [ ] No critical open Sev-1 after pilot window criteria

### Testing Requirements
- Final staging regression pack
- On-site UAT day-0 checklist

### Risks
- Scope creep at release week → enforce out-of-scope list
- Untrained staff bypassing system → training + fast assisted UX
- Printer/network site issues → degraded QR mode + counter fallback

---

## Priority guidance (explicit)

| Priority | Phases | Why |
|---|---|---|
| P0 | 0–10 | Business authority & integrity |
| P0 | 11 | Assisted channel unblocks ops without kiosk hardware |
| P0 | 12–13, 17 | Self-service channel + device trust |
| P0 | 8/16 | Payments (adapter early; terminal when vendor ready) |
| P1 | 14–15 | Printer/scanner for real site ops |
| P1 | 18, 21–22, 24, 27 | Reports, security, tests, deploy, release |
| P2 | 19–20, 23, 25–26 | Analytics, notifications, perf, monitoring, doc polish |

**Do not** start Phase 13 commerce UI before Phases 7–9 exist.  
**Do not** ship Phase 27 without Phases 8, 10, 11, 17, 21, 24.

---

## Cross-phase risk register (top)

| Risk | Impact | Mitigation |
|---|---|---|
| Split-brain channel logic | Critical | Shared Actions only |
| Payment/ticket desync | Critical | Idempotent PAID→Issue + reconcile |
| Provider/hardware TBD | High | Adapters + degraded QR mode |
| Staff Flutter temptation | High | CURSOR Staff Rule |
| Offline fake success | Critical | Offline Rule + recovery UX |
| Doc/code conflict | High | STOP protocol |

---

## Suggested calendar shape (indicative, not binding)

Exact dates depend on team size. Indicative sequencing for a small team:

1. Weeks 1–2: Phases 0–4  
2. Weeks 3–5: Phases 5–10  
3. Weeks 5–7: Phase 11 + 12  
4. Weeks 7–9: Phases 13 + 17 (+ 14–16 as hardware arrives)  
5. Weeks 9–10: 18, 21–22, 24–26  
6. Week 11+: Phase 27 pilot  

---

*End of roadmap. No application implementation code.*
