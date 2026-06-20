#!/bin/sh
set -eu

mkdir -p /var/log/nginx /run/nginx

touch /var/log/nginx/access.log /var/log/nginx/error.log
chown -R nginx:nginx /var/log/nginx /run/nginx || true

# Add www-data to adm group for log access
addgroup www-data adm 2>/dev/null || true

echo "[start] Testing nginx configuration..."
nginx -t

echo "[start] Starting PHP-FPM..."
php-fpm -D

echo "[start] Streaming nginx logs to container output..."
tail -F /var/log/nginx/access.log /var/log/nginx/error.log &

echo "[start] Starting nginx..."
exec nginx -g 'daemon off;'