# CURSOR.md — WanderDesa Engineering Instructions

**Product:** WanderDesa  
**Audience:** Cursor and other AI coding assistants · human engineers  
**Status:** Permanent engineering constitution  
**Precedence:** If implementation conflicts with documented business rules — **STOP** (see Source of Truth Hierarchy).

---

## 0. What WanderDesa is

WanderDesa is an **Integrated Tourism System**, not a consumer mobile app bolted onto a Laravel website.

| Component | Role |
|---|---|
| Flutter Kiosk | Physical self-service terminal client |
| Laravel Backend / REST API | Business Authority and Single Source of Truth |
| Laravel Blade + Livewire Dashboard | Assisted-service + operations + management client |
| MySQL | Persistent authoritative data store |
| External systems | Payment, printer, scanner, optional gate, optional CCTV/AI |

Both Kiosk and Dashboard **must** rely on the **same** orders, transactions/payments, ticketing, QR validation, check-in, business rules, financial rules, authorization, and audit system.

The system must remain: **scalable, secure, maintainable, testable, auditable, production-ready.**

---

## 1. CRITICAL RULE

**NEVER** implement critical business logic independently in:

- Flutter
- Blade
- Livewire
- JavaScript

Critical business logic belongs in **Laravel**.

Clients communicate with Laravel.  
**Laravel decides.**

Examples of critical logic (non-exhaustive): pricing, discounts, tax, service fee, totals, payment status, refund eligibility, ticket status, QR validity, check-in eligibility, permissions, order/payment state transitions.

---

## 2. SOURCE OF TRUTH

Laravel is authoritative for:

- Orders
- Transactions
- Payments
- Refunds
- Tickets
- QR validation
- Check-in
- Pricing
- Discounts
- Taxes
- Service fees
- Financial calculations
- Permissions
- Roles
- Business rules
- Audit logs
- Device state

**Never trust as authority:** `client_price`, `client_total`, `client_payment_status`, `client_ticket_status`, `client_permission`, `client_checkin_status`.

---

## 3. CLIENT RULE

- Flutter Kiosk is a **client**.
- Blade + Livewire Dashboard is a **client**.

Neither client may independently determine authoritative business state.

Clients may:

- Collect input
- Render server results
- Hold UI / session / retry / cache state
- Trigger hardware (print/scan) using server payloads

Clients may **not**:

- Invent PAID / ISSUED / USED
- Override totals
- Open a gate without server check-in success
- Bypass RBAC via hidden UI

---

## 4. STAFF RULE

**Do not** create a Staff Flutter application for MVP.

Staff use the **Laravel Dashboard**.

A future staff mobile app is allowed only with a clear operational need (field/offline/outdoor validation) and an explicit product decision.

---

## 5. KIOSK RULE

Treat Flutter as a **physical terminal**, not a generic mobile application.

Always consider:

- Device identity (`device_id` / `terminal_id`)
- Registration and activation
- Heartbeat and online/offline derivation
- Maintenance mode and remote disable
- Device authentication (Sanctum device token)
- Printer / scanner / payment terminal integration
- Locked fullscreen / landscape kiosk mode
- Crash recovery and safe restart
- Network recovery without fake success
- Telemetry / software version reporting
- Target profile: Advan A10 class tablet, landscape, public mount

Local kiosk storage is **never** the ledger.

---

## 6. FINANCIAL RULE

Never trust financial values supplied by clients.

Laravel calculates authoritative:

- subtotal
- discount
- tax
- service fee
- grand total
- payment amount
- refund amount

Money is **IDR integer (whole Rupiah)** unless docs explicitly change that standard.  
Reject or ignore client authoritative money fields at the API boundary.

---

## 7. PAYMENT RULE

- Payment status must be verified **server-side**.
- Never mark PAID from kiosk/dashboard assertion alone.
- Webhook processing must be **secure** (signature/secret) and **idempotent**.
- Duplicate callbacks must not duplicate payments or tickets.
- Cash assisted payments still create authoritative audited payment records.
- Unpaid flows expire via server TTL/jobs.

---

## 8. CHECK-IN RULE

- Ticket validation and check-in must be performed by **Laravel**.
- Prevent duplicate check-in (transactional; unique consumption for MVP single-entry).
- Never: Scanner → Flutter → Gate open without Laravel validation/check-in.
- Optional gate open only **after** authoritative check-in success.
- CCTV/AI must **not** silently mutate ticket records.

---

## 9. OFFLINE RULE

Local kiosk state must **never** automatically become authoritative transaction state.

Use:

- Idempotency keys
- Status polling
- Reconciliation jobs
- Safe retries

On connectivity loss: block fake success; guide retry or assisted counter.

---

## 10. DATABASE RULE

- Never modify an existing **production** migration.
- Create a **new** migration for schema changes.
- Use proper FK constraints, uniques, and indexes (see `docs/06-DATABASE.md`).
- Do not soft-delete payments, tickets, check-ins, or audit rows.
- Preserve financial integrity and uniqueness: order numbers, payment numbers, ticket codes, QR hashes, check-in ticket uniqueness, device ids, idempotency keys, webhook event ids.

---

## 11. ARCHITECTURE RULES

| Rule | Detail |
|---|---|
| Shape | Modular **monolith** (Laravel). No microservices without strong justification |
| Shared domain | Kiosk API and Dashboard Livewire call the **same Actions/Services** |
| Channel | `kiosk` vs `assisted` is metadata — not a logic fork |
| Structure | Follow `docs/10-PROJECT-STRUCTURE.md` |
| Repo | `backend/` · `kiosk/` · `docs/` · `.cursor/` |
| Stack | Laravel 13.x · PHP 8.4.x · MySQL 8.4.x · Flutter kiosk · Blade + Livewire + Tailwind · Sanctum (device) + session (staff) |
| Versions | Do not invent installed patch versions; verify from environment / lockfiles |

---

## 12. LARAVEL / PHP CONVENTIONS

- PHP 8.4.x style; prefer native Laravel features over unnecessary packages.
- Thin controllers; fat domain lives in **Actions** / **Services**.
- Form Requests validate input; Policies authorize; Actions orchestrate.
- Enums for statuses where practical.
- API Resources for response shaping (`docs/08-API-CONTRACT.md`).
- Prefer Eloquent in Actions/Services; **no Repository layer by default**.
- Jobs for webhooks/reconciliation/notifications; keep check-in synchronous.
- Events/Listeners sparingly; do not create event theater.
- Notifications for ops alerts, not visitor marketing spam in MVP.
- Keep code readable, boring, and consistent with Laravel norms (Pint when available).

---

## 13. MODELS · CONTROLLERS · SERVICES · ACTIONS · FORM REQUESTS · POLICIES

| Layer | Responsibility |
|---|---|
| Models | Persistence, relations; minimal branching |
| Controllers | HTTP in/out → Action → Resource |
| Form Requests | Validation; reject client money authority fields |
| Policies | RBAC from `docs/07-RBAC.md` |
| Actions | Use-case entry (CreateOrder, InitiatePayment, CheckInTicket, …) |
| Services | Shared domain (Pricing, state transitions) |

Livewire components call Actions — they are not a second domain.

---

## 14. JOBS · EVENTS · LISTENERS · NOTIFICATIONS

- Webhooks: verify → persist event id → process idempotently (queue OK).
- Scheduled: expire unpaid, reconcile payments, expire tickets, detect offline devices.
- Failed jobs must be inspectable.
- Do not put gate ALLOW authority solely in eventually-consistent jobs.

---

## 15. LIVEWIRE · BLADE

- Dashboard = assisted-service + ops + management (not CRUD-only).
- Role-specific navigation from permissions.
- Avoid huge Livewire components; split by page/flow.
- **No business logic in Blade**; Blade renders.
- No authoritative totals computed only in Alpine/JS.

---

## 16. FLUTTER ARCHITECTURE

Follow `kiosk/` boundaries in `docs/10-PROJECT-STRUCTURE.md`:

`core` · `network` · `storage` · `security` · `device` · `printer` · `scanner` · `payment` · `features` · `shared`

Rules:

- Landscape locked fullscreen terminal UX (`docs/09-UI-UX.md`)
- Large touch targets; Bahasa Indonesia first
- Idempotency keys on critical writes
- Map API errors to honest UX (pending / fail / recovery)
- Printer failure after PAID → show QR + staff reprint path; do not void payment

---

## 17. NETWORKING · API

- Versioned REST under `/api/v1`
- Consistent error envelope and codes
- Rate limiting on auth, payment, validation
- Device token least privilege
- Staff API (if any) uses server-side permissions
- Contract source: `docs/08-API-CONTRACT.md`

---

## 18. DEVICE · PRINTER · SCANNER · PAYMENT · QR

| Integration | Rule |
|---|---|
| Device | Register → activate → heartbeat → maintenance/disable; tokens revoked on disable |
| Printer | Print **server** print-payload; reprint audited; no second active ticket |
| Scanner | Input only; decision in Laravel |
| Payment | Adapter interface; one MVP provider; server finality |
| QR | Server-generated, server-verified; validate checks authenticity + business rules |

Vendor specifics (`PAYMENT_PROVIDER`, printer, scanner, gate) remain TBD until chosen — use adapters, do not hardcode myths.

---

## 19. SECURITY

- Never commit credentials or secrets.
- Never expose secrets in responses, logs, or clients.
- Never trust client validation alone.
- Validate uploads if/when introduced.
- Prevent IDOR (scope orders/tickets to principal).
- Prevent privilege escalation (`roles.assign` tightly held).
- Server-side authorization always.
- Protect webhooks (signature verification).
- Rate limiting where appropriate.
- TLS in deployed environments.
- Minimal PII on kiosk MVP.

RBAC source: `docs/07-RBAC.md`.

---

## 20. TESTING

Prioritize:

- Order → pay → issue → check-in
- Idempotent retries (order/payment/webhook)
- Double check-in prevention
- Pricing consistency across channels
- RBAC allow/deny matrix
- Webhook signature failure
- Refund eligibility

Do not ship critical money/ticket paths without tests.

---

## 21. LOGGING · ERROR HANDLING · AUDIT

- Structured logs with order/payment/ticket/check-in correlation ids where possible.
- Do not log secrets or full sensitive payloads.
- Payment uncertainty stays non-success until reconciled.
- Domain errors map to stable API/UX codes.
- Audit critical mutations (see business flow / RBAC docs); append-only.

---

## 22. GIT

- Do not commit `.env`, keys, tokens, or production dumps.
- Prefer small, focused commits when asked to commit.
- Do not rewrite production migration history.
- Do not force-push protected branches unless explicitly requested by a human with clear intent.
- Do not update git config unless explicitly requested.

---

## 23. DOCUMENTATION

Normative docs live under `docs/`:

| # | Document |
|---|---|
| 01 | Product Validation |
| 02 | PRD |
| 03 | SRS |
| 04 | System Design |
| 05 | Business Flow |
| 06 | Database |
| 07 | RBAC |
| 08 | API Contract |
| 09 | UI/UX |
| 10 | Project Structure |
| 11 | Roadmap (planning; not a business-rule override) |
| 12 | Security checklist (MVP pilot sign-off; operational) |

Update docs when behavior intentionally changes. Do not silently diverge.

---

## 24. SOURCE OF TRUTH HIERARCHY

When deciding behavior, use this order:

1. Business Requirements  
2. Product Validation (`docs/01-PRODUCT-VALIDATION.md`)  
3. PRD (`docs/02-PRD.md`)  
4. SRS (`docs/03-SRS.md`)  
5. System Design (`docs/04-SYSTEM-DESIGN.md`)  
6. Business Flow (`docs/05-BUSINESS-FLOW.md`)  
7. Database Design (`docs/06-DATABASE.md`)  
8. RBAC (`docs/07-RBAC.md`)  
9. API Contract (`docs/08-API-CONTRACT.md`)  
10. UI/UX (`docs/09-UI-UX.md`)  
11. Project Structure (`docs/10-PROJECT-STRUCTURE.md`)  
12. Implementation  
13. Tests  

### Conflict protocol

If implementation conflicts with documented business rules:

**STOP.**  
Do **not** silently invent a new rule.

Explain:

1. The conflict  
2. Current implementation  
3. Documented requirement  
4. Potential impact  
5. Recommended solution  

Then wait for an explicit human decision (change docs, or change code).

---

## 25. CODE QUALITY

- Keep controllers thin.
- Avoid huge Livewire components.
- Avoid business logic inside Blade.
- Avoid business logic inside Flutter UI.
- Avoid duplicated business logic across channels.
- Avoid unnecessary packages.
- Prefer Laravel-native solutions.
- Prefer clear names over clever abstractions.
- Do not build Staff Flutter “for symmetry.”
- Do not build consumer tourist app inside this MVP scope.

---

## 26. FINAL PRINCIPLE

Design **one system**:

**WanderDesa Integrated Tourism System**

with:

- Flutter Kiosk as Self-Service Client  
- Laravel Dashboard as Assisted-Service and Management Client  
- Laravel Backend as Business Authority  
- MySQL as Persistent Data Store  
- External Systems as Integrations  

Not:

Flutter Application  
+  
Laravel Website  

Same ledger. Same rules. Two clients. One authority.

---

*This file is binding for AI-assisted implementation of WanderDesa.*
