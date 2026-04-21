#!/usr/bin/env bash
set -euo pipefail

HOST="${APP_HOST:-0.0.0.0}"
PORT="${APP_PORT:-8000}"
APP_URL_VALUE="${APP_URL:-}"

if [ ! -f public/build/manifest.json ]; then
  echo "Missing Vite build. Run ./scripts/deploy_pi.sh first." >&2
  exit 1
fi

if [ -z "$APP_URL_VALUE" ] && command -v hostname >/dev/null 2>&1; then
  LAN_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
  if [ -n "${LAN_IP:-}" ]; then
    APP_URL_VALUE="http://${LAN_IP}:${PORT}"
  fi
fi

echo "Server listening on ${HOST}:${PORT}"
if [ -n "$APP_URL_VALUE" ]; then
  echo "Shared inventory URL: ${APP_URL_VALUE}/inventory"
  echo "Remote devices on the same network can open that URL and receive NFC/RFID updates from this server."
fi

php artisan serve --host="${HOST}" --port="${PORT}"
