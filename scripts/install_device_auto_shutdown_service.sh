#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SERVICE_NAME="${DEVICE_AUTO_SHUTDOWN_SERVICE_NAME:-devices-loans-auto-shutdown.service}"
SERVICE_USER="${DEVICE_AUTO_SHUTDOWN_SERVICE_USER:-${SUDO_USER:-$USER}}"
START_SCRIPT="$ROOT_DIR/scripts/start_device_auto_shutdown.sh"
SERVICE_PATH="/etc/systemd/system/$SERVICE_NAME"
SUDOERS_PATH="/etc/sudoers.d/devices-loans-auto-shutdown"
SHUTDOWN_BIN="${DEVICE_AUTO_SHUTDOWN_SUDO_BIN:-}"

if [[ ! -x "$START_SCRIPT" ]]; then
  echo "Missing executable start script: $START_SCRIPT" >&2
  exit 1
fi

if [[ -z "$SHUTDOWN_BIN" ]]; then
  for candidate in /sbin/shutdown /usr/sbin/shutdown "$(command -v shutdown 2>/dev/null || true)"; do
    if [[ -n "$candidate" && -x "$candidate" ]]; then
      SHUTDOWN_BIN="$candidate"
      break
    fi
  done
fi

if [[ -z "$SHUTDOWN_BIN" ]]; then
  echo "Could not detect a shutdown binary." >&2
  exit 1
fi

chmod +x "$START_SCRIPT"

sudo tee "$SERVICE_PATH" > /dev/null <<EOF
[Unit]
Description=Devices Loans auto shutdown watchdog
After=network.target

[Service]
Type=simple
User=$SERVICE_USER
WorkingDirectory=$ROOT_DIR
ExecStart=/bin/bash $START_SCRIPT
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

sudo tee "$SUDOERS_PATH" > /dev/null <<EOF
$SERVICE_USER ALL=(root) NOPASSWD: $SHUTDOWN_BIN
EOF

sudo chmod 0440 "$SUDOERS_PATH"
sudo visudo -cf "$SUDOERS_PATH"
sudo systemctl daemon-reload
sudo systemctl enable --now "$SERVICE_NAME"

echo "Service installed: $SERVICE_NAME"
echo "Service user: $SERVICE_USER"
echo "Shutdown allowed via sudo: $SHUTDOWN_BIN"
echo "Review status with: sudo systemctl status $SERVICE_NAME"
