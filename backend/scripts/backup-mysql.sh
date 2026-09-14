#!/usr/bin/env bash
set -euo pipefail

# Cron entrypoint for SRS-BK-01. Prefer Laravel scheduler (ops:backup-mysql);
# this script is for hosts that cron the dump directly.

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
php artisan ops:backup-mysql
