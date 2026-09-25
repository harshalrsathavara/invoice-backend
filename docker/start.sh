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

# The managed database may still be waking when the web service boots, and a
# failed migration here would leave the app serving 500s against an empty
# schema. Retry a few times, then fail loudly rather than carry on.
attempt=1
until php artisan migrate --force; do
    if [ "$attempt" -ge 5 ]; then
        echo "Database not reachable after $attempt attempts — giving up." >&2
        exit 1
    fi
    echo "Database not ready (attempt $attempt) — retrying in 5s..."
    attempt=$((attempt + 1))
    sleep 5
done

# Free Render services have no shell, so the admin is created from env vars.
# Safe on every boot: the command creates or updates the same account.
if [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then
    php artisan invoice:admin --no-interaction \
        --name="${ADMIN_NAME:-Admin}" \
        --email="$ADMIN_EMAIL" \
        --password="$ADMIN_PASSWORD" \
        ${ADMIN_PHONE:+--phone="$ADMIN_PHONE"}
fi

# Demo data: three businesses with a few months of bills behind them. Keyed
# on the bill prefix, so a boot against a database that already has them
# changes nothing — but still behind a switch, because seeding on every
# deploy is not something a real ledger should have on by accident. Turn
# SEED_DEMO off once actual books are on here.
if [ "$SEED_DEMO" = "true" ]; then
    php artisan db:seed --class=DemoSeeder --force --no-interaction
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache

exec apache2-foreground
