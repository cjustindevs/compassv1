# Scoped COMPASS UI and Reports update

## Implemented

- Seeker Self-Help index, category and resource detail use one scoped mobile stylesheet. At <=768px, content grids stack, search controls wrap, media fits the viewport and spacing shrinks. At <=480px, content has 16px side padding, readable wrapping and 44px tap targets. Desktop styles are unchanged.
- Moderator Analytics navigation link removed. Existing DashboardOverview emergency KPIs, status/priority/trend charts and real data remain on the dashboard. The former analytics quick action scrolls to those dashboard summaries. Recent emergency activity uses the existing scoped overview data. Existing analytics endpoints remain for compatibility; no duplicate page or graph implementation was added.
- Moderator Emergency Alerts grid aligns cards at their natural height instead of stretching the active case container to the height of the side column. Bottom padding reduced. Emergency actions/status logic unchanged.
- Incoming Queue removes waiting/assigned removal controls from the UI. Assignment/reassignment selects include only Helpers allowed by the existing HelperEligibilityService for that specific request. Current assignee is excluded from reassignment targets. Mutation authorization/eligibility remains enforced server-side. Existing removal endpoint retained for compatibility.
- Schedules removes duplicate Shifts on this date block. Removing duty remains available in the existing Duty Schedules table through the same protected endpoint.
- Helper/Adviser Training and Analytics navigation links removed. Existing training and analytics workflows remain accessible to existing authorized integrations. Reports navigation remains; Helper now has a missing scoped Reports page.

## Role-based Reports

`RoleActivityReport` supplies real filtered summaries and paginated records (15 rows, query filters retained). One shared report component/style uses current COMPASS cards, controls and tables. No dashboard graphs copied into Reports.

- Helper: only authenticated Helper's assignments, case outcomes, acceptance intervals, planned duty dates and availability transitions. Foreign helper_id rejected. No system emergencies, private narratives or other Helper performance records shown.
- Adviser: existing AdviserAnalytics scope and report/export flow preserved. Added authorized status/category/outcome summaries, emergency records, acknowledgment/review intervals, resolution times and own actions. New case-status filter also applies to existing Adviser report and export queries. Unrelated Helpers denied.
- Moderator: operational case/queue/emergency tables, status and priority breakdowns, terminal/nonterminal counts, response/resolution intervals, referral coordination counts and own action records. Replaced report-wide competency score disclosure with role-appropriate operations data. Existing report and CSV routes use the same dataset; CSV includes all filtered rows, not only the current page.
- Admin: existing report catalog preserved with additional real system-level report data: users/roles, registrations, case activity, emergencies, queue matches, availability changes, timing and audit-action trends. Catalog preview/download availability remains as previously implemented.

## Definitions and privacy

- Case activity cohort: end_time, otherwise start_time, submitted_at, created_date or created_at. Completed/evaluated are completed; cancelled/no-show never count as completed.
- Emergency cohort: triggered_at, otherwise created_at. Resolved/closed are terminal, all other stored statuses unresolved.
- Response/acceptance intervals use explicit business timestamps. Missing/negative/future intervals excluded. Average minutes rounded to one decimal; no samples display No data.
- Adviser response combines trigger-to-acknowledgment, referral-create-to-review, and summary-submit-to-review events completed in the selected period for the authorized case cohort.
- Queue matching rate: entries with matched_date / queue entries in selected entry cohort, rounded to one decimal percent. Historical eligibility at entry is unavailable, so this is explicitly queue-entry matching rate.
- Dates entered in Asia/Manila, converted to UTC for database queries, displayed in Asia/Manila. Account totals/current Available Helpers are current snapshots, distinct from selected period activity.
- Duty records represent planned coverage, not attendance proof. Private identity, raw conversations, recordings, narratives, clinical notes and audit metadata are omitted.
- Guest, inactive-account and exact-role middleware plus service-level scope checks remain in force. No database migrations required.

## Verification

- Final targeted run: 57 tests, 347 assertions passed (Reports/navigation, Self-Help, queue, existing admin Reports, and system corrections).
- Full suite: 501 passes and 2 failures before final assertion adjustment. One legacy test expected the deliberately removed queue Remove button; updated assertion and retained backend removal tests now pass. The other is the pre-existing SingleDeviceLoginTest idle-threshold failure; authentication was not modified.
- Production build passed. Blade compilation and full route listing passed. New report/navigation tests cover own/foreign records, role rejection, inactive account, filter validation, 15-row pagination, known emergency timing fixtures, filtered CSV, queue eligibility, removed tabs and retained dashboard graphs.
- Browser inventory was empty and in-app browser unavailable. Phone/tablet screenshot validation could not be performed in this session. Do not treat the stylesheet/render checks as live browser verification.

## Manual checks after deployment

1. Self-Help index/category/detail at widths 320, 390, 480, 768 and desktop >768: verify full cards, wrapping, 16px phone padding, usable search/buttons/media and no horizontal page overflow. Test save/progress/complete as before.
2. Moderator dashboard: emergency graphs and recent emergency activity remain; sidebar has Reports but no Analytics. Dashboard summaries quick action scrolls to charts.
3. Emergency Alerts: active container height matches its contents rather than the side column; confirm acknowledgment/status updates unchanged.
4. Queue: no Remove controls; dropdown includes eligible ready Helpers only; assign/reassign still works and server rejects tampered IDs.
5. Schedules: no Shifts on this date block; create/view/remove duty via existing table works.
6. Reports: switch roles, validate own/supervised scope, dates/status/priority filters, pagination, empty data and Moderator CSV. Confirm cancelled cases are excluded from completed and resolved emergencies excluded from unresolved.

Changes are local until explicitly committed/pushed. No production database was modified.
