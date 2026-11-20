#!/bin/sh
set -eu

# Vercel injects the listening port for container services.
PORT="${PORT:-8080}"

sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost *:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Create every directory checked by config/installer.php. Vercel containers
# start from an immutable image and may not retain runtime-created directories.
mkdir -p \
    /var/www/html/storage/framework \
    /var/www/html/storage/logs \
    /var/www/html/storage/app \
    /var/www/html/storage/app/public \
    /var/www/html/storage/uploads \
    /var/www/html/bootstrap/cache \
    /var/www/html/resources/lang \
    /var/www/html/public

# The installer checks public/storage using fileperms(). Keep the standard
# Laravel public-disk symlink and ensure its target exists.
if [ ! -e /var/www/html/public/storage ]; then
    rm -f /var/www/html/public/storage
    ln -s ../storage/app/public /var/www/html/public/storage
fi

# The installer expects 0775 on these paths. Apply it explicitly at runtime
# rather than relying on build-layer ownership or umask defaults.
chmod 0775 \
    /var/www/html/storage/framework \
    /var/www/html/storage/logs \
    /var/www/html/storage/app \
    /var/www/html/storage/app/public \
    /var/www/html/storage/uploads \
    /var/www/html/bootstrap/cache \
    /var/www/html/resources/lang

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chown www-data:www-data /var/www/html/resources/lang

exec apache2-foreground
