# MySQL backups

**Requirements:** SRS-BK-01 daily dump, SRS-BK-02 retention 7–30 days (default **14**), SRS-BK-03 restore drill, SRS-BK-04 `.env` off-box, SRS-BK-05 includes audit tables (full dump).

## What runs

Laravel scheduler (`02:15` **app timezone**, UTC in `config/app.php`):

```text
php artisan ops:backup-mysql
```

Cron alternative: `backend/scripts/backup-mysql.sh` once per day **in addition** is redundant if `schedule:run` is healthy — pick one.

The command no-ops when `DB_CONNECTION` is not `mysql` (PHPUnit SQLite). Enable with `BACKUP_MYSQL_ENABLED=true` (default).

| Env | Meaning |
|---|---|
| `BACKUP_PATH` | Directory on the VPS (default `storage/app/backups`, gitignored) |
| `BACKUP_RETENTION_DAYS` | Delete `wanderdesa-*.sql` older than N days |
| `BACKUP_MYSQLDUMP_BIN` | `mysqldump` path if not on `PATH` |

`mysqldump` uses `--single-transaction` so InnoDB stays consistent. Password is passed via `MYSQL_PWD` for the process only — not argv, not logs.

Copy dumps **off the VPS** (object storage or another host). A dump that only lives on the same disk is not a disaster plan.

## Restore drill (do this at least once on staging)

1. Take a dump: `php artisan ops:backup-mysql`.
2. Create a throwaway database, e.g. `wanderdesa_restore`.
3. `mysql wanderdesa_restore < wanderdesa-YYYYMMDD-HHMMSS.sql`
4. Point a **staging** `.env` at that database (never production DNS).
5. `php artisan migrate --force` (should be no-op if dump is current).
6. Smoke: login, one assisted cash sale, one check-in. Confirm `audit_logs` rows exist.
7. Record date/operator on [mvp-pilot-checklist.md](mvp-pilot-checklist.md).
8. Drop the throwaway database.

Production restore: maintenance mode, restore onto a new schema or restored instance, re-attach `APP_URL`/TLS, smoke, then DNS cut. Prefer restore-forward over editing dump files.

## `.env` backup (SRS-BK-04)

Keep an encrypted copy of production `.env` **off git and off the public web root**. After restore, confirm `PAYMENT_WEBHOOK_SECRET` and `TICKET_QR_SECRET` still match issued tickets/webhooks or rotate and re-issue devices.
