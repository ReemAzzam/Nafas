#!/bin/sh
set -eu
mkdir -p /data storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
# Preserve the configured APP_KEY so HTTP and Artisan use the same encryption key.
# Generate a persistent fallback only when APP_KEY is not configured.
if [ -z "${APP_KEY:-}" ]; then
  if [ ! -f /data/app-key ]; then php -r 'echo "base64:".base64_encode(random_bytes(32));' > /data/app-key; fi
  export APP_KEY="$(cat /data/app-key)"
fi
touch /data/database.sqlite
php artisan config:clear
php artisan migrate --force
if [ ! -f /data/seeded ]; then
  touch /data/seeded
fi
# Restore bundled catalog images on first media-volume initialization only.
if [ ! -f storage/app/public/.catalog-images-v1 ]; then
  php artisan db:seed --class=ContentImageSeeder --force
  touch storage/app/public/.catalog-images-v1
fi
php artisan storage:link || true
exec php artisan serve --host=0.0.0.0 --port=8000
