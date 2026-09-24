# COMPASS Final Integration Report

Date: 2026-09-25. Scope: end-to-end integration of the five core workflows (referral, screening/risk classification, queue priority P1–P4, emergency escalation, helper matching) against the source document (`storage/logs/compass-v4.txt`), with automated tests and deployment notes. Branch: `deploy`.

## 1. Referral workflow

The referral pipeline (helper proposes → seeker consents → adviser approves → professional assigned → identity released) was corrected at three coordination points that previously produced dead ends for the seeker and the adviser.

| Fix | Where | Behavior after fix |
|-----|-------|--------------------|
| Open-status service | `Referral::isOpen()` and `ReferralManagementService::OPEN_STATUSES` / `checkExistingReferrals` | Now includes `Referral::STATUS_NO_PROFESSIONAL_AVAILABLE`, so a referral paused while awaiting a professional is treated as open: the seeker can still store identity details, create no conflicting referral, and the pipeline resumes when a professional is assigned. |
| Post-approval consent link | `notifySeekerPostApprovalConsent` | When the adviser approves before the seeker has consented, the seeker is notified at `/seeker/referrals` (their decision page) instead of an identity URL that is not yet accessible. |
| Identity prompt after consent | New `notifySeekerProvideIdentity` (link `/referrals/{id}/identity`), fired from `recordReview` (consent already given at approval) and `recordConsent` (seeker accepts) | After a professional is assigned, the seeker is proactively asked to provide contact details for coordination. Gated by `Referral::canProvideIdentity()`. |
| Empathetic decline | New `notifySeekerReferralDeclined` (link `/emergency`), fired from `recordConsent` (declined) and `decideConsentRequest` (declined) | A refusal closes the referral, keeps the seeker in the loop, and points them to self-help and emergency resources. |
| Adviser "Request Revision" | `adviser/referral-detail.blade.php` + `info_request` → `adviser.referral.request-info` | The adviser can return a pending recommendation to the Helper with comments. Approval/rejection buttons hide while clarification is pending, and a banner shows the recommendation was returned. Approval is blocked (HTTP 409) until revision. |
| Helper recommendation revision | `helper/referral-status.blade.php` + `HelperSessionController::clarifyReferral` (now accepts optional `referral_reason`) | The Helper sees the adviser's comment and may revise the recommendation. Use of the clarification machinery deliberately keeps status `pending_adviser` (no new status). Revising the reason records a `supervision_record_versions` entry labeled "Helper revised the referral recommendation", preserving the 3-record supervision constraint asserted by `AdviserCompletionTest`. |
| Seeker decision page | `seeker/referrals.blade.php` + `ReferralWorkflowController::consent` | The pending-consent card shows the referral reason and priority with explicit Agree/Decline wording; accept and decline return distinct flash messages and redirect to the seeker decision page. |

## 2. Screening and risk classification

- Verified **correct by design, no change required**: the "Prefer not to say" response is only offered by the full instrument (`ScreeningInstrument`), and any submission that reaches the full-instrument path with unresolved responses routes to adviser review (`adviser_review_required`). The compact short form has no free-text safety option — every compact field is validated as `required|boolean`, so it can never ambiguously signal risk.
- The full and compact instruments classify consistently: `current_suicide_plan = yes` → emergency (immediate escalation, no adviser gate), unresolved or high → adviser review with the original screening retained, low/moderate → normal queuing with `elevated_priority`/`requires_closer_monitoring` set for moderate.

## 3. Emergency escalation

- `EmergencyEscalationService::flagEmergencyCase` (classification branch) now creates a first-class **Incident Report** in addition to the `EmergencyAlert`: `incident_category = classification_emergency`, `risk_level = emergency`, `status = open`, `is_confidential = true`, description derived from the classification reason. Creation is deduplicated per session/status through a `lockForUpdate` guard and recorded via `SupportAudit::record('classification_emergency_incident_created', …)`.
- Result: classification emergencies surface on the **moderator incident board** (and its `emergency_count`) exactly like helper-flagged emergencies, giving moderators and advisers a single review surface. Seeker/helper emergency flags and consent-declined safety fallback behavior are unchanged and covered by existing tests.

## 4. Queue priority (P1–P4)

- The priority mechanism was already functionally complete and is now surfaced: `QueueManagementService` weight assignments (emergency 1, high 2, moderate 3, low 4) and FIFO ordering keep high-priority requests served first.
- `QueueRequest` gained `PRIORITY_CLASS_LABELS` (`emergency` → P1 … `low` → P4) and a `priorityClass()` helper.
- The moderator queue view now shows P-class pills on waiting and assigned items plus a service-target legend (P1 emergency 1 min, P2 high 3 min, P3 moderate 10 min, P4 low 20 min).
- Dashboard sync: the queue stats card shows "X assigned · Avg wait", and `ModeratorDashboardController::stats()` returns `queue_assigned` for live views.

## 5. Helper matching and duty hours

- Matching correctness was audited and confirmed: `manualAssign` re-locks queue + helper inside a transaction, `matchWaitingRequests` applies the same guard, and `HelperEligibilityService` already treats duty hours as off by default — no code change was needed.
- End-to-end testing no longer bypasses duty hours accidentally: `config/app.php`'s `relax_duty_hours` defaults to `env('RELAX_DUTY_HOURS', false)` and both `.env` and `.env.render.example` set `RELAX_DUTY_HOURS=false`. Production therefore enforces operating hours (18:00–22:30 Manila, Mon–Sat) unless explicitly overridden per environment.

## 6. Helper no-response escalation

A scheduled safeguard ensures silent sessions never stall unattended:

- Migration `2026_09_25_000003_add_session_responsiveness_columns` adds nullable `last_helper_message_at` and `no_response_escalated_at` to `counseling_sessions` (model fillable + casts updated).
- `ChatController::sendMessage` sets `last_helper_message_at = now()` and clears `no_response_escalated_at = null` on every helper message.
- New console command `sessions:check-no-response` escalates any active session where `start_time ≤ now − 5 min`, the helper has not sent a message within 5 minutes, and no escalation has already fired: it stamps `no_response_escalated_at`, notifies the session's adviser and all active moderators, and records `helper_no_response_escalated`. Escalation is idempotent (a second run does not re-fire), messages are never discarded, and there is no automatic reassignment — an adviser decides next steps.
- Registered in `routes/console.php` as `->everyMinute()->withoutOverlapping()`.

## 7. Automated tests

- Full suite: **328 passed / 2427 assertions** (`php artisan test`), including all pre-existing suites — no regressions.
- The new coverage was added to `tests/Feature/ConsentReferralEmergencyTest.php` (RefreshDatabase + `SeekerWorkflowFixtures`, frozen clock at 2026-09-16 20:00 Asia/Manila):

| New test | Guards |
|----------|--------|
| `test_post_approval_consent_notification_leads_to_the_seeker_decision_page` | Post-approval consent notification links to `/seeker/referrals`, never a dead identity URL |
| `test_consent_accept_prompts_seeker_to_provide_identity_for_coordination` | Consent-first approval forwards to professional, posts the identity prompt, form is served |
| `test_adviser_approve_then_seeker_consent_prompts_identity` | Approval-first then consent → pending professional + identity prompt |
| `test_seeker_consent_decline_after_approval_redirects_to_self_help` | Empathetic decline notification links to `/emergency` |
| `test_adviser_request_revision_and_helper_revises_recommendation` | 409 before revision, revision records reason + supervision version, approval allowed after |
| `test_classification_emergency_creates_open_incident_for_moderator_board` | Classification emergency → open deduplicated incident, alert, audit |
| `test_identity_can_be_stored_while_a_professional_assignment_is_pending` | Identity form + decision page usable at `no_professional_available` |
| `test_helper_no_response_escalates_once_and_a_message_clears_it` | Escalation fires once (idempotent), audits + notifies, a helper message clears it |

Frontend assets rebuild cleanly (`npm run build`, 13 precache entries, service worker regenerated).

## 8. Deployment and integration notes

- **Migrations**: `php artisan migrate` applies `2026_09_25_000003_add_session_responsiveness_columns` (also `…_000001` helper schedule times and `…_000002` helper non-response counter if not yet applied). Verified on both the test runner (SQLite :memory:) and the local Postgres `compass_db`.
- **Environment**: set/keep `RELAX_DUTY_HOURS=false` (default is false). `php artisan config:cache` after deploy.
- **Scheduler**: ensure `php artisan schedule:work` (or cron `* * * * * php artisan schedule:run`) is running; `sessions:check-no-response` runs every minute and self-overlaps-protects.
- **Assets**: build output and `public/sw.js` must be regenerated in the deployment pipeline (`npm run build`).
- **Known environment note**: `php artisan schedule:list` fails on the local machine because `compass_db` is missing the `cache_locks` table while `CACHE_STORE=database`. This is a pre-existing local environment gap affecting every scheduled task, not caused by this work; run `php artisan cache:table` + migrate (or switch `CACHE_STORE` to a driver with the table present) in any environment relying on the scheduler.
- **Deliverable hygiene**: all changes are uncommitted on `deploy`; the working tree contains 19 modified files, 1 new command, 1 new migration, and this report. No commit or push was made — the branch will only be committed on explicit instruction.

### Appendix: changed surfaces (summary)

Referral logic — `app/Services/ReferralManagementService.php`, `app/Models/Referral.php`, `app/Http/Controllers/ReferralWorkflowController.php`, `app/Http/Controllers/Helper/HelperSessionController.php`; views — `resources/views/{seeker/referrals,adviser/referral-detail,helper/referral-status}.blade.php`.

Emergency — `app/Services/EmergencyEscalationService.php`. Queue — `app/Models/QueueRequest.php`, `app/Http/Controllers/Moderator/ModeratorDashboardController.php`, `resources/views/moderator/{queue,dashboard}.blade.php`.

Matching/no-response — `app/Console/Commands/CheckHelperNoResponse.php`, `routes/console.php`, `app/Http/Controllers/ChatController.php`, `app/Models/Session.php`, `database/migrations/2026_09_25_000003_add_session_responsiveness_columns.php`, `config/app.php`, `.env`, `.env.render.example`.

Tests — `tests/Feature/ConsentReferralEmergencyTest.php`.