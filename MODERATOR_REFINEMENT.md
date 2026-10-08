# COMPASS Moderator refinement

## Scope
Targeted Moderator changes using the supplied Reports and Emergency Alerts references. Existing matching, readiness, duty eligibility, adviser emergency ownership, other-role reports and Identity Vault permissions remain in place. No new data store, fabricated metric, or record deletion was added.

## Emergency definition
`ModeratorEmergencyCases` supplies the Dashboard, sidebar badge, Emergency Alerts, their status endpoint, and Moderator Reports.

- An operational emergency is the latest emergency alert per session. Alerts without a session remain distinct.
- An incident-only legacy emergency is included if its category is `emergency_flag` / `classification_emergency` or its risk is emergency, and the session has no alert. Only its latest emergency incident counts operationally.
- An alert is authoritative over its incident mirror. Duplicate mirrors and older duplicate records never inflate the operational count.
- Active excludes resolved, closed, cancelled, archived, and any record with an archive marker. Acknowledged / responding / escalated cases remain active.
- The active status chart partitions exactly the Active Emergencies KPI. Reports apply this definition inside their selected date period, so a filtered report can legitimately differ from the all-time dashboard.
- Historical duplicate alert events remain available in the centralized Activity Log's legacy fallback. No history is deleted.

## Dashboard and Reports
- Dashboard has three emergency KPIs, a small operational strip, existing real-data charts, and one Recent Activity feed. Live session monitoring remains in Active Sessions. Average Response / Resolution were removed from the Moderator overview.
- Reports follow the reference layout: a compact combined Date Range filter, four summary tiles, Status / Categories / Emergencies / Referrals overview tabs, a bar chart and donut showing the same records, and one always-visible Activity Log. Detailed operational/safety records and archive actions remain accessible through a disclosure beneath the overview. CSV export and backend pagination are retained.
- The shared activity query reuses append-only audit logs and legacy business timestamps. It sorts database timestamps, not relative-time strings, and never selects clinical narratives, identity-vault contents, IP addresses, or audit metadata.
- Date ranges are inclusive Philippine calendar dates converted to UTC query boundaries. No recorded response/resolution samples means "No data".
- Emergency Alerts follow the reference layout with open / escalated-today / acknowledgment-time / resolved-30-day metrics, a recorded workflow strip, a five-record paginated active-case table beside its priority donut, and published contacts below. Average acknowledgment time is limited to actual valid first-acknowledgment timestamps within the last 30 days and is absent from the Moderator Dashboard. Escalated Today uses recorded escalation/referral timestamps, not case detection dates. The first four workflow stages partition active cases once; Closed shows explicitly resolved/closed cases in the last 30 days. Emergency contacts are published resources below the active-case table. The page checks for changed operational cases and rolling metric totals every 30 seconds and reloads their complete table/charts together. Dashboard polling also checks the complete overview so an acknowledgment cannot leave its status chart stale while the active count stays unchanged. Resolution/escalation permissions remain with the responsible Adviser.

## Reference-layout validation (October 9, 2026)

- Report date ranges validate their type, calendar dates and ordering on the server and are limited to 366 inclusive days. The combined date picker applies the same limit in the interface. Legacy `from` / `to` query links are retained.
- Filter enums, search length, overview selection and paginator values are validated before queries/rendering. Empty overview values safely use the default tab. The Scheduled filter includes saved future appointments on pending requests, and Archived emergency filters include archive markers without changing the retained terminal status.
- Pending summary and chart counts exclude future scheduled appointments. Completed includes completed/evaluated records. Zero counts and missing timestamp samples display truthful empty states.
- Referral overview selects aggregate lifecycle counts only. It never fetches or renders referral narratives or Identity Vault fields.
- Activity records reuse the shared audit/legacy timeline with safe relational context for priority, staff helper, linked session and current status. Event outcome remains separate from current status; historical status is not invented from an audit outcome. Date, priority, applicable status, activity type, history and search filters affect its database query. Search matches exact `R-####` case references and supports case-insensitive actions/actors.
- Published contacts only, existing role authorization, CSRF-protected archive actions, and Adviser ownership of emergency actions remain intact.
- The changes use SVG charts and scoped CSS without new dependencies, decorative icons, duplicate stores or schema changes.

## Queue timeout
`StaleQueueRequests::expire()` runs every minute through the existing scheduler, before automatic matching, on stale direct matching attempts, on Moderator queue/dashboard loads, and before manual assignments.

- Waiting queue records at least 24 hours old expire instead of being deleted. `expired_at`, the existing audit log and owner notifications retain the decision.
- An ordinary unstarted request closes through the existing Seeker workflow service; cleanup and notifications are retained.
- Expiring an emergency queue entry NEVER resolves its emergency review. Its staff escalation and emergency resources remain available.
- Active / accepted sessions and future scheduled appointments are protected from expiry.
- Recently Matched initially loads five records, with history accessible through Reports. Existing assignment/reassignment/override eligibility paths remain unchanged.

## Archive
Migration `2026_10_09_000000_add_operational_archive_markers.php` adds nullable `archived_at` and `archived_by` markers to sessions, queues, alerts and incidents.

- Only a logged-in active Moderator can archive terminal records through the Reports history action.
- Active records and sessions with open emergency review cannot be archived.
- Status and relationships are preserved. Archive/restore is audited and idempotent.
- Restore removes the archive marker; it does not reopen a completed or resolved workflow.
- The Moderator Archive navigation links to the existing Reports history filter; no duplicate archive page is created. History filters expose archived records. There is no permanent deletion endpoint.

## Other views
- Manage helpers, workspace lists and unassigned pool use backend pagination with preserved query parameters.
- Connection Review can filter Active, declined replacement offers, Cancelled, Completed and history. A declined offer does not falsely terminate the still-active support session.
- Schedules show saved support appointment Helper, seeker alias, date, time, and status separately from existing duty schedules.
- Moderator notifications show a short title, one-sentence summary and PHT timestamp. Only identical non-emergency updates with the same destination/read state and five-minute window are grouped within a page; all child actions remain available. Emergencies stay individually visible.
- Moderator Settings previously dereferenced a missing Moderator profile. It now renders an unsaved fallback and creates/updates only the signed-in Moderator's profile transactionally on save.

## Deployment
Run the normal non-destructive migrations (`php artisan migrate --force`) through the existing deployment startup. Keep the scheduler enabled for unattended expiry. Do not use `migrate:fresh` on an existing database.

## Validation
See `tests/Feature/ModeratorRefinementTest.php` for regression coverage of count consistency, terminal states/mirrors, expiry and protected sessions, archival permissions/idempotency, Settings without a profile, pagination, Manila date boundaries, connection filters, appointment visibility and notifications.

Validation results:

- Full suite: 522 tests passed; one known pre-existing `SingleDeviceLoginTest::test_live_threshold_is_five_minutes_of_idle` failure remains outside the requested scope (five-minute login idle threshold).
- New Moderator regression suite: ten tests passed, including notification grouping and polling during status changes.
- Production `npm run build`: passed, including service-worker build.
- Final related regression run: 81 tests passed (509 assertions). PHP syntax checks passed for 19 changed application, route and migration files. Blade template compilation and compiled-template PHP syntax checks passed. A final follow-up run passed 24 tests (180 assertions) after polling verification was added.
- Browser/device visual review remains required; this environment currently has no controllable browser. Automated render tests verify server-side pages, not pixel layout or touch interaction.

Reference-layout follow-up verification: 129 related tests passed (870 assertions), followed by 31 tests (258 assertions) after scheduled/archive filter refinements, including seven new `ModeratorReferenceLayoutTest` checks. The production build, scoped CSS parser and inline JavaScript syntax checks passed. Browser/device pixel review remains unavailable in this environment.

## Manual responsive acceptance checks
After migration and deployment, review Dashboard, Reports, Emergency Alerts, queue, Manage, connections and schedules at desktop width, 768 px and 480 px:

1. Tables scroll inside their wrappers; the whole page must not overflow horizontally.
2. Report filters and date-range dialog remain keyboard accessible and usable on touch screens.
3. Emergency contacts appear below operational data; no private identity fields are visible.
4. Groups, pagination, archive/restore and explicit connection actions remain reachable.
5. Resolve an emergency as its assigned Adviser; refresh Moderator views and confirm it leaves the active KPI/chart/list together.

No live database changes or deployment were performed as part of this workspace implementation.
