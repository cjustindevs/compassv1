# COMPASS Helper module implementation and verification

Updated 2026-09-17. This report covers the Helper audit requested in attachment `2ac07a38-e40e-4fd2-9199-3cd7ff0def86`. It does not claim that unrelated working-tree edits were produced in this pass.

## 1. Implemented changes

The existing Helper module now uses a shared eligibility decision for new assignments and accept/start actions; institutional verification is recorded by the assigned adviser. Readiness no longer locks helpers out of their portal. Acceptance, preparation and starting a session are separate. Saving summary or reflection never ends a session. Corrections preserve the original report snapshot, author and reason. Emergency observations do not directly replace the official risk classification. Adviser evaluations retain the documented weighted rubric and display the same result consistently.

## 2. Previous updates found

The repository already contained the Helper shell, readiness and breathing exercise, adviser schedules, queue matching, chat, session limits, notes, referral services, competency history, feedback, notifications, self-help, journals, an Identity Vault and extensive feature tests. Earlier seeker changes supplied consent/version checks, a workflow observer, risk review, matching restrictions, automatic duration expiry and private participant authorization. The working tree was already extensively modified; no reset, reseed or broad checkout was used.

## 3. Connections to existing work

`Helper::isAvailable()` delegates to `HelperEligibilityService`; automatic/manual assignment and `SeekerWorkflowService::accept/start` use this decision. The existing reflection route now calls its own validation handler. Notes use `HelperDocumentationService`. Helper referral proposals reuse `ReferralManagementService`, rather than a second referral implementation. Emergency reports reuse `EmergencyEscalationService` with an explicit preserve-classification context. Reassessment requests enter the existing adviser screening review, retaining the session's closed/active state. Existing adviser feedback stores acknowledgement without inventing a second training module.

## 4. Documentation requirements and policy decisions

Source: `COMPASS-v4.pdf` in `Downloads/Compass - Startup`; text extracted into `storage/logs/compass-v4.txt`. Printed page numbers are five less than PDF page numbers.

| Requirement | Source | Implementation |
| --- | --- | --- |
| Current institutional eligibility, training and adviser approval | BP15, printed p203 | Verification status, actor, date, evidence, training flag and optional expiry; helper self-edits cannot grant them |
| Primary adviser supervision | BP34, p207 | Assigned adviser verifies and receives helper documentation/referral/safety notifications |
| Official duty, readiness, availability, no competing assignment | BP39-BP42, p208 | Shared eligibility and existing matching ranking; workload/idle tie order is deterministic |
| One active session, at most two in the duty shift | BP45, p209 | Pending assignments reserve concurrency; actual started sessions determine workload |
| 85-minute warning, 90-minute maximum, no extension | BP46, p209 | Existing backend duration service and scheduled warning/expiry retained |
| Readiness valid for four hours or shift end, whichever first | BP47-BP49, p209 | Versioned five-skill readiness, latest assessment authoritative, UTC storage of Philippine shift end |
| Conflict/withdrawal handling | BP50-BP51, p209 | Bounded decline reasons, persistent conflict exclusion, queue release, audit and re-match |
| Assignment cutoff 22:30; ongoing sessions may finish later | BP55, p210 | Existing operating-hours service; no new assignments at cutoff |
| Helpers propose risk reassessment | BP61 | Helper report requests adviser review; only adviser review changes official classification |
| Emergency notification without automatic identity release | BP63-BP64 | Governed alert/referral proposal, primary adviser notification, no helper Vault access |
| Documentation within 24 hours, preserve corrections | BP75/BP78 | Submission times, late flag, scheduled reminder, immutable prior snapshots |
| Governed transcript access | BP87 | Helper export denied; closed case view does not expose archived conversations |
| Results and five listener skills | Printed pp245-247 | Stable / Needs follow-up / Needs referral; active listening, empathy, reflection, clarification, summarizing |
| Five weighted competency criteria | Printed pp85,251-252 | 25% listening + 25% empathy + 20% respect/professionalism + 20% ethical practice + 10% referral accuracy; individual 1-5 values |

## 5. Original gaps and root causes

Readiness middleware applied to virtually the entire helper portal. Profile input could alter fields that appeared to be qualifications. Assignment eligibility was duplicated. Acceptance also started the session. Notes completion updated session status and freed the helper. Summary and reflection shared a single validator. Reports were overwritten without revision history. Safety/referral handlers broadcast to unrelated staff. Competency screens mixed 1-5 and percent values, and a shortcut copied one rating into all five criteria. Cached dashboard readiness could remain stale. A newly introduced shift-end comparison needed UTC conversion before persistence; regression tests caught the eight-hour drift.

## 6. UI inventory and old references

| Existing reference | Reused structure |
| --- | --- |
| `layouts/helper.blade.php` and helper sidebar | Inter, green identity, responsive main-content offset, collapsible navigation, flash/error states |
| `dashboard/helper.blade.php` | Existing stat cards, case list and action buttons; added eligibility reasons and pending documentation |
| `helper/readiness.blade.php` | Existing breathing exercise and readiness card; five skill confirmations inserted in the same form |
| `helper/cases*.blade.php` and pre-session assessment | Existing table, side card, buttons and confirmation dialog; separate prepare/start and decline reason |
| `helper/notes.blade.php` | Existing documentation card and session-info column; two forms and correction-history details |
| `helper/calendar.blade.php` | Existing calendar plus official duty list in the same shell |
| `helper/competency*.blade.php`, feedback detail | Existing score cards/charts; normalized scores and acknowledgement |
| `adviser/helper-detail.blade.php`, evaluate | Existing adviser cards; institutional verification and report context |

No new standalone Helper page or competing layout was introduced. Voice's existing unavailable view now uses the Helper shell.

## 7. Shared components reused

Helper layout/sidebar, helper component CSS, form-control/form-group, cards, pills, buttons, grids, flash errors, confirmation modal, existing notification records and broadcasts. New controls use Font Awesome icons; no emoji UI was added.

## 8. Files added

- `app/Services/HelperEligibilityService.php`
- `app/Services/HelperReadinessService.php`
- `app/Services/HelperDocumentationService.php`
- `app/Services/HelperWorkflowMaintenance.php`
- `app/Policies/HelperRecordPolicy.php`
- `database/migrations/2026_09_17_000001_harden_helper_workflow.php`
- `tests/Feature/HelperWorkflowSecurityTest.php`
- This report.

## 9. Files modified

Helper controllers; AdviserHelperController, AdviserEvaluationController, ScreeningReviewController, shared ChatController and RequestSupportController integration; Helper, ReadinessCheck, Session, SessionReport, HelperCompetencyHistory and AdviserFeedback models; AppServiceProvider; EnsureHelperReadiness; existing matching, emergency, transcription, duration and seeker-workflow services; helper/adviser views above and seeker request status; web/console/channel routes; existing Helper, seeker security, moderator/adviser, escalation and implementation tests and their fixture trait. `public/sw.js` is generated by the successful frontend build. See `git diff` for the full working tree, which also contains earlier unrelated work.

## 10. Migration

The additive migration adds verification/readiness provenance, match/documentation/completion metadata, separate report submission/reassessment timestamps, report revisions and feedback acknowledgement. It was applied to the local PostgreSQL database with `php artisan migrate --force`. It does not erase sessions, reseed users, fabricate historical training approval, or alter Identity Vault data. Existing helpers default to **pending verification**. Existing report contents remain available; prior undocumented revisions cannot be reconstructed.

## 11. Authorization

All Helper controller actions check an active exact helper role in addition to route middleware. Record queries scope IDs to the current helper. HelperRecordPolicy covers helper records, reports, readiness, schedules, competency, feedback and journals; competency/schedule/adviser-feedback mutation is denied through the generic helper update policy. The shared participant policy remains in force for chat/end actions. Readiness middleware gates only accept/start, not dashboard/profile/calendar/self-help or documentation of a completed session. Private session channels require active membership and an accepted active session; role channels reject inactive users.

## 12. Services and persistence

Eligibility is read-only, including on GET requests. Readiness submission, acceptance/start, documentation, decline, verification and maintenance use transactions and relevant row locks. Maintenance expires unaccepted recommendations, releases invalid pending matches, marks readiness expired, sends overdue documentation reminders, and records audits. Repeated expiry and start/summary submissions do not duplicate their completed state. The existing report row is retained; previous snapshots live in the narrowly scoped revision table.

## 13. Workflow states

The established `session_status` vocabulary is preserved for compatibility. `match_status` distinguishes awaiting_acceptance, accepted, assigned, declined, expired and release reasons. Acceptance sets `workflow_state=session_ready` without start_time. Start sets active/session_active and start_time. Completion remains independent. `documentation_status` distinguishes pending, incomplete and submitted. A referral recommendation stays pending_adviser; it is neither professional approval nor seeker consent. Emergency referral proposals are not falsely marked as already referred to a professional.

## 14. Tests

`HelperWorkflowSecurityTest` covers role isolation across helper routes (including stray helper profiles), pending verification, assigned-adviser verification, shift expiry and latest readiness failure, returning from unavailable, separate accept/start and idempotency, foreign-record isolation, results/follow-up validation, separate summary/reflection, correction snapshots, scheduler-vs-GET expiry, conflicts/cutoff, weighted rubric/feedback privacy, adviser reassessment without reopening a closed session, inactive users and transcript-export denial. Existing tests were updated where the approved workflow intentionally changed; fixture verification is created only in test code, never as production approval.

## 15. Commands executed

`php artisan migrate --force`; focused and full `php artisan test --compact`; `php artisan route:list --except-vendor`; `php artisan view:cache`; targeted Laravel Pint; `git diff --check`; `npm run build`. Tests use the repository's SQLite test configuration/RefreshDatabase, not the local PostgreSQL data. No `migrate:fresh` or operational reseed was run. Initial sandbox build failed with esbuild spawn EPERM; the authorized build outside the sandbox succeeded.

## 16. Test results

**Full suite: 204 passed, 1 failed, 1,567 assertions** (`storage/logs/helper-verification-final.log`). The new Helper security class has 15 tests; all pass in this full run. After the final presentation changes, the targeted Helper, duration, moderator/adviser and escalation run passed **96 tests / 689 assertions** (`storage/logs/helper-targeted-final.log`). Migration status confirms batch 3 applied. Scheduler listing includes the one-minute helper-maintenance task. A known pre-existing seeker-screening assertion expects the private risk label to be absent, while the separately edited screening view displays it. This work does not silently revert that view or weaken its test.

## 17. Frontend build

Vite/PWA production build succeeded, including generated service worker and postbuild copy. See `storage/logs/helper-build-verified.log`. Blade compilation and route listing succeeded. These verify compilation and rendering through feature tests; they are not a claim of completed visual QA.

## 18. Documentation conflicts and conservative interpretations

- The attachment mentions authorized extensions conditionally; BP46 explicitly prohibits them. No extension feature was added.
- The readiness material discusses high stress without an unambiguous scoring threshold. The existing high-stress restriction is preserved; no clinical threshold was invented.
- The semester evidence-weight example is not a replacement for the documented five-criterion rubric. Existing five-criterion ratings are authoritative; no fabricated composite evidence score was added.
- Existing operational ranking weights remain implementation choices, not a claim of institutionally validated suitability. Language aliases are normalized and language remains a ranking factor; clinical risk remains adviser-governed.
- The existing five-minute recommendation brief timeout is retained; it is distinct from the documented ten-minute no-show rule.
- Capacity is counted from actual starts within the Philippine duty day, preventing a second schedule row from resetting the two-session limit.

## 19. Remaining limitations

Visual desktop/mobile and two-browser real-time QA remain manual. Computer Use stopped because it could not determine the browser URL confidently; no further UI inputs were attempted. PostgreSQL row-lock behavior is implemented but parallel multi-process race testing was not performed by SQLite tests. Institutional evidence must be entered by authorized advisers. There is no invented automatic substitute-adviser assignment or external emergency dispatch. Delivery of an in-app alert does not prove that the adviser has read it. Legacy readiness records retain their previous expiry semantics; new submissions have explicit form/shift provenance. Existing account approvals are not backfilled.

## 20. Unavailable features

Voice calling, voice recording and automatic transcription remain explicitly unavailable; endpoints return 503 and cannot create a fake call log. Routine Helper transcript downloads are denied. Session extensions are prohibited rather than presented as available. OTP inbox delivery from the earlier separate task remains unverified without a chosen test recipient.

## 21. Manual verification

1. Adviser: open own Helper detail, verify institutional evidence, record an appropriate schedule. Another adviser's helper must reject access.
2. Helper: open dashboard before verification and confirm portal access plus specific assignment restrictions. Complete readiness during duty; confirm displayed expiry and five skills.
3. Use separate seeker/helper browsers: submit a consented request during service hours, accept, review brief, then start. Confirm chat stays closed before start and messages/85-minute warning/end state sync in both browsers.
4. Decline with each bounded reason; check queue release and matching. A declared conflict must exclude that helper.
5. Save summary while active; session must remain active. End explicitly. Submit reflection, correct with a reason, and inspect prior snapshot. Check overdue reminder once.
6. Assigned adviser: review a reassessment request and documentation; verify no closed session reopens. Review competency and confirm 3.35/5 displays as 67%, with five independent scores.
7. Check expanded/collapsed sidebar, narrow mobile width, dropdown labels, validation errors, long text and confirmation dialogs across dashboard/readiness/cases/notes/calendar/feedback.
8. Use synthetic data to flag emergency; confirm only the primary adviser receives the helper alert and no Vault identity is disclosed.

## 22. Deployment requirements

Apply the additive migration before serving the updated code, run the frontend build, clear/rebuild Blade cache and restart long-running queue workers after deployment. Run `php artisan schedule:work` locally (or a production cron invoking `schedule:run` every minute), plus the existing queue worker and Reverb server for queued/live events. HTTP polling does not replace scheduler tasks. Institutional verification, adviser assignment, duty schedule and a fresh readiness check are required before a helper appears as eligible. Existing helpers are deliberately not silently approved. Back up operational data before deployment; never run destructive test resets on it.
