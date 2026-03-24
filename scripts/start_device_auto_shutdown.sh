#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
ENABLED="${DEVICE_AUTO_SHUTDOWN_ENABLED:-0}"
IDLE_MINUTES="${DEVICE_AUTO_SHUTDOWN_IDLE_MINUTES:-30}"
POLL_SECONDS="${DEVICE_AUTO_SHUTDOWN_POLL_SECONDS:-30}"
BOOT_GRACE_MINUTES="${DEVICE_AUTO_SHUTDOWN_BOOT_GRACE_MINUTES:-10}"
SHUTDOWN_COMMAND="${DEVICE_AUTO_SHUTDOWN_COMMAND:-}"
DRY_RUN="${DEVICE_AUTO_SHUTDOWN_DRY_RUN:-0}"

case "${ENABLED,,}" in
  1|true|yes|on)
    ;;
  *)
    echo "[auto-shutdown] Disabled. Set DEVICE_AUTO_SHUTDOWN_ENABLED=true to run it."
    exit 0
    ;;
esac

if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  echo "[auto-shutdown] ERROR: PHP CLI not found: $PHP_BIN" >&2
  exit 1
fi

echo "[auto-shutdown] Watchdog started"
echo "[auto-shutdown] Project root: $ROOT_DIR"
echo "[auto-shutdown] Idle minutes: $IDLE_MINUTES"
echo "[auto-shutdown] Poll seconds: $POLL_SECONDS"
echo "[auto-shutdown] Boot grace minutes: $BOOT_GRACE_MINUTES"

ARGS=(
  artisan
  device:auto-shutdown
  "--idle-minutes=$IDLE_MINUTES"
  "--poll-seconds=$POLL_SECONDS"
  "--boot-grace-minutes=$BOOT_GRACE_MINUTES"
)

if [[ -n "$SHUTDOWN_COMMAND" ]]; then
  ARGS+=("--shutdown-command=$SHUTDOWN_COMMAND")
fi

if [[ "${DRY_RUN,,}" == "1" || "${DRY_RUN,,}" == "true" || "${DRY_RUN,,}" == "yes" || "${DRY_RUN,,}" == "on" ]]; then
  ARGS+=(--dry-run)
fi

cd "$ROOT_DIR"
exec "$PHP_BIN" "${ARGS[@]}"
