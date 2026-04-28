#!/bin/sh
set -e

# Install Composer dependencies if vendor is missing (first run with volume mount)
if [ ! -d "/var/www/html/vendor" ]; then
    echo "[entrypoint] Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Fix storage & cache permissions for Laravel
if [ -d "/var/www/html/storage" ]; then
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
fi

# Fix SQLite permissions — php-fpm (www-data) needs write access to both
# the .sqlite file and its parent directory (SQLite writes WAL/journal files there).
# chmod 777/666 is used instead of chown alone so this works regardless of the
# UID mismatch between the host bind-mount owner and the www-data (uid 33) process.
if [ -d "/var/www/html/database" ]; then
    chmod 777 /var/www/html/database
    # Touch the SQLite file if it doesn't exist yet so migrations can open it.
    [ -f /var/www/html/database/database.sqlite ] || touch /var/www/html/database/database.sqlite
    find /var/www/html/database -name "*.sqlite" -exec chmod 666 {} \;
fi

# Run migrations + seed on every boot. Migrations are idempotent (they
# track ran state in the migrations table) and ProductSeeder uses
# DB::updateOrInsert keyed on `code`, so this is safe to repeat.
echo "[entrypoint] Running migrations + seed..."
php artisan migrate --force
php artisan db:seed --force

exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
