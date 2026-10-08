# Dashboard, emergency and Helper matching corrections

Date: October 8, 2026. Scope: the latest requested emergency status, Helper status/matching, Adviser visual corrections and three operational dashboard summaries. OTP behavior was not modified by this task.

## Corrections

- Open incident queries now exclude legacy emergency incidents whose linked alerts are all terminal. This keeps resolved emergency cases out of Moderator lists and badges even before the existing reconciliation migration runs. Incidents without an alert and unrelated incident types preserve their existing behavior.
- Moderator emergency polling hides rows that have become terminal and updates the active total/empty state. Moderator dashboard totals and Helper/Adviser/Moderator sidebar initial values no longer use the stale 60-second cache.
- Helper matching uses the latest submitted readiness record through the authoritative eligibility service instead of independently prefiltering through a timestamp-based readiness relation. Legacy null review flags no longer exclude otherwise eligible candidates.
- Explicit Helper availability is honored even when duty relaxation is configured. Availability counts use eligibility rather than a stale stored operational label. Duty, verification, current readiness, supervision, risk competency, conflicts, consent and concurrency remain enforced according to existing configuration and workflow requirements. No new readiness bypass was added.
- Existing Adviser dashboard cards/tables retain their style. Cards allow horizontal overflow where needed and the detailed performance section remains available but is collapsed initially.

## Dashboard definitions

`app/Services/DashboardOverview.php` centralizes summaries. `resources/views/components/dashboard-overview.blade.php` provides compact KPI cards, SVG line graphs for monthly trends, doughnut charts for status/role distributions, horizontal category bars, exact counts, expandable definitions and empty states. Charts use zero-based counts, actual category names and six chronological months in Asia/Manila. No chart library or frontend framework was added.

- Case = peer-support request/session. Cancelled and no-show requests are excluded from case summaries. Completed/evaluated sessions are resolved support cases; active sessions are active; requests with open emergency review are escalated; other nonterminal requests are pending. Emergency resolution is independent of chat closure.
- Awaiting Adviser action = distinct authorized case IDs with unreviewed documentation, pending referral review or unacknowledged emergency review.
- Emergency = canonical emergency alert. Resolved/closed alerts are terminal and never active. Open alerts appear exactly once as unacknowledged active, acknowledged responding, or professionally escalated.
- Response = explicit trigger-to-acknowledgment, referral-created-to-reviewed, or summary-submitted-to-reviewed timestamps. Missing business events are excluded; updated_at is not a substitute.
- Emergency resolution = trigger-to-resolution. Averages use events completed during the last 30 days, in minutes rounded to one decimal. Invalid/future intervals are excluded; no samples display No data.
- Successful match = queue entry with matched_date. Rate = matched entries / all queue entries, rounded to one decimal percent. Historical eligibility-at-entry is not recorded, so the metric explicitly describes queue-entry success instead of claiming an eligible-Seeker denominator.
- Active accounts = enabled accounts, not online presence. Available Helpers = current matching eligibility. User roles include every recorded role, including Professionals.
- Registration trend = new accounts per month. System activity trend = recorded audit events per month, with no sensitive descriptions exposed.
- Adviser data stays within supervised or explicitly authorized cases. Admin and Moderator receive aggregate operational summaries. New recent activity summaries omit identity and narrative details.

## Validation and limitations

New tests cover event-based emergency times/statuses, empty denominators, queue matching rate, Adviser scope, legacy terminal emergency exclusion, latest readiness, explicit unavailability, an actual queue assignment, dashboard rendering, and role rejection.

Targeted run passed: 19 tests, 108 assertions, including fresh-sidebar rendering assertions. Dashboard page rendering, Blade compilation, route listing and frontend build passed. The full suite run had 484 passes and one failure in the existing SingleDeviceLoginTest idle-threshold scenario, which also failed independently; its workflow was not changed. These edits have not been visually verified in a live browser or deployed to Render during this task. Existing historical reconciliation migration remains applicable. No new migration or destructive database command was introduced.

Dashboard totals are current/all-time, trend charts cover six months, and duration averages cover events in the last 30 days. Detailed Analytics and Reports remain separate and unchanged.

## Dashboard visual integration revision

Adviser, Moderator and Admin dashboards now reuse one responsive chart component and a scoped stylesheet loaded by all three existing layouts. Trend charts use zero-based count axes; doughnut legends and category bars show exact counts. Expanded data tables provide an accessible alternative. Long metric definitions moved out of KPI cards into expandable details, and large time values are formatted in hours/days while preserving original minutes in definitions. Duplicate KPI rows and additional recent-activity panels were removed; existing role-specific activity sections, review links and operational controls remain. Moderator emergency polling continues to update the primary emergency count.

This revision changes presentation only; analytics formulas and record scope are unchanged. Dashboard rendering tests pass (8 tests, 37 assertions). Blade compilation and frontend production build were checked separately. Live Render/mobile visual confirmation remains required.

## Matching retry follow-up

Opening an eligible Helper dashboard now retries existing waiting requests, using the existing matching service and all current eligibility rules. Queue retries release expired/ineligible pending offers through the existing audited maintenance flow before recalculating capacity. A failed request is reported individually and does not prevent other waiting requests from being processed. No verification, readiness, duty, competency, consent, conflict or workload requirements were removed.

Regression coverage in `HelperQueueRetryTest` verifies dashboard-triggered matching, duplicate-notification prevention, expired-readiness exclusion, expired-offer reassignment, failed-request isolation and required Seeker consent. Combined matching/dashboard/Helper security checks: 42 tests, 536 assertions passed. The local configured database had no waiting requests and no currently eligible Helpers; this does not establish the cause of the reported Render request. Hosted Helper eligibility and queue state still require confirmation. No database migration is required.

## Waiting-screen and candidate reservation follow-up

Removed the incorrect warning that blamed verification merely because an unrelated active Helper was unverified. The waiting screen now explains capacity/eligibility accurately, identifies missing current Seeker consent when applicable, and calculates current queue position in priority/FIFO order without counting the request twice. Automatic matching tries the next ranked eligible Helper when the first reservation is rejected under lock. Eligibility requirements remain unchanged.

The container scheduler now exposes its output in Render logs and restarts after an exit instead of silently stopping automatic background matching. Combined matching, Helper security, Seeker workflow, Moderator queue and dashboard tests pass: 82 tests, 1014 assertions. Hosted Helper status and database state remain unverified; the waiting screenshot alone cannot establish that any Helper is currently eligible. No migration is required.

## Completing Helper assignment review

The reported Helper status was Under review: this is an intentional matching restriction created after repeated missed offers. The recovery action was missing. Assigned active Advisers can now complete review from Manage Helpers > Helper Detail, recording a required outcome and next steps. The transaction preserves prior restriction reason/count in an audit event, resets the restriction and missed-offer counter, sends one notification and retries matching. Repeated submissions do not duplicate completion events. All other eligibility requirements remain in force; no existing database restrictions were cleared automatically. Tests cover assigned versus unrelated Adviser authority, Helper self-clear rejection, required reason, audit/notification idempotency and expired-readiness exclusion.
