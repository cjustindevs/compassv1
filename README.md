<p align="center">
  <img src="public/images/compass/logo-wordmark.png" alt="COMPASS" width="280">
</p>

# COMPASS

**Competency Oversight, Monitoring, Peer Assistance, Support, and Supervision**

COMPASS is a web platform for supervised student peer support, developed for **Project Dial-A-Friend at Divine Word College of Calapan**. It connects students seeking support with trained peer Helpers and gives Advisers, Moderators, and Psychology Professionals tools to coordinate care, monitor service quality, and manage professional handoffs.

Students participate through pseudonymous profiles, text chat, and optional live voice calls. Support requests, screening responses, consent decisions, assignments, referrals, and emergency actions are recorded throughout the workflow. Clinical care and emergency intervention remain the responsibility of authorized professionals and emergency services.

[Features](#features) · [User roles](#user-roles) · [Workflows](#workflows) · [Local setup](#local-setup) · [Voice calls](#voice-calls) · [Deployment](#deployment) · [Documentation](#documentation)

## Features

- **Pseudonymous support requests:** guided consent, pre-session screening, concern descriptions, communication preferences, and request history.
- **Queue and assignment management:** prioritized requests, automatic matching, Moderator assignment, and visible Helper eligibility and availability.
- **Helper readiness and duty scheduling:** readiness checks, breathing exercises, workload limits, schedules, and assignment acceptance.
- **Text and voice sessions:** live messaging, WebRTC audio, microphone checks, mute controls, call status, and text chat during a call.
- **Adviser supervision:** screening reviews, session documentation, competency evaluations, Helper performance comparisons, and training follow-up.
- **Professional referrals:** recommendations, Adviser decisions, Seeker consent, Professional selection, appointments, and completion reviews.
- **Emergency coordination:** alerts from pre-session screening or ongoing sessions, staff acknowledgment, operational coordination, escalation, and resolution history.
- **Identity and consent controls:** an encrypted Identity Vault, purpose-specific consent records, authorized identity release, and access auditing.
- **Dashboards and reporting:** role-specific KPIs, date filters, queue performance metrics, reports, and exports.
- **Notifications and resources:** action prompts, a notification inbox, self-help resources, and responsive pages with PWA support.

## User roles

| Role | Main responsibilities |
| --- | --- |
| **Help Seeker** | Request support, choose chat or voice, participate in sessions, review referrals, manage consent, and access resources. |
| **Helper** | Complete readiness checks, accept assignments, provide peer support, document sessions, and recommend referrals or report emergencies. |
| **Moderator** | Coordinate the waiting queue, assign eligible Helpers, manage schedules and workload, and monitor emergency operations. |
| **Adviser** | Supervise Helpers, review screening and referrals, coordinate Professionals, evaluate competency, and review case completion. |
| **Psychology Professional** | Review assigned referrals, schedule appointments, document professional support, and submit completion reports to the Adviser. |
| **System Administrator** | Manage accounts, roles, system settings, audit records, and administrative reports. |

Access is scoped by role and relationship to a case. Moderator coordination does not grant access to private session transcripts.

## Workflows

### Peer support session

1. The Seeker completes consent and pre-session screening and describes their concern.
2. Staff review occurs where required. The original screening responses remain recorded.
3. The Seeker selects communication and language preferences and submits the support request.
4. Matching considers request priority, waiting time, Helper competency, readiness, duty, availability, and capacity. Moderators can assign an eligible Helper through the same eligibility checks.
5. The Helper explicitly accepts the assignment and starts the session.
6. The Seeker and Helper communicate through text chat or a voice session with text messages available alongside it.
7. Session completion leads to Helper documentation and Seeker evaluation, with Adviser review and competency monitoring as applicable.

### Professional referral

1. The Helper submits a referral recommendation from the session.
2. The Adviser approves, requests revision, or rejects the recommendation with a recorded decision.
3. After approval, the Seeker agrees to or declines the referral.
4. Identity disclosure and contact submission are separate from referral consent. Authorized release determines which identity fields an assigned Professional may access.
5. The Adviser selects a Professional. The Professional accepts and schedules support, or declines for further coordination.
6. The Professional submits an outcome and any follow-up requirements for Adviser review.
7. The Adviser confirms referral completion or requests follow-up. A linked emergency requires its own resolution decision.

### Emergency coordination

An emergency may be identified during pre-session screening or reported during a live session. The system presents crisis resources and creates staff alerts. Responsible staff acknowledge the case, document actions, coordinate support, and arrange a professional handoff when appropriate.

Pre-session emergency coordination can continue alongside prioritized peer-support queuing. During an ongoing session, the existing Helper remains part of the support process. Moderators can coordinate an eligible Helper for cases awaiting assignment. Resolving an emergency updates active case lists and alerts while preserving its history.

## Technology stack

| Area | Technology |
| --- | --- |
| Backend | PHP 8.2+, Laravel 12, Composer |
| Interface | Blade, JavaScript, Alpine.js, Tailwind CSS, shared CSS components |
| Frontend build | Vite, Node.js, npm |
| Database | PostgreSQL; the deployed application uses Neon |
| Live updates | Laravel Reverb, Laravel Echo, authenticated polling fallbacks |
| Voice | Browser WebRTC and Metered TURN |
| Charts and documents | Chart.js and Laravel Dompdf |
| Email | Resend integration and Laravel mail configuration |
| Deployment | Docker, Nginx, PHP-FPM, Render |
| Testing | PHPUnit, Laravel feature tests, Node.js test suites |

## Local setup

### Requirements

- PHP **8.2 or newer**, with Laravel's required extensions and the PostgreSQL PDO driver. SQLite support is also needed for the automated tests.
- Composer 2.
- Node.js **22.12 or newer** and npm, matching the Vite requirements in the lockfile.
- PostgreSQL and local databases/users for the application and Identity Vault.

The commands below assume a **new local installation**. Existing installations must retain their application and vault encryption keys so previously encrypted records remain readable.

### 1. Install dependencies

```sh
git clone --branch deploy https://github.com/cjustindevs/compassv1.git
cd compassv1
composer install
npm ci
php -r "file_exists('.env') || copy('.env.example', '.env');"
```

### 2. Configure the local environment

Create the PostgreSQL databases and users, then update `.env` to match your local configuration. For example:

```dotenv
APP_NAME=COMPASS
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=compass
DB_USERNAME=compass
DB_PASSWORD=replace-with-local-password

DB_IDENTITY_CONNECTION=pgsql
DB_IDENTITY_HOST=127.0.0.1
DB_IDENTITY_PORT=5432
DB_IDENTITY_DATABASE=compass_identity_vault
DB_IDENTITY_USERNAME=compass_identity_user
DB_IDENTITY_PASSWORD=replace-with-local-vault-password

SESSION_DRIVER=database
CACHE_STORE=file
QUEUE_CONNECTION=sync
BROADCAST_CONNECTION=log
VOICE_CALLS_ENABLED=false
```

The Identity Vault has a dedicated connection and encryption key. It can use a separate PostgreSQL database, as shown above. Deployments that share a database still run the vault's separate migrations for its `idv_*` tables.

Generate the application key and a separate vault key:

```sh
php artisan key:generate
php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"
```

Save the second command's output as `IDENTITY_VAULT_ENCRYPTION_KEY` in `.env`. Keep environment files, private keys, and database credentials out of Git.

For email verification and staff mail, configure Resend using `RESEND_API_KEY` and an authorized `MAIL_FROM_ADDRESS`. For a local demonstration without sending verification email, `OTP_DEMO_MODE=true` is supported only in local/testing environments. See [email and OTP setup](docs/OTP_HOSTING.md).

### 3. Create the schema and build assets

```sh
php artisan migrate
php artisan migrate --database=identity_vault --path=database/migrations/identity_vault
php artisan storage:link
npm run build
```

For a **fresh local demonstration database**, sample accounts and content can be created with:

```sh
php artisan db:seed
```

Review the local sample accounts in [`DatabaseSeeder.php`](database/seeders/DatabaseSeeder.php) and the role-specific seeders. Seeding is separate from updating an existing installation. The [historical demo import](docs/DEFENSE_DEMO_DATA.md) is an additional, explicitly enabled workflow for an existing demonstration database.

### 4. Run the application

Start the web server:

```sh
php artisan serve --host=127.0.0.1 --port=8000
```

In another terminal, run the scheduler:

```sh
php artisan schedule:work
```

Open **http://127.0.0.1:8000**. The scheduler handles automatic matching, expiry, reminders, and workflow maintenance. When editing frontend assets, run `npm run dev` in another terminal.

The local configuration above uses polling for updates. Follow the voice setup guide to enable Reverb and audio calls.

## Voice calls

Voice calls require explicit enablement, voice-participation consent, microphone access, and a ready TURN configuration. See [Voice call setup](docs/VOICE_CALL_SETUP.md) for the complete Reverb and Metered settings.

- Voice sessions have a dedicated call interface with an in-page Messages drawer.
- **Check microphone** tests access and releases the microphone after the check.
- **Call** and **Accept call** request microphone access directly from a user action.
- **Connection details** shows microphone, session service, signaling, and audio connection status.
- **Leave call** keeps the peer-support session and text messages available. **End session** completes the support session.
- The application does not record audio or automatically transcribe calls.

Check server readiness with:

```sh
php artisan voice:status
```

Use HTTPS for deployed calls; localhost is suitable for local microphone testing. For a live test, use separate Seeker and Helper accounts on a phone and laptop, keep both session pages open, and verify audio in both directions. See the [voice and notification acceptance checklist](docs/VOICE_ROOM_NOTIFICATION_QA.md).

## Deployment

The repository includes a [Dockerfile](Dockerfile), [Render Blueprint](render.yaml), and [container startup script](docker/start.sh). The Blueprint targets the **`deploy` branch**. GitHub's default `main` branch may show a different revision; select `deploy` to view deployment updates.

For the existing Render and Neon setup:

1. Connect the Render web service to this repository and the `deploy` branch, using the Docker runtime.
2. Set the application URL, main database credentials, vault connection, encryption keys, and mail configuration in Render Environment.
3. Enable the scheduler with `RUN_SCHEDULER=true`.
4. For voice, apply the current [voice deployment settings](docs/VOICE_CALL_SETUP.md), including Reverb and Metered credentials. Public WebSockets use the current Render hostname; internal Reverb runs on port 8080.
5. Deploy and review the build/startup logs. Startup applies the application and Identity Vault migrations and prepares voice credentials when Reverb is enabled.

Preserve existing data and encryption keys during updates. Deployment uses forward migrations; resetting or reseeding the online database is not part of the update process. The Blueprint's demo flags and database provisioning should be reviewed when connecting an existing Neon database.

## Validation

The PHP test configuration uses an in-memory SQLite database. Run checks with the repository's test configuration rather than online database credentials:

```sh
php artisan test --compact
node tests/js/voice-call.test.mjs
node tests/js/voice-call-bootstrap.test.cjs
npm run build
php artisan view:cache
```

Additional JavaScript suites are in [`tests/js`](tests/js) and can be run directly with Node in the same way. Tests cover workflow authorization, queue eligibility, consent, referrals, emergency coordination, voice lifecycle, notification behavior, and client-side controls.

Automated tests complement live checks for email delivery, browser permissions, microphone capture, and two-device audio.

## Code navigation

| Path | Purpose |
| --- | --- |
| [`app/Http/Controllers`](app/Http/Controllers) | HTTP actions and role-specific application pages. |
| [`app/Services`](app/Services) | Queue matching, readiness, consent, identity, referrals, emergencies, analytics, and voice logic. |
| [`app/Models`](app/Models) | Application records and relationships. |
| [`app/Policies`](app/Policies) | Record ownership and role authorization. |
| [`app/Console/Commands`](app/Console/Commands) | Maintenance and diagnostic commands. |
| [`routes`](routes) | Web/API routes, private broadcast channels, and scheduled tasks. |
| [`resources/views`](resources/views) | Blade pages, layouts, shared controls, and dialogs. |
| [`resources/js`](resources/js) | Chat, voice, dashboards, and other browser behavior. |
| [`database/migrations`](database/migrations) | Application schema and separate Identity Vault migrations. |
| [`tests`](tests) | PHP and JavaScript regression coverage. |
| [`docs`](docs) | Setup guides, implementation notes, and workflow validation checklists. |

## Documentation

- [Documentation index](docs/INDEX.md)
- [Code-navigation reviewer](docs/DEFENSE_CODE_REVIEWER.md)
- [Queue, assignment, and escalation validation](docs/OCTOBER_19_QUEUE_ESCALATION_QA.md)
- [Professional completion and Adviser review](docs/REFERRAL_COMPLETION_QA.md)
- [Emergency resolution synchronization](docs/EMERGENCY_RESOLUTION_SYNC_QA.md)
- [Adviser competency comparison](docs/ADVISER_COMPETENCY_COMPARISON_QA.md)
- [Voice call setup](docs/VOICE_CALL_SETUP.md)
- [Voice room, notifications, and browser checks](docs/VOICE_ROOM_NOTIFICATION_QA.md)
- [Email and OTP configuration](docs/OTP_HOSTING.md)

Implementation reports in `docs/` describe dated checkpoints. Use the current source and the relevant recent acceptance checklist when validating behavior.
