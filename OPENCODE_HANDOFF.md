# COMPASS handoff for OpenCode

Updated: October 7, 2026 (Asia/Manila)

This document records the recent changes and their verification status so development can continue without rebuilding existing features or losing unrelated work. It is a handoff, not a claim that every historical requirement has been completed.

## Repository and publication status

- Working repository: `C:\Users\CJ LUMANGLAS\Downloads\Compass Source Codes\Compassv1`
- Current branch: `deploy`
- GitHub repository: https://github.com/cjustindevs/compassv1
- Hosted application: https://compassv1.onrender.com
- Latest implementation commit: `c8cacdd`.
- The push of `c8cacdd` to `origin/deploy` succeeded. Render deployment and behavior after that push have not been independently verified.
- This handoff file is newly created locally; its creation does not publish it to GitHub.

## Recent committed changes

| Commit | Change |
| --- | --- |
| `c8cacdd` | Refresh Helper sidebar statistics and correct competency percentage conversion. |
| `a767d8b` | Connect emergency temporary peer support, review reminders, and safe reconnection handoffs. |
| `6f18145` | Remove empty decorative icon containers from notification cards. |
| `f803d4f` | Remove redundant Seeker home action cards while retaining the main action buttons and daily check-in. |
| `16b3204` | Remove decorative icons and emojis while preserving functional navigation and control icons. |
| `834f0c2` | Remove Moderator escalation action and block direct requests to that operation. |
| `61e387d` | Organize Adviser emergency review into responsive case/history and action sections. |

Earlier work also corrected corrupted UI punctuation, improved Moderator connection review, and limited the duty scheduling selection to logged-in, ready Helpers. Consult Git history for the exact earlier implementation rather than assuming every older request is covered by the commits above.

## Product decisions to preserve

- The Adviser coordinates emergency review and preparation for professional support.
- The Moderator is the backup operational coordinator.
- A qualified Helper provides temporary peer support while emergency coordination continues. The Helper does not gain authority to resolve the emergency review or disclose identity.
- An emergency alert must exist independently of Helper matching. No available Helper, a declined offer, or connection loss must not silently resolve the emergency.
- Ordinary referral approval, Seeker consent, identity storage, authorized disclosure, and professional assignment remain separate steps.
- Keep identity information out of ordinary chat messages, queues, notifications, and logs. Continue using the existing Identity Vault authorization services.
- Remove decorative icons and emojis only. Preserve functional menu, navigation, close, send, password visibility, and other control icons.
- Reuse the existing Laravel, Blade, JavaScript, and COMPASS green-and-white design. Preserve unrelated user changes.

## Helper sidebar statistics

### Problem and correction

The sidebar previously calculated values only during page rendering. It also displayed a normalized competency score on a 1-5 scale directly as a percentage: a score of 3 appeared as 3% instead of 60%.

`HelperSidebarStats` now provides a shared payload for both initial rendering and the existing Helper readiness status endpoint:

- Sessions: count of the Helper's session records, including statuses other than completed. It is not a completed-session metric.
- Competency: latest normalized competency score multiplied by 20 and rounded to one decimal place. Missing evaluation data displays `No data`.
- Availability: label and eligibility state from `HelperEligibilityService`, rather than a stale stored label.

The browser refreshes every 15 seconds and on focus, visibility restoration, and return online. It skips hidden/offline polling, prevents overlapping requests, times out requests, preserves the last values on network errors, and stops on authentication/session failures. This is polling, not instantaneous WebSocket delivery.

### Main files

- `app/Services/HelperSidebarStats.php`
- `app/Http/ViewComposers/SidebarComposer.php`
- `app/Http/Controllers/Helper/HelperReadinessController.php`
- `resources/views/layouts/partials/helper-sidebar.blade.php`
- `public/js/helper-sidebar-stats.js`
- `tests/Feature/HelperSidebarStatsTest.php`

The status response uses private, no-store caching. No database migration was added for sidebar polling.

## Emergency support and reconnection

### Implemented sequence

1. An emergency classification creates or reuses an emergency alert immediately. Escalation creation is transactional and duplicate submissions reuse the existing alert.
2. The assigned Adviser is notified when available. An alert without an active Adviser remains pending; the system does not claim that professional support has already been arranged.
3. An unassigned emergency request can enter temporary peer-support matching while the alert remains open.
4. Only eligible Helpers can receive offers. Existing active-account, supervision, readiness, competency, duty, capacity, conflict, and consent checks remain applicable. Emergency support does not authorize bypassing these checks.
5. Declined or released offers preserve the alert and notify operational coordination. Previously declined or expired offers are excluded from subsequent matching as implemented through audit metadata.
6. The Seeker status page shows the temporary support state and access to managed emergency resources.
7. Connection replacement follows the existing Seeker choice, Moderator offer, and Helper acceptance process. The continuation session links to the original emergency alert and keeps its review responsibility. Previous chat messages are not copied into the replacement chat.
8. Adviser acknowledgment and resolution remain separate from temporary chat progress. Professional acceptance does not automatically close the peer-support chat.

Open emergency requests are protected from ordinary stale-request cancellation and Moderator queue removal. Existing session-duration policy remains in place; chat duration and emergency review are separate concerns.

### Main implementation locations

- `app/Services/EmergencyEscalationService.php`
- `app/Services/HelperEligibilityService.php`
- `app/Services/HelperMatchingService.php`
- `app/Services/SessionReconnectionService.php`
- `app/Services/EmergencyReviewReminders.php`
- `config/emergency.php`
- `routes/console.php`
- `resources/views/request/status.blade.php`
- `database/migrations/2026_10_07_120000_link_emergency_support_continuations.php`

Related controllers, maintenance services, session scopes, and tests were updated in `a767d8b`; use `git show --stat a767d8b` for the complete file list.

### Migration and reminders

The migration adds nullable `counseling_sessions.support_emergency_alert_id` linked to `emergency_alerts`, with restricted deletion and a reversible migration. It was exercised against the test database. Production application of this migration has not been verified.

Timed review reminders use `EMERGENCY_REMINDER_MINUTES`. The default is `0`, which disables timed reminders because an institutional interval has not been approved. When configured, reminders are deduplicated, target the assigned active Adviser and active Moderators, and stop after acknowledgment or resolution. A Laravel scheduler must be running for scheduled reminders.

## UI cleanup

- Adviser emergency review uses a responsive two-column arrangement, with case details/history separated from action forms.
- Moderator escalation controls were removed; backend authorization also rejects the removed operation.
- Decorative icon markup and unnecessary wrappers were removed across views and related scripts/styles.
- Notification cards no longer reserve empty decorative icon boxes.
- Seeker home retains the primary Talk to Someone and Self-Help buttons, plus daily check-in, without the duplicate action-card row.

These changes have automated/render compilation checks described below. Browser screenshots and mobile visual QA have not been independently completed for the latest changes.

## Validation record

These are results from prior implementation runs, not tests rerun while writing this document.

| Check | Recorded result |
| --- | --- |
| Helper sidebar tests | 1 test, 8 assertions passed; includes endpoint authorization, no-data behavior, conversion, and caching. |
| Emergency/reconnection targeted suite | Final targeted run: 70 tests, 973 assertions passed. |
| Full suite during emergency implementation | 445 passed, 6 failed, 3,221 assertions. |
| Follow-up runs after addressing those failures | Focused runs passed: 4 tests/36 assertions and 11 tests/73 assertions. |
| Full suite after final corrections | Not rerun; do not report the entire suite as clean yet. |
| Frontend build during emergency implementation | `npm run build` passed. |
| Blade compilation | Passed for emergency changes and latest sidebar changes. |
| PHP syntax, relevant route listing, diff checks | Passed during the applicable implementation work. |
| Live Render and latest mobile/browser behavior | Not independently verified. |

The full-suite failures included two outdated emergency-no-queue expectations and four Moderator authorization/duty fixture issues. Focused reruns passed after corrections. A fresh complete run remains the next verification step.

## Pending local work: inspect before committing

The repository is not clean. The following files were staged before this handoff and were not included in the recent implementation commits:

```text
OTP_HOSTING.md
app/Console/Commands/DiagnoseOtpMail.php
app/Http/Controllers/Auth/OTPController.php
app/Services/OtpMailConfiguration.php
public/sw.js
render.yaml
resources/views/auth/pseudonymous-register.blade.php
tests/Feature/Auth/RegistrationTest.php
```

`public/sw.js` has both staged and unstaged modifications; builds may regenerate it. Review both versions before committing it.

Git also reports missing/deleted files under `storage/tools/pdf-reader` and directory warnings. They were not part of the requested application changes. Do not stage those deletions as part of an unrelated fix.

### OTP work status

The pending OTP work includes mail configuration diagnostics and changes intended to avoid exposing OTP codes through the browser/demo fallback. Read `OTP_HOSTING.md` and inspect the staged diff for the precise behavior. It has not been published by the commits listed above, and successful hosted email delivery has not been verified. Render environment settings and effective cached Laravel configuration must be checked separately from the local `.env`.

## Remaining checks and limitations

- Rerun the complete automated suite after reviewing pending OTP changes.
- Verify the deployed commit and migration state in Render startup logs.
- Perform mobile and desktop visual checks for the emergency pages, notification cards, and sidebar polling.
- Confirm the approved reminder interval before enabling timed reminders.
- Historical emergency records are not automatically backfilled into the new support queue by this change.
- Review production configuration of existing `RELAX_DUTY_HOURS` behavior; do not assume a test relaxation represents the approved production eligibility policy.
- Existing Adviser selection/fallback behavior is not an approved on-duty emergency roster. Review that policy before replacing it or expanding access.
- No automatic identity disclosure or new clinical authority was introduced.
- This document does not establish completion of the entire Adviser module or all earlier user requests.

## Recommended OpenCode continuation

1. Read repository instructions if present, then inspect `git status`, `git diff`, and `git diff --cached` before editing.
2. Read this document and `EMERGENCY_SUPPORT_IMPLEMENTATION.md`. Where older notes say changes were not pushed, the publication status above records the later successful GitHub push; live deployment remains unverified.
3. Inspect `c8cacdd` and `a767d8b`, and preserve their authorization and workflow boundaries.
4. Review the staged OTP changes separately. Never commit `.env`, credentials, tokens, or confidential identity/session data.
5. Confirm the test database configuration before running tests. Existing tests use SQLite in memory. Never run `migrate:fresh` against production or an unknown database.
6. Run appropriate tests and build checks. In this Windows environment, PHP is available at `C:\xampp\php\php.exe`.

```powershell
$env:LOG_CHANNEL = 'null'
& 'C:\xampp\php\php.exe' artisan test
& 'C:\xampp\php\php.exe' artisan route:list
npm run build
```

7. For deployment, confirm the intended database and use the existing startup migration mechanism. If running migrations explicitly against the intended deployment environment, use `php artisan migrate --force`, never `migrate:fresh`. Verify startup logs afterward. Do not assume a local migration updates Render.
8. Commit only reviewed task files. Avoid `git add .` while unrelated staged OTP work and PDF-tool deletions are present.
9. Push to `origin deploy` only when the requested changes and checks are ready. A successful push is not proof of a successful Render deployment.

### Manual verification scenarios

- Helper: open the sidebar, change readiness/availability, and confirm updates within the polling interval or after returning focus. Verify a normalized competency score of 3 displays as 60%.
- Emergency with no eligible Helper: confirm the review remains open, coordination is notified, and the Seeker sees clear support status/resources.
- Emergency Helper decline: confirm the request remains open and the same declined Helper is not immediately offered again.
- Emergency reconnection: confirm Moderator-led replacement links to the original alert, preserves the Adviser, and does not disclose the old chat to the replacement Helper.
- Adviser: acknowledge and resolve an authorized emergency; verify action history and appropriate authorization.
- UI: inspect notification cards and emergency review on mobile and desktop, including focus, wrapping, empty states, and reachable controls.
- Deployment: confirm the expected commit, successful migration logs, scheduler operation if reminders are enabled, and absence of new application exceptions.
