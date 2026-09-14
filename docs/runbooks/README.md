# WanderDesa ops runbooks

Operational procedures for the one-destination MVP pilot. These notes do **not** override `docs/01`–`11`. Laravel remains the business authority.

| Runbook | Use when |
|---|---|
| [MVP pilot checklist](mvp-pilot-checklist.md) | Product / finance / ops sign-off before go-live |
| [Deploy and rollback](deploy-rollback.md) | Releasing or reverting the Laravel monolith |
| [Post-deploy smoke](post-deploy-smoke.md) | After every staging/prod deploy |
| [Backups](backups.md) | MySQL dump, retention, restore drill |
| [Maintenance mode](maintenance-mode.md) | Backend upgrades (`php artisan down`) |
| [Payment unknown](payment-unknown.md) | Kiosk “Status belum diketahui” / PROCESSING |
| [Reprint](reprint.md) | Printer failed after PAID |
| [Device disable](device-disable.md) | Compromised or broken kiosk |
| [Kiosk release](kiosk-release.md) | Staging then production APK |
| [On-call lite](on-call-lite.md) | Pilot weekend health checks |
| [Examples](examples/) | nginx + Supervisor snippets (placeholders) |

VPS OS/panel and the live payment provider stay **TBD at provision time**. Do not invent hostnames, prices, or secrets in git.
