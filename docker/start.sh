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
: "${DB_HOST:?DB_HOST (PostgreSQL host) is required. Add it in Render > Environment.}"
: "${DB_DATABASE:?DB_DATABASE (PostgreSQL database name) is required.}"
: "${DB_USERNAME:?DB_USERNAME (PostgreSQL user) is required.}"
: "${DB_PASSWORD:?DB_PASSWORD (PostgreSQL password) is required.}"

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

# --- Database migrations (managed PostgreSQL on Render) ----------------------
# Render's free tier includes ONE managed Postgres database. The main app and
# the identity vault share it: the vault connection points at that same
# database/credentials and its migrations create the separate idv_* tables.
# Auto-migrating here makes a brand-new (empty) free database work on the first
# deploy. The commands are idempotent and never destructive (no fresh/wipe).
# If the DB is temporarily unreachable we retry, then boot anyway and surface a
# loud ERROR in the logs (the site will HTTP 500 until the DB is migrated).
migrate_with_retry() {
    label="$1"; shift
    attempt=1
    limit="${MIGRATE_RETRIES:-5}"
    until php artisan "$@" --force --step; do
        if [ "$attempt" -ge "$limit" ]; then
            echo "[start] ERROR: ${label} could not be migrated after ${attempt} attempts."
            echo "[start] ERROR: the app will return HTTP 500 until this is resolved. Inspect Render > Logs."
            return 1
        fi
        echo "[start] ${label} attempt ${attempt} failed (database not ready?) -- retrying in 5s."
        sleep 5
        attempt=$((attempt + 1))
    done
    echo "[start] ${label} migrations are up to date."
}

migrate_with_retry "main database" migrate || true
migrate_with_retry "identity vault" migrate --database=identity_vault --path=database/migrations/identity_vault || true

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