#!/usr/bin/env bash
set -euo pipefail

HOST="${APP_HOST:-0.0.0.0}"
PORT="${APP_PORT:-8000}"

if [ ! -f public/build/manifest.json ]; then
  echo "Missing Vite build. Run ./scripts/deploy_pi.sh first." >&2
  exit 1
fi

php artisan serve --host="${HOST}" --port="${PORT}"
