# WanderDesa — Project Structure

**Document ID:** `10-PROJECT-STRUCTURE`  
**File:** `docs/10-PROJECT-STRUCTURE.md`  
**Status:** Recommended repository layout (no implementation code)  
**Based on:** `docs/02-PRD.md` … `docs/09-UI-UX.md`  
**Shape:** Monorepo · Laravel modular monolith + Flutter kiosk client + docs

---

## 0. Goals and constraints

| Goal | Approach |
|---|---|
| One integrated system | Single repo; shared product docs |
| Laravel = business authority | All domain logic in `backend/` |
| Kiosk = device client | Flutter UI/network only in `kiosk/` |
| Avoid over-abstraction | Prefer Actions/Services; **no** Repository layer by default |
| Dual channel, one domain | API controllers and Livewire both call the same Actions |
| Cursor-friendly | `.cursor/` for rules/skills aligned to architecture |

---

## 1. Top-level repository layout

```text
WanderDesa/
├── README.md
├── .gitignore
├── .env.example                    # optional root pointer notes only; real env in backend/
├── backend/                        # Laravel 13.x modular monolith
├── kiosk/                          # Flutter physical self-service terminal
├── docs/                           # Product & engineering documentation
└── .cursor/                        # Cursor rules / project AI guidance
```

### What does *not* belong at root

- Separate `staff-app/` Flutter project (out of MVP)
- Microservice folders (`payment-service/`, etc.)
- Duplicate domain logic under `kiosk/`

---

## 2. `docs/` structure

```text
docs/
├── 01-PRODUCT-VALIDATION.md
├── 02-PRD.md
├── 03-SRS.md
├── 04-SYSTEM-DESIGN.md
├── 05-BUSINESS-FLOW.md
├── 06-DATABASE.md
├── 07-RBAC.md
├── 08-API-CONTRACT.md
├── 09-UI-UX.md
├── 10-PROJECT-STRUCTURE.md          # this file
├── 11-ROADMAP.md
├── 12-SECURITY-CHECKLIST.md         # MVP pilot sign-off (operational)
└── runbooks/                       # ops procedures (TASK-030); not a business-rule override
```

Keep docs numbered and normative. ADRs may later live in `docs/adr/`.

---

## 3. `.cursor/` structure

```text
.cursor/
├── rules/
│   ├── wanderdesa-architecture.mdc  # SoT, no split-brain, kiosk≠mobile app
│   ├── laravel-domain.mdc           # Actions own money/ticket rules
│   └── flutter-kiosk.mdc            # device client constraints
└── (optional) skills/ or plans/
```

Rules should reinforce: shared Laravel domain, no client authoritative money, no Staff Flutter in MVP.

---

## 4. Backend (`backend/`) — Laravel conventions

Standard Laravel root **plus** a light domain module layout under `app/`.

```text
backend/
├── app/
│   ├── Actions/                    # Use-case entrypoints (preferred)
│   ├── Services/                   # Domain services (pricing, state transitions)
│   ├── Models/
│   ├── Enums/
│   ├── Policies/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/V1/
│   │   │   └── Web/                # thin if needed; Livewire-first dashboard
│   │   ├── Requests/               # Form requests
│   │   │   ├── Api/V1/
│   │   │   └── Web/
│   │   ├── Resources/              # API Resources (transformers)
│   │   │   └── Api/V1/
│   │   └── Middleware/
│   ├── Livewire/                   # Dashboard UI components
│   ├── Jobs/
│   ├── Events/
│   ├── Listeners/
│   ├── Notifications/
│   ├── Support/                    # small helpers (money format, idempotency)
│   ├── Integrations/               # payment/printer/gate adapters (interfaces + impl)
│   ├── Exceptions/
│   ├── Providers/
│   └── Traits/                     # only if genuinely reused
├── bootstrap/
├── config/
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── public/
├── resources/
│   ├── views/                      # Blade layouts + Livewire views
│   ├── css/
│   └── js/
├── routes/
│   ├── api.php                     # /api/v1/*
│   ├── web.php                     # dashboard auth + Livewire pages
│   └── console.php
├── storage/
├── tests/
│   ├── Feature/
│   ├── Unit/
│   └── Integration/                # optional payment webhook tests
├── artisan
├── composer.json
├── package.json
├── phpunit.xml / pest.php
└── scripts/                        # VPS deploy + backup entrypoints (TASK-030)
```

### Optional domain grouping (recommended when files grow)

Keep Laravel conventions, group by **capability** inside each layer:

```text
app/Actions/Orders/CreateOrder.php
app/Actions/Payments/InitiatePayment.php
app/Actions/Payments/MarkPaymentPaid.php
app/Actions/Tickets/IssueTickets.php
app/Actions/CheckIns/CheckInTicket.php
app/Actions/Devices/RegisterDevice.php
app/Actions/Devices/HeartbeatDevice.php
...
app/Services/Pricing/PricingService.php
app/Services/Tickets/TicketStateService.php
app/Integrations/Payments/PaymentGateway.php
app/Integrations/Payments/NullPaymentGateway.php
app/Integrations/Payments/{Provider}PaymentGateway.php
```

**Do not** create a full DDD hexagonal forest (no Entities/ValueObjects packages unless pain appears).

---

## 5. Where each backend concern belongs

| Concern | Location | Notes |
|---|---|---|
| **Models** | `app/Models/` | Eloquent models; relationships; minimal business branching |
| **Controllers** | `app/Http/Controllers/Api/V1/` | Thin: authorize → validate → Action → Resource |
| **Form Requests** | `app/Http/Requests/Api/V1/` (+ Web if needed) | Input validation; reject client money fields |
| **Actions** | `app/Actions/{Domain}/` | **Primary** use-case orchestration; shared by API + Livewire |
| **Services** | `app/Services/{Domain}/` | Reusable domain logic (pricing, transitions) called by Actions |
| **DTOs** | `app/Support/DTOs/` or `app/Data/` | **Only if needed** for non-array clarity (QuoteResult, PrintPayload); avoid DTO explosion |
| **Enums** | `app/Enums/` | OrderStatus, PaymentStatus, TicketStatus, DeviceStatus, Channel |
| **Policies** | `app/Policies/` | Resource authorization; maps to `docs/07-RBAC.md` |
| **Jobs** | `app/Jobs/` | Webhook processing, reconcile payments, notifications |
| **Events** | `app/Events/` | Optional domain signals (OrderPaid, TicketIssued) — keep sparse |
| **Listeners** | `app/Listeners/` | React to events (audit side-effects if not inline) |
| **Notifications** | `app/Notifications/` | Ops alerts (kiosk offline, webhook failure) |
| **API Resources** | `app/Http/Resources/Api/V1/` | Response shaping per `docs/08-API-CONTRACT.md` |
| **Repositories** | **Not by default** | Use Eloquent in Actions/Services; add only if query reuse becomes painful |
| **Traits** | `app/Traits/` | Rare (e.g. shared audit helper); prefer Support classes |
| **Support classes** | `app/Support/` | IdempotencyKey, Money (IDR int helpers), CorrelationId |
| **Integrations** | `app/Integrations/` | Payment/gate adapters; hardware payload builders if server-side |
| **Livewire Components** | `app/Livewire/` | Dashboard pages: AssistedSale, CheckInGate, KioskIndex, Reports… |
| **Blade Views** | `resources/views/` | Layouts, Livewire blades, emails |
| **Routes** | `routes/api.php`, `routes/web.php` | Versioned API; dashboard web |
| **Migrations** | `database/migrations/` | Schema from `docs/06-DATABASE.md` |
| **Seeders** | `database/seeders/` | Roles/permissions, demo destination, super admin |
| **Factories** | `database/factories/` | Test data |
| **Tests** | `tests/Feature`, `tests/Unit` | Money/ticket/check-in/idempotency/RBAC critical paths |

### Explicit non-patterns (MVP)

| Avoid | Why |
|---|---|
| `app/Repositories/*` for every model | Extra indirection without benefit |
| Separate `KioskOrderService` vs `DashboardOrderService` | Split-brain risk |
| Fat Livewire components with pricing logic | Logic must call Actions |
| Fat Models with payment side-effects | Prefer Actions/Services |
| Microservice splits under `backend/services/` | Rejected for MVP |

---

## 6. Backend module map (logical)

Align folders to system design modules:

| Module | Typical Actions / Services | Livewire / API |
|---|---|---|
| Identity & Access | role checks via Policies | Users, Roles pages |
| Catalog & Pricing | PricingService, ticket type CRUD | Catalog Livewire; quote API |
| Commerce | CreateOrder, InitiatePayment, Refund | AssistedSale; Orders/Payments API |
| Ticketing | IssueTickets, print payload | Tickets Livewire; ticket API |
| Access | ValidateTicket, CheckInTicket | Gate Livewire; check-in API |
| Devices | Register/Activate/Heartbeat/Maintenance | Kiosks Livewire; kiosk API |
| Reporting | query services | Reports Livewire; report API |
| Audit | AuditWriter support | AuditLog Livewire |
| Integrations | PaymentGateway adapters | Webhooks controllers |

---

## 7. Routes sketch (placement only)

```text
routes/api.php
  → prefix api/v1
  → auth, kiosks, destinations, pricing, orders, payments, webhooks, tickets, check-ins, reports, notifications

routes/web.php
  → login
  → auth middleware group → Livewire full-page components / blades
```

Webhook routes: signature middleware, no user session.

---

## 8. Flutter Kiosk (`kiosk/`) structure

Flutter represents a **physical terminal client**, not a generic consumer app.

```text
kiosk/
├── pubspec.yaml
├── analysis_options.yaml
├── android/                         # kiosk mode / orientation / permissions
├── lib/
│   ├── main.dart
│   ├── app.dart                     # root app, routes, theme
│   ├── core/                        # app-wide constants, result types, config
│   │   ├── config/
│   │   ├── constants/
│   │   ├── errors/                  # error mapping to UX states
│   │   └── utils/
│   ├── network/                     # API client, interceptors, endpoints
│   │   ├── api_client.dart
│   │   ├── auth_interceptor.dart
│   │   └── dto/                     # request/response models (not business authority)
│   ├── storage/                     # secure/session/retry local storage only
│   ├── security/                    # device credential/token handling
│   ├── device/                      # device identity, heartbeat, maintenance flags
│   ├── printer/                     # printer adapter/port
│   ├── scanner/                     # usually unused on purchase kiosk MVP
│   ├── payment/                     # display provider QR / poll status UI helpers
│   ├── features/                    # screen flows by feature
│   │   ├── idle/
│   │   ├── home/
│   │   ├── catalog/
│   │   ├── checkout/                # summary, pay, processing, success/fail
│   │   ├── tickets/                 # QR display, print progress
│   │   ├── maintenance/
│   │   └── recovery/
│   └── shared/                      # shared UI widgets, theme, l10n
│       ├── widgets/
│       ├── theme/
│       └── l10n/
├── test/
├── tool/                           # APK build helper (TASK-030); no secrets
└── assets/
```

### Kiosk boundary rules

| Folder | Allowed | Forbidden |
|---|---|---|
| `network/` | Call Laravel API; parse DTO | Decide PAID from local invent |
| `storage/` | UI session, idempotency keys, last ids for recovery | Authoritative ledger |
| `security/` | Token persistence | Hardcode secrets in source |
| `device/` | Heartbeat, status from server | Bypass maintenance locally to sell |
| `printer/` | Print server print-payload | Create ticket codes |
| `scanner/` | Optional staff/combo later | Local ALLOW → gate open |
| `payment/` | Show next_action; poll status | Mark success without API |
| `features/` | UX flows per `docs/09-UI-UX.md` | Duplicate pricing engine |
| `shared/` | Big touch widgets, ID strings | Business rule engines |
| `core/errors/` | Map API errors to screens | Swallow payment uncertainty as success |

### State management

Prefer **simple** approach already common in Flutter (e.g. lightweight controllers/Riverpod/Bloc — choose one and keep thin). UI state only; server owns commerce state.

---

## 9. Mapping product surfaces → folders

| Product surface | Backend | Kiosk |
|---|---|---|
| Self-service browse/pay/print | API V1 + Actions | `features/catalog`, `checkout`, `tickets` |
| Assisted-service | Livewire + same Actions | — |
| Gate validate/check-in | Livewire + CheckIn Actions (+ API) | — (not default purchase kiosk) |
| Device fleet | Devices Actions + Livewire | `device/`, heartbeat |
| Reports/audit/settings | Livewire + Policies | — |
| Payment webhook | `Controllers/Api/V1/Webhooks` + Jobs | — |

---

## 10. Tests layout emphasis

```text
backend/tests/Feature/Critical/CriticalPathJourneyTest.php
backend/tests/Feature/Kiosks/KioskApiContractSmokeTest.php
backend/tests/Feature/Kiosks/KioskApiMoneyRejectionTest.php
backend/tests/Feature/Orders/CreateOrderTest.php
backend/tests/Feature/Payments/InitiatePaymentTest.php
backend/tests/Feature/Payments/PaymentWebhookTest.php
backend/tests/Feature/Tickets/IssueTicketsTest.php
backend/tests/Feature/CheckIns/DoubleCheckInTest.php
backend/tests/Feature/Payments/RefundPaymentTest.php
backend/tests/Feature/Rbac/RbacMatrixTest.php
backend/tests/Feature/Security/SecurityHardeningTest.php
backend/tests/Feature/AssistedSale/AssistedServiceTest.php
backend/tests/Feature/Gate/GateScannerTest.php
backend/tests/Unit/Services/PricingServiceTest.php

kiosk/test/features/checkout/checkout_session_test.dart
kiosk/test/device/kiosk_controller_test.dart
kiosk/test/network/...
```

Run `php artisan test` for the full gate, or `composer test:critical` for the `@group critical` pack. Prioritize integrity tests over UI snapshot volume.

---

## 11. Environment & secrets placement

| Item | Location |
|---|---|
| Backend `.env` | `backend/.env` (gitignored) |
| Backend example | `backend/.env.example` |
| Kiosk flavors / API base URL | `kiosk` config / `--dart-define` / flavor envs — no secrets in git |
| Docs of required env keys | `docs/` or `.env.example` comments |

---

## 12. Growth path (only when justified)

| Trigger | Allowed evolution |
|---|---|
| Many overlapping queries | Introduce selective query objects / read models |
| Second payment provider | New adapter under `Integrations/Payments` |
| Gate hardware | `Integrations/Gate` + optional kiosk/gate client mode |
| True multi-tenant SaaS | Tenancy package / scoping — not premature folders |
| Microservice need proven | Extract **one** bounded context with metrics — default remains monolith |

---

## 13. Decision summary

1. Monorepo: `backend/` + `kiosk/` + `docs/` + `.cursor/`.  
2. Laravel structure stays conventional; domain clarity via `Actions/` + `Services/` + `Integrations/`.  
3. Livewire and API Controllers are thin facades over **shared Actions**.  
4. Repositories/DTOs only when needed — not as default architecture.  
5. Flutter folders bound device concerns; **no business authority** in the kiosk app.  
6. No Staff Flutter tree in MVP.

---

*End of project structure. No application implementation code.*
