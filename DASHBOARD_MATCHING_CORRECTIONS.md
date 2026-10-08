# Dashboard, emergency and Helper matching corrections

Date: October 8, 2026. Scope: the latest requested emergency status, Helper status/matching, Adviser visual corrections and three operational dashboard summaries. OTP behavior was not modified by this task.

## Corrections

- Open incident queries now exclude legacy emergency incidents whose linked alerts are all terminal. This keeps resolved emergency cases out of Moderator lists and badges even before the existing reconciliation migration runs. Incidents without an alert and unrelated incident types preserve their existing behavior.
- Moderator emergency polling hides rows that have become terminal and updates the active total/empty state. Moderator dashboard totals and Helper/Adviser/Moderator sidebar initial values no longer use the stale 60-second cache.
- Helper matching uses the latest submitted readiness record through the authoritative eligibility service instead of independently prefiltering through a timestamp-based readiness relation. Legacy null review flags no longer exclude otherwise eligible candidates.
- Explicit Helper availability is honored even when duty relaxation is configured. Availability counts use eligibility rather than a stale stored operational label. Duty, verification, current readiness, supervision, risk competency, conflicts, consent and concurrency remain enforced according to existing configuration and workflow requirements. No new readiness bypass was added.
- Existing Adviser dashboard cards/tables retain their style. Cards allow horizontal overflow where needed and the detailed performance section remains available but is collapsed initially.

## Dashboard definitions

`app/Services/DashboardOverview.php` centralizes summaries. `resources/views/components/dashboard-overview.blade.php` provides labelled KPI cards, proportional bars, exact counts, definitions, recent activity and empty states. Charts use zero-based counts, actual category names and six chronological months in Asia/Manila. No chart library or frontend framework was added.

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
