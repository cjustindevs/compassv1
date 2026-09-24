# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1 -- Frontend assets (Vite / Tailwind / PWA)
# ---------------------------------------------------------------------------
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

# Vite build inputs (the PWA plugin reads public/serviceworker.js as its source).
COPY resources ./resources
COPY public ./public
COPY scripts ./scripts
COPY vite.config.js tailwind.config.js postcss.config.js ./

RUN npm run build

# ---------------------------------------------------------------------------
# Stage 2 -- Runtime (PHP-FPM 8.3 + Nginx)
# ---------------------------------------------------------------------------
FROM php:8.3-fpm

# Render free web services must bind to $PORT (default 10000).
ENV PORT=10000 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/html

# System packages: Nginx (web server), gettext (envsubst for the PORT template),
# git/zip/unzip (Composer), mysql client (troubleshooting), plus native libs used
# by the PHP extensions required by composer.json.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx \
        gettext-base \
        git \
        curl \
        zip \
        unzip \
        ca-certificates \
        default-mysql-client \
        libzip-dev \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        libsqlite3-dev \
        libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# Install the PHP extensions the application actually requires.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        pdo_sqlite \
        bcmath \
        intl \
        zip \
        gd \
        opcache \
        pcntl \
        posix \
        sockets \
    && docker-php-ext-enable opcache

# Production PHP tuning: keep memory modest for the free 512 MB instance and
# allow the 10 MB Nginx client_max_body_size for avatar uploads.
RUN printf 'memory_limit = 256M\nupload_max_filesize = 8M\npost_max_size = 10M\nmax_execution_time = 120\n' \
        > /usr/local/etc/php/conf.d/zz-compass.ini

RUN printf 'opcache.enable = 1\nopcache.enable_cli = 0\nopcache.memory_consumption = 128\nopcache.max_accelerated_files = 20000\nopcache.validate_timestamps = 0\nopcache.fast_shutdown = 1\n' \
        > /usr/local/etc/php/conf.d/zz-opcache.ini

# Composer (official image).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Application source (vendor, node_modules, tests, build output are excluded
# in .dockerignore). Ship the container scaffolding alongside the app.
COPY . /var/www/html
COPY docker/nginx.conf /docker/nginx.conf
COPY docker/start.sh /docker/start.sh

# Production dependencies. --no-scripts defers package discovery until the
# application files are on disk; we run it explicitly afterwards.
RUN php -d memory_limit=-1 /usr/bin/composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --no-scripts \
        --no-progress \
        --optimize-autoloader \
    && php artisan package:discover --ansi

# Compiled Vite assets (public/build) and generated PWA files produced in stage 1.
COPY --from=frontend /app/public/build /var/www/html/public/build
COPY --from=frontend /app/public/sw.js /var/www/html/public/sw.js

# Writable runtime directories for www-data (uploads, sessions, cache, views).
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/testing \
        storage/logs \
        bootstrap/cache \
        public/uploads/avatars \
    && chown -R www-data:www-data storage bootstrap/cache public/uploads \
    && chmod -R 775 storage bootstrap/cache public/uploads

EXPOSE 10000

CMD ["/bin/sh", "/docker/start.sh"]