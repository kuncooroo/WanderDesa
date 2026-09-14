# WanderDesa — RBAC Design

**Document ID:** `07-RBAC`  
**File:** `docs/07-RBAC.md`  
**Status:** Authorization architecture (no implementation code)  
**Based on:** `docs/02-PRD.md`, `docs/03-SRS.md`, `docs/04-SYSTEM-DESIGN.md`, `docs/05-BUSINESS-FLOW.md`, `docs/06-DATABASE.md`

---

## 0. Authority principles

1. **Laravel is the authorization authority.** Every mutating and sensitive read path is enforced server-side.
2. **Client-side checks are UX only** (hide/disable buttons). They never grant power.
3. Flutter Kiosk is a **device principal**, not a staff role. Device abilities are separate from user RBAC.
4. Kiosk and Dashboard share the same business rules; they do **not** share the same principal type.
5. Deny by default. Missing permission = deny.
6. Least privilege. Broad “do everything” roles are limited to break-glass administration.
7. Sensitive operations require permission **and** domain eligibility (e.g. refund only if ticket not `USED`).
8. Privilege escalation prevention: only designated admin roles may assign roles/permissions.
9. Critical authz decisions are auditable.

---

## 1. Final role structure (determined from requirements)

### 1.1 Candidate roles evaluated

| Candidate | Decision | Rationale |
|---|---|---|
| SUPER_ADMIN | **Keep** | Maps to PRD System Administrator / break-glass + security controls |
| ADMIN | **Keep** | Day-to-day configuration: catalog, users (limited), kiosks |
| MANAGER | **Keep** | Operational oversight + reports; not finance refunds by default |
| FINANCE | **Keep** | Payments visibility, refunds, reconciliation views |
| TICKET_OFFICER | **Keep** | Assisted-service sales (MVP-critical) |
| GATE_OFFICER | **Keep** | Validation / check-in |
| OPERATOR | **Keep** | Kiosk fleet health & maintenance |
| AUDITOR | **Keep** | Read-only audit + sensitive report access |
| STAFF | **Defer / do not seed in MVP** | Too vague; causes over-permissioning. Use explicit job roles instead |
| Generic “STAFF Flutter role” | **Reject** | No Staff Flutter app in MVP |

### 1.2 MVP seeded roles (final)

| Role code | Display name | Primary job |
|---|---|---|
| `super_admin` | Super Admin | System ownership, integration secrets, role assignment, break-glass |
| `admin` | Admin | Destination ops configuration & user administration (non-break-glass) |
| `manager` | Manager | Operational monitoring & business reports |
| `finance` | Finance | Money visibility, refunds, payment reconciliation |
| `ticket_officer` | Ticket Officer | Assisted ticket sales & print |
| `gate_officer` | Gate Officer | QR validation & check-in |
| `operator` | Operator | Kiosk monitoring & maintenance mode |
| `auditor` | Auditor | Read-only audit trail & compliance reports |

### 1.3 Role assignment policy (MVP)

| Rule | Detail |
|---|---|
| Primary model | One **primary role** per user for simplicity |
| Multi-role | Allowed technically (`role_user`), but discouraged in MVP unless dual-hat is real |
| Visitor | Not a dashboard role |
| Device | Not a `roles` row; uses device token abilities |

---

## 2. Principal types

```text
Staff User  → session auth (web) → RBAC permissions
Kiosk Device → Sanctum token → device abilities (not staff permissions)
System/Job  → internal actor → audited as actor_type=system
Webhook     → signature auth → no user role; narrowly scoped handlers
```

---

## 3. Permission catalog

Permissions use `resource.action` names. Naming below is the **canonical MVP set**, aligned to product needs (including the requested vocabulary). Where PRD used older aliases, mapping is noted.

### 3.1 Users

| Permission | Meaning |
|---|---|
| `users.view` | View staff users |
| `users.create` | Create staff users |
| `users.update` | Update staff users / activate-deactivate |
| `users.delete` | Soft-delete / remove staff users |
| `roles.assign` | Assign/remove roles (**sensitive**) |

### 3.2 Catalog / destinations / ticket types / pricing

| Permission | Meaning |
|---|---|
| `destinations.view` | View destinations |
| `destinations.manage` | Create/update/deactivate destinations |
| `ticket_types.view` | View ticket types |
| `ticket_types.manage` | Create/update/deactivate ticket types & prices |

> Price changes are sensitive; covered by `ticket_types.manage` + mandatory audit.

### 3.3 Orders

| Permission | Meaning |
|---|---|
| `orders.view` | View orders |
| `orders.create` | Create assisted orders |
| `orders.update` | Limited updates (notes) — **no money field edits** |
| `orders.cancel` | Cancel unpaid orders |

Alias note: PRD `orders.create` (assisted) ≡ `orders.create`.

### 3.4 Transactions

In WanderDesa MVP, **transactions = payments** (no separate ledger).

| Permission | Meaning |
|---|---|
| `transactions.view` | Alias visibility into payment/money movements (maps to payments views) |
| `transactions.create` | **Not used as separate grant** — creating money movement is `payments.create` / collect |
| `transactions.update` | **Denied globally** — status only via domain transitions |
| `transactions.cancel` | Maps to cancelling non-paid payments via `orders.cancel` / payment cancel path |

Canonical enforcement should check **`payments.*` / `orders.cancel`**, not a parallel transaction engine.

### 3.5 Payments

| Permission | Meaning |
|---|---|
| `payments.view` | View payments & statuses |
| `payments.create` | Initiate/collect payment (digital assist or cash confirm) |
| `payments.refund` | Create refunds (**sensitive**) |

Aliases: `payments.collect.cash` / `payments.collect.digital` may be modeled as the single `payments.create` for MVP, or split later if segregation is required.

### 3.6 Tickets

| Permission | Meaning |
|---|---|
| `tickets.view` | Lookup/view tickets |
| `tickets.create` | **System-only after PAID** — staff do not manually mint tickets |
| `tickets.update` | **Denied** for money/status free-edit |
| `tickets.cancel` | Cancel unused tickets under policy (usually via refund/cancel flows) |
| `tickets.validate` | Run validation (inspect / gate) |
| `tickets.print` | Print tickets |
| `tickets.reprint` | Reprint existing ticket (**audited**, semi-sensitive) |

### 3.7 Check-ins

| Permission | Meaning |
|---|---|
| `checkins.view` | View check-in history |
| `checkins.create` | Perform check-in (PRD `checkin.perform`) |
| `checkins.reverse` | Reverse USED→ACTIVE (**not in MVP**; deny) |

### 3.8 Reports / analytics

| Permission | Meaning |
|---|---|
| `reports.view` | View operational/finance reports |
| `reports.export` | Export report files |
| `analytics.view` | Lightweight analytics/funnels (post-basic MVP ok; read-only) |

### 3.9 Settings / integrations / audit

| Permission | Meaning |
|---|---|
| `settings.view` | View system settings |
| `settings.update` | Update system settings (**sensitive**) |
| `integrations.manage` | Payment/hardware integration config (**highly sensitive**) |
| `audit_logs.view` | View audit trail |

### 3.10 Kiosks / devices

| Permission | Meaning |
|---|---|
| `kiosks.view` | View kiosk fleet |
| `kiosks.create` | Register kiosk |
| `kiosks.update` | Update kiosk metadata |
| `kiosks.activate` | Activate device / issue activation material |
| `kiosks.deactivate` | Disable / deactivate device (**sensitive**) |
| `kiosks.maintenance` | Toggle maintenance mode |

### 3.11 Explicit non-permissions (MVP)

| Not granted to humans | Reason |
|---|---|
| Free-edit payment status | Domain/webhook only |
| Free-edit ticket status | Domain only |
| `checkins.reverse` | Out of MVP |
| Client-supplied totals authority | Never a permission |

---

## 4. Role definitions

### 4.1 SUPER_ADMIN (`super_admin`)

| Area | Definition |
|---|---|
| **Responsibilities** | Own production safety: integrations, role assignment, emergency device disable, break-glass recovery |
| **Allowed modules** | All dashboard modules |
| **Allowed actions** | Full permission set including `roles.assign`, `integrations.manage`, `settings.update`, kiosk activate/deactivate |
| **Restricted actions** | Still cannot bypass domain invariants (e.g. invent PAID without payment record; AI mutating tickets). Prefer using finance flows for refunds |
| **Sensitive operations** | Role assignment, integration secrets, mass deactivate devices, settings that affect TTL/payment |

### 4.2 ADMIN (`admin`)

| Area | Definition |
|---|---|
| **Responsibilities** | Configure destinations/ticket types/prices; manage ordinary users; manage kiosk lifecycle; support ops |
| **Allowed modules** | Users (limited), Catalog, Orders (view), Tickets (view/print), Payments (view), Kiosks, Reports (view), Settings (view/limited update if delegated), Check-ins view |
| **Allowed actions** | `users.*` (except maybe not delete super_admin), `destinations.manage`, `ticket_types.manage`, `kiosks.*`, `orders.view`, `tickets.view/print/reprint`, `payments.view`, `reports.view`, `checkins.view`, optional `orders.create`/`payments.create` for backup assisted sales |
| **Restricted actions** | No `roles.assign` to grant `super_admin`; no `integrations.manage` by default; refunds only if also finance (default **no** `payments.refund`) |
| **Sensitive operations** | Price changes, user create, kiosk activate/deactivate |

**MVP recommendation:** Admin **may** perform assisted sale (`orders.create` + `payments.create`) for coverage; refunds remain Finance.

### 4.3 MANAGER (`manager`)

| Area | Definition |
|---|---|
| **Responsibilities** | Monitor sales, channel mix, ticket usage, kiosk health; investigate operational issues |
| **Allowed modules** | Reports, Analytics (read), Orders/Tickets/Payments view, Kiosks view, Check-ins view |
| **Allowed actions** | `reports.view`, `reports.export`, `analytics.view`, `orders.view`, `tickets.view`, `payments.view`/`transactions.view`, `kiosks.view`, `checkins.view`, `destinations.view`, `ticket_types.view` |
| **Restricted actions** | No catalog price edit, no refunds, no user admin, no kiosk activate/deactivate, no settings update, no check-in create unless dual-hat |
| **Sensitive operations** | Report export (business-sensitive) |

### 4.4 FINANCE (`finance`)

| Area | Definition |
|---|---|
| **Responsibilities** | Reconcile payments, issue refunds under policy, review money states |
| **Allowed modules** | Payments, Orders, Tickets (view), Reports, Audit (optional read), Refunds |
| **Allowed actions** | `payments.view`, `payments.refund`, `transactions.view`, `orders.view`, `tickets.view`, `reports.view`, `reports.export`, `audit_logs.view` (recommended) |
| **Restricted actions** | No catalog manage, no kiosk admin, no role assign, no check-in reverse, no free payment status edit |
| **Sensitive operations** | `payments.refund` |

### 4.5 TICKET_OFFICER (`ticket_officer`)

| Area | Definition |
|---|---|
| **Responsibilities** | Assisted-service: sell, collect payment, print/deliver tickets |
| **Allowed modules** | Assisted Sale, Orders, Tickets print/lookup, Payments collect |
| **Allowed actions** | `orders.create`, `orders.view` (own/shift scope preferred), `orders.cancel` (unpaid), `payments.create`, `payments.view` (limited), `tickets.view`, `tickets.print`, `tickets.reprint` (same-shift policy), `ticket_types.view`, `destinations.view` |
| **Restricted actions** | No refunds, no user admin, no settings, no kiosk register/activate, no audit full access, no catalog price edit, no check-in (unless dual-hat) |
| **Sensitive operations** | Cash collection (`payments.create`), reprint |

### 4.6 GATE_OFFICER (`gate_officer`)

| Area | Definition |
|---|---|
| **Responsibilities** | Validate QR and perform check-in; handle deny messaging |
| **Allowed modules** | Gate / Validation, Ticket lookup, Check-ins |
| **Allowed actions** | `tickets.validate`, `tickets.view`, `checkins.create`, `checkins.view` |
| **Restricted actions** | No sales, no refunds, no catalog, no kiosk admin, no reports export by default, no `checkins.reverse` |
| **Sensitive operations** | `checkins.create` (entry grant) |

### 4.7 OPERATOR (`operator`)

| Area | Definition |
|---|---|
| **Responsibilities** | Keep kiosks healthy: monitor heartbeat, maintenance mode, escalate printer issues |
| **Allowed modules** | Kiosks, limited ticket reprint support, ops notifications |
| **Allowed actions** | `kiosks.view`, `kiosks.maintenance`, `kiosks.update` (metadata only), `tickets.view`, `tickets.reprint` (optional for print recovery), `orders.view` (optional limited) |
| **Restricted actions** | No `kiosks.activate`/`deactivate` unless delegated; no sales; no refunds; no user admin; no settings/integrations |
| **Sensitive operations** | Maintenance mode (blocks public sales) |

**MVP recommendation:** Activate/deactivate reserved to Admin/Super Admin; Operator gets maintenance + view.

### 4.8 AUDITOR (`auditor`)

| Area | Definition |
|---|---|
| **Responsibilities** | Independent review of who did what; compliance reporting |
| **Allowed modules** | Audit logs, Reports (read), Orders/Payments/Tickets/Check-ins read |
| **Allowed actions** | `audit_logs.view`, `reports.view`, `reports.export`, `orders.view`, `payments.view`, `transactions.view`, `tickets.view`, `checkins.view`, `kiosks.view`, `users.view` (read-only roster) |
| **Restricted actions** | **No mutations** of commerce/catalog/devices/settings/roles |
| **Sensitive operations** | Access to audit content itself |

---

## 5. Role × Permission Matrix

Legend: `✓` allow · blank deny · `○` optional MVP (default deny unless product enables) · `✗` explicitly never

| Permission | Super Admin | Admin | Manager | Finance | Ticket Officer | Gate Officer | Operator | Auditor |
|---|---|---|---|---|---|---|---|---|
| **users.view** | ✓ | ✓ | | | | | | ✓ |
| **users.create** | ✓ | ✓ | | | | | | |
| **users.update** | ✓ | ✓ | | | | | | |
| **users.delete** | ✓ | ✓ | | | | | | |
| **roles.assign** | ✓ | | | | | | | |
| **destinations.view** | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **destinations.manage** | ✓ | ✓ | | | | | | |
| **ticket_types.view** | ✓ | ✓ | ✓ | ✓ | ✓ | | ✓ | ✓ |
| **ticket_types.manage** | ✓ | ✓ | | | | | | |
| **orders.view** | ✓ | ✓ | ✓ | ✓ | ✓ | ○ | ○ | ✓ |
| **orders.create** | ✓ | ✓ | | | ✓ | | | |
| **orders.update** | ✓ | ✓ | | | ○ | | | |
| **orders.cancel** | ✓ | ✓ | | | ✓ | | | |
| **transactions.view** | ✓ | ✓ | ✓ | ✓ | ○ | | | ✓ |
| **transactions.create** | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **transactions.update** | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **transactions.cancel** | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **payments.view** | ✓ | ✓ | ✓ | ✓ | ✓ | | | ✓ |
| **payments.create** | ✓ | ✓ | | | ✓ | | | |
| **payments.refund** | ✓ | | | ✓ | | | | |
| **tickets.view** | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **tickets.create** | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **tickets.update** | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **tickets.cancel** | ✓ | ✓ | | ○ | | | | |
| **tickets.validate** | ✓ | ✓ | | | | ✓ | | |
| **tickets.print** | ✓ | ✓ | | | ✓ | | ○ | |
| **tickets.reprint** | ✓ | ✓ | | | ✓ | | ✓ | |
| **checkins.view** | ✓ | ✓ | ✓ | ○ | | ✓ | | ✓ |
| **checkins.create** | ✓ | ✓ | | | | ✓ | | |
| **checkins.reverse** | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **reports.view** | ✓ | ✓ | ✓ | ✓ | | | | ✓ |
| **reports.export** | ✓ | ✓ | ✓ | ✓ | | | | ✓ |
| **analytics.view** | ✓ | ✓ | ✓ | ○ | | | | ○ |
| **settings.view** | ✓ | ✓ | | | | | | ○ |
| **settings.update** | ✓ | ○ | | | | | | |
| **integrations.manage** | ✓ | | | | | | | |
| **audit_logs.view** | ✓ | ✓ | | ✓ | | | | ✓ |
| **kiosks.view** | ✓ | ✓ | ✓ | | | | ✓ | ✓ |
| **kiosks.create** | ✓ | ✓ | | | | | | |
| **kiosks.update** | ✓ | ✓ | | | | | ✓ | |
| **kiosks.activate** | ✓ | ✓ | | | | | | |
| **kiosks.deactivate** | ✓ | ✓ | | | | | | |
| **kiosks.maintenance** | ✓ | ✓ | | | | | ✓ | |

### Matrix notes

1. `tickets.create` is system-driven after `PAID` — not a staff button permission.
2. `transactions.*` create/update/cancel are ✗ to prevent a second money API; use orders/payments domain permissions.
3. `checkins.reverse` remains ✗ for MVP.
4. Optional `○` cells default to **deny** unless a destination explicitly enables them.

---

## 6. Device abilities (not staff roles)

Kiosk Sanctum tokens may allow only:

| Ability | Purpose |
|---|---|
| `device.heartbeat` | Heartbeat |
| `catalog.read` | Browse active catalog |
| `orders.create` | Self-service create order |
| `payments.initiate` | Start digital payment |
| `payments.status` | Poll payment status |
| `tickets.read_own` | Read tickets for own orders |
| `tickets.print_own` | Trigger print for own issued tickets |

**Never on device tokens:** refunds, user admin, settings, role assign, arbitrary ticket mint, check-in reverse, integration secrets, other devices’ data.

Device must also pass domain gates: `ACTIVE`, not maintenance, not disabled.

---

## 7. Authentication

| Principal | Mechanism | Session/Token properties |
|---|---|---|
| Staff | Laravel session (`web` guard) | HTTP-only cookie, CSRF on web, idle timeout |
| Kiosk | Laravel Sanctum token | Bearer token bound to device; revocable |
| Webhook | Provider signature/secret | No RBAC role |
| Scheduler/Queue | System actor | No interactive login |

### Auth requirements

| ID | Rule |
|---|---|
| AUTH-01 | Inactive users cannot login |
| AUTH-02 | Disabled devices cannot authenticate / tokens revoked |
| AUTH-03 | Password hashed; secrets never logged |
| AUTH-04 | Failed logins logged (security) |
| AUTH-05 | Super Admin accounts minimized and named |

---

## 8. Authorization model

```text
Request
  → Authenticate principal
  → Identify guard (web | sanctum device)
  → Authorize permission/ability (Laravel Policy / Gate / middleware)
  → Execute Action
  → Domain invariants still apply
  → Audit if sensitive
```

### Enforcement layers

| Layer | Responsibility |
|---|---|
| Middleware / ability middleware | Coarse route protection |
| Policies | Resource-level allow/deny |
| Application actions | Re-check permission before mutation |
| Domain services | State machine & money invariants |
| DB constraints | Uniqueness / FK integrity (not RBAC substitute) |

**UI menus** may call a “can()” helper for display only.

---

## 9. Policy rules (normative)

| Policy area | Rule |
|---|---|
| Order create | Requires `orders.create` **or** valid device ability; channel set by principal type |
| Order cancel | `orders.cancel` + order `pending_payment` |
| Payment create | `payments.create` or device `payments.initiate` |
| Payment refund | `payments.refund` + refund eligibility (default ticket not `USED`) |
| Ticket print | `tickets.print` / reprint permission + ticket exists |
| Ticket validate | `tickets.validate` or gate check-in permission path |
| Check-in | `checkins.create` + ticket `ACTIVE` + transactional consume |
| Catalog manage | `ticket_types.manage` / `destinations.manage` |
| Kiosk maintenance | `kiosks.maintenance` |
| Kiosk activate/deactivate | respective permissions; deactivate revokes tokens |
| Role assign | only `roles.assign` (Super Admin) |
| Settings/integrations | `settings.update` / `integrations.manage` |
| Audit view | `audit_logs.view` |
| Report export | `reports.export` |

### Hard denies (all roles)

1. Set `payments.status = paid` directly from client  
2. Mint tickets without PAID payment linkage  
3. Open gate without successful check-in  
4. Reverse check-in in MVP  
5. Assign self greater privileges without `roles.assign`  
6. Use device token for staff dashboard routes  

---

## 10. Permission checks (where they must occur)

| Surface | Required |
|---|---|
| Livewire assisted sale actions | Yes, server-side before CreateOrder/CollectPayment |
| Livewire gate check-in | Yes |
| REST kiosk endpoints | Device ability + device status |
| REST/admin APIs (if any) | Permission middleware |
| Report download endpoints | `reports.view` / `reports.export` |
| Artisan/admin scripts | Restricted to ops hosts; not end-user RBAC |

Pseudo-rule (not code): *every mutator calls authorize(permission) then domain service*.

---

## 11. Server-side enforcement requirements

| ID | Requirement |
|---|---|
| ENF-01 | Authorization runs in Laravel for every sensitive request |
| ENF-02 | Frontend permission flags are never trusted |
| ENF-03 | APIs reject missing abilities with 401/403 |
| ENF-04 | Policies receive the authenticated principal (user or device) |
| ENF-05 | Domain eligibility failures return business deny codes, not silent success |
| ENF-06 | Token revocation is immediate for deactivated devices |
| ENF-07 | Super Admin actions still pass through domain invariants |

---

## 12. Privilege escalation prevention

| Risk | Control |
|---|---|
| Admin grants self Super Admin | Only `roles.assign` holder can assign; Admin lacks `roles.assign` |
| Ticket Officer refunds cash silently | No `payments.refund` |
| Operator activates rogue kiosk | No `kiosks.activate` by default |
| Gate Officer sells tickets | No `orders.create` |
| Auditor mutates via crafted request | Auditor has no mutation permissions; server denies |
| Device token used as staff | Separate guard/abilities; no role_user for devices |
| Hidden UI bypass | Server checks on actions, not only menus |
| Mass assignment of `is_admin` | No client-writable elevation fields |
| Permission name typos creating open routes | Deny default; test matrix |

### Break-glass

Super Admin is break-glass. Use named accounts, stronger password/MFA later, and audit every `roles.assign` / `integrations.manage` / `kiosks.deactivate`.

---

## 13. Audit requirements for RBAC

### Always audit

| Event | Why |
|---|---|
| Login success/failure | Security |
| Role assignment/removal | Privilege escalation trail |
| Permission/role catalog changes | Authz integrity |
| `payments.refund` | Money |
| `orders.cancel` | Commerce |
| `tickets.reprint` | Duplicate print risk |
| `checkins.create` | Entry grant |
| `kiosks.activate` / `deactivate` / `maintenance` | Public device control |
| `ticket_types.manage` price changes | Revenue integrity |
| `settings.update` / `integrations.manage` | System integrity |

### Audit record must include

Actor, action, entity, before/after summary (non-secret), IP/device when available, timestamp (UTC).

Auditors can `audit_logs.view` but cannot edit/delete audit rows.

---

## 14. Scope limitations (optional hardening)

MVP may start with global destination access for simplicity. If multi-destination appears:

| Enhancement | Description |
|---|---|
| Destination scoping | User restricted to destination_ids |
| Shift scoping | Ticket officer sees own shift orders |
| Row policies | `orders.view` limited to created_by or destination |

Do not block MVP on full ABAC; keep RBAC flat first.

---

## 15. Sensitive operations register

| Operation | Min role (typical) | Extra controls |
|---|---|---|
| Assign roles | Super Admin | Audit |
| Manage payment integration secrets | Super Admin | Encrypted config / env |
| Refund | Finance (or Super Admin) | Eligibility rules + audit |
| Activate/deactivate kiosk | Admin / Super Admin | Token revoke on deactivate |
| Maintenance mode | Operator+ | Audit |
| Price change | Admin / Super Admin | Audit before/after |
| Reprint | Ticket Officer / Operator / Admin | Audit |
| Check-in | Gate Officer | Transactional unique |
| Report export | Manager / Finance / Auditor / Admin | Optional audit |
| Settings TTL/payment | Super Admin (Admin optional) | Audit |

---

## 16. Testing requirements (authorization)

| Test type | Examples |
|---|---|
| Allow tests | Ticket Officer can create assisted order |
| Deny tests | Ticket Officer cannot refund |
| Guard tests | Device token cannot access user admin |
| Escalation tests | Admin cannot assign `super_admin` without `roles.assign` |
| UI ≠ security | Direct Livewire/API call without permission fails |
| Domain + authz | Finance with refund permission still blocked on USED ticket (default policy) |
| Matrix coverage | Each ✓/blank cell has at least one automated or scripted assertion over time |

---

## 17. Mapping to previous documents

| Source | RBAC reflection |
|---|---|
| PRD roles | Kept and normalized; SysAdmin → `super_admin`; generic Staff deferred |
| PRD permission table | Expanded to canonical `resource.action` names + matrix |
| SRS auth | Session + Sanctum retained |
| Database `roles`/`permissions` | Backing store for this model |
| Business flows | Assisted sale, refund, check-in, kiosk lifecycle permission-gated |

---

## 18. Decisions summary

1. **Final MVP roles:** `super_admin`, `admin`, `manager`, `finance`, `ticket_officer`, `gate_officer`, `operator`, `auditor`.  
2. **Do not seed `STAFF`.**  
3. **Transactions permissions** are visibility aliases only; money mutations use `payments.*` / `orders.*`.  
4. **`tickets.create` / `checkins.reverse` / free status edits** are not human grants.  
5. **Laravel server-side enforcement is mandatory;** client checks are UX only.  
6. **Device abilities ≠ staff RBAC.**  

---

*End of RBAC design. No application implementation code.*
