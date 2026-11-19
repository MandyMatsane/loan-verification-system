#!/bin/bash
set -e
PORT="${PORT:-10000}"
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/app/public storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

php artisan package:discover --ansi || true
php artisan storage:link || true
php artisan migrate --force
php artisan db:seed --class=AdminSeeder --force || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec apache2-foreground
