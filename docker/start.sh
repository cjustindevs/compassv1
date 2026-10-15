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
# Reverb is optional and runs in this same container when RUN_REVERB=true.
# Voice uses immediate signaling events, so it does not require a queue worker.
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
# Retry transient failures, but do not serve new code against an outdated main schema.
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

migrate_with_retry "main database" migrate
migrate_with_retry "identity vault" migrate --database=identity_vault --path=database/migrations/identity_vault || true

# --- Optional demo seeding (guarded: requested AND demo accounts missing) -----
# A freshly provisioned database has NO accounts, so the documented demo logins
# (admin@example.com / password, seeker|helper|adviser|professional@compass.edu.ph
# / password123, plus named @compass.local accounts such as rina@compass.local)
# do not exist until seeded. Setting RUN_SEED_DEMO=true seeds them once; once the
# demo admin, base seeker AND a @compass.local account exist the seeder is
# skipped on later boots, so it never duplicates or wipes data.
seed_demo_if_empty() {
    if [ "${RUN_SEED_DEMO:-false}" != "true" ]; then
        return 0
    fi
    echo "[start] RUN_SEED_DEMO=true -- checking whether demo accounts exist."
    demo_count="$(php artisan tinker --execute="echo App\\Models\\User::whereIn('email', ['admin@example.com', 'seeker@compass.edu.ph', 'rina@compass.local'])->count();" 2>/dev/null | tr -cd '0-9' | tail -c 8)"
    if [ -z "${demo_count}" ] || [ "${demo_count}" -lt 3 ]; then
        echo "[start] some demo accounts missing (${demo_count:-0} of 3) -- seeding demo data."
        php artisan db:seed --force --no-interaction \
            && echo "[start] Demo data seeded." \
            || echo "[start] ERROR: demo seeding failed -- inspect logs above."
    else
        echo "[start] Demo accounts already present -- skipping seeder."
    fi
}

seed_demo_if_empty

# Explicitly enabled, additive historical batch for the October 16 defense.
# The command records its manifest transactionally and skips repeat deployments.
# It never creates live queues or sends email/notifications.
if [ "${RUN_DEFENSE_DEMO:-false}" = "true" ]; then
    echo "[start] Adding one-time defense history (existing records preserved)."
    php artisan compass:seed-defense --apply --no-interaction \
        || echo "[start] Defense import failed; existing records preserved and normal startup continues."
fi

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
    # Keep matching failures visible in Render logs, and restart the scheduler
    # if it exits while the web service is still running.
    (
        while true; do
            php artisan schedule:work || echo "[start] Scheduler failed; inspect the error above."
            echo "[start] Scheduler exited; restarting in 5 seconds."
            sleep 5
        done
    ) &
else
    echo "[start] RUN_SCHEDULER=false -- Laravel scheduler is disabled."
fi

# --- Optional Reverb process (same free Render service) ------------------
if [ "${RUN_REVERB:-false}" = "true" ]; then
    echo "[start] Starting Reverb on internal port 8080."
    (
        while true; do
            php artisan reverb:start --host=0.0.0.0 --port=8080 || echo "[start] Reverb stopped."
            sleep 5
        done
    ) &
    php artisan voice:prepare || echo "[start] Voice relay is not ready; text chat remains available."
fi

# --- PHP-FPM (background) + Nginx (foreground) ----------------------------
echo "[start] Starting PHP-FPM."
php-fpm -D

echo "[start] Listening on port ${PORT}."
exec nginx -g 'daemon off;'
