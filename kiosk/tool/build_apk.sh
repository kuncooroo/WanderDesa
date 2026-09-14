#!/usr/bin/env bash
set -euo pipefail

# Build a kiosk APK that points at staging or production Laravel.
# Secrets do not belong here. Signing: replace debug keys before a real store/sideload.

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

ENV_NAME="${1:-}"
case "$ENV_NAME" in
  staging)
    API_BASE_URL="${STAGING_API_BASE_URL:-https://staging.example.invalid/api/v1}"
    ;;
  production)
    API_BASE_URL="${PRODUCTION_API_BASE_URL:-https://prod.example.invalid/api/v1}"
    ;;
  *)
    echo "Usage: STAGING_API_BASE_URL=https://host/api/v1 tool/build_apk.sh staging"
    echo "   or: PRODUCTION_API_BASE_URL=https://host/api/v1 tool/build_apk.sh production"
    exit 1
    ;;
esac

SOFTWARE_VERSION="${SOFTWARE_VERSION:-1.0.0}"

echo "Building $ENV_NAME APK → $API_BASE_URL"
flutter pub get
flutter test
flutter build apk --release \
  --dart-define="API_BASE_URL=${API_BASE_URL}" \
  --dart-define="SOFTWARE_VERSION=${SOFTWARE_VERSION}"

echo "APK: build/app/outputs/flutter-apk/app-release.apk"
echo "Replace android/app signingConfig before distributing a production APK."
