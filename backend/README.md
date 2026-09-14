# WanderDesa Backend

Laravel **13.x** modular monolith — the **business authority** for WanderDesa.

Kiosk (Flutter) and Dashboard (Blade + Livewire) are clients. They request; this app decides prices, payments, tickets, check-in, and permissions.

## Layout (docs/10)

| Path | Role |
|---|---|
| `app/Actions/` | Use-case orchestration (shared by API + Livewire) |
| `app/Services/` | Reusable domain logic (pricing, state transitions) |
| `app/Integrations/` | Payment / hardware adapters |
| `app/Http/Controllers/Api/V1/` | Thin REST controllers |
| `app/Livewire/` | Dashboard UI — calls Actions, does not own money rules |
| `routes/api.php` | `/api/v1/*` |
| `routes/web.php` | Dashboard session routes |

No Repository layer by default. No Staff Flutter. No business logic in Blade/JS.

## Local setup

```bash
cd backend
cp .env.example .env   # if needed
php artisan key:generate
php artisan migrate
php artisan serve
```

- PHP **8.4.x** required (`composer.json`: `^8.3`; project baseline is 8.4.x).
- Authoritative DB is **MySQL 8.4.x** (`docs/06-DATABASE.md`). Create `wanderdesa` first (Laragon CLI is often not on PATH):

```bash
C:\laragon\bin\mysql\mysql-8.4.11-winx64\bin\mysql.exe -uroot -e "CREATE DATABASE IF NOT EXISTS wanderdesa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate:fresh
```

- Feature tests use SQLite in-memory (`phpunit.xml`) with `RefreshDatabase`.
- Health: `GET /up`
- Format PHP: `vendor/bin/pint`
- Tests:

```bash
php artisan test                 # full suite (integrity gate)
composer test:critical           # @group critical regression pack
vendor/bin/pint --dirty
```

The critical pack covers quote money rejection, order/payment/webhook/issue idempotency, double check-in, refund eligibility, RBAC, IDOR, assisted sale, and kiosk contract smoke. Prefer those invariants over coverage percentage.

## Scheduler and queue (TASK-026)

Production/staging must run **both**:

```cron
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

```bash
php artisan queue:work --sleep=1 --tries=1
```

Scheduled jobs (see `routes/console.php`): expire unpaid orders/payments, reconcile open digital payments, detect offline kiosks (audit only — online/offline stays derived from `last_heartbeat_at`; operators get a dashboard notification), expire unused tickets past `valid_end_at`, daily MySQL dump (`ops:backup-mysql`, no-op on SQLite). Optional email: `OPS_NOTIFICATION_MAIL=true` after SMTP is configured.

VPS deploy: `backend/scripts/deploy.sh` and [docs/runbooks/](../docs/runbooks/README.md). Never `migrate:fresh` in staging/production.

Local: `php artisan schedule:work` (and a worker unless `QUEUE_CONNECTION=sync`).

## Schema (TASK-002)

Framework tables run first (`0001_01_01_*`: users/sessions, cache, jobs). Domain migrations follow `docs/06-DATABASE.md` and must not be edited after they ship — add a new migration instead.

| Order | Migration |
|---|---|
| 1 | `add_staff_fields_to_users_table` |
| 2 | `create_roles_and_permissions_tables` |
| 3 | `create_catalog_tables` (destinations, ticket_types, visitors) |
| 4 | `create_devices_and_gates_tables` |
| 5 | `create_commerce_tables` (orders, order_items, payments, webhooks, idempotency) |
| 6 | `create_ticketing_tables` (tickets + QR columns, check_ins) |
| 7 | `create_ops_tables` (audit_logs, notifications, settings, integration_settings, files) |

Money columns are **BIGINT IDR**. Soft deletes exist only on catalog/users (`users`, `destinations`, `ticket_types`, `gates`). Role/permission **rows** are seeded in TASK-004.

## Installed (from lockfile — do not invent patches)

See `composer.lock`. Framework and Livewire patches are recorded there after `composer create-project` / `composer require`.

## Production security (TASK-028)

Before exposing the VPS: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS at the reverse proxy, `SESSION_SECURE_COOKIE=true`, and a non-empty `PAYMENT_WEBHOOK_SECRET`. Checklist: `docs/12-SECURITY-CHECKLIST.md`.
