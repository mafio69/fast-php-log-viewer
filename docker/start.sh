#!/bin/sh
set -eu

# Create log files with proper ownership
touch /var/log/nginx/access.log /var/log/nginx/error.log 2>/dev/null || true
chmod 666 /var/log/nginx/access.log /var/log/nginx/error.log 2>/dev/null || true

# Add www-data to adm group for log access
addgroup www-data adm 2>/dev/null || true

# Ensure .ssh directory exists and has correct owner (if not a read-only mount)
# Skip this if we don't have access to the host .ssh directory
if [ -d "/var/www/.ssh" ]; then
    chown -R www-data:www-data /var/www/.ssh || true
    chmod 700 /var/www/.ssh || true
fi

echo "[start] Testing nginx configuration..."
nginx -t

echo "[start] Starting PHP-FPM..."
php-fpm -D

echo "[start] Streaming nginx logs to container output..."
tail -F /var/log/nginx/access.log /var/log/nginx/error.log &

echo "[start] Starting nginx..."
exec nginx -g 'daemon off;'