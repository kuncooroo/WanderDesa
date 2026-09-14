# Maintenance mode (SRS-DEP-07)

Use this for Laravel upgrades, not for a single broken kiosk (that is [device-disable.md](device-disable.md)).

```bash
cd /path/to/backend
php artisan down --retry=60
# optional bypass cookie for operators:
# php artisan down --retry=60 --secret=choose-a-long-token
```

- `GET /up` stays **200** so uptime checks still work.
- Dashboard and API return **503** to visitors/kiosks.
- In-flight digital payments stay `processing` until webhook/reconcile. Do not mark PAID by hand.
- Kiosks should show network/unknown UX, never local PAID.

Bring back:

```bash
php artisan up
```

Then [post-deploy-smoke.md](post-deploy-smoke.md). If you used `--secret`, visit `https://<host>/<secret>` once to set the bypass cookie, then remove the secret from shell history.
