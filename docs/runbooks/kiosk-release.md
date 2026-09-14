# Kiosk APK release (SRS-DEP-06)

The Flutter app is a **physical terminal client**. It does not decide prices, PAID, or check-in.

## Flavors = compile-time `--dart-define`

There is no second Staff Flutter app. Staging vs production is the API base URL baked into the APK.

| Channel | `API_BASE_URL` | Notes |
|---|---|---|
| Local emulator | `http://10.0.2.2:8000/api/v1` | Default in `AppConfig` |
| LAN debug tablet | `http://<lan-ip>:8000/api/v1` | Cleartext allowed in debug/profile |
| Staging | `https://<staging-host>/api/v1` | Sandbox payments |
| Production | `https://<prod-host>/api/v1` | TLS required |

Never put webhook secrets, `APP_KEY`, or device tokens in dart-define or source.

```bash
cd kiosk
flutter test
flutter build apk --release \
  --dart-define=API_BASE_URL=https://<host>/api/v1 \
  --dart-define=SOFTWARE_VERSION=1.0.0 \
  --dart-define=DEVICE_ID=kiosk-gate-01   # optional prefill only
```

Helper (Git Bash / Linux):

```bash
STAGING_API_BASE_URL=https://<staging-host>/api/v1 kiosk/tool/build_apk.sh staging
PRODUCTION_API_BASE_URL=https://<prod-host>/api/v1 kiosk/tool/build_apk.sh production
```

`android/app/build.gradle.kts` still signs with **debug keys**. Replace `signingConfig` with a real keystore before any production sideload.

## Activation (once per device)

1. Operator/Admin registers `device_id` on `/dashboard/kiosks`.
2. Admin issues an activation code (shown **once**).
3. On the tablet activation screen: `device_id` + code.
4. App stores the Sanctum token in encrypted storage and heartbeats.
5. Rotate codes if the code leaked; disable stolen devices ([device-disable.md](device-disable.md)).

Promote APKs **staging → production** only after [post-deploy-smoke.md](post-deploy-smoke.md) on that staging SHA. Sideload the matching production APK; do not point a staging build at production.
