#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BRIDGE="$ROOT_DIR/scripts/nfc_bridge.py"

MODE="${RFID_BRIDGE_MODE:-api}"
API_URL="${RFID_BRIDGE_API_URL:-http://127.0.0.1:8000/inventory/scan/esp}"
SOURCE="${RFID_BRIDGE_SOURCE:-usb-bridge-$(hostname)}"

PYTHON_BIN="$ROOT_DIR/.venv/bin/python"
if [[ ! -x "$PYTHON_BIN" ]]; then
  PYTHON_BIN="$(command -v python3 || true)"
fi

if [[ ! -f "$BRIDGE" ]]; then
  echo "[rfid] ERROR: Bridge script not found: $BRIDGE"
  exit 1
fi

if [[ -z "${PYTHON_BIN:-}" ]]; then
  echo "[rfid] ERROR: Python not found."
  echo "[rfid] Install Python3, then run: python3 -m venv .venv && ./.venv/bin/python -m pip install pyserial"
  exit 1
fi

echo "[rfid] NFC bridge launcher started"
echo "[rfid] Project root: $ROOT_DIR"
echo "[rfid] Python: $PYTHON_BIN"
echo "[rfid] Mode: $MODE"
if [[ "$MODE" == "api" ]]; then
  echo "[rfid] API URL: $API_URL"
fi
echo "[rfid] Source: $SOURCE"

while true; do
  ARGS=(-u "$BRIDGE" --mode "$MODE" --source "$SOURCE")
  if [[ "$MODE" == "api" ]]; then
    ARGS+=(--api-url "$API_URL")
  fi
  if [[ -n "${RFID_ESP_TOKEN:-}" ]]; then
    ARGS+=(--token "$RFID_ESP_TOKEN")
  fi
  if [[ -n "${RFID_BRIDGE_PORT:-}" ]]; then
    ARGS+=(--port "$RFID_BRIDGE_PORT")
  fi
  if [[ -n "${RFID_BRIDGE_BAUD:-}" ]]; then
    ARGS+=(--baud "$RFID_BRIDGE_BAUD")
  fi
  if [[ "${RFID_BRIDGE_INCLUDE_BLUETOOTH:-0}" == "1" ]]; then
    ARGS+=(--include-bluetooth)
  fi
  if [[ "${RFID_BRIDGE_DEBUG:-0}" == "1" ]]; then
    ARGS+=(--debug)
  fi

  echo "[rfid] Launching bridge..."
  "$PYTHON_BIN" "${ARGS[@]}"
  CODE=$?
  echo "[rfid] Bridge exited with code $CODE. Restarting in 2 seconds..."
  sleep 2
done
