# Post-deploy smoke

Run after every staging or production deploy. Laravel decides money and tickets; this list only checks that clients can reach the authority.

## 1. Platform

- [ ] `GET /up` → 200
- [ ] `GET /login` over HTTPS → 200; session cookie `Secure; HttpOnly; SameSite=Lax` in production
- [ ] `php artisan schedule:list` shows `expire-unpaid-orders`, `reconcile-open-payments`, `detect-offline-devices`, `expire-unused-tickets`, `backup-mysql`
- [ ] Queue worker is alive (`queue:work`); `php artisan queue:failed` is empty or triaged
- [ ] Newest MySQL dump exists under `BACKUP_PATH` (or last night’s file)

## 2. Dashboard — assisted sale (AC-02, AC-04)

Role: Ticket Officer (not Super Admin).

1. Open `/dashboard/assisted-sale`.
2. Quote from catalog quantities only — no typed prices.
3. Complete **cash** pay. Totals must match the server quote (`grand_total` IDR integer).
4. Tickets issue; status is server `issued`/`active`.
5. Confirm an audit row for the cash payment (staff identity).

## 3. Kiosk — sandbox digital pay (AC-01, AC-03)

Use the **staging** APK (`API_BASE_URL=https://<staging-host>/api/v1`).

1. Device is active (not maintenance/disabled).
2. Visitor selects tickets → summary totals from `POST /api/v1/pricing/quote`.
3. Pay. Kiosk must stay on processing / QR until Laravel reports `paid` **and** tickets issued.
4. Trigger sandbox webhook only from a trusted operator (HMAC secret on the server). Do not mark PAID from the tablet.
5. Tickets print or fall back to on-screen QR. Print failure must not void payment.

## 4. Check-in once (AC-05, AC-14)

Role: Gate Officer. `/dashboard/check-in`.

1. Scan/paste the issued QR → ALLOW, ticket `used`.
2. Repeat → DENY already used. One `check_ins` row.
3. Gate hardware (if any) opens only after ALLOW. If no gate, check-in still succeeds.

## 5. Integrity samples

- [ ] Retry the same kiosk order idempotency key → same order id (AC-07)
- [ ] Finance `/dashboard/reports` shows the day’s channel/payment rows (AC-09, AC-10)
- [ ] Ticket Officer cannot open refunds; Finance can see payments (AC-11 path via audit as Auditor)

Fail any money/ticket mismatch: stop the release, do not “fix” totals in Flutter or Livewire.
