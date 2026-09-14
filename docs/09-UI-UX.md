# WanderDesa — UI / UX Specification

**Document ID:** `09-UI-UX`  
**File:** `docs/09-UI-UX.md`  
**Status:** UX specification (no implementation code)  
**Based on:** `docs/02-PRD.md`, `docs/03-SRS.md`, `docs/04-SYSTEM-DESIGN.md`  
**Interfaces:**  
A. Flutter **Physical Self-Service Kiosk**  
B. Laravel Blade + Livewire **Operational Dashboard**

---

## 0. Design principles

### Shared product principles

1. Laravel is authority — UI displays server state; never invent PAID / ISSUED / USED locally.
2. Two channels, one language of outcomes — same statuses, money, and ticket meaning on kiosk and dashboard.
3. Clarity under peak load — few steps, large actions, unambiguous success/failure.
4. Safe retry — retries are visible and idempotent; no double-charge UX.
5. Bahasa Indonesia first on kiosk; dashboard Indonesian primary, English optional later.

### A. Kiosk is not a mobile app

| Mobile app pattern | Kiosk pattern |
|---|---|
| Personal account / profile | Anonymous public session |
| Pocket portrait scrolling | Landscape locked fullscreen terminal |
| System gestures / multitasking | Kiosk mode; escape minimized |
| Dense icon nav | One-task flow: browse → pay → ticket |
| Push notifications | Idle attract + on-screen states only |
| Offline-first success | Offline = block success claims |

**Target hardware:** Advan A10 class Android tablet · landscape · touch · public mount.

### B. Dashboard is not CRUD-only admin

Staff can complete **assisted-service** (order → pay → print) and gate ops, with **role-specific navigation**.

---

# A. Flutter Physical Self-Service Kiosk

## A0. Kiosk visual system

| Token | Guidance |
|---|---|
| Orientation | Landscape only |
| Layout | Edge-aware safe margins; avoid notch/status intrusion in kiosk mode |
| Type | Large, high-contrast sans for distance reading (not tiny mobile type) |
| Contrast | WCAG-minded outdoor/indoor glare; avoid low-contrast gray on gray |
| Color roles | Primary CTA, success, warning, danger, neutral surface |
| Motion | Short, purposeful (screen enter, progress pulse); no playful clutter |
| Language | ID labels primary (`Mulai`, `Bayar`, `Cetak tiket`, …) |
| Brand | Destination/operator mark on idle + header strip; product name secondary |

### Touch targets

| Element | Minimum |
|---|---|
| Primary CTA | ≥ 64×64 dp visual; full-width bar preferred for pay/confirm |
| Secondary actions | ≥ 48×48 dp |
| Ticket qty stepper | ≥ 56 dp hit area |
| Spacing between controls | ≥ 8–12 dp to reduce mis-taps |
| No hover-only affordances | Touch + pressed states only |

### Accessibility (kiosk)

- High contrast modes if glare detected/config
- Avoid color-only status (icon + text)
- Readable focus/pressed states
- Timeout warnings announced on-screen (and optional chime)
- Minimal required reading level; icons + short verbs
- Do not rely on small checkbox legal walls mid-flow

### Locked / fullscreen behavior

- App starts in locked fullscreen / kiosk mode
- Soft-nav / status / gesture bars suppressed as far as OS allows
- No path to settings/browser for visitors
- Maintenance / remote disable overrides commerce UI
- Crash recovery returns to Idle or Recovery (safe), never a fake success

### Timeouts & idle

| Timer | Default (configurable) | Behavior |
|---|---|---|
| Idle attract | After 45–60s no input on home | Go Idle Screen |
| Session inactivity | 60–90s mid-flow | Warning 10s → cancel unpaid draft → Idle |
| Payment wait | Align server TTL (e.g. 15 min) with visible countdown | Pending UX; poll status |
| Success dwell | 20–30s | Auto return Idle |
| Error dwell | Manual CTA + 60s auto-idle | |

### Loading / confirmation / error / retry patterns

| State | UX |
|---|---|
| Loading | Full-screen or modal blocker with spinner + short verb (“Menghitung harga…”) |
| Confirmation | Order summary must show **server totals** before pay |
| Error | Icon + title + one action (“Coba lagi” / “Minta bantuan petugas”) |
| Safe retry | Same attempt keeps idempotency; changing cart starts fresh |
| Never | Green “Berhasil” unless server says so |

---

## A1. Kiosk home screen

**Purpose:** Entry to self-service.

**Content:** Brand/destination mark · welcome headline · primary CTA **Mulai** · optional language toggle · subtle “Butuh bantuan? Ke loket” · device not show admin chrome.

**States:** Ready · Maintenance banner if upcoming · Offline if API unreachable.

**Actions:** Mulai → Destination browsing (or Ticket selection if single destination).

---

## A2. Destination browsing

**Purpose:** Choose destination when multi-destination.

**Content:** Large destination cards (name, short blurb, image if available).

**Empty:** “Destinasi belum tersedia” + staff help.

**Skip:** Single-destination deployments land directly on ticket selection.

---

## A3. Ticket selection

**Purpose:** Pick ticket type(s) and quantity.

**Content:** Ticket name · short description · **server unit price** · qty stepper · running server quote if multi-item · CTA **Lanjut**.

**Rules:** Show prices from API only; inactive types hidden; enforce max_per_order.

**Alt:** Visit date picker if ticket type requires it.

---

## A4. Visitor information

**Purpose:** Optional minimal visitor data.

**MVP default:** **Skip screen** — optional note only if operator enables.

**If enabled:** Few fields (name/phone) marked optional; never block purchase for PII; large keyboard-friendly inputs; Skip CTA prominent.

---

## A5. Order summary

**Purpose:** Confirm before payment with authoritative money.

**Content:** Line items · qty · server line totals · subtotal · discount · tax · service fee · **grand total** · destination · validity hint · CTA **Bayar** · Back.

**Loading:** Recalculate quote on enter.

**Error:** Stale catalog → refresh message.

**Confirmation state:** Explicit “Total dibayar” emphasis.

---

## A6. Payment screen

**Purpose:** Choose/start payment method available on kiosk (digital MVP).

**Content:** Total (server) · method instructions · provider QR / VA / instructions region · Cancel order CTA (unpaid).

**Cash:** Not on public kiosk (counter only).

---

## A7. Payment processing

**Purpose:** Waiting for Laravel payment state.

**Content:** Indeterminate/ determinate progress · “Menunggu pembayaran…” · countdown if TTL known · “Jangan tutup layar” · secondary “Cek status” (poll).

**Rules:** Local cache cannot mark success; poll `payments/{id}`.

---

## A8. Payment success

**Purpose:** Celebrate only after server `PAID` (+ tickets issued).

**Content:** Success icon · “Pembayaran berhasil” · order number · ticket count · auto-advance to ticket generation/print.

**Dwell:** Short then continue; no editable money.

---

## A9. Payment failure

**Purpose:** Recover without duplicate confusion.

**Content:** Failure reason (generic safe) · **Coba bayar lagi** · **Ubah tiket** · **Batal** · “Ke loket” help.

**Rules:** Failed payment row is terminal; retry initiates new payment attempt per API rules.

---

## A10. Ticket generation

**Purpose:** Show issuance in progress / ready from server tickets.

**Content:** “Menerbitkan tiket…” then ticket list (codes) when ISSUED/ACTIVE.

**Error:** Paid but issue lag → “Sedang diproses” + poll; never ask to pay again.

---

## A11. QR display

**Purpose:** On-screen QR for each ticket (backup if print fails).

**Content:** Large QR · ticket code · validity · swipe/pager for multi-ticket · warning “Jangan bagikan sembarangan”.

---

## A12. Ticket printing

**Purpose:** Trigger local print of server print-payload.

**Content:** “Mencetak tiket…” · success “Ambil tiket Anda” · multi-ticket progress.

**Failure:** Jump to Printer error (§A16) while keeping tickets valid.

---

## A13. Check-in / scan screen (kiosk)

**MVP decision:** **Not on public purchase kiosk** by default.

Gate check-in lives on **Dashboard** (or dedicated gate client).  
If a future combo terminal exists: separate mode requiring device ability + staff overlay — out of default visitor flow.

---

## A14. Maintenance screen

**Purpose:** Block sales when maintenance/remote policy.

**Content:** Full-screen “Kiosk dalam pemeliharaan” · staff contact · no commerce CTAs · optional QR to counter info.

**Entry:** Heartbeat command / local flag from API status.

---

## A15. Network error

**Purpose:** Honest connectivity failure.

**Content:** “Tidak ada koneksi ke server” · Retry · “Gunakan loket” · no fake success.

**Mid-payment:** “Status belum diketahui — jangan bayar dua kali” · Retry status check.

---

## A16. Printer error

**Purpose:** Print failed after PAID/ISSUED.

**Content:** “Pembayaran berhasil, cetak gagal” · Show QR on screen · “Minta cetak ulang di loket” · Selesai → Idle.

**Tone:** Success of money preserved; print is operational failure.

---

## A17. Scanner error

**N/A on purchase kiosk MVP.** If present on combo device: “Scanner tidak siap” · manual code entry only in staff mode · never local ALLOW.

---

## A18. Payment terminal error

**Purpose:** Provider/terminal session failure.

**Content:** “Terminal pembayaran bermasalah” · Retry · Change nothing about local PAID · Cancel unpaid · Help.

---

## A19. Session timeout

**Purpose:** Warn then clear unpaid session.

**Content:** Modal “Sesi berakhir dalam 10 detik” · Lanjutkan · timeout → cancel unpaid draft if any → Idle.

---

## A20. Idle screen

**Purpose:** Attract loop / attractor.

**Content:** Destination imagery · “Sentuh untuk mulai” · brand · quiet motion (slow ken burns) · no admin UI.

**Tap anywhere/Mulai → Home.**

---

## A21. Recovery screen

**Purpose:** After crash/restart with unknown in-flight payment.

**Content:** “Memulihkan transaksi…” · uses stored order/payment ids + server poll · outcomes: Success path / Failure / “Tanya petugas” with order number if known · never assume PAID from disk alone.

---

## A22. Kiosk end-to-end map

```text
Idle → Home → (Destinations) → Ticket select → [Visitor optional]
  → Order summary → Payment → Processing
  → Success → Ticket generation → QR → Print → Idle
                 ↘ Failure → Retry/Cancel → …
Network / Maintenance / Printer / Terminal / Timeout / Recovery overlay as needed
```

---

# B. Laravel Blade + Livewire Dashboard

## B0. Dashboard visual system

| Area | Guidance |
|---|---|
| Density | Operational: readable tables, clear filters; not marketing hero layouts |
| Theme | Neutral professional tourism-ops; strong status chips |
| Type | Clear UI sans; numeric tabular for money |
| Brand | Operator mark in shell header; product name “WanderDesa” in app shell |
| Motion | Subtle Livewire transitions; prefer instant feedback for POS |

### Shell layout

| Region | Content |
|---|---|
| **Sidebar** | Role-filtered primary nav |
| **Top navigation** | Breadcrumb · global search (orders/tickets) · notifications · profile menu · destination context switcher (if multi) |
| **Main** | Page header (title + primary CTA) · filters · content |
| **Footer** | Minimal version/env indicator for ops |

### Navigation rules

- Build nav from **permissions** (RBAC), not hardcoded role names only
- Hide unauthorized modules (UX); server still enforces
- Group: **Operasional** · **Katalog** · **Perangkat** · **Keuangan** · **Laporan** · **Administrasi** · **Sistem**

### Role-specific navigation (default visible modules)

| Module | Super Admin | Admin | Manager | Finance | Ticket Officer | Gate Officer | Operator | Auditor |
|---|---|---|---|---|---|---|---|---|
| Home dashboard | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Assisted sale | ✓ | ✓ | | | ✓ | | | |
| Orders | ✓ | ✓ | ✓ | ✓ | ✓ | | ○ | ✓ |
| Payments | ✓ | ✓ | ✓ | ✓ | ✓ | | | ✓ |
| Tickets | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Validation / Check-in | ✓ | ✓ | | | | ✓ | | |
| Visitors | ✓ | ✓ | | | ○ | | | ○ |
| Destinations / Ticket types / Pricing | ✓ | ✓ | view | view | view | | | view |
| Kiosks / Devices | ✓ | ✓ | view | | | | ✓ | view |
| Reports / Analytics | ✓ | ✓ | ✓ | ✓ | | | | ✓ |
| Users / Roles / Permissions | ✓ | users | | | | | | view users |
| Audit logs | ✓ | ✓ | | ✓ | | | | ✓ |
| Settings / Maintenance | ✓ | ○ | | | | | ○ maint | |
| Profile | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

○ = optional / limited

---

## B1. Login

**Purpose:** Staff authentication.

**Content:** Email/username · password · submit · error inline · inactive account message · no public register.

**States:** Loading submit · lockout message if applicable · accessibility labeled fields.

---

## B2. Dashboard (home)

**Purpose:** Role-aware ops snapshot.

**Widgets by role examples:**

- Ticket Officer: “Mulai penjualan” CTA · today’s assisted count  
- Gate Officer: shortcut to Scan/Check-in · today’s allows/denies  
- Operator: kiosk online/offline/maintenance cards  
- Manager/Finance: sales today · channel mix · payments pending aging  
- Auditor: recent sensitive audit events  

**Empty:** Friendly first-run / no data yet.  
**Loading:** Skeleton cards.  
**Error:** Banner retry.

---

## B3. Orders

**Purpose:** Search/list orders.

**UI:** Filterable table — order number, channel, status, total, destination, created at, actor/device · row → detail.

**Filters:** date range, status, channel (`kiosk`/`assisted`), destination.  
**Search:** order number.  
**Pagination:** standard.  
**Empty / loading / error:** table patterns (§B patterns).

**Detail:** items, totals (read-only money), payments, tickets, audit snippets.

---

## B4. Assisted-service transactions

**Purpose:** POS-like staff sale (critical UX).

**Flow screens/steps (single page wizard or stepped Livewire):**

1. Select destination (if needed)  
2. Add ticket types + qty (server quote live)  
3. Optional visitor/note  
4. Summary with **server grand total**  
5. Collect payment (cash confirm / digital)  
6. Result + print  

**Rules:** No editable price fields for officers · large Pay/Confirm · success only after server PAID+ISSUED · reprint CTA.

**Confirmation:** Modal “Terima tunai Rp X?” for cash.

---

## B5. Payments

**Purpose:** Finance/ops payment list.

**Table:** payment number, order, method, status, amount, provider ref, timestamps.  
**Filters:** status, method, date, destination.  
**Actions:** view · refund (permissioned) with reason modal.  
**Restricted:** no manual status paint-to-PAID control.

---

## B6. Tickets

**Purpose:** Lookup and inspect tickets.

**Search:** ticket code primary.  
**Table/detail:** status chips, validity, channel, order link, check-in info.  
**Actions:** print/reprint (permission), validate shortcut for gate roles.

---

## B7. Ticket validation

**Purpose:** Inspect ALLOW/DENY without necessarily consuming (if validate-only supported).

**UI:** Scanner input autofocus · manual code field · result panel big ALLOW (green) / DENY (red) + reason text.  
**Loading:** Validating…  
**Error:** API/network banner.

---

## B8. Check-in

**Purpose:** Validate + consume.

**UI:** Same scan-first layout as validation · on ALLOW commit show “Check-in berhasil” · on DENY_ALREADY_USED emphatic reuse warning.  
**Confirmation:** Optional confirm step off by default for speed; rely on transactional API.  
**No reverse UI in MVP.**

---

## B9. Visitors

**Purpose:** Optional lightweight visitor records.

**MVP:** Simple list/search; create from assisted flow.  
**Empty:** Explain visitors optional.  
**Privacy:** Minimal fields; no unnecessary PII forms.

---

## B10. Destinations

**Purpose:** Admin catalog of destinations.

**UI:** Table + form create/edit · active toggle · soft delete caution.  
**Validation:** code unique, timezone.

---

## B11. Ticket types

**Purpose:** Manage sellable products.

**UI:** Per destination list · form for name, code, validity, max per order, active.  
**Link:** Pricing fields on same form or dedicated pricing panel.

---

## B12. Pricing

**Purpose:** Set unit price / tax / fee (IDR integers).

**UI:** Clear currency formatting · warn on change impact · audit implied.  
**Permission:** manage only; others read-only view.  
**Never:** expose client-side calculator as authority — save hits server.

---

## B13. Kiosks

**Purpose:** Fleet list and lifecycle.

**UI:** Cards/table — name, device_id, destination, status, maintenance, last heartbeat, version.  
**Actions:** register · activate (show one-time code modal) · maintenance toggle · deactivate confirm.  
**Status chips:** registered / active / maintenance / disabled / offline (derived).

---

## B14. Devices

**Purpose:** Same domain as kiosks; alias page or tab for hardware identity detail.

**UI:** Detail pane — activation timestamps, software/hardware versions, recent heartbeats (if stored), token revoke on deactivate messaging.

---

## B15. Reports

**Purpose:** EOD/ops reporting.

**UI:** Report picker · date/destination filters · HTML summary tables · Export CSV if `reports.export`.  
**Reports:** daily sales by channel · payments by status · tickets issued vs used · refunds/cancels.  
**Loading:** progress for heavy queries.  
**Empty:** “Tidak ada data pada filter ini”.

---

## B16. Analytics

**Purpose:** Lightweight trends (not full BI).

**UI:** Simple charts/ sparklines — kiosk funnel, deny reasons, offline count.  
**Role:** Manager+ primarily.  
**Empty:** Not enough data yet.

---

## B17. Users

**Purpose:** Staff user admin.

**UI:** Table · create/edit · active flag · assign **primary role** · reset password flow (out-of-band as designed).  
**Restriction:** cannot elevate beyond actor’s assign rights; only Super Admin assigns `super_admin`.

---

## B18. Roles

**Purpose:** View role catalog (MVP mostly seeded).

**UI:** List roles + description · link to permissions matrix read-only for Admin; edit restricted to Super Admin if enabled.

---

## B19. Permissions

**Purpose:** Transparency of grants.

**UI:** Role × permission matrix view (read-only for most) · Super Admin manage if product allows editing seeds carefully.  
**Warning:** Changing permissions is sensitive — confirmation modal.

---

## B20. Audit logs

**Purpose:** Auditor/compliance trail.

**UI:** Filter by action, actor, entity, date · detail drawer JSON summary · **no edit/delete**.  
**Export:** optional with permission.

---

## B21. Settings

**Purpose:** System TTL, heartbeat stale seconds, locale defaults.

**UI:** Grouped forms · save with confirmation · Super Admin (Admin optional).  
**Danger zone:** separate styling for destructive settings.

---

## B22. Maintenance

**Purpose:** Ops controls — system maintenance banner and/or bulk kiosk maintenance.

**UI:** Toggle system banner · list kiosks with maintenance switches · confirm impact (“Penjualan kiosk berhenti”).

---

## B23. Profile

**Purpose:** Self-service account.

**UI:** Name, email (read/restricted), change password, logout · last login info.  
**No:** self role elevation.

---

## B24. Dashboard interaction patterns

### Navigation / sidebar / top nav

- Collapsible sidebar on tablet widths  
- Active item highlighted  
- Top bar sticky; global ticket/order search always available for ops roles  

### Tables

- Sticky header · status chips · row click · bulk actions rare in MVP  
- Money columns right-aligned tabular nums  

### Forms

- Labels above fields · server validation errors inline · disable submit while saving  

### Filters / search / pagination

- Filter bar above tables · “Reset filter” · search debounce Livewire · pagination with total count  

### Modal

- Confirm destructive: deactivate kiosk, refund, permission changes  
- Cash confirm modal  
- Activation code modal (copy once + warning)  

### Toast

- Success/non-blocking confirms (saved, printed ack)  
- Errors prefer inline/banner for POS-critical failures  

### Empty state

- Illustration optional · one sentence · primary CTA if actionable  

### Loading state

- Skeleton tables · button spinners · avoid full blank white  

### Error state

- Page-level alert with retry · 403 dedicated “Tidak berwenang” page  

### Confirmation

- Refund · deactivate · cancel unpaid · maintenance on  

### Responsive behavior

| Breakpoint | Behavior |
|---|---|
| Desktop | Sidebar + multi-column forms |
| Tablet | Collapsible sidebar; assisted sale still usable |
| Mobile phone | **Supported for monitoring/lookup**, not primary for assisted POS or gate scan; show warning if viewport too narrow for POS |

### Accessibility (dashboard)

- Keyboard reachable nav/forms  
- Visible focus  
- Label associations  
- Status not color-only  
- Sufficient contrast  
- Don’t auto-timeout mid-type without warning on long forms  

---

## C. Cross-interface consistency

| Concept | Kiosk presentation | Dashboard presentation |
|---|---|---|
| Order status | Short ID words | Chips + filters |
| Payment pending | Big wait screen | Table chip + detail poll |
| PAID success | Green success only post-server | Same status chip |
| Print fail | Keep success + QR | Reprint action |
| Deny reasons | N/A (gate dashboard) | Large reason text |
| Offline | Hard stop messaging | Kiosk offline badges |

---

## D. Copy tone (ID examples)

| Situation | Tone |
|---|---|
| Idle | Inviting, short |
| Pay wait | Calm, instruct not to double-pay |
| Fail | Blame-free, offer loket |
| Maintenance | Neutral, closed for service |
| Gate deny | Firm, clear, non-accusatory when possible |

---

## E. Out of UX scope (MVP)

- Consumer tourist mobile app UI  
- Staff Flutter app UI  
- Marketing website  
- Dark novelty themes that hurt outdoor kiosk readability  
- Playful gamification on payment  

---

## F. Acceptance checklist (UX)

### Kiosk

- [ ] Landscape fullscreen; large CTAs  
- [ ] Totals only from server  
- [ ] No local PAID success  
- [ ] Idle/timeout/maintenance/network/printer paths exist  
- [ ] Print fail still shows QR  

### Dashboard

- [ ] Role-filtered nav  
- [ ] Assisted sale happy path under ~90s target (PRD)  
- [ ] Gate ALLOW/DENY unmistakable  
- [ ] Refund/deactivate confirmations  
- [ ] Auditor read-only audit UI  

---

*End of UI/UX specification. No implementation code.*
