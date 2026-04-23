#!/usr/bin/env bash
set -euo pipefail

HOST="${APP_HOST:-0.0.0.0}"
PORT="${APP_PORT:-8000}"
APP_URL_VALUE="${APP_URL:-}"

detect_lan_ip() {
  if command -v ip >/dev/null 2>&1; then
    ip route get 1 2>/dev/null | awk '{for (i = 1; i <= NF; i++) if ($i == "src") { print $(i + 1); exit }}'
    return
  fi

  if command -v hostname >/dev/null 2>&1; then
    hostname -I 2>/dev/null | awk '{print $1}'
  fi
}

if [ ! -f public/build/manifest.json ]; then
  echo "Missing Vite build. Run ./scripts/deploy_pi.sh first." >&2
  exit 1
fi

if [[ -z "$APP_URL_VALUE" || "$APP_URL_VALUE" == http://localhost* || "$APP_URL_VALUE" == http://127.0.0.1* ]]; then
  LAN_IP="$(detect_lan_ip)"
  if [ -n "${LAN_IP:-}" ]; then
    APP_URL_VALUE="http://${LAN_IP}:${PORT}"
  fi
fi

echo "Server listening on ${HOST}:${PORT}"
if [ -n "$APP_URL_VALUE" ]; then
  echo "Shared inventory URL: ${APP_URL_VALUE}/inventory"
  echo "Remote devices on the same network can open that URL and receive NFC/RFID updates from this server."
fi

if [ -n "$APP_URL_VALUE" ]; then
  export APP_URL="$APP_URL_VALUE"
fi

php artisan serve --host="${HOST}" --port="${PORT}"
