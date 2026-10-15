# COMPASS — Render.com Free Tier Deployment

This document describes how COMPASS (Laravel 12 + PHP 8.3 + MySQL) is packaged
as a Docker web service for Render.com's free tier, what was changed to make it
MySQL-compatible, and the exact dashboard steps to deploy.

> **Honesty note:** as of writing, the image has **not** been built or run inside
> a container (no Docker engine was available on the dev machine). Static checks,
> the 306-test suite (SQLite) and asset builds passed. Treat everything local as
> "config reviewed + unit-tested", and treat anything green on Render after the
> first deploy as the real acceptance test.

---

## 1. Files created / modified

| File | Purpose |
| --- | --- |
| `Dockerfile` | Multi-stage: Node 22 build → PHP 8.3-FPM (Debian) + Nginx runtime. |
| `docker/nginx.conf` | Nginx server block; listens on `${PORT}` (default 10000), serves `public/`, PHP-FPM at `127.0.0.1:9000`, 10M body limit, hidden-file deny, baseline headers. |
| `docker/start.sh` | Entrypoint: validates env, creates writable dirs, `config:cache` + `view:cache`, **idempotently runs DB migrations on boot (main + identity vault, with retries)**, renders Nginx conf via `envsubst`, starts PHP-FPM (background) + Nginx (foreground), optionally the Laravel scheduler. |
| `.dockerignore` | Excludes `.git`, `.env*` (keeps `.env.example`), `vendor`, `node_modules`, `public/build`, tests, logs, SQLite files/dumps, `public/uploads` (sensitive). |
| `.env.render.example` | Full production environment template (no secrets). Copy into Render Dashboard. |
| `render.yaml` | Render Blueprint: Docker web service, `free` plan, `singapore` region, `healthCheckPath: /up`, `sync:false` secrets. |
| `bootstrap/app.php` | Added `trustProxies(at: '*', ...)` so Render's HTTPS proxy yields correct `https` URLs and secure cookies. |
| `database/migrations/…` (6 files) | **MySQL compatibility fixes** — see below. |

### MySQL migration fixes (important)
COMPASS had been developed/tested on PostgreSQL and SQLite. Six migrations used
Postgres-only SQL that would fail on MySQL:

- `2026_08_12_140000_extend_counseling_sessions_session_status.php`
- `2026_08_18_000001_add_evaluated_to_counseling_sessions_session_status.php`
- `2026_08_19_000001_add_in_progress_to_referrals_status_and_decline_reason.php`
- `2026_09_02_000003_add_escalation_lifecycle_fields.php`
- `2026_09_22_000001_add_consent_requested_status_make_referral_reason_nullable_and_index.php`

Each used `ALTER TABLE ... DROP CONSTRAINT IF EXISTS ... ADD CONSTRAINT ... CHECK`.
On MySQL those now use the same `$table->enum(...)->change()` widening that SQLite
already used (native MySQL `ENUM`). PostgreSQL behavior is unchanged.

- `2026_09_18_000001_add_adviser_assignment_history.php`

Used a **partial unique index** (`WHERE ended_at IS NULL`) which MySQL does not
support. It now creates a functional unique index
`(helper_id, COALESCE(ended_at, '1970-01-01 00:00:00'))` on MySQL/MariaDB 8.0.13+,
preserving "one active assignment per helper". **If** your MySQL provider reports a
syntax error on that line, open EDB on this migration and swap in a generated-column
unique index — see "Troubleshooting".

`2026_09_04_000003_sync_status_constraints.php` already had a MySQL branch and is
unchanged.

---

## 2. How COMPASS behaves on the free tier

| Concern | Free-tier reality | COMPASS setting |
| --- | --- | --- |
| Ephemeral filesystem | Any `/storage`, `/uploads` writes are lost on deploy/sleep-wake. | Sessions, cache, queue state live in the **database** (not files). Avatars are the only file uploads → **they are not persistent**. Docs below. |
| Sleeping service | Free web services sleep after ~15 min idle; first request is slow (~30–60 s). | Health check `/up` wakes it on traffic. No persistent disk while asleep. |
| No separate background workers on free | You cannot create a Render Background (Worker) service on free. | `QUEUE_CONNECTION=sync` (no jobs to process anyway). Scheduler runs **inside** the web container (`RUN_SCHEDULER=true`) so matching/session-expiry keep running. |
| Empty DB on first deploy | The Blueprint creates a brand-new Postgres with no tables. | `start.sh` runs `php artisan migrate --force --step` (main) and `--database=identity_vault --path=database/migrations/identity_vault` on boot, with retries. No manual step needed. |
| Memory | 512 MB | `memory_limit=192M`, only php-fpm + nginx + scheduler. Reverb is NOT started (see §6). |

---

## 3. Render dashboard steps

1. **Push the deployment branch** (after you approve the Git step) — e.g. branch `deploy`.
2. **Link GitHub**: Render Dashboard → **New → Blueprint** → connect the `justindevz/compassDemo` repo → select branch `deploy`. Render reads `render.yaml`.
3. If you prefer manual: **New → Web Service** → pick the repo/branch → **Runtime: Docker** → Root Directory `/` (Dockerfile is at repo root).
4. Under **Environment**, add every variable from `.env.render.example` (none of the `sync:false` ones are stored in Git):
   - `APP_KEY` — generate: `php artisan key:generate --show` (run locally before the DB is reachable).
   - `APP_URL` — your URL, e.g. `https://compass-deploy.onrender.com` (no trailing slash).
   - `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — from your external MySQL provider (§5).
   - `DB_IDENTITY_HOST`, `DB_IDENTITY_DATABASE`, `DB_IDENTITY_USERNAME`, `DB_IDENTITY_PASSWORD` — second DB on the same provider.
   - `IDENTITY_VAULT_ENCRYPTION_KEY` — generate: `php artisan tinker --execute="echo \Illuminate\Support\Str::random(32);"`
5. **Health**: keep `healthCheckPath: /up` (Laravel's built-in health route; returns 200 only when app + DB are up).
6. **Deploy** → watch **Build** logs (this is your first real `docker build`). Then **Live** logs.

---

## 4. Required environment variables (all)

See `.env.render.example` for inline comments. The critical set:

- App: `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`
- DB: `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT=5432`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SSLMODE=prefer`
- Identity vault: `DB_IDENTITY_CONNECTION=pgsql`, `DB_IDENTITY_*` (same host/database/user/password on the one free Postgres), `IDENTITY_VAULT_ENCRYPTION_KEY`, optional `IDENTITY_VAULT_RESPONDER_IDS`
- Runtime: `SESSION_DRIVER=database`, `SESSION_SECURE_COOKIE=true`, `CACHE_STORE=database`, `QUEUE_CONNECTION=sync`, `BROADCAST_CONNECTION=log` (demo) , `FILESYSTEM_DISK=local`, `LOG_CHANNEL=stderr`, `MAIL_MAILER=log`, `RUN_SCHEDULER=true`

---

## 5. Database on the free tier (Render managed PostgreSQL)

The Blueprint provisions ONE free managed PostgreSQL database (`compass-postgres`)
and auto-wires `DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD` (and the identical
`DB_IDENTITY_*`) via `fromDatabase`. There is no free MySQL on Render.

Two things to know:

- **One database only.** Render free Postgres exposes a single database and the
  grant user cannot `CREATE DATABASE`. The identity vault therefore reuses the
  same database/credentials and its dedicated migrations create the `idv_*`
  tables beside the main tables. If you deploy on your own Postgres you may
  point `DB_IDENTITY_*` at a truly separate database instead.
- **Free Postgres expires ~30 days after creation.** After that the DB is
  suspended and the site 500s again — either run the scheduler/backups onto a
  paid plan, or migrate the data out before the window closes. The two-row
  `compass` user is not a superuser, so `identity-vault:install` (which creates
  roles/databases) cannot run against Render's managed instance.

---

## 6. Realtime (Reverb / Echo) on the free tier

- Default: `BROADCAST_CONNECTION=log` — the app's chat, notifications and referral
  consent **poll** every few seconds (designed fallback), so the demo works without
  any WebSocket server. Nothing "silently dies"; it just isn't instant.
- To get true realtime you must run a Reverb WebSocket server. On the free tier this
  means supervising Reverb **inside** the same web container (a second process) with
  an extra port. That is explicitly **not** wired into the default image because it
  was not tested and it raises memory use. If you want it: add to `start.sh` a
  guarded block that runs `php artisan reverb:start` on `REVERB_SERVER_PORT=6001`,
  set the Reverb env vars to `wss` + your domain, and confirm Render exposes the
  port (free tier HTTP services proxy only the main `PORT` — so a second port
  normally requires a second Render service or passing `wss` through Nginx with
  `stream` — test before enabling). Until then, keep `log`.

---

## 7. Migrations (safe, repeatable)

These now run **automatically at container start** (`docker/start.sh`) with
retries, so a fresh deploy migrates both the main database and the identity
vault on first boot. No manual step is required for a normal deploy.

Manual commands (Render → your service → **Shell**) for reference:

```sh
# 1. Main database:
php artisan migrate --force --step

# 2. Identity vault (NOTE the --path -- its migrations live in a subfolder):
php artisan migrate --force --database=identity_vault --path=database/migrations/identity_vault --step

# 3. Inspect status:
php artisan migrate:status
php artisan migrate:status --database=identity_vault --path=database/migrations/identity_vault
```

Rules: never `migrate:fresh` / `db:wipe` on deployment; never seed production demo
data (seeders are for local only); keep backups off the server or in an S3 bucket.

---

## 8. Logs & troubleshooting

- **Logs**: Render → service → **Logs** (stdout/stderr). COMPASS uses
  `LOG_CHANNEL=stderr`, so `LOG_LEVEL=error` plus your exceptions appear there.
  No PII/transcript data is logged (session text is never written to logs).
- **HTTP 500**: set `APP_DEBUG=false` (never true) and inspect the Laravel log or
  `SHELL` → `tail -n 100 storage/logs/laravel.log`. Common: missing `.env` values,
  DB host not reachable (allow-list Render's egress IPs), or a migration not run.
- **Vite/asset 404 / unstyled page**: the Docker build runs `npm run build`
  (stage 1) and copies `public/build` + `public/sw.js`. If you see
  `Vite manifest not found`, the frontend build stage failed — check build logs.
- **WebSocket**: with `log` broadcaster nothing connects; with Reverb, check the
  WebSocket URL/host and that port 6001 is proxied.
- **Sleep wake**: free instance cold start takes ~30–60 s; the health check shows
  "degraded" during that window — not an error.
- **Avatar uploads lost**: expected — free tier has no persistent disk. Options:
  (a) accept for demo, (b) switch `FILESYSTEM_DISK` to an S3-compatible bucket
  (paid storage — outside free tier) and point `ProfileController` uploads there.

---

## 9. Validation checklist (run on Render after first deploy)

- [ ] Build succeeds (Build log green).
- [ ] `/up` returns 200; home page loads styled (Vite assets).
- [ ] Migrations applied on both DBs (Shell: `php artisan migrate:status`).
- [ ] Demo logins: seeker, helper, moderator, adviser, professional, admin (run
      the seeders only on a **disposable** DB if needed — never on live).
- [ ] Helper readiness → matching → queue assignment (scheduler `RUN_SCHEDULER=true`).
- [ ] Chat send/receive (polling fallback works; instant requires Reverb).
- [ ] Referral consent modal → accept/decline records; referral lifecycle.
- [ ] Emergency escalation flow & incident report.
- [ ] Adviser evaluation & supervision.
- [ ] Uploaded avatar persists on next request only (disappears after redeploy —
      free-tier limitation).