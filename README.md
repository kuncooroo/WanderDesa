# WanderDesa

**Integrated Tourism System** — one ledger, two clients, one authority.

| Component | Role |
|---|---|
| `backend/` | Laravel 13 modular monolith — **business authority** (REST API + Blade/Livewire dashboard) |
| `kiosk/` | Flutter **physical self-service terminal** client (not a consumer app) |
| `docs/` | Normative product & engineering documents |
| `.cursor/` | Task index and architecture rules for agents |
| `CURSOR.md` | Binding engineering constitution |

Staff use the Laravel dashboard. **Do not** add a Staff Flutter app for MVP.

Clients collect input and render server results. Laravel calculates prices/totals, payment finality, ticket validity, check-in, and permissions. Local kiosk storage is never the ledger.

## Start here

1. [CURSOR.md](CURSOR.md) — rules every change must follow
2. Docs index:

| # | Document |
|---|---|
| 01 | [Product Validation](docs/01-PRODUCT-VALIDATION.md) |
| 02 | [PRD](docs/02-PRD.md) |
| 03 | [SRS](docs/03-SRS.md) |
| 04 | [System Design](docs/04-SYSTEM-DESIGN.md) |
| 05 | [Business Flow](docs/05-BUSINESS-FLOW.md) |
| 06 | [Database](docs/06-DATABASE.md) |
| 07 | [RBAC](docs/07-RBAC.md) |
| 08 | [API Contract](docs/08-API-CONTRACT.md) |
| 09 | [UI/UX](docs/09-UI-UX.md) |
| 10 | [Project Structure](docs/10-PROJECT-STRUCTURE.md) |
| 11 | [Roadmap](docs/11-ROADMAP.md) |
| 12 | [Security checklist](docs/12-SECURITY-CHECKLIST.md) |

3. Implementation tasks: [`.cursor/tasks/README.md`](.cursor/tasks/README.md)

## Environment (inspected, not invented)

| Tool | Inspected value | Notes |
|---|---|---|
| PHP | **8.4.25** | Laragon CLI |
| Composer | **2.8.12** | |
| MySQL | **8.4.11** | Laragon `mysql-8.4.11-winx64` (CLI not on PATH) |
| Flutter | **3.44.9** stable · Dart **3.12.2** | Kiosk client in `kiosk/` (TASK-021) |
| Node / npm | **v24.20.0** / **11.19.0** | Vite/Tailwind for dashboard assets |
| Laravel skeleton | **v13.10.1** | `composer create-project laravel/laravel` |
| Laravel framework | **v13.31.0** | `backend/composer.lock` |
| Livewire | **v4.4.4** | Dashboard (assisted sale, check-in, kiosks, tickets, reports, audit) |
| Pint | present (`laravel/pint`) | PHP style |
| Laravel Sanctum | installed (`backend/composer.lock`) | Device tokens + staff API tokens |
| Payment / printer / scanner / gate | Sandbox payment + adapters | Live provider / hardware TBD at the pilot site |

Authoritative app env: `backend/.env` (gitignored). Root `.env.example` is a pointer only.

## Quick start (backend)

```bash
cd backend
composer install
copy .env.example .env   # Windows; then php artisan key:generate
# Create MySQL database wanderdesa (utf8mb4), then:
php artisan migrate
php artisan test
php artisan serve
```

Health: `GET /up`. Schema: `docs/06-DATABASE.md` via `backend/database/migrations`.

## Tests

PHPUnit uses SQLite `:memory:` (`backend/phpunit.xml`) and `RefreshDatabase`, so a green suite is the “fresh migrate” gate. Authoritative MySQL is for local/runtime, not these tests.

```bash
cd backend
composer install
php artisan test                 # full Unit + Feature (integrity gate)
composer test:critical           # money / tickets / check-in / RBAC / IDOR pack
vendor/bin/pint --dirty          # style on PHP you touched
```

Release-candidate pack is `--group=critical` (same as `composer test:critical`). It does not replace the full suite.

Kiosk client (does not decide money or ticket state):

```bash
cd kiosk
flutter test
```

CI: `.github/workflows/tests.yml` runs both commands on push/PR to `main`.

## Ops (TASK-030)

VPS deploy, backups, workers, and pilot sign-off live in **[docs/runbooks/](docs/runbooks/README.md)** (not a business-rule override).

| Need | Where |
|---|---|
| Deploy / rollback | [docs/runbooks/deploy-rollback.md](docs/runbooks/deploy-rollback.md) · `backend/scripts/deploy.sh` |
| Worker + scheduler | cron `php artisan schedule:run` every minute + `queue:work` |
| MySQL backups | `php artisan ops:backup-mysql` (daily 02:15 app TZ) |
| Health | `GET /up` (stays up during `php artisan down`) |
| Kiosk staging/prod APK | `--dart-define=API_BASE_URL=https://<host>/api/v1` · [kiosk-release](docs/runbooks/kiosk-release.md) |
| Pilot sign-off | [docs/runbooks/mvp-pilot-checklist.md](docs/runbooks/mvp-pilot-checklist.md) — humans sign; git stays unsigned |

Production env: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `SESSION_SECURE_COOKIE=true`, non-empty `PAYMENT_WEBHOOK_SECRET`. Secrets only on the server. Forward-only `php artisan migrate --force`.

## Git / PR (lightweight)

- Default branch: `main`
- Feature branches: `feat/TASK-00X-short-slug` (example: `feat/TASK-002-database-foundation`)
- Fixes: `fix/short-slug`
- Small, focused PRs against `main`
- Do not commit `.env`, keys, dumps, or credentials
- Do not rewrite production migration history
- Do not force-push protected branches unless a human explicitly requests it

## What this repo is not

- Not “Flutter mobile + Laravel website”
- Not a consumer tourist app
- Not microservices for MVP
- Not a place for client-authoritative money or ticket state
