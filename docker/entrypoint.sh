#!/bin/sh
set -eu

# Vercel injects the listening port for container services.
PORT="${PORT:-8080}"

sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost *:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Ensure Laravel's writable runtime directories exist in the container.
mkdir -p \
    /var/www/html/storage/app/private \
    /var/www/html/storage/app/public \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache \
    /var/www/html/public

# The Laravel installer checks public/storage with fileperms() during
# /install/permissions. Create the standard public disk symlink at runtime
# so it exists on every fresh Vercel container, not just during image build.
if [ ! -e /var/www/html/public/storage ]; then
    rm -f /var/www/html/public/storage
    ln -s ../storage/app/public /var/www/html/public/storage
fi

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

exec apache2-foreground
