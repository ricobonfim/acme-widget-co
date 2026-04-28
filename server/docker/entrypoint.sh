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

exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
