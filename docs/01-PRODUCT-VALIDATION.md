# WanderDesa — Product Validation

**Document:** `01-PRODUCT-VALIDATION.md`  
**Status:** Pre-development validation  
**Audience:** Product, Business, Architecture, Stakeholders  
**Date:** 2026-09-13  

**Project:** WanderDesa  
**Domain:** Tourism / Ticketing / Visitor Management / Self-Service Kiosk / Tourism Operations  
**Market:** Indonesia / Tourism Destination / Tourism Operator / Commercial Tourism Management  

---

## Executive verdict (preview)

**Recommendation: GO — with scoped MVP modifications.**

WanderDesa is commercially and operationally justified for Indonesian tourism destinations that still rely on manual or fragmented ticketing. The correct product shape is **not** “Flutter mobile + Laravel website,” but an **Integrated Tourism Operations System** with:

| Channel | Role |
|---|---|
| Flutter physical kiosk | Self-service visitor terminal |
| Laravel Blade + Livewire dashboard | Assisted-service + management for staff |
| Laravel Backend/API + MySQL | Single Source of Truth and business authority |

Shared backend, ticketing, payment, check-in, and authorization are **mandatory**. A dedicated Staff Flutter app is **out of MVP**.

---

## 0. Locked product configuration (context)

| Field | Value |
|---|---|
| Project name | WanderDesa |
| Description | Integrated tourism system: self-service via physical Flutter kiosk/terminal; assisted-service and management via Laravel Dashboard |
| Target users | Wisatawan, Staff, Ticket Officer, Gate Officer, Operator, Manager, Finance, Admin, Auditor, System Administrator |
| Core problem | Purchase, payment, issuance, validation, check-in, and tourism operations remain manual or fragmented across systems |
| Frontend stack | Laravel Blade + Livewire + Tailwind CSS |
| Kiosk stack | Flutter (physical device, not consumer mobile app) |
| API stack | Laravel REST API |
| Auth | Laravel Sanctum / appropriate Laravel authentication |
| Database | MySQL |
| Deployment | VPS / Cloud / On-Premise / Kiosk local environment |
| Target kiosk hardware | Advan A10 tablet — landscape touchscreen kiosk, locked fullscreen |

### Environment-inspected versions (do not invent Laravel until scaffolded)

| Component | Status |
|---|---|
| PHP | **8.4.25** (local Laragon) |
| MySQL | **8.4.11** (local Laragon) |
| Flutter | **3.44.9** stable · Dart **3.12.2** |
| Laravel | **TBD — Requires Environment Verification** (project not scaffolded) |
| Payment / QR / Printer / Scanner / Gate / CCTV | **TBD** — product decisions pending |

---

## 1. Problem statement

Indonesian tourism destinations often sell and control access through **manual counters, paper tickets, cash-only flows, and disconnected tools** (spreadsheet, POS, WhatsApp, standalone QR apps, or gate hardware without a shared ledger).

This creates:

1. **Long queues** at peak hours when every visitor must speak to staff.
2. **Inconsistent pricing and discounts** when rules live in people, not systems.
3. **Weak payment reconciliation** between counter cash, e-wallet, and bank transfer.
4. **Unreliable ticket authenticity** (forged/paper reuse, unclear validity windows).
5. **Double check-in / gate disputes** when validation is local or offline-only without reconciliation.
6. **Opaque operations reporting** for managers and finance (sold vs used vs refunded).
7. **Staff bottleneck** — every sale requires assisted service even when visitors could self-serve.

**WanderDesa addresses this by unifying self-service kiosk and staff-assisted operations under one authoritative Laravel backend**, so both channels share the same orders, payments, tickets, QR validation, check-in, and audit trail.

---

## 2. Target user personas

### P1 — Visitor / Wisatawan
- Arrives at destination; wants fast ticket purchase and clear entry.
- Prefers touchscreen self-service when language/UI is simple and payment is familiar (QRIS / local methods).
- Low tolerance for complex forms or unclear ticket validity.

### P2 — Ticket Officer
- Sells tickets at counter for visitors who need help, groups, special rates, or cash.
- Needs fast assisted order → pay → print/issue flow with correct prices from the system.

### P3 — Gate Officer / Check-in Staff
- Scans QR / validates tickets at entry.
- Needs unambiguous pass/fail, prevent double entry, handle exceptions with authorization.

### P4 — Operator / Destination Ops
- Owns daily kiosk uptime, queues, printer/scanner health, shift continuity.
- Needs device status, maintenance mode, and operational alerts.

### P5 — Manager
- Needs real-time and end-of-day visibility: sales, occupancy/entries, channel mix (kiosk vs counter), anomalies.

### P6 — Finance
- Needs trustworthy settlement: paid/refunded/expired, fees/taxes, reconciliation against payment provider.

### P7 — Administrator
- Configures destinations, products, ticket types, prices, roles, kiosks, business rules.

### P8 — Auditor / Compliance stakeholder
- Needs immutable-enough audit logs for who sold, refunded, overridden, checked in, and when.

### P9 — System Administrator
- Deploys backend, monitors availability, manages credentials, backups, kiosk fleet registration.

---

## 3. Primary pain points

| # | Pain point | Who feels it | Impact |
|---|---|---|---|
| 1 | Peak-hour queues at ticket counters | Visitors, Ticket Officers | Lost sales, poor experience |
| 2 | Manual price/discount mistakes | Staff, Finance | Revenue leakage, disputes |
| 3 | Fragmented payment records | Finance | Hard reconciliation |
| 4 | Paper / weakly verified tickets | Gate, Manager | Fraud, reuse |
| 5 | No single ticket lifecycle | Ops, Auditor | Unclear status (paid/used/refunded) |
| 6 | Kiosk without shared backend | Product risk | Duplicate logic, inconsistent totals |
| 7 | Staff tools that are only CRUD admin | Ticket Officers | Cannot do assisted sales efficiently |
| 8 | Offline kiosk declaring local “success” | Finance, Ops | Duplicate orders/payments/tickets |
| 9 | Gate opening without server authority | Security | Unauthorized entry |
| 10 | No device identity / fleet for kiosks | SysAdmin, Ops | Unmanaged public terminals |

---

## 4. Existing tourism workflow (as-is, typical)

```text
Visitor arrives
    ↓
Queue at ticket counter
    ↓
Staff asks ticket type / quantity / date
    ↓
Staff quotes price (manual / local POS / spreadsheet)
    ↓
Visitor pays (cash / transfer / e-wallet — often loosely recorded)
    ↓
Staff writes/prints paper ticket or sticker
    ↓
Visitor goes to gate
    ↓
Staff tears stub / stamps / visual check / optional local QR scan
    ↓
Entry allowed (often without shared ledger)
    ↓
End of day: manual count / partial reports
```

**Failure modes:** slow service, inconsistent totals, weak audit, limited self-service, poor refund/exception handling, reporting that cannot be trusted for commercial decisions.

---

## 5. Self-service workflow (to-be — Channel A)

```text
Visitor
  ↓
Flutter Kiosk (physical terminal, registered device)
  ↓
Select destination / ticket type / quantity / visit attributes
  ↓
Laravel API calculates authoritative price (tax, fee, discount)
  ↓
Create Order (server-side, idempotent)
  ↓
Initiate Payment (provider + server state: PENDING → … → PAID/FAILED/…)
  ↓
On verified PAID: Issue Ticket(s) + QR (server-side)
  ↓
Print ticket / show on-screen confirmation
  ↓
Visitor proceeds to entry
  ↓
QR Scanner → Laravel Validation → Check-in transaction
  ↓
(Optional) Gate Controller opens after authoritative success
```

**Rules:** Kiosk may hold UI/session/retry state only. Critical success is confirmed by Laravel. Duplicate prevention via idempotency keys / transaction IDs.

---

## 6. Assisted-service workflow (to-be — Channel B)

```text
Visitor
  ↓
Staff (Ticket Officer / authorized role)
  ↓
Laravel Blade + Livewire Dashboard
  ↓
Create Order (same domain services as kiosk)
  ↓
Collect Payment (cash / provider / hybrid per policy)
  ↓
Server marks payment state authoritatively
  ↓
Issue Ticket(s) + QR
  ↓
Print / deliver ticket
  ↓
QR Validation → Check-in → (Optional) Gate
```

**Rules:** Same order/payment/ticket/check-in engines as Channel A. Differences are **channel, actor, UX, and authorized overrides** — not a second business system.

---

## 7. Proposed solution

**WanderDesa Integrated Tourism System** with seven capability layers:

1. **Flutter Kiosk / Self-Service Terminal** — public touchscreen terminal (Advan A10 class), locked fullscreen, device-registered.
2. **Laravel Backend/API** — business authority: pricing, orders, payments, tickets, QR, check-in, roles, audit, kiosk registry.
3. **Laravel Blade + Livewire Dashboard** — assisted-service + operations + management for internal roles.
4. **MySQL** — authoritative persistence.
5. **Payment integrations** — server-verified, webhook/callback secured, idempotent.
6. **QR + printer + scanner integrations** — issue/print/validate under server rules.
7. **Optional gate + optional CCTV/AI** — advisory/actuation only after Laravel authority; AI must not silently mutate ticket records.

### Architecture principle (non-negotiable)

```text
Clients (Kiosk / Dashboard) request
        ↓
Laravel decides + calculates + persists
        ↓
Clients render authoritative result
```

Never trust client_price, client_total, client_payment_status, client_ticket_status, client_permission, or client_checkin_status as authority.

---

## 8. Core value proposition

**One destination ledger. Two service channels. Zero split-brain operations.**

WanderDesa lets tourism operators:

- **Sell faster** via kiosk self-service during peaks.
- **Serve exceptions** via assisted dashboard without breaking rules.
- **Trust money and tickets** because payment and issuance are server-authoritative.
- **Control entry** with transactional QR validation and double-check-in prevention.
- **Operate devices** as a managed kiosk fleet, not ad-hoc tablets.
- **Audit and report** from a single source of truth.

---

## 9. Product differentiation

| Differentiator | Why it matters |
|---|---|
| Physical kiosk-first (not consumer app-first) | Matches on-site purchase behavior at Indonesian destinations |
| Shared backend for kiosk + counter | Avoids dual pricing/ticket engines |
| Dashboard as operational POS, not only admin CRUD | Staff can complete real assisted transactions |
| Explicit ticket + payment state machines | Finance and gate trust the same statuses |
| Device identity / heartbeat / maintenance mode | Public terminals are security and ops assets |
| Server-side QR validation before gate | Prevents “scan locally → open gate” bypass |
| CCTV/AI as analytics, not ticket mutator | Keeps authority clean while enabling anomaly insight |
| Indonesia-oriented ops model | Counter + QRIS-class payments + peak weekend realities |

**Not differentiated by:** being “another Flutter tourism app” or “another Laravel admin panel.” Differentiation is **integrated on-site commerce + access control**.

---

## 10. Must-have features (MVP)

### Shared platform
- [ ] User roles & permissions (least privilege)
- [ ] Destinations / products / ticket types / prices (server-side)
- [ ] Orders + line items with authoritative totals
- [ ] Payments with explicit states (e.g. PENDING, PROCESSING, PAID, FAILED, EXPIRED, CANCELLED, REFUNDED — finalize from PRD)
- [ ] Tickets with explicit lifecycle (derive final machine from PRD; do not blindly copy placeholders)
- [ ] QR generation + server validation
- [ ] Check-in with transactional double-entry prevention
- [ ] Audit log for critical actions
- [ ] Idempotent APIs for order/payment/ticket/check-in
- [ ] Channel tagging: `kiosk` vs `assisted` (same domain)

### Kiosk
- [ ] Device registration / activation / auth credentials
- [ ] Heartbeat + basic status (online, maintenance, version)
- [ ] Landscape locked fullscreen visitor flow
- [ ] Ticket selection → pay → issue → print/confirm
- [ ] Safe retry UX without local “fake success”
- [ ] Maintenance mode / remote disable support (at least admin-triggered)

### Dashboard
- [ ] Auth + role-based menus
- [ ] Assisted order / pay / issue / print
- [ ] Ticket lookup + validation support
- [ ] Basic operational reports (sales, tickets issued/used)
- [ ] Refund/cancel flows **only if** policy is defined and authorized
- [ ] Kiosk list/status view

### Integrations (minimal viable)
- [ ] One primary payment path suitable for Indonesia (provider TBD)
- [ ] Ticket printing path (hardware TBD)
- [ ] QR scan validation path (hardware TBD)

---

## 11. Nice-to-have features (post-MVP or late MVP if capacity allows)

- Multi-destination / multi-tenant commercial packaging
- Dynamic pricing / promo campaigns / voucher codes
- Group / school / corporate booking packages
- Advanced finance settlement packs per provider
- Real-time ops wallboard
- Rich kiosk telemetry/crash analytics
- Watchdog auto-restart packaging for Android kiosk mode
- Soft gate integration (controller open pulse)
- CCTV/AI visitor-count vs ticket-sold anomaly dashboard
- Multilingual kiosk (beyond ID/EN baseline)
- Accessibility enhancements for kiosk UI
- Offline *queue + reconcile* design (only after explicit offline strategy)

---

## 12. Features that should NOT be included in MVP

| Exclude | Reason |
|---|---|
| Dedicated Staff Flutter mobile app | No clear MVP operational requirement; dashboard covers assisted service |
| Consumer mobile app for tourists | Different product; dilutes kiosk/ops focus |
| Marketplace of many destinations (OTA-style) | Different business model |
| Full offline autonomous selling with local authority | Violates Single Source of Truth unless carefully designed |
| Client-side price as authority | Critical integrity risk |
| Gate open without Laravel validation | Security risk |
| AI auto-void/auto-check-in tickets | Authority contamination |
| Complex loyalty / points ecosystem | Scope explosion |
| Full ERP/HR/inventory | Out of domain |
| Multi-payment-provider abstraction of everything | Start with one proven path |
| Advanced BI data warehouse | Use operational reports first |
| White-label franchise portal | Premature commercialization layer |

---

## 13. Kiosk requirements

### Product / UX
- Public self-service terminal UX: large touch targets, short path to purchase, clear errors.
- Landscape primary orientation (Advan A10 profile).
- Locked fullscreen / kiosk mode; minimize escape to Android system UI.
- Language-simple flows; confirm order summary before payment.
- Clear states for payment waiting, success, failure, timeout.

### Device / ops
- Unique `device_id` / `terminal_id`, location binding, activation.
- Fields conceptually: status, last_heartbeat, software_version, hardware_version, maintenance_mode, is_active, registered_at, activated_at, deactivated_at.
- Heartbeat to backend; admin can disable remotely.
- Printer / scanner / payment device status surfaces (as integrations mature).
- Crash recovery / restart strategy (documented even if packaging is phased).
- Physical security assumptions: public tablet, tamper risk, cable/network dependency.

### Technical
- Talks only to Laravel API for critical operations.
- Local storage = UI/session/retry cache only.
- Idempotency on create order / initiate payment / confirm flows.

---

## 14. Staff dashboard requirements

The dashboard is **not** “admin CRUD only.” It must support authorized operational work:

- Assisted ticket sales (order → payment → issue → print)
- Ticket search / status inspection
- Validation / check-in support for gate staff (as role-permitted)
- Refunds/cancels/overrides under permission + audit
- Product/price/ticket-type configuration (admin)
- User/role management (admin)
- Kiosk fleet status
- Daily/shift reports for manager/finance
- Audit visibility for auditor roles

Access is **permission-based**, not “everyone sees everything.”

---

## 15. Ticketing requirements

- Ticket types bound to destination/product rules.
- Server calculates price, tax, service fee, discount.
- Explicit lifecycle with valid transitions, actor, rule, timestamp, audit where appropriate.
- Candidate states to refine in PRD (do not adopt blindly):  
  `PENDING → RESERVED → PAID → ISSUED → ACTIVE → USED → EXPIRED / CANCELLED / REFUNDED`
- Each ticket has unique identity + QR payload that Laravel can authenticate.
- Validity window (date/time/rules) enforced server-side.
- Channel of issuance recorded (kiosk vs assisted).
- Prevent duplicate issuance for the same paid order line without business justification.

---

## 16. Payment requirements

- Server-side verification only; never trust client “payment success.”
- Explicit payment states; finalize set from business PRD.
- Secure webhook/callback verification.
- Idempotent processing; duplicate callbacks must not duplicate money/tickets.
- Reconciliation hooks for finance (provider reference IDs).
- Support at least one Indonesia-relevant method in MVP (provider **TBD**).
- Cash assisted-service path must still create authoritative payment records (staff-confirmed, audited).
- Clear timeout/expiry behavior for unpaid orders.

---

## 17. QR / check-in requirements

Validation (Laravel) should verify, as applicable:

- Ticket exists and is authentic
- Correct destination / gate context
- Payment valid
- Ticket active / not expired / not cancelled / not refunded
- Not already used (or remaining entries policy if multi-entry is later allowed)
- Scanner/operator authorized
- Replay protection considerations

Check-in must be **transactional** and prevent double check-in.  
Gate actuation (if any) happens **after** successful authoritative check-in — never Flutter-alone → gate open.

---

## 18. Hardware integration requirements

| Integration | MVP posture | Notes |
|---|---|---|
| Touchscreen tablet (Advan A10 class) | Required | Primary kiosk target |
| Ticket printer | Required for real ops | Type **TBD** |
| QR scanner (gate/counter) | Required for validation path | Type **TBD** |
| Payment acceptance (QRIS/EDC/etc.) | Required path | Provider **TBD** |
| Gate controller | Optional | Only after validation architecture is explicit |
| CCTV / AI | Optional | Analytics/anomaly only; no silent ticket mutation |

All hardware choices must be decided before deep implementation of device drivers/adapters. Until then, design interfaces/adapters, not hardcoded vendor myths.

---

## 19. Operational requirements

- Shift-friendly assisted sales
- End-of-day sales and ticket usage reports
- Kiosk online/offline visibility
- Maintenance mode workflow
- Incident handling: failed print after paid, payment timeout, partial webhook
- Reconciliation process for finance
- Role separation: selling vs refunding vs configuring prices
- Auditability for disputes at the gate
- Deployment realistic for VPS/cloud and on-prem destination networks
- Runbooks for kiosk restart, printer jam, network drop

---

## 20. Commercial feasibility

### Demand signals (Indonesia tourism ops)
- Many destinations still mix cash counters and ad-hoc digital tools.
- Peak weekends/holidays create strong ROI for queue reduction via kiosks.
- Operators pay for systems that improve **throughput, control, and reconciliation** — not novelty apps.

### Monetization options (later packaging; not MVP blockers)
- Per-destination license
- Per-kiosk SaaS fee
- Setup + hardware bundle
- Payment MDR pass-through / settlement premium (careful with compliance)
- Premium modules: gate, AI analytics, multi-site

### Feasibility judgment
**Commercially feasible** if MVP proves: faster peak throughput, trustworthy daily settlement, and fewer gate disputes.  
**Not feasible** if built as a generic consumer app without on-site ops depth.

---

## 21. Technical feasibility

| Area | Feasibility | Notes |
|---|---|---|
| Laravel as SoT + API | High | Mature pattern; PHP 8.4 / MySQL 8.4 available locally |
| Blade + Livewire dashboard | High | Fits assisted ops UI |
| Flutter kiosk client | High | Flutter 3.44.9 available; treat as device app |
| Shared domain services | High | Requires discipline; do not fork logic per channel |
| Payments | Medium | Depends on provider choice, webhooks, reconciliation |
| Printer/scanner | Medium | Vendor-specific; needs spike |
| Gate | Medium–Low early | Needs explicit protocol + fail modes |
| Offline-first authority | Low (intentionally) | Avoid in MVP; design retry + reconcile instead |
| CCTV/AI | Medium later | Integration + analytics only |

**Overall technical feasibility: HIGH for MVP** if scope stays on shared authoritative flows + one payment + print/scan path, and Laravel version is chosen deliberately at scaffold time.

---

## 22. Security risks

| Risk | Severity | Mitigation direction |
|---|---|---|
| Public kiosk device compromise | High | Device credentials, short-lived tokens, remote disable, locked OS mode |
| Forged QR / replay | High | Signed/opaque server-verifiable tokens, one-time use rules |
| Privilege abuse on dashboard | High | RBAC, audit, dual-control for refunds |
| Webhook spoofing | High | Signature verification, IP allowlists where applicable |
| Client-trusted totals | Critical | Never accept client money fields as authority |
| Data leakage (visitor PII) | Medium–High | Minimize collected PII in MVP |
| Insecure local kiosk storage | Medium | No secrets in plain form; minimal persistence |
| Gate bypass | High | Server validation before actuation |

---

## 23. Operational risks

| Risk | Impact | Mitigation direction |
|---|---|---|
| Printer failure after payment | High | Reprint authorized flow; ticket still in system |
| Network instability at destination | High | Clear pending states; retry-safe APIs; no local success lie |
| Staff workaround (manual paper outside system) | High | Make assisted flow faster than workaround |
| Unclear refund policy | Medium | Define policy before enabling refunds |
| Training gap | Medium | Simple UI + role-limited screens |
| Peak load spikes | Medium | Capacity planning; queue UX on kiosk |

---

## 24. Kiosk / device risks

| Risk | Notes |
|---|---|
| Theft / tampering | Physical mounts, kiosk mode, remote wipe/disable |
| App crashes in public | Watchdog/auto-start (roadmap if not MVP packaging) |
| Wrong software version fleet-wide | Version reporting + controlled update process |
| Unregistered rogue terminal | Activation/registration gate |
| Landscape/UI not fitting Advan A10 | Design against real device dimensions early |
| Peripheral disconnect (printer) | Status checks + blocking/degraded modes |

---

## 25. Payment risks

| Risk | Mitigation direction |
|---|---|
| Duplicate charge / duplicate ticket | Idempotency + unique provider references |
| PAID without ticket issue | Atomic/compensating workflows; ops reprint/reissue tools |
| Ticket issued without PAID | Hard invariant in domain services |
| Callback delay / out-of-order events | State machine + reconciliation job |
| Cash mismatch in assisted mode | Shift cash-up reports + permissions |
| Provider lock-in | Adapter interface even with one MVP provider |

---

## 26. Product risks

| Risk | Why dangerous | Response |
|---|---|---|
| Building Staff Flutter “for symmetry” | Wastes MVP capacity | Explicitly out of scope |
| Admin-only dashboard | Staff still need paper/other tools | Assisted-service is MVP-critical |
| Separate kiosk business logic | Split-brain pricing/tickets | Shared domain services only |
| Overbuilding OTA/marketplace | Wrong market wedge | Single-operator destination focus first |
| Ambiguous ticket states | Gate/finance chaos | Define state machine in PRD before coding |
| Hardware TBD ignored until late | Integration failure | Spike printer/scanner/payment early |
| Offline “full sell” promises | Integrity failure | Do not market what architecture forbids |

---

## 27. Recommended MVP scope

### In scope
1. **Shared Laravel backend** (users/roles, products, orders, payments, tickets, QR, check-in, audit).
2. **Flutter kiosk** self-service happy path on target tablet profile.
3. **Blade + Livewire dashboard** for assisted sales + basic ops/admin/reports.
4. **One payment integration path** (provider TBD after spike).
5. **Ticket print + QR validate/check-in** path.
6. **Kiosk registration + heartbeat + maintenance/disable**.
7. **Idempotent critical APIs** and server-side money/ticket authority.

### Explicit shared capabilities (required)

| Capability | MVP decision |
|---|---|
| Self-Service Kiosk | **Yes** |
| Assisted-Service Dashboard | **Yes** |
| Shared Backend | **Yes — mandatory** |
| Shared Ticketing | **Yes — mandatory** |
| Shared Payment | **Yes — mandatory** |
| Shared Check-in | **Yes — mandatory** |
| Shared Authorization | **Yes — mandatory** |
| Staff Flutter app | **No** |
| Consumer tourist app | **No** |
| Gate controller | **Optional / later** |
| CCTV/AI | **Optional / later** |

### Out of scope for MVP
Staff mobile app, OTA marketplace, offline authoritative selling, AI ticket mutation, multi-provider payment mesh, advanced BI, loyalty.

### Success criteria (MVP)
- Visitor can buy and receive a valid ticket via kiosk.
- Staff can complete the same business outcome via dashboard.
- Finance can reconcile paid vs issued for a day.
- Gate/check-in rejects used/invalid tickets consistently.
- No duplicate order/payment/ticket under retry conditions in test scenarios.

---

## 28. Future roadmap

### Phase 1 — MVP Foundation
Shared domain, kiosk happy path, assisted sales, one payment, print, QR check-in, basic reports, kiosk registry.

### Phase 2 — Operations Hardening
Reprint/exception workflows, stronger reconciliation, richer device telemetry, packaging for auto-start/watchdog, sharper RBAC, refund policy automation.

### Phase 3 — Access Control Expansion
Gate controller integration with explicit online/degraded modes; multi-gate destinations; scanner fleet management.

### Phase 4 — Intelligence & Scale
CCTV/AI anomaly comparison (sold vs checked-in vs detected), multi-site operator packaging, promotions engine, optional field staff mobile **only if** outdoor/offline validation demand is proven.

### Phase 5 — Commercial Productization
Installer playbooks, hardware certified bundles (tablet + printer + scanner), SLA monitoring, partner payment programs.

---

## 29. Go / Modify / No-Go recommendation

### Decision: **GO — MODIFY MVP BOUNDARIES**

| Option | Result |
|---|---|
| **GO** | Proceed to PRD / architecture / scaffolding |
| **MODIFY** | Enforce shared SoT; exclude Staff Flutter; defer gate/AI; choose payment & peripherals before deep build |
| **NO-GO** | Only if stakeholders insist on consumer-app-first or dual disconnected systems |

### Conditions to proceed
1. Agree that WanderDesa is an **integrated tourism operations system**, not a mobile+website pair.
2. Agree **Laravel is the only business authority**.
3. Keep **both** Self-Service and Assisted-Service in MVP.
4. Exclude Staff Flutter until a field/offline requirement is evidenced.
5. Decide or spike: `[PAYMENT_PROVIDER]`, `[PRINTER]`, `[SCANNER]` before implementation freeze.
6. Write ticket & payment state machines in the next PRD before coding statuses into clients.
7. Scaffold Laravel only after version decision; do not invent `[LARAVEL_VERSION]`.

### Final recommendation statement

**WanderDesa should be built.** The problem is real, the Indonesian on-site tourism wedge is clear, and the proposed dual-channel architecture with a shared Laravel backend is the correct product shape. Proceed to detailed PRD and system architecture with a tightly scoped MVP that proves shared ticketing, payment, and check-in across kiosk and dashboard — then expand into gate, AI, and optional field apps only when operations demand them.

---

## Appendix A — Validation checklist summary

| Question | Answer |
|---|---|
| Is the core problem real? | Yes |
| Is kiosk-first correct vs consumer app? | Yes for this wedge |
| Is assisted dashboard required in MVP? | Yes |
| Must backend/ticketing/payment/check-in/auth be shared? | Yes |
| Is Staff Flutter required for MVP? | No |
| Is technical feasibility adequate? | Yes, with hardware/payment spikes |
| Biggest kill risks? | Split-brain logic; payment/ticket desync; unmanaged public devices |
| Recommendation | **GO with modified MVP scope** |

---

## Appendix B — Next documents (suggested)

1. `02-PRD.md` — detailed requirements & state machines  
2. `03-SYSTEM-ARCHITECTURE.md` — components, trust boundaries, sequences  
3. `04-DOMAIN-MODEL.md` — entities, invariants, lifecycles  
4. `05-API-CONTRACT-DRAFT.md` — idempotent API surfaces  
5. `06-KIOSK-HARDWARE-PROFILE.md` — Advan A10 + peripherals decisions  

---

*End of product validation. No application code in this phase.*
