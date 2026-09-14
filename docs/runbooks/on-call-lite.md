# On-call lite (pilot)

Single-destination weekend coverage. Not a NOC.

## Every shift

1. `GET https://<host>/up` is 200.
2. Supervisor: `queue:work` running; `php artisan queue:failed` empty or acknowledged.
3. `/dashboard/kiosks`: no unexpected mass offline (heartbeat SLA). `device.offline_detected` is audit + notification only — online/offline is still derived from `last_heartbeat_at`.
4. Open `processing` payments older than 15 minutes: leave them; confirm `reconcile-open-payments` is scheduled. Escalate to Finance if a visitor paid and tickets did not issue.
5. Disk: `BACKUP_PATH` has a dump from the last 24h.

## Alerts that matter

| Signal | Action |
|---|---|
| `/up` down | Check php-fpm/nginx/MySQL; [maintenance-mode.md](maintenance-mode.md) if deploying |
| Queue stuck | Restart worker; inspect `failed_jobs` |
| Kiosk mass offline | Site network; do not disable the whole fleet |
| Webhook 401 spike | `PAYMENT_WEBHOOK_SECRET` mismatch — do not disable signature checks |
| 5xx on `/api/v1` | Logs in `storage/logs`; correlation `request_id` |

## Do not

- SSH in and `UPDATE payments SET status='paid'`.
- Ask the kiosk to “just show success.”
- Assign Super Admin to the person at the gate.
