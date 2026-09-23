> **2026-09-17 user-requested restoration:** The Request Support UI now uses the previous short five-question form with concern/description on the same page, followed by preferences and matching. This supersedes the long-questionnaire default described below. The compact rules restore plan ? emergency; thoughts/severe distress ? high; recurring distress/coping difficulty ? moderate; otherwise low. Answers are stored under `compass-compact-restored-1`; unasked answers are not fabricated. High-risk adviser review and emergency separation remain. The longer instrument is retained for existing API clients and its own approval gate; ordinary compact submissions do not wait for that approval. Terms/Privacy now opens in an in-page modal. History, privacy and referral pages use the existing full-width green/Inter card structure, with active sidebar tabs and collapsed icons.

# Help Seeker implementation and verification ? 2026-09-16

This report supersedes earlier Help Seeker workflow claims in COMPASS_IMPLEMENTATION.md and CAPSTONE_DEBUG_REPORT.md. Inter and the existing green design are retained. Automated verification is complete for the changes described below; institutional screening approval and manual browser acceptance remain outstanding.

## 1. Implemented changes

Persisted consent, screening, concern, preferences, queue, helper acceptance, chat completion and categorical evaluation workflow. Added owner/role checks, adviser review, emergency separation, cancellation/history, consent withdrawal, backend time limits and minimum-field referral identity disclosure. GET navigation and polling no longer perform matching, expiry or abandonment. Voice is explicitly unavailable.

## 2. Root causes

The earlier flow mixed browser-session state with database state, treated sparse answers as complete screening, allowed page visits or first messages to advance sessions, reused numeric feedback options, and relied on loosely scoped shared endpoints. Queue urgency and clinical risk were conflated; old consent/version and helper acceptance evidence were missing. Different controllers completed or cancelled sessions inconsistently.

## 3?4. Added and modified files

The file inventory below includes earlier interrupted work already present in this checkout. Core additions are ConsentService, ScreeningInstrument, SeekerWorkflowService, EvaluationInstrument, OperatingHoursService, SupportAudit, SeekerRecordPolicy, SessionWorkflowObserver, EnsureActiveAccount, ScreeningReviewController, SeekerConsentController, config/screening.php, shared seeker-step and request/privacy/referral/review views, and SeekerWorkflowSecurityTest. Core modifications cover request/session/chat, helper acceptance, matching, referrals, Identity Vault, consent, queue, scheduling, route/channel authorization and regression fixtures.

## 5. Migration and legacy records

`2026_09_16_000001_harden_seeker_workflow.php` adds workflow/acceptance/submission/end/warning/review timestamps, versioned screening evidence, purpose-specific consent decisions, queue timestamps and urgency, categorical evaluation answers, audit metadata, emergency provenance, request_status_events and helper_conflicts. Helper-less referrals and unclassified risk are nullable. PostgreSQL retains existing risk constraints while dropping NOT NULL. The migration ran successfully on the local PostgreSQL database; no reset or reseed was performed in this continuation.

Existing records remain historical evidence. Old consent does not imply acceptance of the current document version. Old requests without submitted_at cannot match; no missing answers or acceptance timestamps are fabricated. Cancel/restart unfinished legacy requests after accepting current consent. Accounts with retained support/consent history cannot use self-service hard deletion; consent withdrawal remains available, with adviser review for closure. Do not roll this migration back on live data: down() removes new evidence columns/tables, and deliberately leaves relaxed risk/helper nullability and category additions. Back up and use a reviewed forward migration for production corrections.

## 6. Authorization and evidence

EnsureActiveAccount and role middleware reject inactive/wrong-role requests before route binding. SeekerRecordPolicy enforces owned sessions, queues, screening, messages, evaluations, referrals, incidents, emergency alerts, calls and consent; participation requires the assigned helper or owning seeker. Shared transcript access is denied to seekers. Referral consent is authenticated seeker-only; professional views require approval/consent and assignment. Identity Vault retains its separate database/key, controlled release and audit gateway. Normal release defaults to name/phone; advisers select necessary fields and a purpose, and recipients see only released fields.

Consent and AuditLog model updates/deletes are rejected. Status events retain actor, from/to state and time. This is application-enforced append-only behavior, not a claim of database-administrator-proof storage. Denied vault access is audited; general HTTP denial does not guarantee a dedicated audit event for every rejected request.

## 7. Workflow and route map

| Step | Routes / persisted state |
| --- | --- |
| Documents | `/seeker/consent`, privacy decisions; version `compass-v4-2026-09` |
| Screening | `/request/screening`; `concern_required`, `adviser_review_required` or `emergency_escalated` |
| Adviser review | `/adviser/screenings` and POST `/adviser/screenings/{session}`; original answers retained; unassigned reviews may be claimed atomically |
| Concern | `/request/concern`; `session_preferences_required` |
| Submission | `/request/preferences`; `ready_for_submission` ? `submitted` ? `queued` |
| Matching | scheduled matcher; `helper_pending_acceptance`; `/request/matching` is read-only |
| Acceptance | assigned helper action; `session_active`, helper_accepted_at and start_time |
| Completion | authorized end/expiry POST or scheduler; `evaluation_pending` ? `closed` after evaluation |
| History / withdrawal | `/seeker/requests`, `/seeker/privacy`, `/seeker/referrals`; cancellation/expiry retain evidence |

Emergency bypasses the ordinary queue and records adviser-notification outcome plus published resource IDs. No automatic identity disclosure. High risk requires adviser review and a referral recommendation; peer support requires explicit review approval. Clarification and reassessment append evidence rather than replacing answers. Waiting time changes operational urgency, not session risk. Matching uses clinical eligibility, readiness, schedule, account status, conflicts and ranked compatibility, with FIFO ties inside queue priority. Language compatibility is ranked, not a guarantee of a particular language.

Assignments/acceptance: Monday?Saturday, 18:00 to before 22:30 Asia/Manila; published shift ends 23:00. One active/reserved session and at most two started sessions per official daily shift. Counters derive from persisted sessions; old stored counters do not grant capacity. Sessions warn at 85 minutes, block late messages and close at 90. Polling reports the deadline; authorized POST or scheduler persists completion. Repeated completion is idempotent.

## 8?10. Tests, commands and results

- `php artisan test --compact`: **185 passed, 1,156 assertions**; isolated SQLite refresh/migrations, including separate test vault.
- New security tests exercise wrong-role/guest access across seeker routes, missing consent/steps, complete actual answers, emergency/high/uncertain/unapproved screening, read-only GETs, cancellation retention, cross-account IDs, helper acceptance/capacity, hours/cutoff, risk-preserving queue aging, consent withdrawal, categorical feedback, disabled voice, adviser reassessment, conflict/legacy matching exclusion and evidence deletion rejection.
- Existing helper, moderator/adviser, professional, referral, duration and vault tests updated for the documented flow. Vault tests also assert unreleased email is absent.
- `node --test tests/js/chat-timer.test.cjs`: **2 passed**.
- `npm run build`: **passed**, including service worker; `php artisan view:cache`: **passed**.
- `php artisan route:list --except-vendor --json`: generated successfully.
- `php artisan migrate --pretend --path=database/migrations/2026_09_16_000001_harden_seeker_workflow.php`: reviewed; PostgreSQL enum ALTER issue corrected before applying.
- `php artisan migrate --path=database/migrations/2026_09_16_000001_harden_seeker_workflow.php --force`: **passed** on local PostgreSQL.
- `php artisan compass:verify-schema`: **18 core tables/relationships verified**.
- `php scripts/verify-demo-logins.php`: actual local HTTP login/expected landing checks for six demo roles. Helper readiness is an expected landing when its previous assessment has expired.
- PHP syntax: **288 files passed**. `git diff --check`: **passed**.
- PHP emits an existing duplicate-openssl extension warning. This is an environment warning, not a failed application test.

## 11. Documentation conflicts and interpretation

The PDF provides a risk rubric, but not a complete approved questionnaire; proposed wording is versioned and automatic continuation is gated on institutional approval. Its conflicting consent timing is handled by requiring consent before support processing after pseudonymous account creation. Detailed emergency bypass takes precedence over generic queue diagrams. Voice participation, recording and transcription are distinct purposes and unavailable endpoints cannot create fake calls. Categorical experience answers are authoritative; evenly spaced 1?10 legacy report values are a reporting convention, not a clinical scale or a PDF-specified numeric instrument. A session accepted before cutoff retains its 90-minute maximum even if it extends past 23:00; the PDF's shift end versus maximum-duration ambiguity needs institutional confirmation.

## 12. Remaining limitations

- The proposed questionnaire must receive adviser and qualified professional approval. By default routine screening goes to adviser review, rather than silently classifying it as approved. Emergency indicators still show resources and record the alert.
- Emergency notification is in-app; no claim of delivered SMS/email, guaranteed response time or emergency-service dispatch. Resource publication is managed by staff; external phone validity was not independently verified in this pass.
- Live voice transport, recording and automatic transcription are unavailable. Chat is server-readable; this implementation does not claim end-to-end encryption or encrypted-at-rest chat transcripts.
- Conflict records are enforced, but a dedicated conflict-management UI is not included. Language matching is a preference ranking. No PostgreSQL multi-process race stress test or full browser accessibility audit was performed.
- No current two-browser websocket delivery or visual modal/layout acceptance result is claimed. The older verify-live-chat.mjs uses the old screening contract and must be updated before using it for this flow; do not treat an earlier run as current evidence.
- Staff must manage retention/closure requests; no automatic deletion of operational support history is introduced.

## 13. Manual acceptance checklist

1. Sign in as seeker; accept current documents; decline/withdraw and verify support processing stops. Confirm old records remain visible.
2. Complete every screening field. With approval unset, verify adviser review; review/clarify and continue. Check high-risk recommendation and emergency resources without ordinary matching.
3. Choose one of six concerns and chat/language. Submit, reload and verify one queue entry. With a scheduled, ready helper during service hours, verify automatic assignment, no chat before acceptance, then two-browser bidirectional messages after acceptance.
4. Verify another seeker cannot read/cancel/evaluate that session. End once, submit categorical feedback once, and verify retained history. Exercise 85/90-minute behavior in an isolated test environment, without altering production timestamps.
5. Review a referral as adviser; accept/decline as its seeker; release only selected identity fields; verify only the assigned professional can see them. Withdraw consent and check access is revoked.
6. At desktop and phone widths, inspect labels, focus order, validation, history, consent/referral screens and dialogs. Keep Inter/green. Confirm disabled voice is clearly explained.

## 14. Runtime / deployment

The local HTTP server was started hidden on `127.0.0.1:8000` for verification. Reverb was started on `127.0.0.1:8080`; the scheduler log confirmed expiry, matching and warning callbacks completed successfully. A queue worker was started because the local queue driver is `database`. These are development processes, not installed Windows services; restart after reboot. Logs are in `storage/logs/local-seeker-*.log`. For ongoing local work use separate terminals/process supervision for:

```text
php artisan serve --host=127.0.0.1 --port=8000
php artisan schedule:work
php artisan reverb:start --host=127.0.0.1 --port=8080
php artisan queue:work
```

Run queue workers when the configured queue driver is asynchronous. The scheduler is required for unattended matching, 85-minute warning, 90-minute completion and abandoned-request expiry. Request paths still validate acceptance, consent and deadlines. Production should invoke `php artisan schedule:run` every minute, supervise workers/Reverb, configure private vault connectivity and a separate persistent IDENTITY_VAULT_KEY, preserve key backups, configure secure transport and restart long-running processes after deployment. Do not generate a replacement vault key over existing encrypted data.

Only after actual questionnaire approval set `SCREENING_APPROVAL_REFERENCE` to the institutional approval reference and `SCREENING_APPROVED_VERSION=compass-v4-proposed-1` (or the reviewed instrument version if wording changes). Blank defaults in .env.example intentionally keep automatic continuation disabled. Refresh cached config after approved configuration changes. No approval was fabricated or written into local .env.

## File inventory

Added:

- `CAPSTONE_DEBUG_REPORT.md`
- `HELP_SEEKER_IMPLEMENTATION.md`
- `app/Http/Controllers/ScreeningReviewController.php`
- `app/Http/Controllers/SeekerConsentController.php`
- `app/Http/Middleware/EnsureActiveAccount.php`
- `app/Observers/SessionWorkflowObserver.php`
- `app/Policies/SeekerRecordPolicy.php`
- `app/Services/ConsentService.php`
- `app/Services/EvaluationInstrument.php`
- `app/Services/OperatingHoursService.php`
- `app/Services/ScreeningInstrument.php`
- `app/Services/SeekerWorkflowService.php`
- `app/Services/SupportAudit.php`
- `config/screening.php`
- `database/migrations/2026_09_16_000001_harden_seeker_workflow.php`
- `resources/views/adviser/screenings.blade.php`
- `resources/views/components/seeker-step.blade.php`
- `resources/views/request/concern.blade.php`
- `resources/views/request/history.blade.php`
- `resources/views/request/status.blade.php`
- `resources/views/seeker/privacy.blade.php`
- `resources/views/seeker/referrals.blade.php`
- `tests/Concerns/SeekerWorkflowFixtures.php`
- `tests/Feature/SeekerWorkflowSecurityTest.php`

Modified:

- `.env.example`
- `COMPASS_IMPLEMENTATION.md`
- `README.md`
- `app/Console/Commands/MarkAbandonedSessions.php`
- `app/Http/Controllers/Adviser/AdviserHelperController.php`
- `app/Http/Controllers/Adviser/AdviserReferralController.php`
- `app/Http/Controllers/Adviser/AdviserReportController.php`
- `app/Http/Controllers/Adviser/AdviserResourceController.php`
- `app/Http/Controllers/ChatController.php`
- `app/Http/Controllers/Helper/HelperCaseController.php`
- `app/Http/Controllers/Helper/HelperChatController.php`
- `app/Http/Controllers/Helper/HelperCompetencyController.php`
- `app/Http/Controllers/Helper/HelperSessionController.php`
- `app/Http/Controllers/IdentityVaultController.php`
- `app/Http/Controllers/Moderator/ModeratorQueueController.php`
- `app/Http/Controllers/Moderator/ModeratorReportController.php`
- `app/Http/Controllers/Professional/ProfessionalCaseController.php`
- `app/Http/Controllers/Professional/ProfessionalDashboardController.php`
- `app/Http/Controllers/Professional/ProfessionalProfileController.php`
- `app/Http/Controllers/Professional/ProfessionalReferralController.php`
- `app/Http/Controllers/Professional/ProfessionalReportController.php`
- `app/Http/Controllers/ProfileController.php`
- `app/Http/Controllers/ReferralWorkflowController.php`
- `app/Http/Controllers/RequestSupportController.php`
- `app/Http/Controllers/RiskClassificationController.php`
- `app/Http/Controllers/SeekerDashboardController.php`
- `app/Http/Controllers/SeekerOnboardingController.php`
- `app/Http/Controllers/SessionController.php`
- `app/Http/Controllers/SettingsController.php`
- `app/Http/Middleware/EnsureUserRole.php`
- `app/Models/AuditLog.php`
- `app/Models/ConsentRecord.php`
- `app/Models/HelpSeekerEvaluation.php`
- `app/Models/Helper.php`
- `app/Models/QueueRequest.php`
- `app/Models/Referral.php`
- `app/Models/ScreeningResponse.php`
- `app/Models/Session.php`
- `app/Models/User.php`
- `app/Providers/AppServiceProvider.php`
- `app/Services/ChatTranscriptionService.php`
- `app/Services/EmergencyEscalationService.php`
- `app/Services/HelperMatchingService.php`
- `app/Services/IdentityVaultService.php`
- `app/Services/IncidentReportService.php`
- `app/Services/QueueManagementService.php`
- `app/Services/ReferralManagementService.php`
- `app/Services/RiskClassificationService.php`
- `app/Services/SessionDurationService.php`
- `bootstrap/app.php`
- `database/seeders/TestUsersSeeder.php`
- `public/sw.js`
- `resources/js/chat.js`
- `resources/views/adviser/helper-detail.blade.php`
- `resources/views/adviser/referral-detail.blade.php`
- `resources/views/adviser/reports.blade.php`
- `resources/views/adviser/resources.blade.php`
- `resources/views/auth/seeker-consent.blade.php`
- `resources/views/emergency.blade.php`
- `resources/views/helper/competency.blade.php`
- `resources/views/helper/voice.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/partials/adviser-sidebar.blade.php`
- `resources/views/moderator/queue.blade.php`
- `resources/views/partials/sidebar.blade.php`
- `resources/views/request/preferences.blade.php`
- `resources/views/request/screening.blade.php`
- `resources/views/request/voice-consent.blade.php`
- `resources/views/session/evaluation.blade.php`
- `resources/views/session/identity.blade.php`
- `resources/views/session/voice.blade.php`
- `routes/channels.php`
- `routes/console.php`
- `routes/web.php`
- `scripts/verify-demo-logins.php`
- `tests/Feature/CompassImplementationTest.php`
- `tests/Feature/EscalationWorkflowTest.php`
- `tests/Feature/HelpSeekerModuleTest.php`
- `tests/Feature/HelperModuleTest.php`
- `tests/Feature/IdentityVaultTest.php`
- `tests/Feature/ModeratorAdviserModuleTest.php`
- `tests/Feature/ProfessionalModuleTest.php`
- `tests/Feature/SessionDurationTest.php`
