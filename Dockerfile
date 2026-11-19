# HRM production image for Vercel Services (container runtime).
# Build on PHP 8.4 with the extensions required by Laravel, spreadsheets,
# document generation, QR codes, image handling, and payment SDKs.

FROM node:22-bookworm-slim AS node-runtime
FROM composer:2 AS composer-runtime

FROM php:8.4-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

RUN apt-get update && apt-get install -y --no-install-recommends \
        build-essential \
        python3 \
        git \
        unzip \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libicu-dev \
        libonig-dev \
        libpq-dev \
        libxml2-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        dom \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        simplexml \
        xml \
        xmlreader \
        xmlwriter \
        zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Install Node 22 and Composer into the PHP build image.
COPY --from=node-runtime /usr/local/bin/node /usr/local/bin/node
COPY --from=node-runtime /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/npm
COPY --from=composer-runtime /usr/bin/composer /usr/local/bin/composer
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

WORKDIR /var/www/html
COPY . .

# Install PHP dependencies before building Vite because the Vite config uses
# the installed Ziggy package from vendor/.
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress

# Build the complete React/Inertia frontend. Build output is served by Apache
# from Laravel's public directory.
RUN npm ci --no-audit --no-fund \
    && npm run build \
    && rm -rf node_modules

RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/hrm-entrypoint
RUN chmod 0755 /usr/local/bin/hrm-entrypoint

EXPOSE 8080
CMD ["/usr/local/bin/hrm-entrypoint"]
