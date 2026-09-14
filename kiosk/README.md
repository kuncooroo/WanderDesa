# WanderDesa Kiosk

Physical **self-service terminal** client (Flutter), not a consumer mobile app and not a staff app.

| Constraint | Detail |
|---|---|
| Role | Client only — Laravel is business authority |
| Hardware target | Advan A10 class tablet, landscape, locked fullscreen |
| Language | Bahasa Indonesia first |
| Forbidden | Local PAID / ISSUED / USED; local pricing; Staff Flutter |

Layout follows `docs/10-PROJECT-STRUCTURE.md`: `lib/core`, `network`, `storage`, `security`, `device`, `printer`, `scanner`, `payment`, `features`, `shared`.

Staff operations use the Laravel Blade + Livewire dashboard, not a second Flutter project.

## Run (staging)

API base URL is compile-time (`--dart-define`). No secrets belong in source.

```bash
cd kiosk
flutter pub get
flutter test
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

`flutter test` includes retry/offline unit tests: network error on launch keeps the token; `retryConnection` returns to idle from Laravel; checkout `retryStatus` / `retryPayment` never invent local PAID.

Physical tablet on a LAN (debug/profile allow cleartext):

```bash
flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8000/api/v1 --dart-define=DEVICE_ID=kiosk-gate-01
```

Production builds should use HTTPS. Device token is stored in Android encrypted storage. Staging vs production is a compile-time `API_BASE_URL` (see [docs/runbooks/kiosk-release.md](../docs/runbooks/kiosk-release.md)).

```bash
# Staging APK (replace the host; no secrets in dart-define)
flutter build apk --release --dart-define=API_BASE_URL=https://staging.example.invalid/api/v1

# Helper (Git Bash / Linux)
STAGING_API_BASE_URL=https://staging.example.invalid/api/v1 ./tool/build_apk.sh staging
```

`build.gradle.kts` still uses debug signing — replace the keystore before a real production sideload.

### Activation

1. Register and issue an activation code from the dashboard (`kiosks.activate`).
2. On the kiosk activation screen, enter `device_id` + one-time code.
3. The app stores the Sanctum device token and starts heartbeat (`POST /api/v1/kiosks/heartbeat`).
4. Maintenance / disable come from server status and heartbeat `commands` — the kiosk does not invent them.

Checkout commerce (TASK-022) uses Laravel quote/order/payment/ticket APIs. The kiosk never sends `unit_price` / totals / `amount`. Success is shown only after `GET /payments/{id}` reports `paid` and tickets are issued.

Sandbox digital payments stay `processing` until a provider webhook marks them paid; the kiosk polls and shows the server `next_action` QR.
