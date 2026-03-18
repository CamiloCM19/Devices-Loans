#!/usr/bin/env bash
set -euo pipefail

APP_URL_VALUE="${APP_URL:-http://10.204.248.185:8000}"

if [ ! -f .env ]; then
  cp .env.example .env
fi

mkdir -p bootstrap/cache \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/testing \
  storage/framework/views \
  storage/logs

touch database/database.sqlite

APP_URL="$APP_URL_VALUE" php -r '
$file = ".env";
$appUrl = getenv("APP_URL") ?: "http://10.204.248.185:8000";
$updates = [
    "APP_URL" => $appUrl,
    "DB_CONNECTION" => "sqlite",
    "DB_DATABASE" => "database/database.sqlite",
    "SESSION_DRIVER" => "database",
    "CACHE_STORE" => "database",
    "QUEUE_CONNECTION" => "database",
];
$content = file_get_contents($file);
if ($content === false) {
    fwrite(STDERR, "Unable to read .env\n");
    exit(1);
}
foreach ($updates as $key => $value) {
    $line = $key."=".$value;
    if (preg_match("/^".preg_quote($key, "/")."=/m", $content)) {
        $content = preg_replace("/^".preg_quote($key, "/")."=.*/m", $line, $content, 1);
    } else {
        $content .= PHP_EOL.$line;
    }
}
file_put_contents($file, $content);
'

composer install --no-interaction --prefer-dist --optimize-autoloader

if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

php artisan migrate --force
npm ci
npm run build
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "Deployment ready for ${APP_URL_VALUE}"
echo "Start the server with: APP_HOST=0.0.0.0 APP_PORT=8000 ./scripts/start_server_pi.sh"
