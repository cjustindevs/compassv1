> **2026-09-21 continuation:** See [Shift capacity, eligibility, screening review and seeker cancel hardening](#shift-capacity-eligibility-screening-review-and-seeker-cancel-hardening---completed-2026-09-21) below for the latest capacity, status, assignment-reason, scheduling, screening-review, and cancel changes.
>
> **2026-09-16 continuation:** See [Help Seeker implementation and verification](HELP_SEEKER_IMPLEMENTATION.md) for current workflow, migration, tests, approval gate and remaining manual checks. Earlier results below are historical.

# COMPASS capstone audit - completed 2026-09-16

## Scope and decisions

Audited Help Seeker, Helper, Moderator, and Adviser features against the supplied checklist using the actual application routes/controllers. Voice calls were excluded. Inter and the existing green styling were retained at the user's request. Existing local data was preserved; destructive migration resets were not needed. Tests use an isolated database.

## Fixes delivered

- Repaired the incomplete adviser helper-detail template, including competency-history and empty states.
- Implemented adviser supervision transfer with source ownership checks, active target-account checks, supervision capacity checks, transactional updates, and an audit record. Active referrals follow supervision; completed/closed/declined referrals retain their previous owner.
- Unified moderator manual assignment and reassignment through the matching service. Queue/helper rows are locked, duplicate assignment is rejected, normal assignments enforce risk competency/readiness/schedule/capacity, and assignment records include queue linkage and pre-session briefing expiry. Emergency override behavior remains explicit. Reassignment updates counters and sends notifications after commit.
- Excluded disabled user accounts from automatic matching and labelled them in moderator helper choices.
- Added audited queue-priority increases for waiting requests. Priority cannot be lowered through this operational action.
- Fixed negative waiting-minute calculations.
- Fixed pending-only referral reports dividing by zero and counted adviser approvals by their recorded approval timestamp.
- Fixed first-response analytics to use the first reply by the assigned helper, not every message from either participant.
- Fixed PostgreSQL-incompatible competency queries in adviser reports, moderator reports, and helper CSV export. Earliest/latest records are now selected in chronological order without invalid GROUP BY clauses.
- Corrected custom-range monthly trends, selected-helper ranking filters, and competency trend date ranges.
- Added cancelled/abandoned session counts, session referral rate, and scheduled duty hours to adviser reports. Scheduled hours are labelled as planned time, not actual attendance.
- Fixed the resource edit modal to submit PUT while new resources submit POST; added visible validation errors.
- Added adviser-managed emergency contacts with publication controls, validation, audit logging, and display on the seeker emergency page.
- Replaced the seeker emergency page's placeholder crisis number with NCMH 1553. Source: https://caro.doh.gov.ph/30s-mh-law-accomplishment/ (DOH). Telephone delivery/availability was not tested.
- Corrected helper competency displays to use scores out of five and properly scaled trend bars.
- Updated demo duty-schedule generation to use the Philippine schedule date.

## Feature evidence

| Area | Checked behavior | Evidence |
|---|---|---|
| Seeker account | Generated aliases, uniqueness validation, password hashing, OTP, persisted verification timestamp, consent requirements, permitted profile changes | RegistrationTest, SetupValidationTest, CompassImplementationTest, ProfileTest |
| Seeker request | Category/description rules, hidden preliminary risk, emergency resource redirect, chat-only preferences, matching and history | HelpSeekerModuleTest, CompassImplementationTest, HelperModuleTest; PostgreSQL dashboard/history pages |
| Seeker chat/feedback | Authenticated chat access, messaging endpoints, 90-minute expiry, 1-10 feedback validation, notifications/resources | HelpSeekerModuleTest, SessionDurationTest, HelperModuleTest, JavaScript timer tests |
| Helper readiness | Readiness expiry, availability, duty eligibility, schedule timezone, assignment | HelperModuleTest, ModeratorAdviserModuleTest |
| Helper session lifecycle | Accept/decline, pre-session privacy, completion, notes/reflection, released capacity, ability to accept further sessions | HelperModuleTest, SessionDurationTest |
| Helper performance | Competency, feedback, completed-session count, referral submission | HelperModuleTest; corrected competency score UI |
| Moderator workflow | Dashboard/queue, automated/manual assignment, reassignment, priority changes, emergency operations, reports | ModeratorAdviserModuleTest, ModuleSmokeTest, HelperModuleTest; PostgreSQL page checks |
| Adviser supervision | Helper detail, reports/reflections, competency evaluation, referral review, emergency ownership, transfer and active referrals | ModeratorAdviserModuleTest, EscalationWorkflowTest, ModuleSmokeTest |
| Adviser reporting | Waiting/response time, completion, referrals, satisfaction, trends, scheduled duty hours, helper performance ranking, PDF/CSV | Regression tests plus real PostgreSQL report rendering; existing PDF export tests |
| Adviser resources | Content CRUD, corrected edit method, emergency contacts, transcript review | ModuleSmokeTest, ModeratorAdviserModuleTest, HelperModuleTest; local resource/transcript pages |
| Access boundaries | Role restrictions, inactive accounts, session ownership, identity encryption/release/consent, referral ownership | IdentityVaultTest, EscalationWorkflowTest, ProfessionalModuleTest, SetupValidationTest |

## Verification results

- Baseline: 165 PHP tests passed (797 assertions).
- Final: 169 PHP tests passed (825 assertions), recorded in `storage/logs/checklist-final-tests.txt`.
- Both JavaScript chat-timer tests passed (expiry composer lock and duplicate end-event handling).
- PHP syntax checks covered 366 application/route/compiled-view files with no errors at the syntax-audit stage; Blade compilation repeated after edits.
- Route inspection found no missing controller actions. All existing main-database migrations were already applied.
- PostgreSQL and the separate identity vault both connected successfully.
- Local PostgreSQL HTTP-kernel checks returned 200 for seeker dashboard/history/resources/notifications, moderator dashboard/queue/schedules/reports, and adviser dashboard/helpers/reports/resources/schedules/transcripts/emergencies after fixes.
- The local helper pages returned readiness redirects because the demo assessment had expired. Successful helper page flows were verified in isolated automated tests; live readiness was not fabricated.
- Production frontend build and service-worker generation completed; final build repeated for the last UI changes.

## Limits and demo checks

Automated tests and server rendering are not browser visual verification. Still check responsive layouts, keyboard/modal interactions, fresh OTP inbox delivery, and two-browser live Reverb chat in a browser. The previous live-chat smoke script is available at `scripts/verify-live-chat.mjs`; it creates session data and requires a ready, on-duty helper. It was not claimed as a fresh successful live check in this audit.

For a demo, the helper must complete readiness, select available, and have a current Philippine-time duty schedule. Run the Reverb service for live broadcasts and `php artisan schedule:work` for unattended matching/expiry. Waiting seekers also retry matching through the existing waiting-page refresh. The UI adds no artificial loading delay.

Historical referral timestamps, verification timestamps, and missing schedules were not invented or backfilled. Clinical hotline availability cannot be established by rendering the page; administrators should maintain published contacts from official agency information.

# Shift capacity, eligibility, screening review and seeker cancel hardening - completed 2026-09-21

## 1. Scope

Repair of the helper duty-shift capacity rule, consolidation of helper status/eligibility into one service, explicit reasons for manual assignment outcomes, automatic availability at shift start, live queue synchronisation, adviser screening-review authorization and assignment, and a seeker cancel option in the request flow. COMPASS UI, alias-based anonymity, emergency escalation, operating hours, and existing authorization were preserved.

## 2. Root causes addressed

- Capacity was counted per calendar day rather than per duty shift, and the count was read from a stored counter that could drift.
- Helper availability/eligibility was recomputed differently in the moderator queue, adviser pages, helper dashboard, and the matching engine, so surfaces could disagree with matching.
- Manual assignment returned a silent `null` on every rejection, giving moderators no reason.
- Willing helpers who passed readiness before their shift stayed offline until a manual toggle.
- Unclaimed screening reviews could be seen by any adviser (`orWhereNull('review_adviser_id')`) and review selection was "oldest adviser", ignoring workload.
- The seeker waiting screen (Step 3) and request history offered no way to cancel an in-flight request.

## 3. Duty-shift capacity (`app/Models/Helper.php`)

`currentAssignedSessionsCount()` now counts sessions whose `start_time` falls inside the helper's active `HelperSchedule` shift window for today (schedule timezone), with overnight handling (`end <= start` adds a day) and a whole-day fallback when no shift is scheduled. `currentDutyWindow()` is public. `hasCapacity()`, `getRemainingCapacity()`, `calculateWorkloadScore()` and `syncSessionCounters()` all derive from the same count.

## 4. Single source of helper status (`app/Services/HelperEligibilityService.php`)

Readiness uses `getCurrentReadiness()` (active record that is currently valid, i.e. shift-bound) instead of the raw `isReady()`. New `status(Helper, ?Session, bool $activating)` returns `{label, reason, assignable, reasons}` and `labelForReason(string)` maps reasons to human labels (Available, Account inactive, Pending verification, No active adviser, Under review, Outside service hours, Off duty, Readiness required, Not available, In session, At capacity (2/2), Unavailable for this request, Conflict prevented). Moderator queue, adviser helper list, helper dashboard, and the matching service now consume the same service.

## 5. Explicit assignment outcomes (`app/Services/HelperMatchingService.php`)

`manualAssign()` returns `Session|string|null`: a concrete reason string for every rejection inside the row-locked transaction (queue no longer waiting, requires different competency/adviser review, service closed, helper not found, consent invalid, conflict, the primary eligibility reason, an explicit two-session capacity message, helper not available, reassign state, same helper). `processQueueRequest()` treats a string as no-match. `ModeratorQueueController::assignHelper` surfaces the string verbatim as the error flash.

## 6. Automatic availability at shift start (`app/Services/HelperWorkflowMaintenance.php`)

`reconcileHelperAvailability()` flips a willing helper to Available when they hold no active session and all non-service-hours requirements pass, logging a `HelperAvailabilityLog` (`shift_start_auto_available`), a `helper_auto_available` audit record, and re-checking the waiting queue after commit. `reconcileAllReadyHelpers()` runs at the top of the scheduled `run()` pass, so no manual toggle is needed once a shift begins. `HelperReadinessService::submit()` now records willingness (`availability = available`) even before the shift while keeping assignment gated until on duty. The reconcile log uses the availability-log enum values.

## 7. Queue integrity and live sync

`SeekerWorkflowService` broadcasts `QueueUpdated` to every active moderator on submit and cancel via the shared `BroadcastsSafely` trait, keeping the moderator queue counters live alongside the existing per-minute maintenance pass. Matching and reassignment already broadcast after commit.

## 8. Adviser screening reviews

`ScreeningReviewController::index()` is scoped to `review_adviser_id = current adviser` (the unclaimed-review branch was removed) and `review()` requires that the assigned adviser performs the review. New reviews are assigned in `SeekerWorkflowService::pickReviewer()` to the active adviser with the fewest unresolved pending reviews, then the fewest handled helpers, then the oldest account (`Adviser::reviewSessions` relation added). The sidebar link moved directly below Dashboard with a pending badge, and `adviser/screenings.blade.php` was rebuilt to show the seeker alias, screened time, concern, risk/priority, workflow/peer-support state, assigned helper, retained screening responses, and a confirmed review action.

## 9. Seeker cancel and history

The Step 3 waiting screen (`request/matching.blade.php`) now offers "Cancel This Request" (with a confirmation dialog) both while queued and while awaiting helper acceptance. Request history (`request/history.blade.php`) offers a confirmed cancel for open requests and links open requests to the matching screen. The existing `SeekerWorkflowService::cancel()` already locks and is idempotent; it now also notifies an assigned-but-not-yet-accepted helper that the recommendation was withdrawn and broadcasts `QueueUpdated`.

## 10. Verification results and limits

- Full suite: 219 PHP tests passed (1666 assertions), including new tests for shift-window capacity, pre-shift readiness reconciled at shift start, explicit capacity rejection, adviser review scoping, least-loaded reviewer selection, and idempotent seeker cancel.
- Updated existing tests to the new contract: the moderator queue explanation now asserts `Pending verification` / detail text and `assignable === false`, and the conflict test asserts the explicit reason string instead of `null`.
- Limits: automated tests and server rendering are not browser visual verification. Still confirm the cancel dialog, live `QueueUpdated` updates over Reverb, and shift-start auto-availability against a running scheduler in a browser/real-time session.
