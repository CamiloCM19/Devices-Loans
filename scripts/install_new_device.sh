#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

INSTALL_SYSTEM_PACKAGES="${INSTALL_SYSTEM_PACKAGES:-1}"
INSTALL_NODE_FROM_NODESOURCE="${INSTALL_NODE_FROM_NODESOURCE:-1}"
INSTALL_DJANGO_PORT="${INSTALL_DJANGO_PORT:-1}"
RUN_TESTS="${RUN_TESTS:-0}"
ADD_USER_TO_DIALOUT="${ADD_USER_TO_DIALOUT:-1}"
INSTALL_AUTO_SHUTDOWN_SERVICE="${INSTALL_AUTO_SHUTDOWN_SERVICE:-0}"
APP_HOST_VALUE="${APP_HOST:-0.0.0.0}"
APP_PORT_VALUE="${APP_PORT:-8000}"
RFID_READER_DRIVER_VALUE="${RFID_READER_DRIVER:-auto}"
NODE_MAJOR_TARGET="${NODE_MAJOR_TARGET:-22}"

log() {
  echo "[install-device] $*"
}

fail() {
  echo "[install-device] ERROR: $*" >&2
  exit 1
}

is_truthy() {
  case "${1,,}" in
    1|true|yes|on)
      return 0
      ;;
    *)
      return 1
      ;;
  esac
}

run_as_root() {
  if [[ "$(id -u)" -eq 0 ]]; then
    "$@"
    return
  fi

  if command -v sudo >/dev/null 2>&1; then
    sudo "$@"
    return
  fi

  fail "This step needs root privileges. Install sudo or run this script as root."
}

detect_app_url() {
  if [[ -n "${APP_URL:-}" ]]; then
    echo "$APP_URL"
    return
  fi

  if command -v hostname >/dev/null 2>&1; then
    local lan_ip
    lan_ip="$(hostname -I 2>/dev/null | awk '{print $1}')"
    if [[ -n "${lan_ip:-}" ]]; then
      echo "http://${lan_ip}:${APP_PORT_VALUE}"
      return
    fi
  fi

  echo "http://localhost:${APP_PORT_VALUE}"
}

ensure_apt_packages() {
  if ! is_truthy "$INSTALL_SYSTEM_PACKAGES"; then
    log "Skipping system packages because INSTALL_SYSTEM_PACKAGES=$INSTALL_SYSTEM_PACKAGES"
    return
  fi

  if ! command -v apt-get >/dev/null 2>&1; then
    log "apt-get not found. Skipping automatic system package installation."
    return
  fi

  log "Installing system packages with apt-get..."
  run_as_root apt-get update
  run_as_root apt-get install -y \
    ca-certificates \
    composer \
    curl \
    default-libmysqlclient-dev \
    git \
    pkg-config \
    python3 \
    python3-dev \
    python3-pip \
    python3-venv \
    sqlite3 \
    unzip \
    build-essential \
    php-cli \
    php-bcmath \
    php-curl \
    php-mbstring \
    php-mysql \
    php-sqlite3 \
    php-xml \
    php-zip
}

ensure_php() {
  command -v php >/dev/null 2>&1 || fail "PHP CLI was not found."

  php -r '
    if (version_compare(PHP_VERSION, "8.2.0", "<")) {
        fwrite(STDERR, "PHP 8.2 or newer is required. Current: ".PHP_VERSION.PHP_EOL);
        exit(1);
    }
  ' || exit 1
}

ensure_composer() {
  command -v composer >/dev/null 2>&1 || fail "Composer was not found."
}

node_is_supported() {
  command -v node >/dev/null 2>&1 || return 1
  node -e '
    const [major, minor] = process.versions.node.split(".").map(Number);
    process.exit((major > 20 || (major === 20 && minor >= 19)) ? 0 : 1);
  ' >/dev/null 2>&1
}

ensure_node() {
  if node_is_supported && command -v npm >/dev/null 2>&1; then
    log "Node $(node --version) and npm $(npm --version) detected."
    return
  fi

  if command -v apt-get >/dev/null 2>&1 && is_truthy "$INSTALL_NODE_FROM_NODESOURCE"; then
    log "Installing Node.js ${NODE_MAJOR_TARGET}.x from NodeSource..."
    curl -fsSL "https://deb.nodesource.com/setup_${NODE_MAJOR_TARGET}.x" | run_as_root bash -
    run_as_root apt-get install -y nodejs
  fi

  node_is_supported || fail "Node.js 20.19+ or 22.x is required for Vite. Current: $(node --version 2>/dev/null || echo missing)"
  command -v npm >/dev/null 2>&1 || fail "npm was not found."
}

prepare_permissions() {
  chmod +x \
    scripts/deploy_pi.sh \
    scripts/install_new_device.sh \
    scripts/start_server_pi.sh \
    scripts/start_rfid_listener.sh \
    scripts/start_device_auto_shutdown.sh \
    scripts/install_device_auto_shutdown_service.sh

  local target_user="${DEVICE_USER:-${SUDO_USER:-${USER:-}}}"
  if [[ -n "$target_user" ]] && command -v usermod >/dev/null 2>&1 && is_truthy "$ADD_USER_TO_DIALOUT"; then
    if getent group dialout >/dev/null 2>&1; then
      log "Adding $target_user to the dialout group for USB serial access..."
      run_as_root usermod -aG dialout "$target_user" || true
      log "If this user was not already in dialout, log out and back in before using the RFID listener."
    fi
  fi
}

install_laravel_app() {
  local app_url_value
  app_url_value="$(detect_app_url)"

  log "Preparing Laravel app at $ROOT_DIR"
  log "APP_URL=$app_url_value"
  log "RFID_READER_DRIVER=$RFID_READER_DRIVER_VALUE"

  APP_URL="$app_url_value" \
  RFID_READER_DRIVER="$RFID_READER_DRIVER_VALUE" \
  bash scripts/deploy_pi.sh
}

install_django_port() {
  if ! is_truthy "$INSTALL_DJANGO_PORT"; then
    log "Skipping Django port because INSTALL_DJANGO_PORT=$INSTALL_DJANGO_PORT"
    return
  fi

  if [[ ! -f django_port/requirements-django.txt ]]; then
    log "Django port requirements were not found. Skipping."
    return
  fi

  command -v python3 >/dev/null 2>&1 || fail "python3 was not found."

  log "Installing Django port dependencies in django_port/.venv..."
  python3 -m venv django_port/.venv
  # shellcheck source=/dev/null
  source django_port/.venv/bin/activate
  python -m pip install --upgrade pip wheel
  python -m pip install -r django_port/requirements-django.txt
  deactivate

  log "Building Django TypeScript assets..."
  npm run build:django-ts
}

run_optional_tests() {
  if ! is_truthy "$RUN_TESTS"; then
    return
  fi

  log "Running validation tests..."
  composer test
  npm run build
  if is_truthy "$INSTALL_DJANGO_PORT"; then
    npm run build:django-ts
  fi
}

install_optional_services() {
  if is_truthy "$INSTALL_AUTO_SHUTDOWN_SERVICE"; then
    log "Installing auto-shutdown systemd service..."
    bash scripts/install_device_auto_shutdown_service.sh
  fi
}

APP_URL_VALUE="$(detect_app_url)"

log "Starting new device installation"
log "Project root: $ROOT_DIR"
log "App host: $APP_HOST_VALUE"
log "App port: $APP_PORT_VALUE"
log "App URL: $APP_URL_VALUE"

ensure_apt_packages
ensure_php
ensure_composer
ensure_node
prepare_permissions
install_laravel_app
install_django_port
run_optional_tests
install_optional_services

cat <<EOF

[install-device] Done.
[install-device] Open the app with:
  APP_HOST=${APP_HOST_VALUE} APP_PORT=${APP_PORT_VALUE} ./scripts/start_server_pi.sh

[install-device] Start the RFID listener with:
  RFID_READER_DRIVER=${RFID_READER_DRIVER_VALUE} ./scripts/start_rfid_listener.sh

[install-device] Browser URL:
  ${APP_URL_VALUE}/inventory
EOF
