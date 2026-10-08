# Adviser interface refinement

## Scope

Changes are limited to the Adviser workspace and the shared services/components needed to support it. Existing role permissions, matching eligibility, screening review, evaluations, referrals, and resource publication remain in place. No production records were changed during implementation.

## Interface changes

- Dashboard: removed the Awaiting Adviser Action summary, duplicated pending-action links, metric-definition section, and 30-day performance block. Removed the unused queries behind those sections. Kept actual case charts and supervision links. Helper and review previews have bounded scroll containers, sticky table headers, and working View All links.
- Reports: one filter panel and four groups: Sessions / Cases, Helper Performance, Emergency / Safety, and Activity History. The duplicate report presentation and filter form are gone. Both existing report services use the same Philippine Time interval. Case tables and exports use the same case-status and archive filters.
- Fixed a report filter that incorrectly queried `helpers.session_status`. Case status now filters sessions and related competency evidence, rather than a nonexistent Helper column.
- Screening reviews: removed the two top summary cards. Recorded screening answers, risk information, review decisions, and escalation actions remain available.
- Manage Helpers: replaced the decorative star with a clear Advanced / Expert competency label, using the existing competency count.
- Adviser-facing Helper labels use the existing registered first/last-name accessor. Seeker aliases and other roles' Helper aliases are unchanged. No Identity Vault access was added.
- Duty schedules: separate columns for Helper/date, all-day duty period, schedule state, current availability/readiness, and actions. Existing day-based duty rules remain unchanged. The page now uses the shared application layout and mobile sidebar.
- Resources: one emergency-directory editor at `adviser/resources?section=emergency`, linked from Resources. It uses the existing save endpoint, publication rules, and version history.
- Notifications: concise presentation, separate Archived Notifications view, owner-scoped restore action, and corrected unread badge invalidation after read/archive/restore.

## Archive behavior

Archive preserves the original record and business status. Restore clears only archive metadata; it does not reopen a concluded case.

- Cases: reuse existing `counseling_sessions.archived_at` and `archived_by` markers. Completed/evaluated cases must have reviewed documentation. Active cases, ongoing emergency work, open incident work, and ongoing referrals cannot be archived.
- Completed reviews: archive their concluded case through the completed-review list. Archived reviews remain retrievable through the Archived reviews filter.
- Duty days: only past duty days belonging to supervised Helpers can be archived. Archived days must be restored before editing or removing duty. Their original active/cancelled state is retained.
- Notifications: reuse existing notification archive/restore methods. The archived view and actions remain restricted to the account owner.
- Case and duty archive/restore actions are recorded in the existing audit log. No duplicate record or archive subsystem was created.
- Active Helper profiles are not archived because they are ongoing supervision and matching records.

## Deployment

Run the normal application migration process to apply:

`2026_10_09_100000_add_archive_markers_to_helper_schedules.php`

It adds nullable archive metadata to duty records without changing duty coverage or matching eligibility. No live database migration was performed during local validation. Deployment uses the `deploy` branch.

## Verification

- 95 related feature tests passed, including Adviser, role-based reporting, screening, scheduling, notification, dashboard, and module smoke checks.
- 12 additional implementation tests passed, including PDF export and date validation.
- Six new refinement tests cover report grouping/filtering, registered names and scope, case archive guards, duty archive guards, notification history/restore/badges, and the emergency-directory editor. All passed after adding export and archived-duty mutation checks.
- Production asset build passed.
- 286 PHP source/compiled-view files passed syntax validation; the new stylesheet parsed successfully.
- Responsive styles provide single-column stacking, readable scrollable tables, and accessible actions. Browser/device visual inspection was unavailable in this session and remains a manual QA step.

Existing unrelated changes in `OPENCODE_HANDOFF.md` were left untouched.

## Review shortcut removal

Removed the bulk Mark as reviewed control, selection checkboxes, optional shortcut note, and the Skip action. Both shortcut endpoints and controller methods are removed. Pending reports now require the existing structured competency evaluation to complete the review. Previously recorded reviews, completion notes, and audit history remain unchanged. Helper eligibility-review completion is a separate workflow and remains available.

Verification for shortcut removal: 32 related feature tests passed (235 assertions), including rejected legacy shortcut requests and successful structured evaluation submission.

## Transcript access UI

The Adviser transcript queue now uses compact expandable session rows with registered Helper names, recorded status, and Philippine Time dates. Each row retains the existing purpose-based access form. Failed validation returns to the relevant row with its purpose/reason preserved; whitespace-only or too-short reasons cannot grant access.

The authorized reading view separates Helper and Seeker messages, preserves the Seeker alias, shows recorded message timestamps in Philippine Time, and provides a readable scrollable conversation. Mobile layouts stack the form fields and keep actions accessible. Ten-minute authorization, current-supervision checks, and audit logging remain unchanged. Transcript verification returns to the access list and does not complete the separate competency evaluation.

Verification: 29 related feature tests passed (227 assertions), including invalid access, grant expiry, scope/privacy checks, verification authorization, and the verification redirect. Changed PHP files passed syntax checks; CSS and the accordion script parsed successfully. Browser/device visual inspection remains a manual QA step.
