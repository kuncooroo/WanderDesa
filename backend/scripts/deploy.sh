#!/usr/bin/env bash
set -euo pipefail

# WanderDesa VPS release (SRS-DEP-05). Run from a checkout of backend/.
# Never run migrate:fresh or migrate:refresh on staging/production.

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ "${1:-}" == "--help" ]]; then
  echo "Usage: scripts/deploy.sh"
  echo "Forward-only migrate, cache, queue restart. See docs/runbooks/deploy-rollback.md"
  exit 0
fi

php artisan down --retry=60
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction

if [[ -f package.json ]]; then
  npm ci --ignore-scripts
  npm run build
fi

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up

echo "Deploy finished. Smoke: docs/runbooks/post-deploy-smoke.md"
