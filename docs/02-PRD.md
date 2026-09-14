# WanderDesa — Product Requirements Document (PRD)

**Document ID:** `02-PRD`  
**File:** `docs/02-PRD.md`  
**Status:** Draft for implementation planning  
**Based on:** `docs/01-PRODUCT-VALIDATION.md`  
**Product decision inherited:** GO — with modified MVP scope  

---

## 1. Document Information

| Field | Value |
|---|---|
| Product name | WanderDesa |
| Document type | Product Requirements Document |
| Version | 1.0.0 |
| Date | 2026-09-13 |
| Owner | Product Management |
| Audience | Product, Engineering, QA, Operations, Stakeholders |
| Related docs | `01-PRODUCT-VALIDATION.md` |
| Code in this phase | **None** — requirements only |

### Authority principles (binding)

1. Flutter is a **physical kiosk / self-service terminal client**, not a consumer mobile app.
2. Laravel Backend/API is the **business authority** and **Single Source of Truth**.
3. Laravel Blade + Livewire Dashboard supports **assisted-service transactions** and management.
4. Self-service (kiosk) and assisted-service (dashboard) **must share** the same backend business rules for orders, payments, tickets, QR validation, check-in, authorization, and audit.
5. Clients may request operations; Laravel decides, calculates, persists, and returns authoritative results.
6. Never trust client-supplied price, total, payment status, ticket status, permission, or check-in status as authority.

### Placeholder / environment notes

| Item | Value |
|---|---|
| PHP (local inspected) | 8.4.25 |
| MySQL (local inspected) | 8.4.11 |
| Flutter (local inspected) | 3.44.9 · Dart 3.12.2 |
| Laravel version | TBD — Requires Environment Verification |
| Payment provider | TBD |
| QR provider / format | TBD (server-verifiable required) |
| Printer | TBD |
| Scanner | TBD |
| Gate controller | Optional / TBD |
| CCTV / AI | Optional / TBD |

---

## 2. Product Overview

WanderDesa is an **Integrated Tourism System** that enables:

1. **Self-service ticketing** via physical Flutter kiosks at the destination.
2. **Assisted-service and management** via a Laravel Blade + Livewire dashboard for authorized internal users.
3. **Shared authoritative processing** of orders, payments, ticket issuance, QR validation, check-in, reporting, and audit through Laravel + MySQL.

### System constituents

| # | Component | Role |
|---|---|---|
| 1 | Flutter Kiosk / Self-Service Terminal | Public visitor purchase client on physical device |
| 2 | Laravel Backend / REST API | Business authority |
| 3 | Laravel Blade + Livewire Dashboard | Staff assisted-service + ops + admin |
| 4 | MySQL Database | Authoritative persistence |
| 5 | Payment integrations | Server-verified payment processing |
| 6 | QR scanner integrations | Entry validation input |
| 7 | Ticket printer integrations | Physical ticket output |
| 8 | Optional gate controller | Actuation after server check-in success |
| 9 | Optional CCTV / AI | Analytics / anomaly comparison only |

---

## 3. Product Vision

**Vision:** Every ticket sold at a destination — whether by kiosk or by staff — exists in one trustworthy ledger that also controls payment truth, ticket authenticity, and entry validation.

**North star:** One destination ledger. Two service channels. Zero split-brain operations.

---

## 4. Business Context

| Dimension | Description |
|---|---|
| Domain | Tourism / Ticketing / Visitor Management / Self-Service Kiosk / Tourism Operations |
| Market | Indonesia / Tourism Destination / Tourism Operator / Commercial Tourism Management |
| Commercial wedge | On-site purchase + access control for destination operators |
| Primary deployment | Destination premises (kiosk) + VPS/Cloud/On-Premise backend |
| Target kiosk hardware | Advan A10 class Android tablet, landscape, locked fullscreen |

Indonesian destinations commonly still rely on manual counters, paper tickets, and disconnected tools. WanderDesa targets operators who need queue reduction, payment reconciliation, and reliable gate/check-in control without adopting a consumer-app-first model.

---

## 5. Problem Statement

Proses pembelian tiket, pembayaran, penerbitan tiket, validasi tiket, check-in, dan operasional wisata masih dapat dilakukan secara manual atau melalui sistem yang terpisah.

This causes queues, inconsistent pricing, weak reconciliation, unreliable ticket authenticity, double-entry risk, opaque reporting, and staff bottlenecks.

**Product response:** Unify Channel A (kiosk self-service) and Channel B (dashboard assisted-service) under one Laravel authority.

---

## 6. Product Objectives

| ID | Objective | Testable outcome |
|---|---|---|
| O1 | Enable visitor self-service ticket purchase on physical kiosk | Visitor completes buy → pay → issue without staff in happy path |
| O2 | Enable staff assisted-service on dashboard | Authorized staff completes buy → pay → issue for a visitor |
| O3 | Enforce single pricing/ticket authority | Same ticket type + qty yields same authoritative totals on both channels |
| O4 | Ensure payment truth is server-side | Ticket issuance requires authoritative PAID (or policy-equivalent) state |
| O5 | Ensure ticket authenticity and single-use check-in | Invalid/used/cancelled/refunded/expired tickets fail validation |
| O6 | Provide operational visibility | Manager/Finance can view daily sales and ticket usage by channel |
| O7 | Manage kiosks as devices | Each kiosk is registered, heartbeats, and can be remotely disabled |
| O8 | Preserve auditability | Critical actions are attributable and timestamped |

---

## 7. Success Metrics

Metrics are MVP-oriented and must be measurable in staging/production.

| ID | Metric | Target (MVP) | Measurement |
|---|---|---|---|
| M1 | Kiosk happy-path completion rate | ≥ 90% of started paid sessions reach ISSUED ticket(s) under normal network | Funnel: start → order → PAID → ISSUED |
| M2 | Assisted sale median time | ≤ 90 seconds for single-ticket cash/digital happy path | Dashboard timing logs / observational test |
| M3 | Pricing consistency | 100% identical totals for identical inputs across channels | Automated comparison tests |
| M4 | Duplicate prevention | 0 duplicate orders/payments/tickets under idempotent retry tests | QA idempotency suite |
| M5 | Double check-in prevention | 100% rejection of second check-in for single-entry tickets | Gate/validation tests |
| M6 | Payment→ticket integrity | 0 ISSUED tickets without valid PAID payment linkage | Invariant query / audit |
| M7 | Daily reconciliation usability | Finance can produce paid vs issued report for a day in ≤ 5 minutes | UAT |
| M8 | Kiosk fleet visibility | Operator sees online/offline/maintenance for each device | Dashboard status view |
| M9 | Critical audit coverage | 100% of refund/cancel/override/check-in events audited | Audit completeness tests |

---

## 8. Target Users

- Tourists / Visitors (Wisatawan)
- Staff
- Ticket Officers
- Gate Officers
- Operators
- Managers
- Finance
- Administrators
- Auditors
- System Administrators

---

## 9. User Personas

### P1 — Visitor / Wisatawan
Wants fast on-site purchase, clear price, familiar payment, printable/usable ticket, unambiguous entry.

### P2 — Ticket Officer
Needs fast assisted order/pay/print for cash, groups, confused visitors, or exceptions.

### P3 — Gate Officer
Needs clear ALLOW/DENY validation results and protection against reuse.

### P4 — Operator
Owns kiosk uptime, printers, maintenance mode, and shift continuity.

### P5 — Manager
Needs sales, channel mix, and entry/usage visibility for decisions.

### P6 — Finance
Needs trustworthy paid/refunded/expired records and provider references.

### P7 — Administrator
Configures destinations, ticket types, prices, users, roles, kiosks, and rules.

### P8 — Auditor
Reviews who did what on money, tickets, overrides, and check-ins.

### P9 — System Administrator
Manages deployment, credentials, backups, availability, and device activation secrets.

---

## 10. User Roles

Roles are logical product roles. Permissions are fine-grained (Section 29). One user may hold one primary role in MVP.

| Role code | Name | Primary channel | Purpose |
|---|---|---|---|
| `visitor` | Visitor | Kiosk (unauthenticated public flow via device auth) | Self-service purchase |
| `ticket_officer` | Ticket Officer | Dashboard | Assisted sales |
| `gate_officer` | Gate Officer | Dashboard (validation/check-in UI) | Entry validation |
| `operator` | Operator | Dashboard | Device/ops monitoring |
| `manager` | Manager | Dashboard | Operational reports |
| `finance` | Finance | Dashboard | Settlement/reconciliation views |
| `admin` | Administrator | Dashboard | Configuration & user admin |
| `auditor` | Auditor | Dashboard | Read-only audit/report access |
| `sysadmin` | System Administrator | Dashboard + infra | System/device security controls |
| `staff` | Generic Staff | Dashboard | Limited operational helper (optional mapping) |

**Note:** Kiosk visitors are not dashboard users. Kiosk access is via **device authentication**, not visitor login, in MVP.

---

## 11. Visitor Journey

```text
Arrive at destination
  → Choose channel: Kiosk (self) OR Counter (assisted)
  → Select ticket type & quantity
  → Confirm order summary (authoritative totals from Laravel)
  → Pay
  → Receive issued ticket(s) + QR (print and/or on-screen proof per policy)
  → Proceed to entry point
  → Present QR for validation
  → Enter on ALLOW
```

**Acceptance:** A visitor can complete either channel and receive a ticket that passes validation when rules are met.

---

## 12. Self-Service Kiosk Journey

```text
Idle / attract screen (registered, active, not maintenance)
  → Visitor starts session
  → Select ticket type(s) / quantity / visit date if required
  → Request quote/totals from Laravel (display only authoritative values)
  → Confirm → Create Order (idempotent)
  → Initiate Payment
  → Wait for server payment state
  → On PAID: server issues ticket(s)+QR
  → Print ticket(s) / show success
  → Session ends / returns to idle
```

### Kiosk journey rules (testable)

| ID | Rule |
|---|---|
| KJ-01 | Unregistered or inactive kiosk cannot create orders |
| KJ-02 | Maintenance or remotely disabled kiosk shows unavailable state and blocks purchase |
| KJ-03 | Client cannot submit its own final price as authority |
| KJ-04 | Payment success shown only after Laravel reports PAID (or equivalent terminal success state) |
| KJ-05 | Retry of create-order/payment with same idempotency key does not duplicate records |
| KJ-06 | Network loss during payment shows pending/unknown guidance; does not locally mark PAID |

---

## 13. Assisted-Service Journey

```text
Staff authenticates to Dashboard
  → Opens Assisted Sale
  → Selects ticket type(s)/qty (and visitor notes if required)
  → Laravel returns authoritative totals
  → Staff confirms order create
  → Staff collects payment (cash and/or digital method per config)
  → Staff confirms / system confirms payment → PAID
  → System issues ticket(s)+QR
  → Staff prints/delivers ticket
  → Visitor proceeds to gate
```

### Assisted journey rules (testable)

| ID | Rule |
|---|---|
| AJ-01 | Only users with `orders.create` + `payments.collect` (or role equivalents) can complete sale |
| AJ-02 | Cash payment creates an authoritative payment record with actor identity |
| AJ-03 | Same pricing engine as kiosk for identical ticket inputs |
| AJ-04 | Channel marked `assisted` on order/tickets |
| AJ-05 | Issuance cannot occur without valid payment state per business rules |

---

## 14. Staff Journey

Staff journeys vary by role:

| Role | Typical journey |
|---|---|
| Ticket Officer | Login → Assisted sale → Print → Logout/shift end |
| Gate Officer | Login → Scan/validate → ALLOW/DENY → Handle exception with authorized override if permitted |
| Operator | Login → Monitor kiosks → Toggle maintenance / escalate printer issues |
| Manager | Login → View live/daily reports → Investigate anomalies |
| Finance | Login → Reconciliation reports → Export/filter payment states |
| Admin | Login → Configure catalog/prices/users/kiosks |
| Auditor | Login → Read audit logs / critical event history |
| SysAdmin | Login → Device activation, security settings, system health |

**MVP constraint:** No dedicated Staff Flutter application.

---

## 15. Ticketing Journey

```text
Order line created
  → Payment reaches PAID
  → Ticket records created/issued with unique IDs
  → QR payload bound to ticket
  → Ticket becomes ACTIVE (valid for entry per validity window)
  → On successful check-in → USED (for single-entry MVP tickets)
  → Or EXPIRED / CANCELLED / REFUNDED per rules
```

### Ticketing invariants (testable)

| ID | Invariant |
|---|---|
| TJinv-01 | Every ticket references an order and payment linkage consistent with PAID |
| TJinv-02 | Ticket codes/IDs are unique |
| TJinv-03 | Ticket validity window evaluated server-side at validation time |
| TJinv-04 | Channel of issuance is stored (`kiosk` \| `assisted`) |
| TJinv-05 | Re-issuance/reprint does not create a second active ticket unless policy explicitly allows replacement with audit |

---

## 16. Payment Journey

```text
Order created (totals authoritative)
  → Payment record PENDING
  → PROCESSING when provider/cash confirmation started
  → PAID on verified success
  → or FAILED / EXPIRED / CANCELLED
  → REFUNDED only via authorized refund flow
```

### Payment invariants (testable)

| ID | Invariant |
|---|---|
| PJinv-01 | Client cannot force PAID |
| PJinv-02 | Provider webhook/callback signature (or equivalent) verified before state advance |
| PJinv-03 | Duplicate callbacks do not create duplicate payments or tickets |
| PJinv-04 | Unpaid orders expire per configured TTL and move to EXPIRED (payment/order policy) |
| PJinv-05 | Provider reference IDs stored when applicable for reconciliation |

---

## 17. QR Validation Journey

```text
Scanner/operator captures QR
  → Request sent to Laravel validate endpoint
  → Laravel verifies authenticity + ticket business rules + actor authorization + destination/gate context
  → Returns ALLOW or DENY with reason codes
```

### Validation checks (must all be server-side)

1. Ticket exists  
2. QR/ticket authentic (not forged)  
3. Destination/context matches  
4. Related payment valid  
5. Ticket status eligible (e.g. ACTIVE)  
6. Not expired  
7. Not cancelled  
8. Not refunded  
9. Not already used (MVP single-entry)  
10. Actor/device authorized  
11. Replay considerations addressed (nonce/timestamp/one-time use as designed)

---

## 18. Check-in Journey

```text
Validation ALLOW candidate
  → Begin DB transaction
  → Re-check ticket eligibility under lock
  → Create check-in record
  → Transition ticket ACTIVE → USED
  → Commit
  → Return success
  → (Optional) trigger gate open command
```

| ID | Requirement |
|---|---|
| CI-01 | Check-in is transactional |
| CI-02 | Concurrent double scan results in exactly one success for single-entry tickets |
| CI-03 | Check-in stores ticket_id, actor/device, timestamp, destination/gate context, channel source |
| CI-04 | Failed validation never creates check-in |

---

## 19. Gate Entry Journey

```text
QR scan
  → Laravel validation + check-in success
  → Gate controller command (optional module)
  → Gate opens
```

| ID | Requirement |
|---|---|
| GE-01 | Gate must not open based solely on Flutter/local decision |
| GE-02 | Gate open is allowed only after authoritative check-in success |
| GE-03 | If gate module disabled, ALLOW still records check-in for manual barrier ops |
| GE-04 | Offline gate strategy is **out of MVP** until explicitly designed |

---

## 20. Product Scope

### In product (overall)
- Dual-channel ticketing (kiosk + assisted)
- Shared backend domain for money and tickets
- QR validation and check-in
- Role-based dashboard operations
- Kiosk device management basics
- Reporting and audit for operations/finance
- One Indonesia-relevant payment path
- Printer + scanner integration path

### Shared capability matrix (mandatory)

| Capability | Required |
|---|---|
| Self-Service Kiosk | Yes |
| Assisted-Service Dashboard | Yes |
| Shared Backend | Yes |
| Shared Ticketing | Yes |
| Shared Payment | Yes |
| Shared Check-in | Yes |
| Shared Authorization | Yes |

---

## 21. MVP Scope

### MVP includes
1. Destination + ticket catalog + server pricing  
2. Order create with idempotency  
3. Payment states + one digital payment path + assisted cash path  
4. Ticket issue + QR  
5. Print path  
6. Validate + check-in (single-entry)  
7. Flutter kiosk happy path on Advan A10 class landscape device  
8. Dashboard assisted sale + ticket lookup + basic reports + kiosk status  
9. RBAC + audit on critical actions  
10. Kiosk registration, heartbeat, maintenance/remote disable  

### MVP success definition
Visitor or staff can sell a ticket; visitor can enter once via QR; finance can reconcile the day; retries do not duplicate money/tickets.

---

## 22. Out of Scope

| Item | Phase |
|---|---|
| Dedicated Staff Flutter app | Future (only if field/offline need proven) |
| Consumer tourist mobile app | Out / separate product |
| OTA multi-destination marketplace | Out |
| Offline authoritative selling on kiosk | Out of MVP |
| Gate controller deep integration | Optional post-MVP |
| CCTV/AI ticket mutation | Never |
| CCTV/AI analytics dashboard | Post-MVP optional |
| Loyalty points ecosystem | Out of MVP |
| Multi-payment-provider mesh | Out of MVP (adapter OK, one provider live) |
| Advanced BI warehouse | Out of MVP |
| Dynamic promo engine / vouchers | Post-MVP |
| Multi-entry tickets | Post-MVP unless explicitly added |
| White-label franchise portal | Out |

---

## 23. Functional Requirements

Requirements ID format: `FR-<AREA>-###`. Each is testable.

### 23.1 Catalog & pricing

| ID | Requirement |
|---|---|
| FR-CAT-001 | Admin can create/update/deactivate destinations |
| FR-CAT-002 | Admin can create/update/deactivate ticket types bound to a destination |
| FR-CAT-003 | Admin can set ticket unit price and optional tax/service-fee rules |
| FR-CAT-004 | System calculates subtotal, discount (if any), tax, service fee, and grand total server-side |
| FR-CAT-005 | Quote/totals API returns authoritative amounts used by both channels |
| FR-CAT-006 | Inactive ticket types cannot be sold on either channel |

### 23.2 Orders

| ID | Requirement |
|---|---|
| FR-ORD-001 | System creates orders with unique IDs |
| FR-ORD-002 | Orders store channel (`kiosk` \| `assisted`), actor/device, timestamps |
| FR-ORD-003 | Order create accepts idempotency key; repeats return same order |
| FR-ORD-004 | Order lines store ticket type, qty, unit price snapshot, line totals from server |
| FR-ORD-005 | Clients cannot override authoritative money fields |
| FR-ORD-006 | Unpaid orders expire per configured TTL |

### 23.3 Payments

| ID | Requirement |
|---|---|
| FR-PAY-001 | Every order payment has explicit status from Section 27 |
| FR-PAY-002 | Digital payment initiation creates PENDING/PROCESSING server records |
| FR-PAY-003 | Webhook/callback verification required before PAID |
| FR-PAY-004 | Duplicate provider events are idempotent |
| FR-PAY-005 | Assisted cash payment requires authorized staff confirmation and audit |
| FR-PAY-006 | PAID payment stores amount equal to authoritative order total (unless partial-pay explicitly supported — **not in MVP**) |
| FR-PAY-007 | Refunds only via authorized flow; creates REFUNDED state and audit |

### 23.4 Tickets & QR

| ID | Requirement |
|---|---|
| FR-TKT-001 | On PAID, system issues N tickets for purchased quantities |
| FR-TKT-002 | Each ticket has unique public code and server-verifiable QR payload |
| FR-TKT-003 | Ticket status follows Section 27–28 |
| FR-TKT-004 | Reprint authorized action does not clone a second valid ticket by default |
| FR-TKT-005 | Ticket search by code/ID available to authorized dashboard roles |

### 23.5 Validation & check-in

| ID | Requirement |
|---|---|
| FR-VAL-001 | Validate endpoint performs all checks in Section 17 |
| FR-VAL-002 | DENY responses include stable reason codes |
| FR-VAL-003 | Successful check-in transitions ticket to USED (MVP single-entry) |
| FR-VAL-004 | Concurrent checks yield single success |
| FR-VAL-005 | Validation/check-in requires authorized actor or authorized scanner context |

### 23.6 Kiosk client

| ID | Requirement |
|---|---|
| FR-KIO-001 | Kiosk authenticates as a registered device |
| FR-KIO-002 | Kiosk supports landscape locked fullscreen purchase flow |
| FR-KIO-003 | Kiosk displays only server totals |
| FR-KIO-004 | Kiosk supports payment waiting / success / failure / timeout UX |
| FR-KIO-005 | Kiosk triggers print after ISSUED when printer available |
| FR-KIO-006 | Kiosk blocks sales when maintenance_mode or inactive |

### 23.7 Dashboard

| ID | Requirement |
|---|---|
| FR-DSH-001 | Users authenticate to dashboard |
| FR-DSH-002 | Menus/actions respect permissions |
| FR-DSH-003 | Assisted sale flow implements Section 13 |
| FR-DSH-004 | Gate officer can validate/check-in via dashboard UI |
| FR-DSH-005 | Operator/admin can view kiosk list and statuses |
| FR-DSH-006 | Manager/finance can view MVP reports in Section 37 |

### 23.8 Device management

| ID | Requirement |
|---|---|
| FR-DEV-001 | Admin/sysadmin can register kiosk devices |
| FR-DEV-002 | Device stores identity, location, status, versions, heartbeat, maintenance flags |
| FR-DEV-003 | Device sends heartbeat on interval |
| FR-DEV-004 | Authorized user can remotely disable or set maintenance mode |
| FR-DEV-005 | Disabled devices are rejected for order creation |

### 23.9 Audit & reports

| ID | Requirement |
|---|---|
| FR-AUD-001 | Critical actions write audit events (Section 39) |
| FR-RPT-001 | Daily sales report by channel and payment status |
| FR-RPT-002 | Tickets issued vs used vs cancelled/refunded for a date range |

---

## 24. Non-Functional Requirements

| ID | Category | Requirement | Acceptance |
|---|---|---|---|
| NFR-01 | Authority | All critical business decisions occur in Laravel | Architecture review + tests |
| NFR-02 | Consistency | Both channels use same domain services | Shared service tests |
| NFR-03 | Integrity | No ticket ISSUED without valid PAID linkage | Invariant tests |
| NFR-04 | Idempotency | Critical writes safe to retry | QA suite |
| NFR-05 | Security | RBAC enforced server-side | Permission tests |
| NFR-06 | Auditability | Critical mutations attributable | Audit tests |
| NFR-07 | Availability (MVP) | Backend target ≥ 99% during operating hours (ops-defined) | Monitoring |
| NFR-08 | Performance | Quote/order create p95 < 2s on normal LAN/VPN conditions | Load/smoke |
| NFR-09 | Performance | Validation/check-in p95 < 1.5s | Load/smoke |
| NFR-10 | Usability (kiosk) | Primary purchase path ≤ 5 screens before payment | UX review |
| NFR-11 | Usability (kiosk) | Touch targets suitable for Advan A10 landscape | Device UAT |
| NFR-12 | Resilience | Payment uncertainty states are explicit; no local fake PAID | Failure injection tests |
| NFR-13 | Privacy | MVP collects minimal visitor PII; document fields collected | Privacy checklist |
| NFR-14 | Observability | Structured logs for order/payment/ticket/check-in IDs | Log review |
| NFR-15 | Locale | Kiosk MVP supports Bahasa Indonesia; English optional if capacity | UAT |
| NFR-16 | Time | All authoritative timestamps stored in UTC; display local TZ configurable | Data checks |
| NFR-17 | Currency | MVP currency IDR | Config assertion |

---

## 25. User Stories

Format: *As a … I want … so that …* with acceptance pointers.

### Visitors / Kiosk
1. As a visitor, I want to buy a ticket on the kiosk without staff so that I can skip the counter queue. **AC:** KJ-01–06, FR-KIO-*  
2. As a visitor, I want to see the final price before paying so that I am not surprised. **AC:** FR-CAT-004/005  
3. As a visitor, I want a printed/usable QR ticket after payment so that I can enter. **AC:** FR-TKT-001/002, FR-KIO-005  

### Ticket Officer
4. As a ticket officer, I want to create an assisted order quickly so that I can serve cash/help-needed visitors. **AC:** AJ-*, FR-DSH-003  
5. As a ticket officer, I want the system to calculate totals so that I do not misprice. **AC:** FR-CAT-004  

### Gate Officer
6. As a gate officer, I want clear ALLOW/DENY with reason so that I can manage entry disputes. **AC:** FR-VAL-002  
7. As a gate officer, I want reused tickets to be rejected so that fraud is reduced. **AC:** FR-VAL-003/004  

### Operator
8. As an operator, I want to see which kiosks are offline/maintenance so that I can fix them. **AC:** FR-DEV-003/005, FR-DSH-005  
9. As an operator, I want to put a kiosk in maintenance mode so that visitors are not mid-failure. **AC:** FR-DEV-004, FR-KIO-006  

### Manager / Finance
10. As a manager, I want daily sales by channel so that I can evaluate kiosk ROI. **AC:** FR-RPT-001  
11. As finance, I want payment statuses and provider refs so that I can reconcile. **AC:** FR-PAY-005/007, FR-RPT-001  

### Admin / Auditor / SysAdmin
12. As an admin, I want to configure ticket types and prices so that selling rules are centralized. **AC:** FR-CAT-*  
13. As an auditor, I want an audit trail of refunds and overrides so that abuse is detectable. **AC:** FR-AUD-001  
14. As a sysadmin, I want to register/activate kiosks so that rogue devices cannot sell. **AC:** FR-DEV-001/005  

---

## 26. Business Rules

| ID | Rule |
|---|---|
| BR-01 | Laravel is sole authority for price, tax, fee, discount, total |
| BR-02 | Laravel is sole authority for payment status |
| BR-03 | Laravel is sole authority for ticket status and check-in |
| BR-04 | Kiosk and assisted channels share identical pricing and issuance rules for the same inputs |
| BR-05 | Tickets cannot be issued unless payment is PAID (MVP) |
| BR-06 | MVP tickets are single-entry: first successful check-in consumes ticket |
| BR-07 | USED, EXPIRED, CANCELLED, REFUNDED tickets cannot check in |
| BR-08 | Inactive kiosks cannot create orders |
| BR-09 | Maintenance-mode kiosks cannot create orders |
| BR-10 | Refunds require explicit permission and produce audit + ticket impact per policy |
| BR-11 | Cancels of unpaid orders do not issue tickets |
| BR-12 | Idempotency keys required on order create and payment initiate |
| BR-13 | Partial payments are not supported in MVP |
| BR-14 | Negative quantities / zero-price bypasses are rejected unless admin configures a free ticket type explicitly |
| BR-15 | Clock/validity evaluated server-side at validation time |
| BR-16 | AI/CCTV events must not mutate ticket status |
| BR-17 | Gate actuation only after successful check-in |
| BR-18 | Staff Flutter app is not part of product behavior in MVP |
| BR-19 | Order channel is immutable after creation |
| BR-20 | Money field snapshots on order lines are retained for historical accuracy if catalog prices later change |

---

## 27. Status Definitions

Statuses below are **MVP canonical**. Clients display them; only Laravel transitions them.

### 27.1 Order status

| Status | Meaning |
|---|---|
| `DRAFT` | Optional internal pre-submit (if used); not sellable terminal state |
| `PENDING_PAYMENT` | Order created; awaiting payment |
| `PAID` | Order fully paid |
| `CANCELLED` | Cancelled before/without successful payment completion |
| `EXPIRED` | Unpaid beyond TTL |
| `REFUNDED` | Fully refunded after prior PAID (MVP assumes full refund only) |

### 27.2 Payment status

| Status | Meaning |
|---|---|
| `PENDING` | Payment record created; not completed |
| `PROCESSING` | Submitted to provider or awaiting staff cash confirmation finalization |
| `PAID` | Authoritatively paid |
| `FAILED` | Failed attempt |
| `EXPIRED` | Timed out unpaid |
| `CANCELLED` | Cancelled |
| `REFUNDED` | Refund completed |

### 27.3 Ticket status

| Status | Meaning |
|---|---|
| `PENDING` | Created before payment finality (if pre-created); not entry-eligible |
| `ISSUED` | Issued after PAID; may map immediately to ACTIVE depending on validity start |
| `ACTIVE` | Entry-eligible within validity window |
| `USED` | Successfully checked in (MVP single-entry) |
| `EXPIRED` | Past validity; unused |
| `CANCELLED` | Voided by policy |
| `REFUNDED` | Voided due to refund |

**Note:** `RESERVED` is deferred unless inventory hold is required; not needed for unlimited on-site MVP ticket types.

### 27.4 Kiosk device status

| Status | Meaning |
|---|---|
| `REGISTERED` | Known but not activated |
| `ACTIVE` | Allowed to operate |
| `MAINTENANCE` | Visible maintenance mode; sales blocked |
| `DISABLED` | Remotely/administratively disabled |
| `OFFLINE` | Derived if heartbeat stale (operational view) |

### 27.5 Check-in result codes (examples)

| Code | Meaning |
|---|---|
| `ALLOW` | Validation passed; check-in committed |
| `DENY_NOT_FOUND` | Ticket/QR unknown |
| `DENY_INVALID_AUTH` | Forged/invalid payload |
| `DENY_WRONG_DESTINATION` | Context mismatch |
| `DENY_NOT_PAID` | Payment not valid |
| `DENY_NOT_ACTIVE` | Status not entry-eligible |
| `DENY_EXPIRED` | Validity window failed |
| `DENY_CANCELLED` | Cancelled |
| `DENY_REFUNDED` | Refunded |
| `DENY_ALREADY_USED` | Already checked in |
| `DENY_UNAUTHORIZED` | Actor/device not permitted |

---

## 28. Status Transition Rules

Transitions must specify: from → to, actor, condition, audit.

### 28.1 Payment transitions

| From | To | Allowed actor/system | Condition |
|---|---|---|---|
| — | PENDING | System | Payment created |
| PENDING | PROCESSING | System/Staff | Provider start or cash confirm start |
| PROCESSING | PAID | System (verified webhook) / Staff (cash confirm permission) | Verification success |
| PROCESSING | FAILED | System | Provider failure |
| PENDING/PROCESSING | EXPIRED | System job | TTL exceeded |
| PENDING/PROCESSING | CANCELLED | Authorized staff/system | Cancel policy |
| PAID | REFUNDED | Authorized finance/admin | Refund policy completed |
| FAILED/EXPIRED/CANCELLED/REFUNDED | *terminal* | — | No reopen to PAID without new payment record |

### 28.2 Order transitions

| From | To | Condition |
|---|---|---|
| PENDING_PAYMENT | PAID | Linked payment PAID |
| PENDING_PAYMENT | CANCELLED | Authorized cancel / user abandon policy |
| PENDING_PAYMENT | EXPIRED | TTL |
| PAID | REFUNDED | Refund completed; tickets impacted |

### 28.3 Ticket transitions

| From | To | Condition |
|---|---|---|
| PENDING | ISSUED/ACTIVE | Payment PAID + issuance |
| ISSUED | ACTIVE | Validity start reached (or immediate) |
| ACTIVE | USED | Successful check-in |
| ACTIVE | EXPIRED | Validity end passed (job/on-validate) |
| ACTIVE/ISSUED/PENDING | CANCELLED | Authorized cancel before use |
| ACTIVE/ISSUED/USED* | REFUNDED | Refund policy (*if used, refund policy must define eligibility — default MVP: refund only if not USED) |

**MVP refund default:** Refund allowed only if ticket not `USED`, unless manager override permission exists and is audited.

### 28.4 Illegal examples (must fail tests)

- ACTIVE → PAID (nonsensical cross-entity)
- USED → ACTIVE without audited admin reverse (not in MVP)
- Client forcing PAID
- Check-in from CANCELLED

---

## 29. Permissions Requirements

Permissions are checked **server-side**. UI hiding is not security.

| Permission | Ticket Officer | Gate Officer | Operator | Manager | Finance | Admin | Auditor | SysAdmin |
|---|---|---|---|---|---|---|---|---|
| `orders.create` (assisted) | ✓ | | | | | ✓ | | |
| `payments.collect.cash` | ✓ | | | | | ✓ | | |
| `payments.collect.digital` | ✓ | | | | | ✓ | | |
| `tickets.print` | ✓ | | ✓ | | | ✓ | | |
| `tickets.reprint` | ✓* | | ✓* | | | ✓ | | |
| `tickets.lookup` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `checkin.perform` | | ✓ | | | | ✓ | | |
| `refunds.create` | | | | | ✓ | ✓ | | |
| `orders.cancel_unpaid` | ✓ | | | | | ✓ | | |
| `catalog.manage` | | | | | | ✓ | | |
| `users.manage` | | | | | | ✓ | | |
| `kiosk.view` | | | ✓ | ✓ | | ✓ | ✓ | ✓ |
| `kiosk.maintain` | | | ✓ | | | ✓ | | ✓ |
| `kiosk.register` | | | | | | ✓ | | ✓ |
| `reports.sales.view` | | | | ✓ | ✓ | ✓ | ✓ | |
| `audit.view` | | | | | | ✓ | ✓ | ✓ |

\* reprint may require same-shift or elevated permission — configure; must be audited.

Kiosk device credential may allow only: catalog read, quote, order create, payment initiate, payment status poll, ticket status poll for its own orders.

---

## 30. Kiosk Requirements

| ID | Requirement | Testable |
|---|---|---|
| KR-01 | Runs as physical terminal client on Android tablet (Advan A10 class) | Device UAT |
| KR-02 | Primary orientation landscape | UI assert |
| KR-03 | Locked fullscreen / kiosk mode intent | Device UAT |
| KR-04 | Device authentication required | API rejects without credentials |
| KR-05 | Heartbeat interval configurable; default ≤ 60s | Observed |
| KR-06 | Shows maintenance/unavailable screens when blocked | UI test |
| KR-07 | Purchase flow uses server quotes only | Contract test |
| KR-08 | Idempotency keys generated per attempt and reused on retry | Logs/tests |
| KR-09 | Local storage may hold UI/session/retry state only | Design/review + tests |
| KR-10 | Must not mark payment/ticket success from local cache alone | Failure tests |
| KR-11 | Bahasa Indonesia labels for MVP core screens | UAT |
| KR-12 | Large touch targets; readable outdoor/indoor contrast | UX UAT |
| KR-13 | Session timeout returns to idle without leaving unpaid confusion | UX test |
| KR-14 | Printer failure after PAID shows recovery guidance (reprint via staff if needed) | UAT |
| KR-15 | Software version reported in heartbeat | API payload |

---

## 31. Dashboard Requirements

| ID | Requirement | Testable |
|---|---|---|
| DR-01 | Secure login for staff users | Auth tests |
| DR-02 | Role-based navigation | Permission matrix tests |
| DR-03 | Assisted sale end-to-end | UAT AJ-* |
| DR-04 | Ticket lookup by code/ID | Functional test |
| DR-05 | Check-in/validation UI for gate role | Functional test |
| DR-06 | Kiosk fleet status list | Shows heartbeat/status |
| DR-07 | Maintenance/disable controls for authorized roles | Action + audit |
| DR-08 | Catalog/price admin screens | FR-CAT-* |
| DR-09 | User/role admin screens | Permission assign test |
| DR-10 | Sales and ticket usage reports | FR-RPT-* |
| DR-11 | Refund UI only for permitted roles | Negative permission test |
| DR-12 | Audit log viewer for auditor/admin | FR-AUD-001 |
| DR-13 | Dashboard is operational tool, not CRUD-only | Assisted sale present in MVP |

---

## 32. Ticket Requirements

| ID | Requirement |
|---|---|
| TR-01 | Unique ticket ID/code |
| TR-02 | Bound to order line and destination/ticket type |
| TR-03 | Stores price snapshot fields needed for audit |
| TR-04 | Stores channel and issuer actor/device |
| TR-05 | Has validity start/end (date or datetime per ticket type config) |
| TR-06 | QR payload server-verifiable |
| TR-07 | Status machine per Sections 27–28 |
| TR-08 | Single-entry consumption on check-in in MVP |
| TR-09 | Searchable by authorized users |
| TR-10 | Printable representation includes destination, type, code/QR, validity |

---

## 33. Payment Requirements

| ID | Requirement |
|---|---|
| PAYR-01 | Server-side state machine only |
| PAYR-02 | One digital provider path in MVP (provider TBD) |
| PAYR-03 | Assisted cash path with actor audit |
| PAYR-04 | Secure callback verification |
| PAYR-05 | Idempotent callback handling |
| PAYR-06 | Amounts must match order total (no partial pay in MVP) |
| PAYR-07 | TTL expiry for unpaid |
| PAYR-08 | Provider reference stored when applicable |
| PAYR-09 | Refunds permissioned + audited; default not-USED tickets only |
| PAYR-10 | Clients never set PAID directly |

---

## 34. QR Requirements

| ID | Requirement |
|---|---|
| QR-01 | Each issued ticket has a QR payload |
| QR-02 | Payload alone is insufficient without server validation |
| QR-03 | Validation detects unknown/forged tokens with DENY codes |
| QR-04 | QR printable at kiosk and dashboard |
| QR-05 | QR format/provider choice TBD but must support authenticity strategy |
| QR-06 | Replay of an already-used ticket yields `DENY_ALREADY_USED` |

---

## 35. Check-in Requirements

| ID | Requirement |
|---|---|
| CHR-01 | Transactional check-in |
| CHR-02 | Exactly-once success under concurrency for single-entry |
| CHR-03 | Records actor/device, time, place/gate context |
| CHR-04 | Updates ticket to USED atomically with check-in insert |
| CHR-05 | Unauthorized actors cannot check in |
| CHR-06 | Check-in history queryable for authorized roles |

---

## 36. Hardware Requirements

| ID | Component | MVP requirement |
|---|---|---|
| HW-01 | Advan A10 class tablet | Primary kiosk target; landscape touch |
| HW-02 | Network connectivity | Required for authoritative transactions |
| HW-03 | Ticket printer | Required for production ops; type TBD; integration spike before freeze |
| HW-04 | QR scanner | Required for gate/counter validation path; type TBD |
| HW-05 | Payment acceptance medium | Per selected provider (QRIS display/EDC/etc.) TBD |
| HW-06 | Gate controller | Optional; not MVP-blocking |
| HW-07 | CCTV/AI | Optional; analytics only; never mutates tickets |
| HW-08 | Physical mount / kiosk enclosure | Operational recommendation for public deployment |

---

## 37. Reporting Requirements

| ID | Report | Filters | Roles |
|---|---|---|---|
| RP-01 | Daily sales summary | Date, channel, destination | Manager, Finance, Admin, Auditor |
| RP-02 | Payments by status | Date range, method, status | Finance, Admin, Auditor |
| RP-03 | Tickets issued vs used | Date range, destination | Manager, Admin, Auditor |
| RP-04 | Refunds/cancels list | Date range | Finance, Admin, Auditor |
| RP-05 | Kiosk health snapshot | Current | Operator, Admin, SysAdmin, Manager |

Reports must reflect authoritative DB state, not client caches.

---

## 38. Notification Requirements

MVP notifications are **operational**, not marketing.

| ID | Event | Channel | Recipient |
|---|---|---|---|
| NT-01 | Kiosk offline beyond threshold | Dashboard badge / optional email | Operator |
| NT-02 | Payment webhook processing failure | System log + admin alert (optional email) | SysAdmin/Admin |
| NT-03 | Printer failure after PAID (if detectable) | Kiosk UI + optional ops signal | Visitor guidance + Operator |
| NT-04 | Remote disable acknowledgment | Device state + dashboard | Operator/Admin |

Out of MVP: SMS/WhatsApp ticket delivery, promotional push, visitor accounts.

---

## 39. Audit Trail Requirements

| ID | Requirement |
|---|---|
| AUD-01 | Record actor, action, entity type/id, before/after or payload summary, IP/device if available, timestamp |
| AUD-02 | Audit on: login failures (security), order create, payment PAID, refund, cancel, ticket issue, reprint, check-in, permission changes, catalog price changes, kiosk register/disable/maintenance |
| AUD-03 | Audit logs append-only from application perspective (no user edit UI) |
| AUD-04 | Auditor role can read, not mutate business records |
| AUD-05 | Retention policy documented (default ≥ 365 days recommended; finalize with operator policy) |

---

## 40. Security Requirements

| ID | Requirement |
|---|---|
| SEC-01 | Server-side authorization for all mutating APIs |
| SEC-02 | Kiosk device credentials distinct from staff user credentials |
| SEC-03 | Secrets not stored in client source control |
| SEC-04 | HTTPS/TLS for all client↔API traffic in deployed environments |
| SEC-05 | Payment webhook authenticity verification |
| SEC-06 | QR authenticity / anti-forgery strategy |
| SEC-07 | Prevent privilege escalation via hidden UI alone |
| SEC-08 | Remote disable must block further sales |
| SEC-09 | Minimal PII in MVP |
| SEC-10 | Rate-limit validation endpoints against brute-force QR guessing where applicable |
| SEC-11 | Session timeout for dashboard users |
| SEC-12 | Gate never trusts unverified client allow decisions |

---

## 41. Error Handling

| Scenario | User-visible behavior | System behavior |
|---|---|---|
| Network timeout on order create | Retry guidance; no success claim | Idempotent retry safe |
| Payment pending long | Waiting state + timeout message | Remain PENDING/PROCESSING until terminal state |
| Payment failed | Failure + try again / seek staff | FAILED recorded |
| Paid but print failed | Success with reprint instruction | Tickets already ISSUED; reprint audited |
| Invalid QR | DENY with reason | No check-in |
| Already used QR | DENY_ALREADY_USED | No second check-in |
| Unauthorized dashboard action | 403 / friendly denial | No mutation |
| Disabled kiosk | Unavailable screen | Order create rejected |
| Stale quote / price changed before confirm | Refresh totals; require reconfirm | Server recalculates on order create |

All errors that affect money/tickets must leave an inspectable server trail.

---

## 42. Offline / Recovery Requirements

| ID | Requirement |
|---|---|
| OFF-01 | Local kiosk state is never authoritative for PAID/ISSUED/USED |
| OFF-02 | On connectivity loss, kiosk blocks new paid claims; may show offline/unavailable |
| OFF-03 | In-flight payment must be reconcilable via status poll / provider sync jobs |
| OFF-04 | Recovery job resolves PENDING/PROCESSING payments safely and issues tickets only when PAID |
| OFF-05 | Duplicate prevention via idempotency and unique provider references |
| OFF-06 | Full offline selling with local authority is **out of MVP** |
| OFF-07 | Documented runbook: payment unknown, reprint, kiosk restart |

---

## 43. Device Management Requirements

| ID | Field / capability | Required |
|---|---|---|
| DM-01 | `device_id` / `terminal_id` | Yes |
| DM-02 | `location_id` / destination binding | Yes |
| DM-03 | `status` | Yes |
| DM-04 | `last_heartbeat` | Yes |
| DM-05 | `software_version` | Yes |
| DM-06 | `hardware_version` (optional string) | Recommended |
| DM-07 | `maintenance_mode` | Yes |
| DM-08 | `is_active` | Yes |
| DM-09 | `registered_at` / `activated_at` / `deactivated_at` | Yes |
| DM-10 | Activation credential rotation by sysadmin | Yes (MVP basic) |
| DM-11 | Remote disable | Yes |
| DM-12 | Offline detection via heartbeat SLA | Yes |

---

## 44. Analytics Requirements

### MVP analytics (lightweight)
| ID | Metric |
|---|---|
| AN-01 | Orders by channel per day |
| AN-02 | Conversion funnel kiosk: start → order → paid → issued |
| AN-03 | Check-ins per day |
| AN-04 | Deny reason distribution |
| AN-05 | Kiosk offline count/time |

### Post-MVP
- CCTV/AI visitor estimate vs tickets used (comparison only)
- Real-time ops wallboard
- Cohort/promo analytics

AI detection must not auto-change ticket records.

---

## 45. Acceptance Criteria

### Epic-level AC (MVP release)

| ID | Criterion |
|---|---|
| AC-01 | Visitor completes kiosk purchase to ISSUED ticket with authoritative totals |
| AC-02 | Staff completes assisted purchase to ISSUED ticket with same pricing engine |
| AC-03 | Digital payment cannot be marked PAID without server verification path |
| AC-04 | Cash assisted payment is audited with staff identity |
| AC-05 | Issued ticket QR validates ALLOW once, then DENY_ALREADY_USED |
| AC-06 | Invalid/expired/cancelled/refunded tickets DENY with correct codes |
| AC-07 | Idempotent retries do not duplicate order/payment/ticket |
| AC-08 | Disabled/maintenance kiosk cannot sell |
| AC-09 | Manager can view daily sales by channel |
| AC-10 | Finance can list payments by status for a day |
| AC-11 | Auditor can view refund and check-in related audit entries |
| AC-12 | No Staff Flutter app shipped |
| AC-13 | No client-trusted money authority path exists in API contract |
| AC-14 | Gate open (if present) only after check-in success; if absent, check-in still works |

---

## 46. Definition of Done

A requirement/story is Done only when:

1. Behavior matches this PRD  
2. Server-side business rules enforced (not UI-only)  
3. Automated and/or scripted tests cover happy path + denial/idempotency where applicable  
4. Audit events written for critical mutations  
5. Both channels verified for shared rule cases when relevant  
6. Error states show safe user guidance  
7. QA sign-off on device UAT for kiosk flows on target tablet profile  
8. No secrets committed  
9. Documentation updated (API/ops notes as available)  
10. Product owner accepts against AC IDs  

---

## 47. Product Risks

| Risk | Impact | Mitigation in PRD |
|---|---|---|
| Split-brain channel logic | Critical | Shared backend mandate BR-04 |
| Payment/ticket desync | Critical | BR-05, recovery jobs OFF-03/04 |
| Public kiosk compromise | High | Device auth, remote disable, SEC-* |
| Hardware TBD delay | High | Spike printer/scanner/payment before freeze |
| Staff bypass outside system | High | Make assisted flow fast; training |
| Scope creep (staff app, OTA, AI) | High | Hard out-of-scope list |
| Ambiguous refunds after entry | Medium | Default refund only if not USED |
| Offline expectations mismatch | High | OFF-06 explicit |

---

## 48. Future Development

- Gate controller module with explicit degraded modes  
- CCTV/AI anomaly comparison dashboards  
- Promotions/vouchers  
- Multi-entry / timed tickets  
- Group/corporate packages  
- Multi-site operator packaging  
- Rich device telemetry + watchdog packaging  
- Optional field staff mobile app **only if** outdoor/offline validation demand is proven  
- Additional payment methods via provider adapters  

---

## 49. Version Roadmap

| Version | Name | Deliverables |
|---|---|---|
| v0.1 | Foundations | Auth, roles, catalog, domain skeletons, kiosk registry |
| v0.2 | Commerce core | Orders, payments (one provider + cash), ticket issuance, QR |
| v0.3 | Kiosk MVP | Flutter terminal happy path on Advan A10 class device |
| v0.4 | Assisted + gate ops | Dashboard assisted sale, validation/check-in UI, reprint |
| v0.5 | Ops readiness | Reports, audit views, heartbeat monitoring, runbooks |
| v1.0 | MVP Release | AC-01…AC-14 met; production pilot at one destination |
| v1.1 | Hardening | Reconciliation jobs, exception workflows, device packaging |
| v1.2 | Access expand | Optional gate integration |
| v2.0 | Intelligence / scale | Optional AI analytics comparison, multi-site, promos |

---

## Appendix A — Channel comparison (normative)

| Step | Self-Service Kiosk | Assisted Dashboard |
|---|---|---|
| Actor | Visitor + device credential | Staff user credential |
| Client | Flutter terminal | Blade + Livewire |
| Pricing | Laravel | Laravel (same) |
| Order | Laravel | Laravel (same) |
| Payment | Laravel + provider | Laravel + cash/provider |
| Ticket/QR | Laravel | Laravel (same) |
| Check-in | Laravel | Laravel (same) |
| UX differences allowed | Yes | Yes |
| Business rule differences | **No** (except explicit authorized overrides) | **No** |

---

## Appendix B — Traceability to Product Validation

| Validation verdict | PRD reflection |
|---|---|
| GO with modified MVP | Sections 21–22, 49 |
| Shared SoT mandatory | Sections 2, 23, 26, Appendix A |
| No Staff Flutter in MVP | Sections 14, 22, AC-12 |
| Gate/AI optional | Sections 19, 22, 36, 48 |
| Explicit state machines required | Sections 27–28 |

---

## Appendix C — Open decisions (blockers before implementation freeze)

1. `[LARAVEL_VERSION]` at scaffold time  
2. `[PAYMENT_PROVIDER]` and MDR/settlement assumptions  
3. `[PRINTER_TYPE]` and print payload layout  
4. `[SCANNER_TYPE]` and gate desk topology  
5. Exact QR signing/opaque token strategy  
6. Order TTL default (recommended starting point: 15 minutes; confirm with ops)  
7. Whether English kiosk locale is in v1.0 or v1.1  
8. Refund override permission for USED tickets (default: disallowed)

---

*End of PRD. No application code in this document.*
