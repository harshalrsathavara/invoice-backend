#!/bin/sh
set -e

cd /var/www/html

php artisan storage:link || true

# SQLite (testing): the file isn't in the image, so create it on each boot.
# Render's free disk is ephemeral — data resets whenever the service restarts.
if [ "$DB_CONNECTION" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    touch "$DB_FILE"
    chown www-data:www-data "$DB_FILE" "$(dirname "$DB_FILE")"
fi

php artisan migrate --force

# Free Render services have no shell, so the admin is created from env vars.
# Safe on every boot: the command creates or updates the same account.
if [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then
    php artisan invoice:admin --no-interaction \
        --name="${ADMIN_NAME:-Admin}" \
        --email="$ADMIN_EMAIL" \
        --password="$ADMIN_PASSWORD"
fi
php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache

exec apache2-foreground
