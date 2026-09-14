# WanderDesa — API Contract

**Document ID:** `08-API-CONTRACT`  
**File:** `docs/08-API-CONTRACT.md`  
**Status:** API contract (no implementation code)  
**Based on:** `docs/02-PRD.md` … `docs/07-RBAC.md`  
**Base URL:** `/api/v1`  
**Format:** JSON · UTF-8  
**Authority:** Laravel calculates all money fields. Clients never submit authoritative financial values.

---

## 0. Contract principles

| ID | Principle |
|---|---|
| API-01 | Laravel is the Single Source of Truth for prices, totals, payment amount, refund amount, ticket/check-in state |
| API-02 | Clients may send **selection inputs** (ticket_type_id, quantity, method). Clients must **not** send authoritative `unit_price`, `subtotal`, `tax`, `service_fee`, `discount`, `grand_total`, `amount`, `payment_status`, `ticket_status` |
| API-03 | If money fields appear in a request body, API **ignores or rejects** them (prefer **422** reject for explicit client money fields) |
| API-04 | Kiosk uses Sanctum **device token**; staff integrations use Sanctum **user token** or session-equivalent staff API auth if exposed |
| API-05 | Dashboard primary UX is Livewire (server-side). This contract is for **Kiosk + external/machine clients**. Staff-facing REST may reuse the same resources with user permissions |
| API-06 | Idempotency required on critical creates |
| API-07 | Stable machine-readable `error.code` values |
| API-08 | All timestamps ISO-8601 UTC (`2026-09-13T06:00:00Z`) |
| API-09 | Money integers = **IDR whole Rupiah** (`50000` = Rp 50.000) |
| API-10 | Channel is derived from principal (`kiosk` for device; `assisted` for staff), not freely forged to escalate privilege |

---

## 1. Cross-cutting conventions

### 1.1 Standard headers

| Header | Required | Used for |
|---|---|---|
| `Authorization` | Yes (except public webhook/activate exchange as specified) | `Bearer {token}` |
| `Accept` | Yes | `application/json` |
| `Content-Type` | Yes on body | `application/json` |
| `Idempotency-Key` | Conditional | Critical POSTs |
| `X-Request-Id` | Recommended | Client correlation (echoed when valid) |

### 1.2 Idempotency

| Rule | Detail |
|---|---|
| Header | `Idempotency-Key: {opaque string ≤ 128 chars}` |
| Scope | Per principal (device_id or user_id) + route/action |
| Replay | Same key + same payload hash → same resource/response |
| Conflict | Same key + different payload → **409** `idempotency.payload_conflict` |
| In progress | Same key while first request still running → **409** `idempotency.in_progress` |
| Required on | Create order, initiate payment, confirm cash, check-in, refund |

### 1.3 Rate limiting (baseline)

| Class | Limit (MVP baseline) |
|---|---|
| Auth / activation | 10 / min / IP |
| Heartbeat | 120 / min / device |
| Catalog reads | 120 / min / token |
| Order/payment writes | 30 / min / token |
| Validate/check-in | 60 / min / token |
| Webhooks | 300 / min / IP (provider NAT aware) |
| Reports | 30 / min / user |

Exact numbers tunable via settings; document defaults here.

### 1.4 Pagination (list endpoints)

Request: `?page=1&per_page=20` (max 100)  
Response meta: `meta.current_page`, `meta.per_page`, `meta.total`, `meta.last_page`

### 1.5 Money field policy

**Server response may include:** `unit_price`, `subtotal`, `discount_total`, `tax_total`, `service_fee_total`, `grand_total`, `amount`, `refund_amount`.

**Client request must not authoritatively supply those values.** Allowed client commerce inputs:

- `destination_id` / `ticket_type_id`
- `quantity`
- `visit_date` (if required by ticket type)
- `payment.method` (`cash` \| `qris` \| `debit` \| `e_wallet`) where permitted; legacy `digital` accepted as alias of `e_wallet`
- notes / idempotency key / device health fields

---

## 2. Consistent response envelopes

### 2.1 Success

```json
{
  "success": true,
  "data": {},
  "meta": {},
  "request_id": "req_..."
}
```

`meta` optional. `data` may be object or array.

### 2.2 Error

```json
{
  "success": false,
  "error": {
    "code": "order.validation_failed",
    "message": "Human readable summary",
    "details": [
      { "field": "items.0.quantity", "code": "validation.max", "message": "Max 20" }
    ]
  },
  "request_id": "req_..."
}
```

### 2.3 Standard error codes

| HTTP | `error.code` examples |
|---|---|
| 400 | `request.malformed` |
| 401 | `auth.unauthenticated`, `auth.token_invalid` |
| 403 | `auth.forbidden`, `device.inactive`, `device.maintenance` |
| 404 | `resource.not_found` |
| 409 | `idempotency.payload_conflict`, `idempotency.in_progress`, `order.state_conflict`, `checkin.already_used` |
| 422 | `validation.failed`, `money.client_values_forbidden`, `refund.ineligible` |
| 429 | `rate_limit.exceeded` |
| 500 | `server.error` |
| 502/503 | `upstream.payment_provider_unavailable` |

### 2.4 Check-in / validation deny codes (in `error.code` or `data.result`)

Align to business flow:  
`DENY_NOT_FOUND`, `DENY_INVALID_AUTH`, `DENY_WRONG_DESTINATION`, `DENY_NOT_PAID`, `DENY_NOT_ACTIVE`, `DENY_EXPIRED`, `DENY_CANCELLED`, `DENY_REFUNDED`, `DENY_ALREADY_USED`, `DENY_UNAUTHORIZED`

Prefer success response with `result: "DENY"` for gate UX (HTTP 200) **or** 422 with deny code — **pick one project-wide**.  
**Contract choice (MVP):** HTTP **200** for completed validation decision; `data.result = "ALLOW" | "DENY"` + `data.reason_code`. Transport errors still 4xx/5xx.

---

## 3. Authentication

### 3.1 Staff token login (optional external/staff API)

| Field | Value |
|---|---|
| **HTTP Method** | `POST` |
| **Path** | `/api/v1/auth/login` |
| **Authentication** | None |
| **Authorization** | Public credentials endpoint |
| **Headers** | `Content-Type`, `Accept` |
| **Request** | `{ "email": "string", "password": "string", "device_name": "string?" }` |
| **Validation** | email required, password required; user `is_active` |
| **Response** | `{ token, token_type:"Bearer", user:{id,name,email,roles[]} }` |
| **Error Response** | 401 `auth.invalid_credentials`; 403 `auth.user_inactive` |
| **Status Codes** | 200, 401, 403, 422, 429 |
| **Idempotency** | Not required |
| **Rate Limiting** | Auth class |
| **Audit** | `auth.login_success` / `auth.login_failed` |

### 3.2 Logout / revoke current token

| Field | Value |
|---|---|
| **Method / Path** | `POST /api/v1/auth/logout` |
| **Authentication** | Bearer user token |
| **Authorization** | Authenticated user |
| **Request** | `{}` |
| **Response** | `{ "revoked": true }` |
| **Status Codes** | 200, 401 |
| **Idempotency** | Not required |
| **Rate Limiting** | Standard |
| **Audit** | `auth.logout` |

### 3.3 Current principal

| Field | Value |
|---|---|
| **Method / Path** | `GET /api/v1/auth/me` |
| **Authentication** | Bearer user **or** device token |
| **Response** | User profile + permissions **or** device profile + abilities |
| **Status Codes** | 200, 401 |
| **Audit** | No |

---

## 4. Kiosk registration

Registration is typically **Admin dashboard** creating the device record. API for machine provisioning:

### 4.1 Register device (staff)

| Field | Value |
|---|---|
| **Method / Path** | `POST /api/v1/kiosks` |
| **Authentication** | Bearer user token |
| **Authorization** | `kiosks.create` |
| **Headers** | Auth + JSON |
| **Request** | `{ "device_id":"string", "terminal_id":"string?", "destination_id":1, "name":"string" }` |
| **Validation** | `device_id` unique; destination exists/active |
| **Response** | Device object status=`registered` |
| **Errors** | 403, 409 duplicate device_id, 422 |
| **Status Codes** | 201, 401, 403, 409, 422, 429 |
| **Idempotency** | Recommended |
| **Rate Limiting** | Write class |
| **Audit** | `device.registered` |

---

## 5. Kiosk activation

### 5.1 Issue activation material (staff)

| Field | Value |
|---|---|
| **Method / Path** | `POST /api/v1/kiosks/{id}/activate` |
| **Authentication** | Bearer user |
| **Authorization** | `kiosks.activate` |
| **Request** | `{ "rotate_secret": true? }` |
| **Response** | `{ device, activation_code, expires_at }` — code shown once |
| **Status Codes** | 200, 403, 404, 409 |
| **Idempotency** | Recommended |
| **Audit** | `device.activated` (do not audit raw secret) |

### 5.2 Exchange activation code → device token (kiosk)

| Field | Value |
|---|---|
| **Method / Path** | `POST /api/v1/kiosks/activate` |
| **Authentication** | None (activation code) |
| **Authorization** | Valid unused activation code |
| **Request** | `{ "device_id":"string", "activation_code":"string", "software_version":"string?" }` |
| **Response** | `{ token, token_type:"Bearer", device:{...} }` status becomes `active` |
| **Errors** | 401 invalid code; 403 disabled |
| **Status Codes** | 200, 401, 403, 422, 429 |
| **Idempotency** | Recommended |
| **Rate Limiting** | Auth class |
| **Audit** | `device.token_issued` |

---

## 6. Kiosk heartbeat

| Field | Value |
|---|---|
| **Method / Path** | `POST /api/v1/kiosks/heartbeat` |
| **Authentication** | Bearer device token |
| **Authorization** | ability `device.heartbeat` + device not disabled |
| **Request** | `{ "software_version":"string", "hardware_version":"string?", "printer_ok":true?, "extras":{}? }` |
| **Validation** | versions max lengths; no money fields |
| **Response** | `{ "server_time":"ISO-8601", "device_status":"active\|maintenance\|disabled", "commands":["enter_maintenance"?] }` |
| **Status Codes** | 200, 401, 403, 422, 429 |
| **Idempotency** | Not required |
| **Rate Limiting** | Heartbeat class |
| **Audit** | No per-beat; threshold offline event audited by job |

---

## 7. Kiosk configuration

| Field | Value |
|---|---|
| **Method / Path** | `GET /api/v1/kiosks/me/config` |
| **Authentication** | Bearer device |
| **Authorization** | Active/maintenance device may read; disabled denied |
| **Request** | — |
| **Response** | `{ destination, locale_default:"id", currency:"IDR", payment_ttl_seconds, features:{print:true}, theme? }` |
| **Status Codes** | 200, 401, 403 |
| **Idempotency** | N/A |
| **Rate Limiting** | Catalog class |
| **Audit** | No |

### 7.1 Staff update kiosk

| Field | Value |
|---|---|
| **Method / Path** | `PATCH /api/v1/kiosks/{id}` |
| **Authentication** | Bearer user |
| **Authorization** | `kiosks.update` |
| **Request** | `{ "name":"string?", "destination_id":1? }` |
| **Response** | Updated device |
| **Audit** | `device.updated` |

### 7.2 Maintenance / deactivate

| Endpoint | Authz | Effect | Audit |
|---|---|---|---|
| `POST /api/v1/kiosks/{id}/maintenance` `{enabled:true\|false}` | `kiosks.maintenance` | Toggle maintenance | `device.maintenance_on/off` |
| `POST /api/v1/kiosks/{id}/deactivate` | `kiosks.deactivate` | Disable + revoke tokens | `device.disabled` |

---

## 8. Destinations

### 8.1 List active destinations (kiosk/staff)

| Field | Value |
|---|---|
| **Method / Path** | `GET /api/v1/destinations` |
| **Authentication** | Bearer device or user |
| **Authorization** | device `catalog.read` or staff authenticated |
| **Query** | `?active=1` |
| **Response** | `[ { id, code, name, timezone, is_active } ]` |
| **Status Codes** | 200, 401, 403 |
| **Audit** | No |

### 8.2 Manage destination (staff)

| Endpoints | Authz |
|---|---|
| `POST /api/v1/destinations` | `destinations.manage` |
| `PATCH /api/v1/destinations/{id}` | `destinations.manage` |

Request excludes any pricing totals. Audit `destination.upsert`.

---

## 9. Ticket types

### 9.1 List ticket types

| Field | Value |
|---|---|
| **Method / Path** | `GET /api/v1/destinations/{destination_id}/ticket-types` |
| **Authentication** | Bearer device/user |
| **Authorization** | catalog read / `ticket_types.view` |
| **Response** | items with **server** `unit_price`, `tax_amount`, `service_fee_amount`, validity fields, `max_per_order` |
| **Status Codes** | 200, 401, 403, 404 |
| **Audit** | No |

### 9.2 Manage ticket types (staff)

`POST/PATCH /api/v1/ticket-types` with `destinations`/`ticket_types.manage`.  
Admin supplies catalog unit price configuration (admin authority), not kiosk self-price. Audit required on price changes.

---

## 10. Pricing

### 10.1 Quote (authoritative)

| Field | Value |
|---|---|
| **Method / Path** | `POST /api/v1/pricing/quote` |
| **Authentication** | Bearer device/user |
| **Authorization** | catalog read / assisted sale permission |
| **Headers** | Auth + JSON |
| **Request** | `{ "destination_id":1, "items":[ { "ticket_type_id":1, "quantity":2, "visit_date":"YYYY-MM-DD?" } ] }` |
| **Validation** | items required; qty ≥1 ≤ max; **reject** if body contains `unit_price`/`subtotal`/`grand_total`/etc. → 422 `money.client_values_forbidden` |
| **Response** | `{ currency:"IDR", items:[... server priced ...], subtotal, discount_total, tax_total, service_fee_total, grand_total, quote_expires_at? }` |
| **Status Codes** | 200, 401, 403, 422, 429 |
| **Idempotency** | Not required (read-calc) |
| **Rate Limiting** | Catalog/write hybrid |
| **Audit** | No |

**Laravel calculates:** subtotal, discount, tax, service fee, total.

---

## 11. Visitors

MVP visitors are optional.

### 11.1 Create/find lightweight visitor (staff optional)

| Field | Value |
|---|---|
| **Method / Path** | `POST /api/v1/visitors` |
| **Authentication** | Bearer user |
| **Authorization** | `orders.create` (assisted) |
| **Request** | `{ "display_name?:", "phone?:", "email?:" }` — minimal PII |
| **Response** | visitor object |
| **Status Codes** | 201, 401, 403, 422 |
| **Idempotency** | Optional |
| **Audit** | Optional `visitor.created` |

Kiosk MVP typically omits visitor create and uses order notes only.

---

## 12. Orders

### 12.1 Create order

| Field | Value |
|---|---|
| **Method / Path** | `POST /api/v1/orders` |
| **Authentication** | Bearer device **or** user |
| **Authorization** | device `orders.create` **or** permission `orders.create` |
| **Headers** | **`Idempotency-Key` required** |
| **Request** | `{ "destination_id":1, "visitor_id?:", "customer_note?:", "items":[ { "ticket_type_id":1, "quantity":2, "visit_date?:" } ] }` |
| **Validation** | items valid; reject client money fields; device must be sellable |
| **Server sets** | `channel` (`kiosk`\|`assisted`), all totals, `status=pending_payment`, `expires_at` |
| **Response** | order + items with authoritative money snapshots |
| **Errors** | 403 device maintenance; 409 idempotency conflict; 422 |
| **Status Codes** | 201, 401, 403, 409, 422, 429 |
| **Idempotency** | **Required** |
| **Rate Limiting** | Order/payment writes |
| **Audit** | `order.created` |

### 12.2 Get order

| Field | Value |
|---|---|
| **Method / Path** | `GET /api/v1/orders/{id}` |
| **Authentication** | Bearer device/user |
| **Authorization** | Device: only own orders; User: `orders.view` |
| **Response** | order, items, payment summary, ticket summary |
| **Status Codes** | 200, 401, 403, 404 |
| **Audit** | No |

### 12.3 Cancel unpaid order

| Field | Value |
|---|---|
| **Method / Path** | `POST /api/v1/orders/{id}/cancel` |
| **Authentication** | Bearer device/user |
| **Authorization** | owner device or `orders.cancel` |
| **Headers** | Idempotency recommended |
| **Request** | `{ "reason?:string" }` |
| **Response** | order `cancelled` |
| **Errors** | 409 if already paid |
| **Status Codes** | 200, 403, 404, 409 |
| **Audit** | `order.cancelled` |

---

## 13. Transactions

MVP: **no separate `/transactions` money ledger resource.**

| Guidance | Detail |
|---|---|
| Read money movements | Use `GET /payments` / `GET /payments/{id}` |
| Alias (optional) | `GET /api/v1/transactions` may **proxy** payment list for naming compatibility, same payload as payments |
| Create/update/cancel transaction | **Not exposed** — prevents parallel money API |

If alias exists:

| Field | Value |
|---|---|
| **Method / Path** | `GET /api/v1/transactions` |
| **Authorization** | `transactions.view` or `payments.view` |
| **Response** | Payment list DTO |
| **Audit** | No |

---

## 14. Payments

### 14.1 Initiate payment

| Field | Value |
|---|---|
| **Method / Path** | `POST /api/v1/orders/{order_id}/payments` |
| **Authentication** | Bearer device/user |
| **Authorization** | device `payments.initiate` or `payments.create` |
| **Headers** | **`Idempotency-Key` required** |
| **Request** | `{ "method":"cash"|"qris"|"debit"|"e_wallet" }` (`digital` accepted as alias of `e_wallet`) |
| **Validation** | order `pending_payment`; method allowed for principal (`cash` staff only); **reject client `amount`** |
| **Server sets** | `amount = order.grand_total`, status `pending`→`processing`, provider session if provider-backed (`qris`/`debit`/`e_wallet`) |
| **Response** | `{ payment, next_action?: { type:"display_qr", qr_content, expires_at } }` |
| **Status Codes** | 201, 401, 403, 404, 409, 422, 429, 502 |
| **Idempotency** | **Required** |
| **Rate Limiting** | Order/payment writes |
| **Audit** | `payment.initiated` |

**Laravel calculates payment amount.**

### 14.2 Get payment status

| Field | Value |
|---|---|
| **Method / Path** | `GET /api/v1/payments/{id}` |
| **Authentication** | Bearer device/user |
| **Authorization** | own order scope or `payments.view` |
| **Response** | payment status + order status + tickets_issued boolean |
| **Status Codes** | 200, 401, 403, 404 |
| **Audit** | No |

### 14.3 Confirm cash payment (staff)

| Field | Value |
|---|---|
| **Method / Path** | `POST /api/v1/payments/{id}/confirm-cash` |
| **Authentication** | Bearer user |
| **Authorization** | `payments.create` |
| **Headers** | Idempotency required |
| **Request** | `{ "received": true }` — **no amount override** |
| **Response** | payment `paid`, order `paid`, tickets issued payload |
| **Status Codes** | 200, 403, 409, 422 |
| **Audit** | `payment.paid` (cash) |

### 14.4 Refund

| Field | Value |
|---|---|
| **Method / Path** | `POST /api/v1/payments/{id}/refund` |
| **Authentication** | Bearer user |
| **Authorization** | `payments.refund` |
| **Headers** | Idempotency required |
| **Request** | `{ "reason":"string" }` — **no client refund amount in MVP** (full refund only) |
| **Server calculates** | `refund_amount = payment.amount` if eligible |
| **Response** | payment/order/tickets refunded states |
| **Errors** | 422 `refund.ineligible` (e.g. ticket USED) |
| **Status Codes** | 200, 403, 404, 409, 422 |
| **Audit** | `refund.created` |

---

## 15. Payment callbacks (webhooks)

| Field | Value |
|---|---|
| **Method / Path** | `POST /api/v1/webhooks/payments/{provider}` |
| **Authentication** | Provider signature / secret (not Bearer user) |
| **Authorization** | Valid signature only |
| **Headers** | Provider signature headers (TBD per vendor) |
| **Request** | Provider payload |
| **Validation** | Signature verify; idempotent `event_id` |
| **Processing** | Mark paid/failed idempotently; issue tickets on paid |
| **Response** | `{ "received": true }` quickly |
| **Errors** | 401 invalid signature; 409 duplicate ignored as success |
| **Status Codes** | 200, 401, 422, 429, 500 |
| **Idempotency** | **Required** via `payment_webhook_events` unique (provider, event_id) |
| **Rate Limiting** | Webhook class |
| **Audit** | `payment.paid` / `payment.failed` when state changes |

Clients cannot call this to force PAID without valid provider authenticity.

---

## 16. Tickets

### 16.1 Get ticket

| Field | Value |
|---|---|
| **Method / Path** | `GET /api/v1/tickets/{ticket_code}` |
| **Authentication** | Bearer user/device |
| **Authorization** | `tickets.view` or device own-order tickets |
| **Response** | ticket public fields + validity + status (**QR payload included only for authorized print/own flows**) |
| **Status Codes** | 200, 401, 403, 404 |
| **Audit** | No |

### 16.2 List tickets by order

`GET /api/v1/orders/{id}/tickets` — same authz scoping.

### 16.3 Print data

| Field | Value |
|---|---|
| **Method / Path** | `GET /api/v1/tickets/{ticket_code}/print-payload` |
| **Authorization** | `tickets.print` / reprint / device own |
| **Response** | destination, type, code, qr_payload, validity, issued_at |
| **Audit** | `ticket.printed` on successful client ack via `POST .../print-ack`; reprint → `ticket.reprinted`; hardware fail → `ticket.print_failed`. Print never changes payment/ticket money state. |

### 16.4 Print acknowledgement

| Field | Value |
|---|---|
| **Method / Path** | `POST /api/v1/tickets/{ticket_code}/print-ack` |
| **Authorization** | `tickets.print` / `tickets.reprint` / device own (`tickets:print_own`) |
| **Headers** | Auth + JSON |
| **Request** | `{ "result":"success"|"failed", "message":"string?" }` |
| **Validation** | `result` required enum; reject client money / ticket_status fields |
| **Response** | `{ ticket_code, result, audit_action, print_log_id, ticket_status }` |
| **Status Codes** | 201, 401, 403, 404, 422 |
| **Audit** | `ticket.printed` (first success), `ticket.reprinted` (later success), `ticket.print_failed` |
| **Business rules** | Does **not** mint a ticket; ticket/payment status unchanged; IDOR 404 for other-device tickets |

Manual ticket create endpoint: **not provided** (system issues after PAID).

---

## 17. QR generation

QR is generated **server-side during issuance**, not via a public “make QR” client API.

| Field | Value |
|---|---|
| **Method / Path** | Internal to IssueTickets (no public POST for arbitrary QR) |
| **Exposure** | Returned on paid order response / ticket print-payload |
| **Authz** | Only for tickets the principal may access |
| **Audit** | Covered by `ticket.issued` |

If an explicit endpoint is required for ops regeneration of print image only:

`POST /api/v1/tickets/{ticket_code}/qr/refresh-display` — does **not** mint new ticket; authz `tickets.reprint`; audit `ticket.qr_display_refreshed`.

---

## 18. QR validation

| Field | Value |
|---|---|
| **Method / Path** | `POST /api/v1/tickets/validate` |
| **Authentication** | Bearer user (gate) — device gate tokens only if explicitly issued |
| **Authorization** | `tickets.validate` or `checkins.create` |
| **Headers** | Auth + JSON |
| **Request** | `{ "qr_payload":"string", "destination_id":1, "gate_id?:number" }` |
| **Validation** | payload required |
| **Response** | `{ "result":"ALLOW"|"DENY", "reason_code":null|"DENY_...", "ticket":{limited fields}? }` |
| **Status Codes** | 200 (decision), 401, 403, 422, 429 |
| **Idempotency** | Not required for validate-only |
| **Rate Limiting** | Validate/check-in class |
| **Audit** | `ticket.validated` / deny reasons |

Does not change ticket to USED by itself unless combined endpoint used.

---

## 19. Check-in

| Field | Value |
|---|---|
| **Method / Path** | `POST /api/v1/check-ins` |
| **Authentication** | Bearer user |
| **Authorization** | `checkins.create` |
| **Headers** | **`Idempotency-Key` required** |
| **Request** | `{ "qr_payload":"string", "destination_id":1, "gate_id?:number" }` |
| **Server behavior** | Transactional validate + insert check_in + ACTIVE→USED |
| **Response** | `{ "result":"ALLOW"|"DENY", "reason_code?:", "check_in?:{id,checked_in_at}, "ticket?:{code,status}" }` |
| **Errors** | 200 DENY_ALREADY_USED; 409 concurrency conflict mapped to DENY |
| **Status Codes** | 200, 401, 403, 409, 422, 429 |
| **Idempotency** | **Required** |
| **Rate Limiting** | Validate/check-in |
| **Audit** | `ticket.checked_in` on ALLOW |

### Combined convenience endpoint (optional)

`POST /api/v1/tickets/redeem` — same contract as check-in (validate+consume).

`checkins.reverse` — **not exposed** in MVP.

---

## 20. Gates

### 20.1 List gates

| Field | Value |
|---|---|
| **Method / Path** | `GET /api/v1/destinations/{id}/gates` |
| **Authorization** | authenticated staff with gate/check-in related access |
| **Response** | `[ { id, code, name, is_active } ]` |
| **Audit** | No |

### 20.2 Manage gates (staff)

`POST/PATCH /api/v1/gates` — admin/operator as product decides; prefer Admin. Audit `gate.upsert`.

### 20.3 Gate open command (optional module)

| Field | Value |
|---|---|
| **Method / Path** | Not public from kiosk. Internal after ALLOW check-in **or** `POST /api/v1/gates/{id}/open` restricted |
| **Authorization** | system after check-in success; manual open requires elevated permission (not MVP default) |
| **Rule** | Never open without authoritative check-in success |
| **Audit** | `gate.open_command` |

---

## 21. Reports

Staff reporting may be Livewire-first. REST for exports/integrations:

| Endpoint | Authz | Notes |
|---|---|---|
| `GET /api/v1/reports/sales/daily?date=&destination_id=` | `reports.view` | Aggregates from DB |
| `GET /api/v1/reports/payments?from=&to=&status=` | `payments.view`/`reports.view` | |
| `GET /api/v1/reports/tickets/usage?from=&to=` | `reports.view` | issued vs used |
| `GET /api/v1/reports/sales/daily/export` | `reports.export` | CSV; audit `report.exported` |

**Status codes:** 200, 401, 403, 422, 429  
**Idempotency:** N/A  
**Money in reports:** server aggregated only  

---

## 22. Notifications

### 22.1 List my notifications

| Field | Value |
|---|---|
| **Method / Path** | `GET /api/v1/notifications` |
| **Authentication** | Bearer user |
| **Authorization** | authenticated staff |
| **Response** | Laravel notification DTOs |
| **Status Codes** | 200, 401 |
| **Audit** | No |

### 22.2 Mark read

`POST /api/v1/notifications/{id}/read` — owner only.

Kiosk does not consume staff notifications.

---

## 23. Device health

### 23.1 Heartbeat

See §6.

### 23.2 Staff fleet health

| Field | Value |
|---|---|
| **Method / Path** | `GET /api/v1/kiosks/health` |
| **Authentication** | Bearer user |
| **Authorization** | `kiosks.view` |
| **Response** | `[ { id, device_id, status, maintenance_mode, last_heartbeat_at, online:true\|false, software_version } ]` |
| **Status Codes** | 200, 401, 403 |
| **Audit** | No |

### 23.3 Device self health

`GET /api/v1/kiosks/me` — device token; returns status flags for local UX.

---

## 24. Endpoint index (quick reference)

| Method | Path | Principal |
|---|---|---|
| POST | `/auth/login` | Public |
| POST | `/auth/logout` | User |
| GET | `/auth/me` | User/Device |
| POST | `/kiosks` | User `kiosks.create` |
| POST | `/kiosks/{id}/activate` | User `kiosks.activate` |
| POST | `/kiosks/activate` | Activation code |
| POST | `/kiosks/heartbeat` | Device |
| GET | `/kiosks/me` | Device |
| GET | `/kiosks/me/config` | Device |
| PATCH | `/kiosks/{id}` | User |
| POST | `/kiosks/{id}/maintenance` | User |
| POST | `/kiosks/{id}/deactivate` | User |
| GET | `/kiosks/health` | User |
| GET | `/destinations` | User/Device |
| POST/PATCH | `/destinations`… | User manage |
| GET | `/destinations/{id}/ticket-types` | User/Device |
| POST | `/pricing/quote` | User/Device |
| POST | `/visitors` | User optional |
| POST | `/orders` | User/Device |
| GET | `/orders/{id}` | User/Device |
| POST | `/orders/{id}/cancel` | User/Device |
| POST | `/orders/{id}/payments` | User/Device |
| GET | `/payments/{id}` | User/Device |
| POST | `/payments/{id}/confirm-cash` | User |
| POST | `/payments/{id}/refund` | User |
| POST | `/webhooks/payments/{provider}` | Provider sig |
| GET | `/tickets/{code}` | User/Device |
| GET | `/orders/{id}/tickets` | User/Device |
| GET | `/tickets/{code}/print-payload` | User/Device |
| POST | `/tickets/{code}/print-ack` | User/Device |
| POST | `/tickets/validate` | User |
| POST | `/check-ins` | User |
| GET | `/destinations/{id}/gates` | User |
| GET | `/reports/...` | User |
| GET | `/notifications` | User |
| GET | `/transactions` | User (alias optional) |

---

## 25. Authoritative calculation map

| Value | Calculated by Laravel when |
|---|---|
| unit price application | Quote, Create order |
| subtotal | Quote, Create order |
| discount | Quote, Create order (rules engine) |
| tax | Quote, Create order |
| service fee | Quote, Create order |
| grand total | Quote, Create order |
| payment amount | Initiate payment (= order.grand_total) |
| refund amount | Refund (full MVP) |

**Rejection rule:** Any client body including authoritative money fields → `422 money.client_values_forbidden` (or strip-only if explicitly documented; **MVP = reject** for safety).

---

## 26. Resource DTO sketches (non-code)

### Order (response excerpt)

```json
{
  "id": 1,
  "order_number": "WD-...",
  "channel": "kiosk",
  "status": "pending_payment",
  "currency": "IDR",
  "subtotal": 100000,
  "discount_total": 0,
  "tax_total": 0,
  "service_fee_total": 0,
  "grand_total": 100000,
  "expires_at": "2026-09-13T06:15:00Z",
  "items": []
}
```

### Payment (response excerpt)

```json
{
  "id": 10,
  "payment_number": "PAY-...",
  "status": "processing",
  "method": "digital",
  "amount": 100000,
  "currency": "IDR",
  "paid_at": null
}
```

### Ticket (response excerpt)

```json
{
  "ticket_code": "TCK-...",
  "status": "active",
  "valid_start_at": "...",
  "valid_end_at": "...",
  "qr_payload": "..."
}
```

---

## 27. Security notes

1. HTTPS only in deployed environments  
2. Webhook signature verification mandatory  
3. Device tokens least privilege  
4. Staff token routes permission-checked per `docs/07-RBAC.md`  
5. Do not return integration secrets  
6. Limit ticket QR exposure to authorized print/own flows  

---

## 28. Open vendor-dependent details

| Item | Status |
|---|---|
| Payment provider webhook header names | TBD with `[PAYMENT_PROVIDER]` |
| Activation code format/TTL | TBD settings |
| Exact gate open API to hardware | Optional / TBD |
| Staff API vs Livewire-only for some admin routes | Product choice; contract remains valid for kiosk-critical paths |

---

*End of API contract. No application implementation code.*
