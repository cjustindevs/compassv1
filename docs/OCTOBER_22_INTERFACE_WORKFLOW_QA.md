# October 22 interface and workflow revision

Implemented October 23, 2026. Checked items describe source changes and automated
verification. Deployment, visual inspection and a live two-device call are still
pending. No demo reseeding or replacement of historical records is included.

## Implementation

- [x] Keep workspace gutters and mobile spacing; align the new forms, tables and
  emergency panels with the shared layout. Preserve the existing session controls
  and mobile safe-area treatment.
- [x] Show numeric KPI values and short labels through the shared dashboard
  components. Remove sample notation (`n=`), Definition / View records blocks,
  repeated date descriptions and the Seeker live-status paragraph. Keep the
  underlying calculations, role scope, date filters and detailed reports.
- [x] Replace the long competency rows with a compact chart on a fixed 0-5 scale
  and a table showing Helper, score, evaluation count and skill needing attention.
  Use an explicit View evaluation button. Show evidence and training actions in
  the detail dialog; do not treat one evaluation as strong evidence or an
  unevaluated Helper as a zero score.
- [x] Match the referral identity fields to the requested form: read-only alias,
  optional age and gender, phone or email, and an optional authorized emergency
  contact group. Keep identity encrypted in the separate vault. Disclosure consent,
  referral participation and authorized Professional release remain separate.
- [x] Replace the large emergency banner with a compact status row and stop
  showing it when the emergency is no longer active.
- [x] Keep the existing real server deadline and 90-minute limit. Add an amber
  **5 minutes remaining** toast for each participant in chat and voice, below the
  header. It is dismissible, hides after 10 seconds, creates an inbox record and
  is claimed once per user/session across refreshes, tabs and devices. Expired
  session reminders do not replay as new popups.
- [x] Show actual queue position while waiting. Reserve appointment wording for
  genuine future scheduled starts. Remove the close-this-page paragraph and keep
  the existing guarded polling that refreshes queue state.
- [x] Add Call controls inside active text chat. Require explicit Seeker voice
  consent before starting or accepting audio. Reuse the current call signaling
  and microphone flow without resetting the support session or its deadline.
  Leaving or declining a call preserves messages and the support session.
- [x] Detect prolonged Helper connection loss using heartbeats in both chat and
  voice. At 10 minutes, show the Seeker a once-per-incident popup; at 15 minutes,
  create a deduplicated staff follow-up. Reconnection cancels pending replacement
  offers and stops absence follow-ups. A connected voice call does not count as
  missing text responses; after audio ends, a new five-minute response window
  applies. No-response messages report actual elapsed time when detected.
- [x] Render Moderator All Helpers as a searchable, filtered, paginated table with
  primary Adviser, readiness and shared capacity information. Preserve assignment
  eligibility and the existing duty/live-session capacity rules.
- [x] Use the compact table styling for Seeker request history while preserving
  its filters, pagination and record actions.
- [x] After complete Helper documentation or Seeker evaluation, redirect out of
  the form and show one success dialog. Partial documentation remains editable.
  Refreshing the destination does not repeat the success dialog.
- [x] Prepare the user-confirmed **Helper #16 Lean Dancel -> Adviser #5 James**
  correction as a guarded deployment migration. It verifies IDs, names and active
  Adviser role, uses the normal assignment/history mechanism, and skips unrelated
  local/demo identities. Display primary Adviser separately from emergency
  coordinator. Preserve historical case reviewers and actions.
- [x] Rename Version history to Update history. Replace its dropdowns with a
  dated table, actor names and View changes dialogs, including prior values where
  a previous version exists.
- [x] Remove duplicate Seeker feedback text and repeated No written comments.
  Show a detail action only when there is longer content to read.
- [x] Add configurable emergency acknowledgment reminders (default 5 minutes)
  and stalled-progress follow-ups after acknowledgment (default 15 minutes).
  A progress follow-up is deduplicated until another documented action or
  Professional referral changes the progress checkpoint. Terminal/archived cases
  are excluded. These reminders do not acknowledge, resolve or close cases.
- [x] Simplify emergency review with compact case facts and explicit action
  buttons opening focused dialogs. Keep support-session status, emergency status,
  referral progress and Professional acceptance distinct. Preserve private-review
  permissions, required notes and action history.
- [x] Rebuild Adviser Emergency Management with Active / Resolved tabs, search,
  ten records per page, resolution-date ordering and a responsive table. Resolved
  details show the recorded outcome and history without reopening resolution
  forms. Existing case visibility and private-documentation gates still apply.

## Additional findings before push: queue interface and Adviser ownership

- [x] All allowed matching/review states now use `request.matching`. An urgent
  review flag no longer selects the separate status-page layout previously used
  for Adviser review. Genuine legacy review gates remain enforced.
- [x] Show matching progress and staff review as separate sections. Waiting
  requests retain actual queue position; invited, preparing and active states
  retain their existing controls. Display the responsible emergency coordinator
  separately from a screening reviewer. Resolved review does not rewind a live
  support session.
- [x] Correct the Prefer not to say explanation: matching can continue alongside
  review. Keep its classification unknown and match qualified Helpers; preserve
  the Yes answer, High classification and emergency queue priority.
- [x] Live reassignment messages use one short text announcement instead of
  inserting a second competing queue card. Untrusted Helper names remain text.
- [x] Centralize emergency coordinator selection and fallback. New cases prefer
  the active Helper supervisor, then the assigned screening reviewer, then an
  active Adviser with fewer pending reviews. Keep an existing active coordinator
  when Helper assignment changes. Do not rewrite an independently assigned
  screening reviewer.
- [x] Persist ownership before notifying staff. Initial alerts distinguish
  responsible coordinator, Helper supervisor, shared Adviser awareness and
  Moderator operational coordination. Names come from the stored coordinator;
  recipients use user-account IDs, not Adviser-profile IDs.
- [x] Add an authorized Transfer coordinator dialog with a reason, active target
  validation, terminal-case guard and a check that ownership has not changed since
  the form opened. Record old/new owners and assignment mode. Preserve original
  acknowledgment and historical actions; notify the new coordinator once.
- [x] Render current ownership in the notification feed, urgent dialog and inbox
  without rewriting stored notification history. Superseded assignment popups
  become non-actionable. Reconciliation does not resend archived/legacy alerts or
  repeat a fresh report delivered during coordinator recovery.
- [x] Limit action follow-ups to the coordinator, Helper supervisor and active
  Moderators. Other Advisers retain shared initial awareness. Distinct notification
  delivery, acknowledgment and resolution statuses remain separate.
- [x] Show primary Adviser and responsible coordinator consistently in emergency
  lists and coordination details. Shared viewers have no private-review button;
  authorized coordinators can open case documentation without gaining unrelated
  Helper-supervision permissions.
- [x] Validate the full regression run and additional ownership, queue and
  coordinator-recovery checks. See the exact results below.

These changes do not force Emergency #21 to another Adviser based on a screenshot.
An active recorded coordinator remains responsible unless an authorized handoff
is recorded. The live records and deployment are unchanged during local testing.

## Automated evidence

- Latest full backend run: **849 tests, 8,210 assertions, zero errors and one
  failure**. The failure caught unsafe raw legacy notification text in the urgent
  dialog. Presentation now uses safe role-specific summaries, including for
  unknown legacy titles.
- After that fix and the final changes, the affected suites passed:
  **115 backend tests, 1,319 assertions, zero failures or errors**. This includes
  the original failing privacy test, nine ownership tests, repeated Yes requests,
  Prefer not to say, live queue transitions, preserved legacy review gates,
  inactive target rejection, stale handoff forms and private-access checks.
- The earlier interface affected-feature run passed **115 tests, 1,254 assertions**
  with zero failures or errors. It covers voice consent/signaling, session deadlines, notifications,
  reconnection/replacement, identity validation/encryption, emergency visibility,
  competency comparison, documentation completion and the exact-ID correction.
- Earlier full backend run: 837 tests. Ten failures in old UI/redirect assertions
  and a clock-boundary assertion were corrected. The failed cases and new
  ownership/resolved-case checks passed in a 13-test rerun; the final affected
  feature run above also passed.
- JavaScript: **124 tests passed**, including toast visibility/deduplication,
  microphone/call handling, signal polling, incoming-panel behavior and safe queue
  event announcements without a second queue card.
- Syntax: **32 changed Blade views**, 89 PHP/JavaScript syntax checks and 13 inline
  script checks passed. `php artisan view:cache` and scoped `git diff --check`
  passed.
- `npm run build` passed. Generated service-worker output was excluded from the
  source edits.
- Tests used isolated SQLite databases. The local demo and live databases were
  not migrated or reseeded during verification.

## Deployment

1. Build and deploy the source changes through the existing Render process.
2. The normal startup migration runs
   `2026_10_23_000002_correct_confirmed_helper_supervision.php`. Confirm Helper #16
   now has primary Adviser #5 in Manage; if the account guard skips the correction,
   inspect the matching log notice rather than changing unrelated accounts.
   Historical emergency coordinators can still have different names.
3. Confirm the identity-vault migration
   `identity_vault/2026_10_23_000001_add_referral_demographics.php` succeeds on the
   separate vault connection. It adds nullable encrypted age/gender fields and
   keeps existing ciphertext.
4. Defaults are `EMERGENCY_REMINDER_MINUTES=5` and
   `EMERGENCY_PROGRESS_REMINDER_MINUTES=15`. Existing explicit environment values
   take precedence; zero disables the corresponding reminder. Keep the scheduler
   running for timed follow-ups.

## Live acceptance still required

- [ ] Inspect desktop and phone spacing, sidebar gutters, table scrolling,
  dialogs and the visibility/reach of session end controls.
- [ ] Test a phone-to-laptop voice call from an active text chat: explicit consent,
  microphone prompt, accept, two-way audio, messages and leave-call behavior.
- [ ] Verify each participant sees one toast during the actual final five minutes;
  refresh/open a second tab and confirm no duplicate or old-session replay.
- [ ] Verify the waiting Seeker sees queue position rather than a fabricated
  appointment time.
- [ ] Submit two fresh Yes requests and one Prefer not to say request on separate
  test accounts. Verify one queue layout, appropriate priority and separate staff
  review status through assignment, acceptance and review closure.
- [ ] On Emergency #21, compare the coordinator name and notification wording
  across the responsible Adviser, Helper supervisor and a shared Adviser viewer.
  Confirm shared awareness never appears as a personal assignment.
- [ ] Transfer a test emergency to another active Adviser. Confirm the new owner
  receives one assignment, stale forms are rejected, prior acknowledgment/history
  are preserved and the old assignment popup does not replay.
- [ ] Simulate sustained Helper disconnection, then reconnection, to check the
  10-/15-minute notices and cancellation of replacement offers.
- [ ] Confirm the live Helper #16 / Adviser #5 primary relationship and distinct
  historical coordinator labels.
- [ ] Check an active and a resolved emergency with authorized and unrelated
  Advisers; confirm search, pagination, outcome history and access boundaries.
- [ ] Submit referral identity with email only and with an explicitly authorized
  emergency contact; verify the assigned Professional sees only released fields.
- [ ] Complete both documentation and evaluation; confirm the success dialog
  disappears after dismissal/refresh and no completed form traps the user.

No browser surface was available to automation, so visual inspection and live
audio have not been claimed as verified.
