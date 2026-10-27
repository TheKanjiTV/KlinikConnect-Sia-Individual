#!/bin/sh
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY is not set. Add it to .env.production before starting the API." >&2
    exit 1
fi

# The storage volume is mounted at runtime, so make sure PHP can write to it.
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

php artisan package:discover --ansi

php artisan storage:link || true

# Wait for MySQL to accept connections and the application credentials to work.
# Do not use `migrate:status` here: it exits non-zero on a fresh database without
# the migrations table, which would look like a connection failure.
attempts=0
until php -r '
    $host = getenv("DB_HOST") ?: "127.0.0.1";
    $port = getenv("DB_PORT") ?: "3306";
    $name = getenv("DB_DATABASE") ?: "klinik";
    $user = getenv("DB_USERNAME") ?: "root";
    $pass = getenv("DB_PASSWORD") ?: "";
    try {
        new PDO("mysql:host={$host};port={$port};dbname={$name}", $user, $pass);
    } catch (Throwable $e) {
        exit(1);
    }
'; do
    attempts=$((attempts + 1))
    if [ "$attempts" -ge 30 ]; then
        echo "ERROR: the database was not reachable after 60 seconds." >&2
        exit 1
    fi
    sleep 2
done

php artisan migrate --force --no-interaction

if [ "${RUN_SEEDER:-false}" = "true" ]; then
    php artisan db:seed --force --no-interaction
fi

php artisan config:cache
php artisan view:cache

# Artisan runs as root here; hand the generated caches back to Apache.
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

exec "$@"
