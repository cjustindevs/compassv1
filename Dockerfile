
# syntax=docker/dockerfile:1

# ============================================================
# COMPASS — Render Docker Deployment
# PHP 8.3 + PostgreSQL + Nginx + Vite + Tailwind + PWA
# ============================================================

# STAGE 1: Build frontend assets
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci --no-audit --no-fund

COPY resources ./resources
COPY public ./public
COPY scripts ./scripts

COPY vite.config.js ./
COPY tailwind.config.js ./
COPY postcss.config.js ./

RUN npm run build


# ============================================================
# STAGE 2: PHP and Nginx runtime
# ============================================================

FROM php:8.3-fpm-bookworm

ENV PORT=10000 \
    APP_ENV=production \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/html


# ============================================================
# 1. Install system dependencies
# ============================================================

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx \
        gettext-base \
        git \
        curl \
        zip \
        unzip \
        ca-certificates \
        libpq-dev \
        postgresql-client \
        libzip-dev \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        libsqlite3-dev \
        libxml2-dev \
    && rm -rf /var/lib/apt/lists/*


# ============================================================
# 2. Install PHP extensions
# ============================================================

RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        pdo_sqlite \
        bcmath \
        intl \
        zip \
        gd \
        opcache \
        pcntl \
        sockets \
        posix


# Verify PostgreSQL support during the build
RUN php -m | grep -i pdo_pgsql \
    && php -m | grep -i pgsql


# ============================================================
# 3. PHP configuration for Render free tier
# ============================================================

RUN printf '%s\n' \
    'memory_limit = 192M' \
    'upload_max_filesize = 8M' \
    'post_max_size = 10M' \
    'max_execution_time = 120' \
    'display_errors = Off' \
    'log_errors = On' \
    > /usr/local/etc/php/conf.d/zz-compass.ini

RUN printf '%s\n' \
    'opcache.enable = 1' \
    'opcache.enable_cli = 0' \
    'opcache.memory_consumption = 64' \
    'opcache.max_accelerated_files = 10000' \
    'opcache.validate_timestamps = 0' \
    > /usr/local/etc/php/conf.d/zz-opcache.ini

# Limit PHP-FPM workers for the 512 MB instance
RUN printf '%s\n' \
    '[www]' \
    'pm = dynamic' \
    'pm.max_children = 2' \
    'pm.start_servers = 1' \
    'pm.min_spare_servers = 1' \
    'pm.max_spare_servers = 1' \
    'pm.max_requests = 200' \
    'catch_workers_output = yes' \
    > /usr/local/etc/php-fpm.d/zz-compass.conf


# ============================================================
# 4. Install Composer
# ============================================================

COPY --from=composer:2 \
    /usr/bin/composer \
    /usr/bin/composer


# ============================================================
# 5. Copy application source
# ============================================================

COPY . /var/www/html

COPY docker/nginx.conf /docker/nginx.conf
COPY docker/start.sh /docker/start.sh


# ============================================================
# 6. Install production Composer dependencies
# ============================================================

RUN php -d memory_limit=-1 \
    /usr/bin/composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --no-scripts \
        --no-progress \
        --optimize-autoloader

RUN php artisan package:discover --ansi


# ============================================================
# 7. Copy compiled frontend and PWA assets
# ============================================================

COPY --from=frontend \
    /app/public/build \
    /var/www/html/public/build

COPY --from=frontend \
    /app/public/sw.js \
    /var/www/html/public/sw.js


# ============================================================
# 8. Configure Laravel storage permissions
# ============================================================

RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/testing \
        storage/logs \
        bootstrap/cache \
        public/uploads/avatars \
    && chown -R www-data:www-data \
        storage \
        bootstrap/cache \
        public/uploads \
    && chmod -R 775 \
        storage \
        bootstrap/cache \
        public/uploads


# ============================================================
# 9. Prepare startup script
# ============================================================

RUN sed -i 's/\r$//' /docker/start.sh \
    && chmod +x /docker/start.sh

# Verify Nginx configuration is valid
RUN nginx -t


# ============================================================
# 10. Expose Render HTTP port
# ============================================================

EXPOSE 10000


# ============================================================
# 11. Start COMPASS
# ============================================================

CMD ["/bin/sh", "/docker/start.sh"]