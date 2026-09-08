# COMPASS implementation

## Setup verified on 2026-09-09

Final verification: **153 PHP tests passed** (677 assertions in the full run), with the final profile-routing change also passing its focused setup tests. **Two JavaScript countdown tests passed**, the production asset build passed, Blade templates compiled, and `git diff --check` passed. The existing duplicate PHP OpenSSL-extension warning is non-fatal; it is emitted by this machine's PHP configuration.

- The requested `migrate:fresh` reset was run on **compass_db**, followed by `TestUsersSeeder`. Previous operational records were replaced by the demo setup. The vault was migrated separately without resetting its data.
- Both PostgreSQL connections work. The local vault database is named **compassIdentityVault**, as configured in the updated `.env`. `app('identity.vault')->isAvailable()` checks connectivity, schema, and the dedicated key without opening identity records.
- `identity-vault:verify` passed against PostgreSQL: encrypted storage round-trip and audit writes succeeded, and the synthetic identity was removed. `compass:verify-schema` verified 18 operational tables and their foreign keys.
- All 289 PHP files present at the initial validation passed syntax checks; Blade templates compiled. JavaScript syntax checks and Composer validation passed. Composer reports an advisory style warning for the pinned CommonMark version.
- All six accounts below passed real HTTP login and dashboard checks against the local PostgreSQL app using `php scripts/verify-demo-logins.php`. These checks exercise sessions and CSRF, but do not replace a visual browser check.
- `node scripts/verify-live-chat.mjs` passed against the running app and Reverb with two independent authenticated clients. It exercised screening, matching, helper acceptance, bidirectional websocket delivery, helper aliases, the server countdown, and session-end broadcasting. One labelled demo session and its two messages remain in the operational database as verification data.
- The local app (`127.0.0.1:8000`), Reverb (`127.0.0.1:8080`), and scheduler were started as hidden background processes and verified running. Output is in `storage/logs/local-*-output.log`. These processes must be restarted after a reboot; they are not installed Windows services.

| Role | Login | Demo password |
| --- | --- | --- |
| Admin | admin@compass.local | Admin@123 |
| Adviser | maria.santos@compass.local | Adviser@123 |
| Helper | rina@compass.local | Helper@123 |
| Seeker | SilentWillow52 or silentwillow52@compass.local | Seeker@123 |
| Moderator | moderator@compass.local | Moderator@123 |
| Professional | anna.cruz@compass.local | Professional@123 |

`TestUsersSeeder` only runs in local/testing environments. It is repeatable and resets these demo credentials, today's helper schedule, and a four-hour readiness check. Helpers must renew readiness and schedules after they expire.

## Identity vault and authorization

The provider is registered in `bootstrap/providers.php` (Laravel 12). It resolves lazily: registration, login, matching, and ordinary chat do not connect to the vault. The `User` model remains an authenticatable model, and relationships retain the existing `user_account_id` and `seeker_id` foreign keys.

The separate database contains `idv_identities`, `idv_access_logs`, and `idv_release_records`. There are no cross-database foreign keys. Identity fields are encrypted with AES-256-CBC using `IDENTITY_VAULT_ENCRYPTION_KEY`; access IPs, user agents, and free-text audit/release notes are encrypted too. Never replace this key after storing data without a controlled re-encryption procedure. Back up the key securely with the encrypted database.

Referral flow: helper recommends → assigned adviser approves → authenticated seeker consents → seeker submits encrypted identity → assigned adviser authorizes release → assigned professional opens and acknowledges receipt. The adviser release screen never displays identity fields. Each replacement identity receives a new version and requires a new release; earlier authorizations cannot read its replacement. Closed, declined, completed, expired, unapproved, or unconsented referrals cannot be used to read identity.

Admin, helper, moderator, unrelated adviser, and unassigned professional requests are denied. Caller-supplied actor IDs and a string such as `system` do not bypass authorization. The model convenience methods require the actual referral and still use the authenticated actor. Use `identity-vault:verify` for a console storage test instead of unrestricted identity reads from Tinker.

Life-threatening emergency access requires an active emergency alert and either a dedicated responder role or a professional explicitly listed in `IDENTITY_VAULT_RESPONDER_IDS`. No accounts are designated automatically. A justification is required; access releases only essential contact fields, records the consent override, and creates a release record awaiting adviser review. Automatic emergency escalation only flags and routes the case; it does not expose identity to the person triggering the alert. If identity was never collected, there is no identity to retrieve.

Access attempts, denials, successful operations, and failures are audited. The service fails closed if audit storage or decryption fails and returns a generic error without SQL bindings or identity details. Identity forms do not flash submitted values into operational sessions; identity responses use `Cache-Control: no-store, private`.

Expiry is enforced on every read. `identity-vault:purge-expired` erases identity fields after 365 days and clears associated sensitive audit notes while retaining event metadata. The daily scheduler runs this command.

This implementation isolates **seeker identity**. Staff profile names/emails remain in the operational database, as required by the supplied demo accounts. The updated local `.env` uses `compass_user` for both database connections: application access is controlled, but these credentials do not provide independent database-role isolation. Separate database credentials and restricted network access are needed for that additional deployment boundary.

The retired operational `identity_vault` table remains empty as a migration bridge; its application model and relationship were removed. For installations with legacy seeker names/emails, `identity-vault:migrate-legacy` encrypts and verifies source values before scrubbing operational identity fields. Conflicts or unavailable audit storage stop migration and retain unverified source data. The local fresh setup had zero legacy records to migrate. Old email/OTP registration endpoints return 410; seeker account settings cannot write a real name/email back into operational storage.

```sh
php artisan migrate --database=identity_vault --path=database/migrations/identity_vault
php artisan identity-vault:verify
php artisan identity-vault:migrate-legacy
php artisan identity-vault:purge-expired
```

For a new local PostgreSQL installation, `identity-vault:install --admin-user=postgres` can provision the default separate vault role/database. It prompts for the administrator password in the terminal without storing it. An already reachable configured vault is preserved.

The implementation extends the existing Laravel application and preserves its database naming and established role modules.

## Database compatibility

| Specification concept | Existing implementation |
| --- | --- |
| Support sessions | `counseling_sessions`, `App\Models\Session` |
| User profile ownership | `user_account_id` foreign keys |
| Competency evaluations | `helper_competency_history`, `HelperCompetencyHistory` |
| Seeker feedback | `help_seeker_evaluations`, `HelpSeekerEvaluation` |
| Documentation and reflection | `session_reports`, `SessionReport` |
| Preferred language | `users.preferred_language` |

Three additive migrations introduce registration metadata, consent audit metadata, and screening completion state. `php artisan compass:verify-schema` checks the core tables and foreign keys without changing data.

## User flows

- `/register`: age, gender, language, strong password, and generated sign-in alias. Consent is recorded separately at `/seeker/consent` before screening. Existing email accounts continue to sign in.
- Screening requires the five core answers. Concern description is optional except for Other. Risk classifications are omitted from seeker request pages. Emergency screening routes to resources without entering the queue.
- New requests accept chat only. Matching requires availability, current readiness, a current schedule, sufficient competency, and capacity. Pending assignments reserve capacity. Ranking retains the specified 30/20/15/15/10/10 weights.
- Chat messages are stored even if broadcasting fails. Server checks and the scheduled expiry job enforce the 90-minute limit.
- Seeker feedback requires an ended, owned session and five 1–10 ratings. Duplicate feedback is rejected.
- Helper notes require summary, observations, actions, and reflection. Referral recommendations first go to the assigned adviser. After approval, the seeker reviews consent and provides identity through a separate form; the helper sees a status prompt. Polling supports the prompt when websockets are unavailable.
- Adviser reports use scoped data, validated date filters, and a risk distribution display. Export defaults to PDF with logo, header, institution footer, and page numbers. `format=csv` retains CSV export.

## API authentication

`POST /api/login` accepts `login` (email or generated alias) and `password`, returning a signed one-hour JWT. Send it as `Authorization: Bearer TOKEN` to `/api/user`, `/api/sessions`, `/api/messages`, and `/api/sessions/{id}/messages`. Session lists are restricted to the authenticated seeker or helper and omit risk classification. Existing Blade routes retain Laravel session authentication and CSRF protection.

## Local operation and verification

```sh
php artisan migrate
php artisan compass:verify-schema
php artisan serve
php artisan schedule:work
php artisan reverb:start
```

Open chat rooms display a server-based countdown and check session status every five seconds, even when websockets are connected. Reaching zero locks messaging and requests server confirmation; an expired session redirects the seeker to feedback and the helper to notes. Message polling, sending, ending, and documentation also enforce expiry. The saved end time is capped at start time plus 90 minutes, and repeated end requests preserve it.

Run the scheduler in a separate process to close sessions while both participants have closed their browsers or lost connectivity. An open, connected chat room does not require the scheduler to enforce the limit. Reverb supports live chat; polling remains available. PHP GD is required for the PNG logo in PDF exports; it was enabled in this machine's XAMPP PHP configuration.

```sh
php artisan test
npm run build
composer audit --locked
```

The test configuration uses an isolated configuration-cache path so a cached local configuration does not override the test database. Automated coverage includes registration/consent, screening privacy, matching capacity, referral ownership, expiry, feedback constraints, JWT scoping, PDF rendering, vault encryption, role restrictions, unavailable auditing, legacy migration, identity replacement, emergency authorization, and retention. Two-client live Reverb communication was verified through the local smoke test; visual interaction in two browser windows has not been manually checked.
