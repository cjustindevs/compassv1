#!/bin/sh
set -e

# ---------------------------------------------------------------------------
# COMPASS container entrypoint for Render.com (free web service).
#
# Responsibilities:
#   * Fail fast when essential configuration is missing.
#   * Build Laravel caches (config + views) -- never destructive DB operations.
#   * Render the final Nginx configuration from the PORT env var.
#   * Run PHP-FPM (background) and Nginx (foreground, the PID 1 process).
#   * Optionally run the Laravel scheduler when RUN_SCHEDULER=true.
#
# COMPASS has no queued jobs, so no queue worker or Reverb process is started
# here. See RENDER_DEPLOYMENT.md for how to enable Reverb separately.
# ---------------------------------------------------------------------------

cd /var/www/html

# --- Fail fast when essential configuration is missing --------------------
: "${APP_KEY:?APP_KEY is required. Generate one with 'php artisan key:generate --show' and add it in Render > Environment.}"
: "${DB_HOST:?DB_HOST (MySQL host) is required. Add it in Render > Environment.}"
: "${DB_DATABASE:?DB_DATABASE (MySQL database name) is required.}"
: "${DB_USERNAME:?DB_USERNAME (MySQL user) is required.}"
: "${DB_PASSWORD:?DB_PASSWORD (MySQL password) is required.}"

export PORT="${PORT:-10000}"

# --- Writable runtime directories (idempotent) ----------------------------
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/framework/testing \
         storage/logs \
         bootstrap/cache \
         public/uploads/avatars
chmod -R 775 storage bootstrap/cache public/uploads
chown -R www-data:www-data storage bootstrap/cache public/uploads 2>/dev/null || true

# Public-disk symlink (supported by the app's filesystems config).
php artisan storage:link --force >/dev/null 2>&1 || echo "[start] storage:link skipped"

# --- Laravel caches --------------------------------------------------------
php artisan config:cache
php artisan view:cache
# NOTE: routes contain closures, so route:cache is intentionally NOT run.

# --- Nginx configuration (inject Render's $PORT) --------------------------
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
envsubst '${PORT}' < /docker/nginx.conf > /etc/nginx/conf.d/default.conf
nginx -t

# --- Background scheduler (optional but required for automatic matching) --
# COMPASS schedules helper matching, queue aging, session expiry and workflow
# maintenance. Free Render has no separate background workers, so the scheduler
# runs inside this container when enabled.
if [ "${RUN_SCHEDULER:-true}" = "true" ]; then
    echo "[start] Starting Laravel scheduler (RUN_SCHEDULER=true)."
    # shellcheck disable=SC2024
    php artisan schedule:work >/dev/null 2>&1 &
else
    echo "[start] RUN_SCHEDULER=false -- Laravel scheduler is disabled."
fi

# --- PHP-FPM (background) + Nginx (foreground) ----------------------------
echo "[start] Starting PHP-FPM."
php-fpm -D

echo "[start] Listening on port ${PORT}."
exec nginx -g 'daemon off;'