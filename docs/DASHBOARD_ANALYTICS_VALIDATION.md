# Dashboard analytics and Reports — first phase

Scope: System Admin and Psychology Professional only. No navigation tab, PostHog integration, workflow changes, schema migrations, or report-generation service is added. Other roles retain their pages and report behavior.

## Presentation

- Admin keeps its account cards and recent administrative activity. The intervening analytics section contains registration and support-activity line charts, plus current accounts-by-role bars.
- Professional keeps current pending/active workload and actionable case lists above the two charts: received/completed referral events and status of the received cohort. Completed referrals and acceptance time cards describe the selected period.
- The existing Chart.js library is bundled locally for these pages, loaded only when a chart has data. Monotone curves preserve real extrema; tooltips and expandable exact-value tables provide the recorded counts. Failed chart loading leaves the tables usable.
- Tables scroll inside their containers on mobile. Chart grids stack. Admin theme changes refresh chart text/grid colors, and reduced-motion preferences disable animation.

## Sources and definitions

The default range is 30 Philippine calendar days including today. Custom dates are inclusive in Asia/Manila and translated to half-open UTC intervals. Future events are excluded. Trends use daily buckets up to 62 days, monthly buckets for longer periods. Empty count buckets are genuine zeros; unknown timings remain unavailable.

| Measure | Source and rule |
| --- | --- |
| Registrations | `users.created_at`, one account per record. Current account totals and role distribution are independent of dates. Enabled accounts do not mean online users. |
| Requests submitted | One `counseling_sessions` record per support case, using `submitted_at`, then legacy `created_date`, then `created_at`. This counts support requests, not queue re-entries. |
| Sessions completed | Status completed/evaluated and recorded `end_time` within the range. Missing completion dates cannot be assigned to a trend bucket. |
| Professional received | Approved, consented referrals assigned to this Professional, using `professional_notified_at`. Missing notification dates use a visibly labeled legacy creation-date fallback. |
| Professional completed | Authorized completed/closed referrals with `completed_at`, or `closed_date` only for legacy completed-status records. Counted by completion event date, even if received earlier. Closed-only cases are not called completed. |
| Professional closed | Authorized closed-status records using `closed_date`. |
| Acceptance time | Mean hours from `professional_notified_at` to `accepted_at`, with acceptance in the selected period. Both events are required; negative/future intervals are excluded. Later edits cannot change this measure. No valid samples displays No data. |
| Acceptance rate | Valid recorded acceptances among the referrals received in the period, divided by that received cohort. This is not a count of decisions made in the period and excludes invalid pre-receipt/future acceptance dates. |
| Referral status distribution | Current statuses of the authorized received cohort. It does not reconstruct historical statuses at the end of the period. |
| Current Professional workload | Pending/active authorized referrals whose linked session is not archived. Historical authorized reporting retains archived cases. |

Professional reporting and CSV share the same authorization query. Unapproved, unconsented, and another Professional's referrals are excluded. The existing workflow removes/reassigns declined work, so this phase does not claim to reconstruct each Professional's historical declines. It does not expand read access.

## Report behavior

Admin keeps its existing reports, pagination, and catalog. Period totals for registrations, submitted requests, and completed sessions correspond to the Dashboard. Case date basis can select latest activity, request submission, or completion. Case-status filters apply to the case table; named overall event totals intentionally cover the whole period. Account registrations have their own paginated table, with account references, roles, enabled status, and dates. Unavailable catalog generation/export actions remain unavailable.

Professional Reports uses dates, a received/completed/closed date basis, and an authorized-status filter. Status applies to summaries and table records; each summary uses its named event date. The table has 15 records per page and exposes received, completed, and closed dates independently. CSV streams all matching records in batches, preserves the filters and authorization scope, uses Philippine Time, and escapes spreadsheet formula prefixes. Printing covers only the visible page; CSV is the complete filtered export.

## Manual review

1. Log in as System Admin. Open Dashboard. Confirm account cards and audit activity are retained, charts match COMPASS styling, and View records / View report are buttons.
2. Apply a known single-day range. Compare registration, submitted request, and completed-session totals with Reports. Use request-submission/completion date basis to compare the appropriate case table. Registration links open the registration section.
3. Change the range. Historical charts change; current account distribution remains the same. Check a range with no support activity and invalid/reversed dates.
4. Log in as Professional. Confirm referral acceptance, scheduling, and case actions still work. Current workload remains separate from period-based counts.
5. Apply the same Dashboard/Reports dates. Compare received counts with Received date basis, and completion counts with Completed date basis. Completed and closed dates should not be confused.
6. Filter by an authorized status. Change report pages, then export CSV. Confirm the filter survives pagination and the CSV contains all matching pages, with no private narratives or another Professional's records.
7. Check legacy records: creation fallback is labeled; missing acceptance events display No data; completed records without usable event dates do not appear as invented timeline entries.
8. At desktop, tablet, and phone widths, check chart labels, legends, filters, buttons, and table scrolling. The page itself should not scroll horizontally. In Admin, also check dark theme and reduced motion.
9. Check exact-value tables and empty states. In browser developer tools, verify chart rendering has no console errors; blocking chart assets should leave a readable fallback.
10. Review both roles before approving a later phase for Moderator, Adviser, Helper, or Seeker.

## Changed areas

- `app/Services/AnalyticsPeriod.php`, `AdminDashboardAnalytics.php`, `ProfessionalAnalytics.php`: dates, SQL trend aggregation, shared reporting rules.
- Admin Dashboard controller/view and Admin activity-report component; Admin branch of `RoleActivityReport.php`: charts and matching report records.
- Professional Dashboard/Reports controllers and views: integrated charts, valid event timings, scoped pagination/export.
- Shared analytics Blade components, CSS, JavaScript, Vite entry, and pinned Chart.js dependency.
- `tests/Feature/DashboardAnalyticsTest.php`, `tests/js/dashboard-analytics.test.mjs`: boundaries, permission/export consistency, archive handling, missing data, chart configuration.

Validation: full PHP suite passed (681 tests, 6363 assertions); final focused regression run passed (12 tests, 108 assertions) after the last report refinements. All 18 JavaScript tests passed. Production Vite/PWA build passed, and the targeted Git whitespace check passed. The tracked service worker was restored after build verification to avoid including generated output in the source changes.

No live browser was available in the tool session. Visual inspection, browser-console checks, and live-site testing remain manual review items. No commit or push is included in this task.
