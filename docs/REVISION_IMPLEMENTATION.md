# Moderator, screening, referral and scheduling revision

Status: implementation and automated verification complete for the changes described below, and committed to the `deploy` branch. Full document/form compliance and browser verification remain pending.

## Scope and sources

Preserved existing Laravel/Blade architecture, Inter/green styling, accounts, operational data and separate Identity Vault. Reviewed the extracted COMPASS-v4 referral workflow (PDF page 119), emergency workflow (120), Appendix O (263?270), and existing consent/privacy implementation. The public landing testimonials and resource section were removed; authenticated seeker self-help remains at `/selfhelp`.

No AGENTS.md was found during inspection. Prior OTP changes and unrelated missing/permission-denied PDF tooling files were left alone.

## Root causes and changes

- Moderator Remove supplied its ID in the URL while the controller required a body field. It also cancelled started sessions without checking their state. `ModeratorQueueRemoval` now locks the queue/session, rejects terminal/started cases, cancels a waiting request, or returns an unaccepted assignment to waiting, preserves records and audit provenance, releases helper capacity and notifies participants.
- Queue options used global helper eligibility rather than the linked session. They now use the canonical queue/session relationship, per-request eligibility and existing specialty/language/competency ranking. Removed waiting-list priority control, appointment input and eligibility override; backend rejects forged override fields. Matching still requires helper acceptance before activation. Dashboard cached counts are invalidated after assignment/removal.
- Moderator dashboard already had no Recent Messages widget/query in this checkout. Existing operational Recent Activity is retained.
- Emergency screening did not assign a screening reviewer and the review list/submission guard excluded emergency workflow states. Emergency cases now receive a reviewer, deep-linked notification, review visibility and submission support. Original answers and reassessment records remain separate. Review notifies the seeker, updates waiting priority when relevant and blocks duplicate preliminary reviews. Emergency resources remain available independently.
- Referral paths conflicted: one requested consent before review. Recommendations now reach the assigned adviser first; existing legacy consent-requested records remain reviewable. Consent cannot be submitted before approval. Notification broadcasts are scoped to the assigned adviser.
- Consent previously immediately forwarded the referral. Professional assignment now requires a committed current vault submission. Approval, referral consent, identity disclosure confirmation, identity storage and professional assignment are distinct steps. Decline closes the ordinary referral and links to authenticated self-help. Emergency history persists independently.
- Referral consent opens the identity modal from the seeker referral page. Identity fields use manual validation without flashing PII into the operational session. Disclosure consent is appended to consent history. The existing encrypted vault gateway stores fields. Vault commit precedes coordination; forwarding locks the referral and skips duplicate assignment notifications on retry.
- Added professional appointment creation/rescheduling, encrypted instructions, Manila display times, future/end-time validation, conflict prevention and role/consent checks. A professional-row lock serializes competing calendar writes. Rescheduling retains the previous row. Seeker, adviser and professional receive generic notifications. Seeker notification links open a details modal; adviser and professional details show appointment history.

## Files added

- `app/Services/ModeratorQueueRemoval.php`
- `app/Services/ReferralAppointmentService.php`
- `app/Models/ReferralAppointment.php`
- `database/migrations/2026_09_26_000001_add_referral_appointments.php`
- `resources/views/partials/referral-appointments.blade.php`
- `resources/views/partials/referral-identity-modal.blade.php`
- `tests/Feature/ModeratorQueueRemovalTest.php`
- `tests/Feature/ReferralAppointmentTest.php`
- This report.

## Existing files connected

Controllers: ModeratorQueueController, ScreeningReviewController, IdentityVaultController, ReferralWorkflowController, HelperSessionController, AdviserReferralController, ProfessionalReferralController.
Services/models: SeekerWorkflowService, EmergencyEscalationService, HelperMatchingService, ReferralManagementService, IdentityVaultService, ConsentService, Referral.
Views: landing/index, moderator/queue, adviser/screenings, adviser/referral-detail, professional/referral-detail, seeker/referrals, session/identity, session/referral-prompt, helper/chat/show. Existing app layouts, modal styling, notifications and referral detail cards are retained. `routes/web.php` adds the protected appointment POST route.
Tests: ConsentReferralEmergencyTest, IdentityVaultTest, ModeratorAdviserModuleTest, SeekerWorkflowSecurityTest, CompassImplementationTest, EscalationWorkflowTest and HelperModuleTest updated for review-first ordering and mandatory eligibility.

## Migration and deployment

One additive operational migration creates `referral_appointments` with foreign keys, time indexes and predecessor references. No existing table is reset; no real database migration or seeding was executed. SQLite isolated test databases apply the migration. Meeting details use the existing application encryption key: preserve APP_KEY and IDENTITY_VAULT_KEY.

After explicit deployment approval: back up databases, deploy code/assets, run `php artisan migrate --force`, rebuild configuration/views as appropriate, restart long-running workers and verify the separately configured vault connection. Keep scheduler/queue services running for existing matching and notifications. No new external mail service, SMS channel or hosting configuration is introduced by this revision.

## Documentation gaps and limitations

The supplied COMPASS-v4 contains Appendix O referral fields, participation consent and a privacy notice. A separately approved Identity Disclosure Consent and Personal Information form could not be located. The requested verbatim complete forms therefore are NOT certified complete. A question requesting a newer document path remains pending.

**Identity Disclosure Consent is now derived from the referral consent rather than separately drafted.** The approved referral consent wording was extracted into `resources/views/partials/referral-consent-terms.blade.php` as the single source of truth. Both the in-chat referral prompt and the Identity Disclosure Consent step render that same partial, so the identity step cannot drift from the consent the seeker already accepted; it adds only identity-specific facts (vault encryption, who may authorize release, retention, replacement requiring fresh release, and the logged emergency exception). `IdentityVaultTest::test_identity_disclosure_reuses_the_canonical_referral_consent_terms` guards this. Because no separately approved form exists, this wording remains derived-and-traceable rather than verbatim, and must still be reconciled with the approved form before publication.

Appointment start/end and meeting instructions are proposed implementation fields. The PDF lists Scheduled as a referral outcome but does not specify duration, rescheduling/cancellation policy or seeker confirmation. Appointment state is kept in the appointment record rather than changing the referral enum. No arbitrary fixed duration or mandatory seeker confirmation is imposed. Standalone appointment cancellation and external-offline-professional registration are not added without an approved workflow; existing adviser reassignment to authorized professional accounts remains available.

Existing duty-hour testing relaxation remains configurable; production must use approved eligibility settings. Specialty is an existing ranking factor, not a newly invented hard exclusion. No production/PostgreSQL concurrency load test or real-browser end-to-end verification has been performed. Database locks were exercised by functional tests on SQLite, which does not simulate PostgreSQL locking concurrency.

## Manual verification by role

1. Moderator: verify eligible assignment, stale duplicate rejection, helper acceptance, removal/requeue, capacity refresh and both waiting/assigned sections at desktop/mobile sizes.
2. Helper: recommend a referral during an active session; verify assigned adviser notification and no premature seeker-consent action.
3. Adviser: open emergency notification, review recorded answers, submit confirmed/clarified classification, verify seeker result and retained original history. Review/approve/reject a referral and inspect feedback/status history.
4. Seeker: accept approved referral, verify identity modal opens; reject missing disclosure confirmation, submit fictional identity, verify errors stay in the modal. Decline another referral and open self-help.
5. Professional: accept an assigned referral, schedule a future appointment, attempt overlap, reschedule and verify old record remains. Test another professional is denied.
6. Seeker/adviser: open appointment notifications; verify Manila time/details and no unrelated records. Withdraw referral consent and verify future scheduling/professional access is blocked.
7. Verify keyboard focus, Escape/close behavior, narrow viewport layout, network retry and no PII in browser URLs or operational logs.

## Verification results

- `php artisan test --compact`: **343 passed, 2,493 assertions**. Log: `storage/logs/revision-verified-tests.txt`.
- `npm run build`: passed, including PWA service-worker generation. The sandbox initially blocked esbuild process creation (EPERM); an approved local build outside the sandbox succeeded. Log: `storage/logs/revision-build.txt`.
- `php artisan view:cache`: passed after the final edits.
- `php artisan route:list`: 304 routes, including protected professional appointment POST route. Log: `storage/logs/revision-routes.txt`.
- `git diff --check -- app resources routes tests database`: passed. Whole-repository check encounters pre-existing inaccessible PDF tooling files; these were not changed.
- PHP emits an existing duplicate OpenSSL-extension warning; tests/build results above are taken from their actual completion reports, not PowerShell's warning-derived exit code.

| Acceptance area | Evidence and limits |
| --- | --- |
| Public sections removed | Blade compilation/build; browser visual check pending |
| Moderator assignment/eligibility | ModeratorAdviserModuleTest and existing matching tests |
| Removal/stale/active protection | ModeratorQueueRemovalTest (URL ID, requeue, cancellation, authorization, terminal guard) |
| Dashboard Recent Messages | No such widget/query existed in the inspected checkout |
| Emergency screening review | SeekerWorkflowSecurityTest creates emergency, opens scoped review, submits review, preserves history and notification link |
| Adviser-before-consent referral | ConsentReferralEmergencyTest, HelperModuleTest, CompassImplementationTest |
| Seeker decline | Closed referral, retained emergency incident and self-help notification tests |
| Identity modal | `IdentityVaultTest` renders the shared terms partial on both the standalone page and the modal; per-field inline errors, success panel, backdrop/Escape close and Blade compile. Browser keyboard focus and automatic opening not manually verified |
| Explicit identity disclosure and storage | IdentityVaultTest with separate in-memory vault DB, missing consent rejection, encrypted values, professional assignment gate |
| Professional accept/reject | Existing escalation/referral tests; authorized account coordination only |
| Professional appointments | ReferralAppointmentTest: future schedule, reschedule history, encrypted instructions, conflicts, unrelated professional and withdrawn consent denial |
| Seeker appointment notification/modal | Implemented links and rendered dialog; interactive browser verification pending |
| Waiting-list controls | Removed from Blade, override prohibited server-side; UI smoke test pending |
| Regression safety | Full 342-test suite and production asset build pass; no live database reset or real-user test data |
