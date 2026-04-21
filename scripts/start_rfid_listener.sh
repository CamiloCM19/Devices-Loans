#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
READER="${RFID_READER_DRIVER:-mfrc522}"
DEFAULT_SOURCE_PREFIX="$READER"
if [[ "$DEFAULT_SOURCE_PREFIX" == "auto" ]]; then
  DEFAULT_SOURCE_PREFIX="nfc-reader"
fi
DEVICE="${RFID_SERIAL_DEVICE:-auto}"
BAUD="${RFID_SERIAL_BAUD:-115200}"
SOURCE="${RFID_READER_SOURCE:-${DEFAULT_SOURCE_PREFIX}-$(hostname)}"
DEDUPE_MS="${RFID_SERIAL_DEDUPE_MS:-1200}"
RECONNECT_DELAY="${RFID_SERIAL_RECONNECT_DELAY:-2}"
FRAME_IDLE_MS="${RFID_SERIAL_FRAME_IDLE_MS:-150}"
APP_PORT_VALUE="${APP_PORT:-8000}"
APP_URL_VALUE="${APP_URL:-}"

if [[ -z "$APP_URL_VALUE" ]] && command -v hostname >/dev/null 2>&1; then
  LAN_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
  if [[ -n "${LAN_IP:-}" ]]; then
    APP_URL_VALUE="http://${LAN_IP}:${APP_PORT_VALUE}"
  fi
fi

if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  echo "[rfid] ERROR: PHP CLI not found: $PHP_BIN"
  exit 1
fi

echo "[rfid] NFC/RFID serial listener started"
echo "[rfid] Project root: $ROOT_DIR"
echo "[rfid] PHP: $PHP_BIN"
echo "[rfid] Reader profile: $READER"
echo "[rfid] Device: $DEVICE"
echo "[rfid] Baud: $BAUD"
echo "[rfid] Source: $SOURCE"
if [[ -n "$APP_URL_VALUE" ]]; then
  echo "[rfid] Shared inventory URL: ${APP_URL_VALUE}/inventory"
fi

while true; do
  ARGS=(artisan rfid:listen-serial --reader="$READER" --device="$DEVICE" --baud="$BAUD" --source="$SOURCE" --dedupe-ms="$DEDUPE_MS" --frame-idle-ms="$FRAME_IDLE_MS" --reconnect-delay="$RECONNECT_DELAY")
  if [[ "${RFID_SERIAL_DEBUG:-0}" == "1" ]]; then
    ARGS+=(--debug)
  fi

  echo "[rfid] Launching listener..."
  "$PHP_BIN" "${ARGS[@]}"
  CODE=$?
  echo "[rfid] Listener exited with code $CODE. Restarting in 2 seconds..."
  sleep 2
done
