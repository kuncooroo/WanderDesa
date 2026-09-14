# 12 — Security checklist (MVP pilot)

**Product:** WanderDesa  
**Status:** Signed for TASK-028 / Phase 21 (implementation evidence in `backend/tests/Feature/Security/SecurityHardeningTest.php` and related suites)  
**Scope:** Operational sign-off. Does **not** override `docs/01`–`11` business rules.

Laravel remains the business authority. Flutter Kiosk and Blade/Livewire Dashboard are clients.

---

## 1. Sign-off summary

| Gate | Result |
|---|---|
| Critical IDOR on kiosk order/ticket/payment reads | Pass (404 `resource.not_found` for foreign device) |
| Kiosk cannot initiate payment or cancel another device’s order | Pass (TASK-028 fix) |
| Webhook rejects missing/invalid/empty secret | Pass |
| Device cannot refund, admin, reports, notifications, role assign | Pass |
| `roles.assign` Super Admin only | Pass |
| Rate limits on auth / commerce / validate | Pass (`429 rate_limit.exceeded`) |
| No secrets in git | Pass (`.gitignore` + `.env.example` inventory) |
| Dashboard 403 without permission | Pass |
| File uploads | N/A — no upload endpoint in MVP (`files` table reserved) |

---

## 2. Requirement map

| ID | Control | Evidence |
|---|---|---|
| SEC-01 / SRS-SEC-04 | Server-side Form Requests; reject client money fields | `RejectsClientMoneyFields`; `KioskApiMoneyRejectionTest` |
| SEC-02 | Staff session vs Sanctum device tokens | Distinct principals; `DeviceAbilities` ≠ permission catalog |
| SEC-03 / SRS-BE-06 | Secrets not in source | `.gitignore` `.env`; kiosk uses `--dart-define` for API URL only |
| SEC-04 / SRS-SEC-01 | TLS in deployed environments | Production `URL::forceScheme('https')`; nginx/VPS still terminates TLS |
| SEC-05 / SRS-SEC-05 | Webhook HMAC, fail-closed if secret empty | `SandboxPaymentGateway`; `PaymentWebhookTest` |
| SEC-06 | QR HMAC server-generated/verified | `QrPayloadGenerator` |
| SEC-07 | Hidden UI cannot escalate | Livewire `authorize` / `Authorizer`; coming-soon routes gated |
| SEC-08 / SRS-SEC-07 | Remote disable blocks sales | Tokens revoked; `device.inactive` even if `is_active` is inconsistent |
| SEC-09 / SRS-SEC-10 | Minimal PII | No visitor accounts in MVP |
| SEC-10 / SRS-API-06 | Rate limits | `throttle:auth` 10/min; `commerce` 30/min; `access` (validate/check-in) 60/min; `429 rate_limit.exceeded` |
| SEC-11 / SRS-SES-01 | Dashboard session | HTTP-only cookie; `SESSION_LIFETIME=120`; production secure cookie default |
| SEC-12 | Gate trusts Laravel only | Device cannot validate or check-in |
| SRS-SEC-03 | CSRF on web; Bearer on API | Laravel `PreventRequestForgery` on `web`; Sanctum on `api` |
| SRS-SEC-06 | Least privilege | RBAC matrix + device abilities |
| SRS-SEC-08 | Security headers | `SetSecurityHeaders` (`nosniff`, `SAMEORIGIN`, Referrer-Policy, Permissions-Policy, COOP; HSTS on HTTPS production) |
| AUTH-03 | Secrets not logged | `AuditWriter` redacts token/secret/password keys |

---

## 3. Production configuration (must)

Set on the VPS **before** pilot traffic:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<host>
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
PAYMENT_WEBHOOK_SECRET=<long random>
TICKET_QR_SECRET=<long random>   # or rely on APP_KEY
```

Place the app behind HTTPS (nginx/Caddy). Laravel trusts `X-Forwarded-*` from the reverse proxy (`trustProxies: *`). Do not expose PHP directly to the internet without a proxy.

Optional: `OPS_NOTIFICATION_MAIL=true` only after SMTP is configured.

---

## 4. Secrets inventory

| Secret | Location | Notes |
|---|---|---|
| `APP_KEY` | `backend/.env` | Required |
| `DB_PASSWORD` | `backend/.env` | MySQL |
| `PAYMENT_WEBHOOK_SECRET` | `backend/.env` | Empty → all sandbox webhooks 401 |
| `TICKET_QR_SECRET` | `backend/.env` | Falls back to `APP_KEY` |
| `SUPER_ADMIN_PASSWORD` | `backend/.env` | Seeder only; leave blank after create |
| Sanctum device token | Kiosk `FlutterSecureStorage` | Not in git; never a ledger |
| Activation code | Shown once at issue time; stored hashed | |

Never commit `backend/.env`, kiosk flavor secrets, or dumps.

---

## 5. Explicit non-goals (MVP)

- Destination-row scoping for staff (`docs/07` §14 optional) — staff with `orders.view` / `tickets.view` / `payments.view` may read any destination’s records.
- Browser CSP nonce (Livewire/Vite); reverse-proxy CSP may be added later.
- File upload validation — no public upload API yet. When added: MIME/size/disk private, no provider secrets in public disk (SRS-FS-04).

---

## 6. Manual verification (pilot)

1. `APP_DEBUG=false`, open `/login` over HTTPS; cookie is `Secure; HttpOnly; SameSite=Lax`.
2. Kiosk A cannot `GET` / pay / cancel kiosk B’s order (404).
3. Bad webhook signature → 401; empty `PAYMENT_WEBHOOK_SECRET` → 401.
4. Device token `POST /payments/{id}/refund` → 403; disable kiosk → subsequent commerce 401 then 403.
5. Admin cannot assign Super Admin (`roles.assign` denied).
6. Burst login / order create / ticket validate → 429 after limiter.
7. Ticket Officer opening Audit logs → 403 “Tidak berwenang”.
8. Confirm no `.env` or keys in `git status`.
