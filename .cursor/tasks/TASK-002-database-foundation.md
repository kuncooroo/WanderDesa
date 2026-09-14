# TASK-002 — Database Foundation

| Field | Value |
|---|---|
| **Task ID** | TASK-002 |
| **Title** | Database foundation |
| **Priority** | 2 Database |

## Objective
Create Laravel app (if not done) and implement MySQL schema migrations from `docs/06-DATABASE.md` with integrity constraints.

## Background
MySQL is the system of record. Money is BIGINT IDR. Shared tables for both channels. No soft deletes on financial/audit rows.

## Dependencies
- TASK-001
- Laravel 13.x · PHP 8.4.x · MySQL 8.4.x

## Affected Files
- `backend/composer.json`, `composer.lock`
- `backend/database/migrations/*`
- `backend/database/factories/*` (stubs)
- `backend/database/seeders/DatabaseSeeder.php` (minimal)
- `backend/.env.example`
- `backend/app/Models/*` (minimal models OK)

## Database Changes
Create tables (InnoDB): users, roles, permissions, pivots, destinations, ticket_types, visitors (optional), orders, order_items, payments, payment_webhook_events, idempotency_keys, tickets (+ QR columns), check_ins, gates, devices, device_heartbeats, audit_logs, notifications, settings, integration_settings, files, plus Laravel jobs/sessions/cache as needed.

## Backend Requirements
- Migrations only (never edit production migrations later — new ones for changes)
- Uniques: order_number, payment_number, ticket_code, qr_payload_hash, device_id, check_ins.ticket_id, idempotency scope keys, webhook (provider,event_id)
- Models with relations; no business Actions yet beyond nothing

## API Requirements
- None (schema only)

## Dashboard Requirements
- None

## Kiosk Requirements
- None

## Validation
- Migration-level CHECKs optional; app will validate later

## Authorization
- Tables ready for RBAC; seeds deferred to TASK-004

## Business Rules
- One orders table with `channel`
- Payments are the money transaction entity (no separate ledger table)

## Edge Cases
- Nullable unique provider_payment_id
- Soft deletes only on catalog/users per docs

## Security
- No secrets in seeders

## Testing
- `migrate:fresh` succeeds
- Feature/unit test asserting critical unique indexes exist (optional schema test)

## Acceptance Criteria
- [ ] `php artisan migrate:fresh` clean on MySQL 8.4
- [ ] Critical uniques/FKs present per docs/06
- [ ] Money columns are BIGINT
- [ ] `composer.lock` records Laravel 13.x patch

## Definition of Done
- Schema matches docs/06 for MVP tables
- Ready for TASK-003 authentication against `users`
