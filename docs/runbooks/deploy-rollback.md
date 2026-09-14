# Deploy and rollback

**Scope:** Laravel monolith on a VPS (`backend/`). Kiosk APKs are a separate channel — [kiosk-release.md](kiosk-release.md).  
**Never:** `migrate:fresh`, `migrate:refresh`, rewrite old migrations, or commit `.env`.

## Components (SRS-DEP-02)

| Process | Command / unit |
|---|---|
| Web | PHP-FPM + nginx/Caddy (TLS terminates at the proxy) |
| App | Laravel 13, `APP_ENV=staging` or `production` |
| DB | MySQL 8.4.x (authoritative ledger) |
| Scheduler | cron `* * * * * cd /path/to/backend && php artisan schedule:run` |
| Worker | Supervisor/systemd `php artisan queue:work --sleep=1 --tries=1` |

Health: `GET /up` (excluded from maintenance mode). Dashboard: `/login`. API: `/api/v1`.

## Staging vs production

| | Staging | Production |
|---|---|---|
| `APP_ENV` | `staging` | `production` |
| `APP_DEBUG` | `false` | `false` |
| `APP_URL` | `https://<staging-host>` | `https://<prod-host>` |
| Payments | `PAYMENT_GATEWAY=sandbox` | Adapter when the vendor is chosen; never mark PAID from a client |
| Kiosk APK | staging `--dart-define` | production HTTPS `--dart-define` |
| Users | seeded least-privilege pilot staff | same roles; rotate passwords |

Placeholders only in git. Real hosts live in server env.

## Deploy (happy path)

1. Confirm `GET /up` is 200 and a backup from the last 24h exists ([backups.md](backups.md)).
2. Put the app in maintenance: see [maintenance-mode.md](maintenance-mode.md).
3. From `backend/`:

```bash
./scripts/deploy.sh
```

Or manually: `git pull --ff-only` → `composer install --no-dev --optimize-autoloader` → `npm ci && npm run build` → `php artisan migrate --force` → `config/route/view:cache` → `queue:restart` → `php artisan up`.

4. Confirm worker + cron are running (`ps` / Supervisor).
5. Run [post-deploy-smoke.md](post-deploy-smoke.md).
6. Staging: `cd backend && composer test:critical` (or `php artisan test --group=critical`) before promoting the same git SHA to production.

## Rollback

1. `php artisan down --retry=60`.
2. `git fetch` and `git checkout <previous-release-sha>` (or the previous tag). Do not force-push `main`.
3. `composer install --no-dev --optimize-autoloader`.
4. **Migrations:** only roll forward with a new migration that undoes a bad change. Do not `migrate:rollback` on production unless a human explicitly accepts data loss risk.
5. Restore caches, `queue:restart`, `php artisan up`.
6. If the bad release corrupted data: restore MySQL from the pre-deploy dump ([backups.md](backups.md)), then bring the matching SHA up.
7. Smoke again. If payments were in `processing` during the window, leave them to reconcile — do not invent PAID.

## Secrets

Server `.env` only. Inventory: `backend/.env.example` and `docs/12-SECURITY-CHECKLIST.md`. After a suspected leak: rotate `APP_KEY` (session invalidation), `PAYMENT_WEBHOOK_SECRET`, `TICKET_QR_SECRET`, DB password, and re-issue kiosk activation codes.
