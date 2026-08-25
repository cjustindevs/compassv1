# COMPASS Repository Handoff

Last updated: 2026-08-14

## Project snapshot

COMPASS is a Laravel 12 peer-support application for Project Dial-A-Friend at Divine Word College of Calapan. The backend uses PHP 8.2+ and Eloquent; the UI uses Blade, Tailwind/standalone CSS, Alpine.js, and Vite. The configured local database is PostgreSQL.

The application currently recognizes these five product roles:

- Help seeker (`users.role = seeker`)
- Helper (`users.role = helper`)
- Adviser (`users.role = adviser`)
- Psychology professional (`users.role = professional`)
- System administrator (`users.role = admin`)

Older repository code also contains a `moderator` role. Do not remove or rename it without checking its dashboard, model, migration, and existing data.

Authentication uses one `web` session guard, the `users` provider, and `App\Models\User` for every portal. A system administrator is identified by `users.role = admin`; the separate `system_administrators` table is optional profile data and is not part of authentication.

## Current authentication architecture: one shared login

The dedicated Admin login was retired on 2026-08-14. All roles now authenticate through the existing COMPASS form at `GET/POST /login` using a normalized, case-insensitive email and password. The form keeps remember-me, shared forgot-password, seeker-only public registration, generic credential errors, an accessible password visibility control, and responsive COMPASS styling. There is no frontend role selector.

Role routing is centralized in `App\Support\RoleDashboard` and reads only the authenticated server-side `users.role` value:

- `admin` → `admin.dashboard` (`/admin/dashboard`)
- `moderator` → `moderator.dashboard`
- `adviser` → `adviser.dashboard`
- `professional` → `professional.dashboard`
- `helper` → `helper.dashboard`
- `seeker` → `seeker.dashboard`
- Unknown roles fail authorization rather than falling through to a seeker portal.

The resolver is used by the shared login controller, `/dashboard`, authenticated-user guest redirects, and the shared navigation. Login intentionally ignores a stored `url.intended` value and sends the account to its authorized role portal, preventing a helper who first requested an Admin URL from being redirected back to that URL after sign-in.

Compatibility and security behavior:

- `GET /admin/login` (`admin.login`) is retained only as a guest-only redirect to `/login`; it renders no form.
- There is no `POST /admin/login` or `admin.login.store` route.
- `AdminAuthenticatedSessionController`, `AdminLoginRequest`, and `resources/views/auth/admin-login.blade.php` were removed after confirming no production references remained.
- Every Admin page still requires `auth` plus `EnsureUserIsAdministrator`, so authenticated non-Admins receive 403.
- Every unauthenticated protected route, including `/admin/*`, redirects to `/login` through `bootstrap/app.php`.
- Authenticated users reopening `/login` are redirected directly to their own portal.
- Shared logout invalidates the session, regenerates the CSRF token, and redirects to `/login`.
- Successful Admin sign-ins and failed password attempts against a known Admin email continue to append sanitized Audit Log events. The browser always receives Laravel's generic credential failure response.
- Login retains Laravel's five-attempt per email/IP rate limiting, session regeneration, remember-me behavior, shared password broker, and `web` guard. No second Admin guard or cookie exists.
- The user table has no active/suspended/deactivated account field and the repository has no authenticator-app 2FA backend, so no unsupported account-status or 2FA behavior was invented.
- Public registration stores no role from the request and the database defaults new accounts to `seeker`; privileged roles remain provisioned through authorized Admin user management.

Unified authentication files added:

- `app/Support/RoleDashboard.php`

Unified authentication files updated:

- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Requests/Auth/LoginRequest.php`
- `bootstrap/app.php`
- `routes/web.php`
- `resources/views/auth/login.blade.php`
- `resources/views/layouts/navigation.blade.php`
- `tests/Feature/Auth/AdminAuthenticationTest.php`
- `tests/Feature/Auth/AuthenticationTest.php`
- Admin feature tests now expect the shared login destination.

Unified authentication files removed:

- `app/Http/Controllers/Auth/AdminAuthenticatedSessionController.php`
- `app/Http/Requests/Auth/AdminLoginRequest.php`
- `resources/views/auth/admin-login.blade.php`

Focused authentication/Admin verification covers all six role redirects, old Admin URL compatibility, authenticated `/login` behavior, intended-URL isolation, generic invalid-password handling, Admin login auditing, logout, guest Admin redirects, non-Admin 403 enforcement, five-attempt rate limiting, and session-ID regeneration.

Unified authentication verification:

- `php artisan test tests/Feature/Auth/AdminAuthenticationTest.php`: 17 tests passed, 78 assertions.
- Repository-wide result: 84 tests passed and 6 failed (416 assertions after the Admin shell coverage was expanded).
- The former stale shared-login redirect failure is fixed. The six remaining unrelated failures are four guest-layout screens requiring the missing Vite manifest and two profile tests expecting `/profile` instead of the controller's existing `/profile/edit` redirect.
- Laravel Pint, PHP syntax, Blade compilation, login route inspection, and `git diff --check` pass.

Continue to preserve `users.role` and `EnsureUserIsAdministrator` as the authorization source unless an explicit role-model migration is planned.

## Completed: system administrator dashboard

The `/admin/dashboard` placeholder has been replaced at the routing/controller level with a reusable, responsive System Overview implementation based on the supplied dashboard reference. The original `resources/views/dashboard/admin.blade.php` remains an unused UTF-16 legacy placeholder; the live controller intentionally renders the UTF-8 view at `resources/views/admin/dashboard.blade.php`.

Architecture:

- `App\Http\Controllers\Admin\DashboardController` renders the overview and owns all clearly isolated demo dashboard data. Replace `mockDashboardData()` with services or API-backed view models when real monitoring sources are available.
- `resources/views/components/admin/layout.blade.php` provides the reusable admin shell.
- `sidebar.blade.php` contains grouped Platform, System, and Account navigation plus dynamic admin identity and the existing logout flow.
- `header.blade.php` contains page context, prepared global search, notifications, dynamic admin identity, and a logout dropdown.
- `stat-card.blade.php`, `status-card.blade.php`, `chart-card.blade.php`, `activity-feed.blade.php`, and `recent-logs.blade.php` render data-driven dashboard sections.
- `icon.blade.php` is a dependency-free outline SVG icon set for the admin portal.
- `public/css/admin-dashboard.css` and `public/js/admin-dashboard.js` are admin-only static assets. They avoid the missing local Vite manifest and do not affect other user portals.

Dashboard behavior and scope:

- Desktop has a fixed sidebar, sticky header, four-column statistics/status grids, three charts, and a two-thirds/one-third activity/log grid.
- Breakpoints reduce grids to two and then one column. Below 980px the sidebar becomes an accessible drawer with backdrop, Escape handling, and focus behavior.
- Charts are responsive native SVG line/area/bar charts; no chart dependency was added because the repository has none.
- `Cmd/Ctrl + K` focuses the prepared GET search field. Submitted searches show an explicit integration-ready notice; no backend search endpoint was invented.
- The non-dashboard sidebar modules display as intentionally inactive prepared destinations with their future paths stored in `data-future-route`; no placeholder routes or pages were created.
- Guest requests to `/admin/*` are directed to the shared `/login`; authenticated non-admin access remains a 403 through `EnsureUserIsAdministrator`.

Dashboard mock data currently includes primary stats, system statuses, three chart series, six activity events, and six recent audit entries. Actual authenticated administrator name, initials, and email are dynamic.

Dashboard verification:

- `php artisan test tests/Feature/Auth/AdminAuthenticationTest.php`
- Result: 8 tests passed, 35 assertions.
- Blade view caching succeeds.
- Laravel Pint, PHP syntax, JavaScript syntax, static asset existence, registered admin routes, and `git diff --check` all pass.

## Completed: administrator user management

The protected `/admin/users` module now reuses the existing admin shell and provides a responsive user directory based on the supplied reference. The sidebar's Users item is a real link and receives the same active treatment as Dashboard without changing the shared visual design.

Routes and authorization:

- `GET /admin/users` (`admin.users`) renders the directory.
- `POST /admin/users` (`admin.users.store`) creates a universal user account.
- Both routes require the existing `auth` and `admin` middleware. The form request also checks the authenticated user's `admin` role as defense in depth.

Data source and mappings:

- The directory uses real `users` rows through `App\Models\User`; no mock user records or invented API endpoints were added.
- `User::ROLE_LABELS` is the centralized display map for `seeker`, `helper`, `moderator`, `adviser`, `professional`, and `admin`.
- Helper availability comes from the existing `helpers.status` relationship. A helper without a profile is displayed as Offline. Other verified accounts display as Available and unverified accounts as Offline until a richer shared presence source exists.
- Joined dates are derived from `users.created_at`, avatars use the existing `avatar` path when present, and initials are calculated from the real name.
- The heading's registered count uses `User::count()`. Pending invitations currently means unverified accounts because the repository has no invitation model or invitation API.

Implemented interactions:

- Case-insensitive, trimmed, combined client-side search by name/email/role and role filtering.
- Row selection, select-all for the currently displayed rows, selected-row styling, and correct checked/indeterminate header state.
- Keyboard-accessible row action menus with click-outside, scroll/resize, and Escape handling.
- Reusable role/status badges, calculated initials, empty filtered state, clear-filters action, and responsive horizontal table behavior.
- Native accessible dialogs with labelled headings, focus trapping, Escape/backdrop close, and focus restoration.
- Create User performs real server validation and account creation for universal fields only. It generates a secure random initial password rather than exposing or collecting one; the success message directs the account owner to the existing Forgot Password flow. `Active` marks the email verified, while `Pending invitation` leaves it unverified.
- Import CSV validates that a non-empty `.csv` file no larger than 5 MB is selected. It intentionally stops before submission because no import service/endpoint exists.
- View, edit, role, password-reset, and deactivate menus are prepared UI integration points. Deactivation includes a confirmation dialog but intentionally does not mutate data without a confirmed backend operation.

Users files added:

- `app/Http/Controllers/Admin/UserController.php`
- `app/Http/Requests/Admin/StoreUserRequest.php`
- `resources/views/admin/users/index.blade.php`
- `resources/views/components/admin/dialog.blade.php`
- `resources/views/components/admin/user-role-badge.blade.php`
- `resources/views/components/admin/user-status-badge.blade.php`
- `tests/Feature/Admin/UserManagementTest.php`

Users-related files updated:

- `app/Models/User.php`: centralized role labels and the existing helper profile relationship.
- `routes/web.php`: protected Users index/store routes.
- `resources/views/components/admin/layout.blade.php`: page-specific active navigation and header-search props with dashboard-safe defaults.
- `resources/views/components/admin/sidebar.blade.php`: real Users route.
- `resources/views/components/admin/header.blade.php`: configurable search action/placeholder.
- `resources/views/components/admin/icon.blade.php`: Users-page outline icons.
- `public/css/admin-dashboard.css`: Users directory, badge, menu, form, dialog, and responsive styles.
- `public/js/admin-dashboard.js`: shared dialog, directory filtering/selection, action-menu, and CSV interactions.

Users verification:

- `php artisan test tests/Feature/Admin/UserManagementTest.php tests/Feature/Auth/AdminAuthenticationTest.php`
- Result: 14 tests passed, 61 assertions.
- Coverage includes guest redirect, non-admin denial, real user/helper data rendering, active and pending account creation, and invalid/duplicate input.
- Blade view compilation, Laravel Pint, JavaScript syntax checking, route registration, and `git diff --check` pass.
- The repository-wide result at this stage was 32 passed and 7 failed (117 assertions). These were the same unrelated failures recorded above: missing Vite output in four guest-auth views, one stale shared-login redirect expectation, and two stale profile redirect expectations.

Remaining Users backend integration:

- Add an invitation model/mail workflow if `Pending invitation` must represent more than `email_verified_at = null`.
- Add role-specific profile creation or onboarding; the current create form correctly limits itself to universal `users` fields.
- Add confirmed endpoints/policies for view, edit, manage role, reset password, deactivate, bulk actions, and CSV validation/preview/import before enabling those mutations.
- Add a shared presence source for non-helper roles and server-side search/pagination when the directory volume warrants it.

## Completed: administrator roles and permissions

The protected `/admin/roles-permissions` page now reuses the existing admin layout, header, responsive sidebar, dynamic administrator identity, logout flow, static Admin CSS/JavaScript assets, and accessible dialog component. Roles & Permissions is a real sidebar link and receives the shared active-navigation treatment.

Route and authorization:

- `GET /admin/roles-permissions` (`admin.roles-permissions`) is rendered by the invokable `App\Http\Controllers\Admin\RolePermissionController`.
- The route requires the existing `auth` and `admin` middleware. There is intentionally no POST/API route because this repository has no RBAC persistence or enforcement layer.

Current data source:

- The controller owns one isolated local permission view model for the Administrator role. It supplies the six actions (`read`, `create`, `update`, `delete`, `approve`, and `export`) and ten reference categories.
- Initial state matches the supplied UI: 60 accessible switches total, with 13 enabled and 47 disabled.
- Existing duplicate-name validation reuses `User::ROLE_LABELS`; no competing role enum was introduced.
- This matrix is a frontend/local draft and does not affect Laravel authorization. The existing `users.role = admin` middleware check remains the only Admin authorization behavior.

Implemented behavior:

- The permission matrix is rendered from controller arrays through a reusable `x-admin.permission-switch` Blade component rather than repeated switch markup.
- Switches are real keyboard-accessible buttons with `role="switch"`, `aria-checked`, category/action data, and descriptive labels.
- Enabling Create, Update, Delete, Approve, or Export automatically enables Read. Read cannot be disabled while another permission in that category is enabled; a warning toast explains why.
- Save snapshots the current matrix locally, clears the dirty state, disables Save/Reset, and displays an integration-aware success toast.
- Reset becomes available only for unsaved changes and restores the last local snapshot after confirmation.
- Duplicate Role validates a trimmed role name against both the six existing role labels and local drafts, then copies the current matrix into an in-memory role draft.
- Dirty/saved status is announced in the matrix context. The page intentionally does not add an intrusive navigation prompt because the project has no shared navigation-guard pattern.
- The large table scrolls horizontally on narrow screens; the Category column becomes sticky on mobile. Page actions wrap without compressing the matrix.
- Loading and failure states are not shown because data is synchronously server-rendered. The empty-category state is implemented for a future service returning no modules.

Roles & Permissions files added:

- `app/Http/Controllers/Admin/RolePermissionController.php`
- `resources/views/admin/roles-permissions/index.blade.php`
- `resources/views/components/admin/permission-switch.blade.php`
- `tests/Feature/Admin/RolePermissionTest.php`

Roles & Permissions files updated:

- `routes/web.php`: registered the protected named route.
- `resources/views/components/admin/sidebar.blade.php`: activated the real route.
- `resources/views/components/admin/icon.blade.php`: added matching action/category outline icons.
- `public/css/admin-dashboard.css`: added matrix, switch, toast, page-action, and responsive styles using existing Admin tokens.
- `public/js/admin-dashboard.js`: added dependency handling, dirty-state tracking, local Save/Reset, duplicate validation, and toast behavior.
- `codex.md`: recorded this implementation and its backend boundary.

Roles & Permissions verification:

- `php artisan test tests/Feature/Admin/RolePermissionTest.php tests/Feature/Admin/UserManagementTest.php tests/Feature/Auth/AdminAuthenticationTest.php`
- Result: 18 tests passed, 85 assertions.
- The new coverage verifies guest redirect, non-admin denial, the active protected route, all ten categories, accessible switches, exact initial state counts, and the absence of a fabricated RBAC endpoint.
- Laravel Pint, Blade compilation, JavaScript syntax, registered route checks, and `git diff --check` pass.
- Current repository-wide result: 36 passed and 7 failed (141 assertions). The seven unchanged failures are outside the Admin portal: four Vite-manifest-dependent guest-auth screens, one outdated shared-login redirect assertion, and two outdated profile redirect assertions.

Remaining RBAC backend integration:

- Design permission/role storage, models, migrations, policies or gates, validation, transactions, and audit logging before persisting the matrix.
- Add server-side safeguards that prevent administrators from removing critical access or locking out the final privileged administrator.
- Connect role selection and persistent role duplication once custom roles have an approved schema and lifecycle.
- Replace the controller's local matrix with a service/query, then add protected save/reset endpoints with error and loading responses. Until then, local drafts are intentionally lost on navigation or refresh and do not grant access.

## Completed: administrator resource library

The protected `/admin/resource-library` page now reuses the shared Admin shell and presents the repository's real published self-help resources in the supplied four-column browsing design. Resource Library is a real sidebar link with the established active treatment.

Route and authorization:

- `GET /admin/resource-library` (`admin.resource-library`) is rendered by the invokable `App\Http\Controllers\Admin\ResourceLibraryController`.
- The page requires the existing `auth` and `admin` middleware.
- Resource details continue to use the established authenticated `selfhelp.show` route. Bookmark changes reuse the established `selfhelp.save` and `selfhelp.unsave` JSON-capable POST routes with CSRF protection.

Current data source and mappings:

- Cards come from real `SelfHelpResource::published()` records; unpublished drafts never appear. No mock records or new resource API endpoints were introduced.
- Initial bookmark state comes from the authenticated administrator's existing `savedResources()` relationship and persists in `user_saved_resources`.
- The existing `self_help_resources.category` field represents a content format (`article`, `exercise`, `meditation`, `tool`, or `video`), not the reference's subject categories. The Admin controller therefore derives Mental Health, Stress, Depression, Anxiety, Meditation, and Emergency from the real category, title, description, and tags. This mapping is isolated in `subjectCategory()` for future replacement by an explicit taxonomy field.
- Article and Video keep their formats; meditation, exercise, and tool content render as Exercise cards. There is currently no Contact resource record, so no fake crisis directory or hotline details were created.
- Durations are displayed exactly as stored; the first numeric value is derived only for client-side maximum-duration filtering.

Implemented behavior:

- Case-insensitive combined search covers title, description, derived subject category, resource type, and tags.
- Category chips, type/category/duration dialog filters, and bookmark-only mode combine into one filtering predicate.
- My Bookmarks toggles a clearly pressed state and changes to `All resources` while active. The dialog's bookmarked-only control stays synchronized with it.
- Bookmark controls use accessible dynamic labels, disabled/busy request state, the existing real save/unsave backend, and success/failure toasts. Removing a bookmark immediately updates a bookmark-filtered view.
- Cards are rendered through reusable `x-admin.resource-card` markup with pastel tones, type badges, outline icons, bookmark state, duration, and a real Open link.
- Empty search/filter and empty bookmark states provide recovery actions. A contained database failure state reports the exception internally and offers Retry without exposing details.
- Resources are synchronously server-rendered, so no asynchronous skeleton is necessary. The grid changes from four columns to two and then one while preserving the existing Admin sidebar/header responsiveness.

Resource Library files added:

- `app/Http/Controllers/Admin/ResourceLibraryController.php`
- `resources/views/admin/resource-library/index.blade.php`
- `resources/views/components/admin/resource-card.blade.php`
- `tests/Feature/Admin/ResourceLibraryTest.php`

Resource Library files updated:

- `routes/web.php`: registered the protected named Resource Library route.
- `resources/views/components/admin/sidebar.blade.php`: activated the real Resource Library link.
- `resources/views/components/admin/icon.blade.php`: added bookmark, filter, video, and phone outline icons.
- `public/css/admin-dashboard.css`: added toolbar, chips, cards, pastel visual states, empty/error states, and responsive grids using existing Admin tokens.
- `public/js/admin-dashboard.js`: added combined filtering, bookmark mode, filter-dialog synchronization, persistent bookmark requests, and resource feedback.
- `codex.md`: recorded the real data source and taxonomy assumptions.

Resource Library verification:

- `php artisan test tests/Feature/Admin/ResourceLibraryTest.php tests/Feature/Admin/RolePermissionTest.php tests/Feature/Admin/UserManagementTest.php tests/Feature/Auth/AdminAuthenticationTest.php`
- Result: 23 tests passed, 114 assertions.
- New coverage verifies guest redirect, non-admin denial, real published-resource rendering, exclusion of unpublished records, derived category/type metadata, active navigation, real detail links, initial bookmark state, persistent save/unsave JSON behavior, and the empty-library state.
- Laravel Pint, PHP syntax, Blade compilation, JavaScript syntax, route registration, and `git diff --check` pass.
- Current repository-wide result: 41 passed and 7 failed (170 assertions). The unchanged failures remain outside the Admin portal: four Vite-manifest-dependent guest-auth screens, one outdated shared-login redirect assertion, and two outdated profile redirect assertions.

Remaining Resource Library integration:

- Add an explicit subject taxonomy/relationship if counseling staff need authoritative Mental Health, Stress, Depression, Anxiety, Meditation, and Emergency classifications instead of tag-based derivation.
- Add verified Contact resources through the existing content pipeline before displaying any crisis directory or hotline information.
- Add server-side search/filter pagination if the published library becomes too large for one server-rendered collection.
- The existing shared save action increments `saved_count` after `firstOrCreate`; a future bookmark hardening pass should only increment when a bookmark was newly created and should consider transaction-safe counter updates.

## Completed: administrator audit logs

The protected `/admin/audit-logs` page now reuses the Admin shell and displays a read-only Recent Events table based on the supplied reference. Audit Logs is a real sidebar destination with the established active treatment.

Route and authorization:

- `GET /admin/audit-logs` (`admin.audit-logs`) is rendered by the invokable `App\Http\Controllers\Admin\AuditLogController`.
- The route requires the existing `auth` and `admin` middleware. The Roles & Permissions matrix is still local-only UI state, so there is not yet a persistent `audit-logs.read` permission to enforce.
- No create, update, delete, rewrite, or timestamp mutation route/control was added for audit records.

Current data source:

- The repository already had an `audit_logs` table and placeholder `AuditLog` model. The model now has an actor relationship to `User` for the page query.
- `App\Services\AuditLogger` is the centralized append-only writer. It persists actor ID, normalized event code, module, target description, request IP, user agent, and timestamps.
- The controller always reads persisted rows newest-first with eager-loaded actors, server-side filters, and Laravel pagination. There are no controller preview/demo records.
- A successful Admin sign-in through the shared login records `ADMIN_LOGIN_SUCCEEDED`; rejected password attempts against known Admin accounts record `ADMIN_LOGIN_FAILED` without storing the password; an account created through Admin User Management records `USER_CREATED` inside the same database transaction as the new account.
- An empty database displays the genuine `No audit events found` state. Existing activity that occurred before the writer was added cannot be reconstructed automatically; subsequent connected actions appear immediately after persistence.
- For current real rows, `description` is displayed as Target because the schema has no dedicated target fields. Raw action codes are normalized into human-readable labels, and the event category is derived from `module` plus `action`.

Implemented behavior:

- Actor, Action, Target, and Time render from structured records through reusable `x-admin.audit-log-table` markup.
- Administrator actors display their email; other user actors display role plus name; records without an actor display `system`.
- Today's records display time, yesterday's display `yesterday`, and older records display a date. Every `<time>` includes ISO datetime data and an exact human-readable tooltip.
- Search covers action, module, target/description, actor name, and actor email. It combines with actor type, event category, and date filters.
- Filters and 25/50/100 page sizes use GET query parameters, so filtered views remain linkable. Invalid filter values safely fall back to defaults.
- Persisted records use `LengthAwarePaginator`; pagination controls appear only when results exceed the selected page size.
- Empty filtered results show `No matching audit events` with a Clear Filters action. A true empty data source shows `No audit events found`.
- Database failures are reported internally and produce a contained Retry state without raw exception details.
- Data is synchronously server-rendered, so no asynchronous skeleton is shown. The table horizontally scrolls on narrow screens while the shared Admin sidebar retains its existing drawer behavior.
- IP address and user-agent fields are intentionally omitted from the primary table. The page contains no row actions or clickable mutation affordances.

Audit Logs files added:

- `app/Http/Controllers/Admin/AuditLogController.php`
- `app/Services/AuditLogger.php`
- `resources/views/admin/audit-logs/index.blade.php`
- `resources/views/components/admin/audit-log-table.blade.php`
- `tests/Feature/Admin/AuditLogTest.php`

Audit Logs files updated:

- `app/Models/AuditLog.php`: added the actor relationship.
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`: records successful and rejected Admin sign-in attempts through the shared login.
- `app/Http/Controllers/Admin/UserController.php`: records Admin-created users transactionally.
- `routes/web.php`: registered the protected named route.
- `resources/views/components/admin/sidebar.blade.php`: activated the real Audit Logs link.
- `public/css/admin-dashboard.css`: added page heading, card, toolbar, table, immutable states, and pagination styling using existing Admin tokens.
- `codex.md`: recorded the live persisted data path and remaining audit coverage.

Audit Logs verification:

- `php artisan test tests/Feature/Admin/AuditLogTest.php tests/Feature/Admin/ResourceLibraryTest.php tests/Feature/Admin/RolePermissionTest.php tests/Feature/Admin/UserManagementTest.php tests/Feature/Auth/AdminAuthenticationTest.php`
- Result: 30 tests passed, 162 assertions.
- New coverage verifies guest redirect, non-admin denial, true empty state, persisted record rendering, actor resolution, action normalization, readable/exact timestamps, combined search/filter/date behavior, pagination, active navigation, live Admin authentication and user-creation writes, and the absence of mutation controls.
- Laravel Pint, PHP syntax, Blade compilation, JavaScript syntax, route registration, and `git diff --check` pass.
- Current repository-wide result: 48 passed and 7 failed (218 assertions). The unchanged failures remain outside the Admin portal: four Vite-manifest-dependent guest-auth screens, one outdated shared-login redirect assertion, and two outdated profile redirect assertions.

Remaining audit backend integration:

- Connect `AuditLogger` as later mutation endpoints are implemented for role/permission changes, password reset, deactivation, backups/restores, reports/exports, announcements, and other privileged actions. The current Admin portal only has real server mutations for authentication and user creation.
- Enforce immutability beyond the UI with database/application policy, restricted update/delete access, retention rules, and appropriate indexes for timestamp/module/actor searches.
- Persist actor display/role snapshots so deleting a user does not cause their historical row to appear as `system` after the nullable foreign key is cleared.
- Add structured event code, category, target type, and target ID fields; the current schema requires best-effort category derivation and uses description as Target.
- Enforce a persistent Audit Logs read permission once the RBAC backend replaces the current local-only matrix.

## Completed: administrator backup and restore interface

The protected `/admin/backup-restore` page now reuses the shared Admin shell and matches the supplied Backup & Restore reference while accurately representing the repository's current backend capabilities.

Repository findings and data source:

- No backup package, controller, database model, queue job, Artisan backup command, restore command, scheduled backup task, dedicated backup disk, or protected download route existed.
- The only scheduled console definition remains Laravel's default `inspire` command. The default private local filesystem is `storage/app/private`; no public or private backup path was inferred.
- `App\Services\BackupCatalog` is an explicit unavailable-provider adapter. It currently returns a real empty collection and `create`, `download`, and `restore` capabilities as false. No mock snapshot dates, sizes, files, commands, URLs, or successful operations are presented.
- `Admin\BackupRestoreController` maps future provider records into newest-first view data, validates supported statuses, formats byte sizes and timestamps, and suppresses download URLs unless the provider explicitly reports protected-download capability.
- The page is synchronously server-rendered, so no asynchronous skeleton is necessary. Catalog exceptions are reported internally and render a contained Retry state without revealing paths or exception details.

Implemented behavior:

- `GET /admin/backup-restore` (`admin.backup-restore`) uses the existing `auth` and `admin` middleware, Admin layout, dynamic authenticated profile, and responsive sidebar. Backup & Restore is now a real active sidebar destination.
- The page includes the COMPASS/Admin/Backup breadcrumb, requested heading/actions, Recent Snapshots card, completed/running/failed/restoring row variants, exact timestamp tooltips, byte-size formatting, responsive rows, true empty state, and error state.
- The reusable snapshot row only renders a download link when a protected server URL and capability are both supplied. The current adapter therefore renders no file URLs, and no public storage path is constructed.
- Run Backup opens the requested confirmation dialog, but the final button is disabled and clearly states that no command or request will run while the provider is unavailable. There is no fake POST endpoint and duplicate requests are impossible.
- Restore opens a completed-snapshot picker. The prepared workflow then shows an impact warning and a second dialog requiring the exact text `RESTORE`; the destructive button also remains disabled unless a future provider enables restoration.
- No backup, download, restore, retention, maintenance-mode, or shell operation was implemented or simulated. Consequently no backup Audit Log is written yet; `AuditLogger` should be called only by future real operation handlers after their outcome is known.
- Persistent granular backup permissions do not exist yet, so access uses the current administrator-only middleware rather than inventing RBAC records.

Backup & Restore files added:

- `app/Http/Controllers/Admin/BackupRestoreController.php`
- `app/Services/BackupCatalog.php`
- `resources/views/admin/backup-restore/index.blade.php`
- `resources/views/components/admin/backup-snapshot-row.blade.php`
- `tests/Feature/Admin/BackupRestoreTest.php`

Backup & Restore files updated:

- `routes/web.php`: registered the protected read-only page route.
- `resources/views/components/admin/sidebar.blade.php`: replaced the pending item with the named route.
- `resources/views/components/admin/icon.blade.php`: added the shared download outline icon.
- `public/css/admin-dashboard.css`: added reference-aligned card, rows, states, dialogs, controls, and responsive behavior.
- `public/js/admin-dashboard.js`: added snapshot selection, two-stage restore flow, exact confirmation gating, and focus-compatible dialog transitions.
- `codex.md`: recorded the truthful backend status and future integration requirements.

Backup & Restore verification:

- `php artisan test tests/Feature/Admin tests/Feature/Auth/AdminAuthenticationTest.php`
- Result: 36 tests passed, 195 assertions.
- New coverage verifies guest redirect, non-admin denial, active navigation, true unconfigured empty state, absence of fake snapshots, prepared confirmation dialogs, disabled destructive action, data-driven sorting/formatting/statuses, suppression of unprotected download URLs, contained error state, and the absence of mutation endpoints.
- Laravel Pint, PHP syntax, Blade compilation, JavaScript syntax, route registration, and `git diff --check` pass.
- Current repository-wide result: 54 passed and 7 failed (251 assertions). The unchanged failures remain outside the Admin portal: four Vite-manifest-dependent guest-auth screens, one outdated shared-login role redirect assertion, and two outdated profile redirect assertions.

Remaining backup backend integration:

- Select and configure a reviewed backup provider/package and a non-public encrypted backup disk with retention, checksum, access-control, and credential-management policies.
- Implement queued backup generation, protected identifier-based downloads, pre-restore snapshots, backup verification, maintenance-window coordination, restoration validation, concurrency locks, failure recovery, and operation status polling.
- Add protected CSRF mutation routes/controllers only after that provider exists. Never accept browser-supplied filesystem paths or raw shell arguments.
- Record real initiation/completion/failure/download/restore outcomes through `AuditLogger`; do not log a successful operation until the backend confirms it.
- Replace the current administrator-only capability check with persistent read/create/download/restore permission enforcement once the RBAC backend is implemented.

## Completed: administrator system health interface

The protected `/admin/system-health` page now reuses the shared Admin shell and presents the supplied System Health monitoring layout through centralized, data-driven view data.

Repository findings and current data source:

- No system-health controller, monitoring service/model, infrastructure metrics endpoint, monitoring threshold configuration, polling, Laravel Echo, or WebSocket integration existed.
- No JavaScript chart package is installed. The Admin Dashboard already uses the reusable server-rendered `x-admin.chart-card` SVG component, so System Health reuses and extends that component without adding a dependency.
- `App\Services\SystemHealthMonitor` is the isolated monitoring-provider integration point. It currently returns explicitly identified preview data for services, charts, storage, uptime history, and incidents; it does not call an invented endpoint or present itself as live infrastructure telemetry.
- The page shows a visible preview notice. There is no polling or real-time transport. Replacing the provider data with protected monitoring measurements is the remaining backend task.
- The Roles & Permissions page currently stores only local UI state, so no persistent `system-health.read` permission exists. Access uses the existing `auth` and `admin` middleware.

Implemented behavior:

- `GET /admin/system-health` (`admin.system-health`) uses the existing authenticated Admin layout, profile, logout behavior, mobile sidebar, header, and active navigation treatment.
- Ten service cards are rendered from structured data with Healthy, Warning, Critical, and Unknown-capable states: Server, Database, Authentication, API, Storage, Memory, CPU, Queue, Notification, and Voice Call.
- Overall status is calculated in the controller instead of hardcoded: a critical core service is Critical; warnings or a non-core critical service produce Degraded; unknown data produces Unknown; otherwise the result is Operational. The supplied preview data therefore reports Degraded and two active critical incidents.
- CPU Usage, Memory Usage, and API Response Time reuse the existing responsive SVG chart component. The component now accepts an accessible summary and optional dashed series while remaining backwards-compatible with Dashboard charts.
- Storage bars calculate their widths from structured usage values. The 90-day uptime card renders data-driven operational/degraded/outage blocks and calculates its SLA state.
- The recent incident list renders structured warning/critical events and avoids presenting vendor-specific claims such as Twilio when no real provider is configured.
- The Incidents button and View all action focus the in-page incident section; no unrequested incident-management route was invented.
- A provider failure renders a contained retry state without leaking the exception. Data is synchronously server-rendered, so no asynchronous loading skeleton is displayed.
- The service grid responds from five columns to three, two, and one; chart and lower grids stack at existing Admin breakpoints.
- No restart, database administration, shell execution, or destructive infrastructure controls were added.

System Health files added:

- `app/Http/Controllers/Admin/SystemHealthController.php`
- `app/Services/SystemHealthMonitor.php`
- `resources/views/admin/system-health/index.blade.php`
- `resources/views/components/admin/service-health-card.blade.php`
- `resources/views/components/admin/health-incident-list.blade.php`
- `tests/Feature/Admin/SystemHealthTest.php`

System Health files updated:

- `routes/web.php`: registered the protected named route.
- `resources/views/components/admin/sidebar.blade.php`: activated the System Health destination.
- `resources/views/components/admin/icon.blade.php`: added lock, memory, and layers outline icons using the established icon component.
- `resources/views/components/admin/chart-card.blade.php`: added accessible chart summaries and optional dashed series without changing existing Dashboard usage.
- `public/css/admin-dashboard.css`: added reference-aligned service states, metric grids, progress bars, uptime blocks, incidents, preview/error states, and responsive layouts using existing Admin tokens.
- `codex.md`: recorded the monitoring source, calculations, limitations, and future integration points.

System Health verification:

- `php artisan test tests/Feature/Admin tests/Feature/Auth/AdminAuthenticationTest.php`
- Result: 41 tests passed, 229 assertions.
- New coverage verifies guest redirect, non-admin denial, active navigation, all preview service/metric sections, calculated Degraded and Critical overall states, accessible chart data, two active incidents, contained provider failure, and absence of infrastructure-control actions.
- Laravel Pint, JavaScript syntax, Blade compilation, route registration, and `git diff --check` pass.
- Current repository-wide result: 59 tests passed and 7 failed (285 assertions). The unchanged failures remain outside the Admin portal: four Vite-manifest-dependent guest-auth screens, one outdated shared-login role redirect assertion, and two outdated profile redirect assertions.

Remaining System Health backend integration:

- Replace preview measurements in `SystemHealthMonitor` with a protected infrastructure monitoring adapter or persisted time-series source. Server-calculated states and centrally configured thresholds should remain authoritative.
- Add partial-provider failure mapping so unavailable individual metrics become Unknown without hiding healthy measurements from other sources.
- Add a protected refresh endpoint and conservative 30–60 second polling only when a real monitoring provider exists; no polling or WebSockets are currently used.
- Persist incident lifecycle data if acknowledgement/resolution workflows are later required. The current page is read-only and contains no incident-management backend.
- Enforce a persistent System Health read permission once the local-only RBAC matrix is replaced by backend authorization.

## Completed: administrator reports catalog interface

The protected `/admin/reports` page now reuses the shared Admin shell and presents the supplied six-card Reports catalog with live source-activity metadata and truthful operation capabilities.

Repository findings and current data source:

- `AnalyticsReport`, `SessionReport`, and `IncidentReport` models/tables already existed, but no Admin Reports controller, catalog service, generator, queue job, preview/detail route, print view, protected report download route, or file authorization policy existed.
- The existing Settings export is a help seeker's personal JSON data export and is not appropriate for aggregated administrator reports.
- Composer contains no PDF, CSV, or XLSX report-generation package. No file format or successful export/download behavior was invented.
- `App\Services\ReportCatalog` centralizes the six predefined catalog definitions. Each definition derives its record count and latest update timestamp from real source tables: counseling sessions, referrals, helper competency history, users, and incident reports. The visible dates are therefore dynamic rather than hardcoded reference dates.
- The existing `HelperCompetencyHistory` model did not map Laravel's pluralized default to the repository's singular `helper_competency_history` table; its table mapping is now explicit so real competency source activity can be queried.
- The existing `analytics_reports` rows are adviser-scoped generated-report metadata with an unprotected `file_path`; they are intentionally not exposed as downloadable Admin files.
- The Roles & Permissions matrix remains local-only UI state. Reports uses the existing `auth` and `admin` middleware because persistent Reports Read/Create/Export permissions do not yet exist.

Implemented behavior:

- `GET /admin/reports` (`admin.reports`) uses the existing Admin layout, dynamic profile, logout, responsive sidebar, global search, and active navigation treatment.
- The catalog renders Monthly session report, Referral outcomes, Helper competency growth, Performance benchmarks, Users & activity, and Emergency incident log from structured service data through reusable `x-admin.report-card` markup.
- Cards contain the requested icon, category badge, title, description, real source-updated state, and accessible View/Export/Print/Download controls. No card is manually duplicated.
- Global search filters the catalog client-side by title, category, description, and source. Category, last 7/30/90 days, and output-type filters combine with it. The filter count, visible-result summary, Clear Filters action, and `No reports found` state update immediately.
- View opens a safe metadata preview with source name, source record count, and latest source activity. It does not expose session narratives, identities, recordings, or incident content.
- Emergency Incident Log preview displays an additional privacy notice explaining that detailed access needs reviewed backend authorization.
- Export, Print, and Download controls open a contained unavailable dialog. They do not generate, print, expose, or download a file. No public storage URL is rendered.
- New Report opens a reusable accessible dialog with report type and date range fields. Because no secure formats/generator exist, the format control and Generate button are disabled with an explicit explanation; no mutation endpoint exists.
- A true empty catalog and filtered-empty state are separate. Catalog failures render a contained Retry state without leaking exception text. Data is synchronously rendered, so no asynchronous card skeleton is shown.
- The card grid responds from three columns to two and one using the existing Admin breakpoints and preserves keyboard/focus behavior through the shared dialog implementation.
- No Audit Log entry is written for metadata previews or unavailable actions. Future real generation/export/download handlers should record their confirmed outcomes through the existing `AuditLogger`.

Reports files added:

- `app/Http/Controllers/Admin/ReportController.php`
- `app/Services/ReportCatalog.php`
- `resources/views/admin/reports/index.blade.php`
- `resources/views/components/admin/report-card.blade.php`
- `tests/Feature/Admin/ReportTest.php`

Reports files updated:

- `app/Models/HelperCompetencyHistory.php`: mapped the existing model to its actual singular table.
- `routes/web.php`: registered the protected named Reports route.
- `resources/views/components/admin/sidebar.blade.php`: replaced the pending Reports item with the named route.
- `resources/views/components/admin/icon.blade.php`: added spreadsheet and printer outline icons.
- `public/css/admin-dashboard.css`: added reference-aligned catalog cards, report actions, states, dialogs, privacy notices, and responsive grids.
- `public/js/admin-dashboard.js`: added combined live filtering, preview population, privacy state, and unavailable-operation dialogs.
- `codex.md`: recorded the real source mappings, backend limitations, and future integration requirements.

Reports verification:

- `php artisan test tests/Feature/Admin tests/Feature/Auth/AdminAuthenticationTest.php`
- Result: 48 tests passed, 267 assertions.
- New coverage verifies guest redirect, non-admin denial, all six catalog definitions, real source counts/timestamps, active navigation, filters/dialogs/actions, privacy-safe capability messaging, true empty and contained failure states, suppression of public file links, and the absence of generation/download endpoints.
- Laravel Pint, JavaScript syntax, Blade compilation, route registration, and `git diff --check` pass.
- Current repository-wide result: 66 tests passed and 7 failed (323 assertions). The unchanged failures remain outside the Admin portal: four Vite-manifest-dependent guest-auth screens, one outdated shared-login role redirect assertion, and two outdated profile redirect assertions.

Current report/export status:

- Metadata catalog preview: available and dynamic.
- PDF export: not implemented.
- CSV export: not implemented.
- XLSX export: not implemented.
- Printable report view: not implemented.
- Protected generated-file download: not implemented.
- Report generation queue/job: not implemented.

Remaining Reports backend integration:

- Define aggregated, privacy-reviewed queries for each report and pseudonymize help-seeker data by default. Emergency incident reporting requires stricter explicit authorization and access logging.
- Implement persistent Reports Read/Create/Export permissions and enforce them in policies/middleware and every report endpoint, not only in the interface.
- Choose reviewed PDF/CSV/XLSX generators, queued generation jobs, status tracking, retention policy, and a non-public report storage disk.
- Add identifier-based protected preview/print/download routes. Never accept client-supplied file paths or expose `analytics_reports.file_path` directly.
- Record confirmed generation, export, download, and sensitive-report access outcomes through `AuditLogger`; do not record unavailable UI interactions as completed operations.

## Completed: administrator settings interface

The protected `/admin/settings` page now reuses the shared Admin shell and implements the supplied Settings layout with device-level appearance controls, persisted account preferences, the real password-update flow, and truthful security capability states.

Repository findings and integration decisions:

- The repository already had account fields for `dark_mode`, `email_notifications`, `show_email`, and `allow_data_research`, plus Laravel's authenticated `PUT /password` flow. There was no Admin Settings route/page, applied Admin theme runtime, accent/reduced-motion implementation, narrow preference endpoint, password-change audit event, authenticator-app 2FA backend, verified Admin phone/SMS preference backend, session revocation endpoint, or installed interface translations.
- The existing general Settings controller expects section-wide forms, so the Admin page uses a narrow protected preference action that permits only the three known account boolean fields. It does not invent a second general preference model.
- Device-specific theme, accent, reduced motion, and in-app sound preferences use guarded `localStorage` keys. No passwords, security state, session identifiers, or account-level security preferences are stored there.
- The existing `users.dark_mode` value seeds the Admin theme only when no device preference exists. Light, Dark, and System then use one Admin runtime and are applied before the stylesheet to avoid a theme flash.
- Accent choices use dedicated interactive CSS tokens and intentionally do not replace semantic Healthy, Warning, Critical, or Info colors.
- The existing database session driver and sessions table are used in production to list the current administrator's devices. The view adapter selects only session ID, user agent, and last-activity timestamp, hashes the ID before view use, and never exposes payloads or IP addresses. Tests use the configured array driver and therefore show the current session only unless a provider is injected.
- Because secure 2FA, SMS, and session-revocation backends do not exist, those actions are shown as disabled/unavailable or read-only rather than as fake toggles.
- Only English is installed, so the Language section accurately shows a disabled English selection and does not claim Filipino localization.

Implemented behavior:

- `GET /admin/settings` (`admin.settings`) and `PATCH /admin/settings/preferences` (`admin.settings.preference.update`) use the existing `auth` and `admin` middleware. Settings is now a real active sidebar link.
- The page includes the requested COMPASS/Settings breadcrumb, heading, responsive local section navigation, and Appearance, Notifications, Privacy, Security, and Language cards.
- Appearance supports Light, Dark, and system-color-scheme modes; five accessible accent choices; and a reduced-motion preference that suppresses non-essential transitions while continuing to respect the operating-system preference.
- Email notifications, profile-email visibility, and anonymized analytics save immediately to the existing user record. The client optimistically updates a switch, rolls it back on failure, and displays a contained error message. In-app sounds persist locally and do not play audio during setup. Emergency SMS remains unavailable.
- Privacy explicitly states that mandatory audit, security, emergency, and institutional records cannot be disabled.
- Change Password opens the shared accessible dialog and submits to Laravel's real current-password/new-password confirmation flow. Successful Admin password changes append the sanitized `PASSWORD_CHANGED` Audit Log event without logging password content.
- Active Sessions opens a read-only device review derived from actual session storage when available. Provider failure leaves the rest of Settings usable and shows only the current session with a contained warning. No sign-out control is rendered without a protected revocation backend.
- Local navigation becomes horizontally scrollable on tablet/mobile, cards stack, control rows adapt, and shared dialogs retain focus trapping, Escape handling, and trigger-focus restoration.

Settings files added:

- `app/Http/Controllers/Admin/SettingsController.php`
- `app/Services/ActiveSessionCatalog.php`
- `resources/views/admin/settings/index.blade.php`
- `resources/views/components/admin/settings-switch.blade.php`
- `tests/Feature/Admin/SettingsTest.php`

Settings files updated:

- `routes/web.php`: registered the protected Settings page and narrow preference route.
- `resources/views/components/admin/sidebar.blade.php`: activated the real Settings destination.
- `resources/views/components/admin/layout.blade.php`: added the pre-paint Admin theme/accent/reduced-motion bootstrap.
- `resources/views/components/admin/icon.blade.php`: added shared sun, moon, monitor, and globe outline icons.
- `public/css/admin-dashboard.css`: added accent tokens, light/dark theme variables, Settings cards/controls/session UI, reduced-motion rules, and responsive behavior.
- `public/js/admin-dashboard.js`: added theme, system-theme tracking, accent, local and backend switch persistence, rollback feedback, and section navigation.
- `app/Http/Controllers/Auth/PasswordController.php`: records confirmed Admin password changes through the existing audit service.
- `app/Services/AuditLogger.php`: added the `PASSWORD_CHANGED` event constant.
- `codex.md`: recorded the implementation, truthful security status, and remaining backend work.

Settings verification:

- `php artisan test tests/Feature/Admin tests/Feature/Auth/AdminAuthenticationTest.php tests/Feature/Auth/PasswordUpdateTest.php`
- Result: 58 tests passed, 326 assertions.
- New Settings coverage verifies guest redirect, non-admin denial, shared-layout rendering, active navigation, existing preference persistence, unsupported-setting rejection, real password update and sanitized audit entry, non-sensitive session metadata, and contained session-provider failure.
- Laravel Pint, PHP syntax, JavaScript syntax, Blade compilation, route registration, and `git diff --check` pass.
- Current repository-wide result: 74 tests passed and 7 failed (374 assertions). The unchanged failures remain outside this Admin Settings work: four Vite-manifest-dependent guest-auth screens, one outdated shared-login role redirect assertion, and two outdated profile redirect assertions.

Current Settings capability status:

- Light theme: functional and device-persisted.
- Dark theme: functional and device-persisted.
- System theme: functional, device-persisted, and updates when the operating-system preference changes.
- Accent color: functional and device-persisted through interactive design tokens; semantic status colors remain stable.
- Reduced motion: functional and device-persisted; operating-system reduced-motion remains respected.
- Email notifications: persisted to the existing backend user field.
- In-app sounds: device-local preference only; no sound is played by this page.
- Emergency SMS: unavailable; verified Admin phone and SMS backend still required.
- Profile-email visibility and anonymized analytics: persisted to existing backend fields.
- Password change: fully connected to Laravel validation/password hashing and Audit Logs.
- Two-factor authentication: unavailable; no authenticator-app backend exists.
- Active sessions: connected read-only to database session metadata in production; revocation is not implemented.
- Interface language: English only; localization infrastructure/content is still required for additional languages.

Remaining Settings backend integration:

- Implement a reviewed authenticator-app 2FA enrollment, verification, recovery-code, and password/2FA-protected disable flow before enabling its control.
- Add verified administrator phone storage and an authorized SMS preference/delivery provider before enabling Emergency SMS.
- Add protected current-user session-revocation actions, including a safe `sign out all other sessions` operation, with CSRF protection and Audit Log events.
- Decide whether device-level appearance/sound preferences should sync to accounts; if so, consolidate them into one authoritative preference store instead of retaining two sources of truth.
- Add actual localization catalogs and translated Admin UI before enabling any language other than English.

## Completed: Admin visual alignment with the Help Seeker portal

The System Administrator portal shell was restyled on 2026-08-14 to use the existing Help Seeker portal as its visual source of truth. This was a presentation-only refactor: all protected Admin routes, controllers, data sources, authentication, authorization, dialogs, page interactions, and semantic health/status colors remain unchanged.

Repository findings and scope:

- Help Seeker standalone pages use `resources/views/partials/sidebar.blade.php`, a fixed 260px sidebar, Inter typography, `#04A052` branding, `#F8FBF9` page background, pale-green active navigation with a left accent, and a profile/settings plus full destructive logout footer.
- Every live Admin page already uses `resources/views/components/admin/layout.blade.php`, `sidebar.blade.php`, `header.blade.php`, `public/css/admin-dashboard.css`, and `public/js/admin-dashboard.js`.
- Directly reusing the Help Seeker sidebar Blade partial would expose Seeker-specific routes and Font Awesome markup in the Admin portal. The safe change was to retain the Admin's data-driven navigation and SVG icon component while aligning its shared visual contract.
- A full page rewrite was unnecessary. Updating the Admin shell and base component tokens automatically aligned Dashboard, Users, Roles & Permissions, Resource Library, Audit Logs, Backup & Restore, System Health, Reports, and Settings.

Visual changes:

- Admin now uses the Help Seeker's 260px translucent-white sidebar, compact green COMPASS mark, uppercase muted section labels, 42px navigation rows, pale-green active state, green left accent, and restrained hover behavior.
- The bottom account area now uses dynamic administrator initials/name, the System Administrator role with a green presence dot, a `View Profile & Settings` link to `/admin/settings`, and the existing logout POST flow rendered as a full pale-red/red-bordered Logout button.
- Default Admin tokens now map to the Help Seeker palette: `#04A052` green, `#027039` dark green, `#EAF8F0` green-soft, `#F8FBF9` page background, `#E5E7EB` border, `#1F2937` text, and `#6B7280` muted text.
- The existing Admin header remains because it contains global search, help, notifications, and the authenticated profile menu, but it is shorter, lighter, and uses the same subtle border/background treatment. Desktop page-context duplication is hidden; mobile retains the menu control.
- Page headings, white card surfaces, subtle shadows, buttons, search/filter controls, form inputs, padding, and mobile spacing now use one consistent COMPASS visual language. Semantic Healthy/Warning/Critical/Info colors were not changed.
- Inter is loaded by the Admin layout to match the Help Seeker screens. The default Admin accent swatch now uses the same COMPASS green while the existing user-selectable accent functionality remains intact.
- Below 980px, the existing accessible Admin drawer/backdrop/Escape behavior remains. Mobile content uses compact Seeker-like padding and extra bottom breathing room; existing page grids/tables continue using their established responsive rules.

Files updated for this visual refactor:

- `resources/views/components/admin/layout.blade.php`
- `resources/views/components/admin/sidebar.blade.php`
- `resources/views/admin/settings/index.blade.php`
- `public/css/admin-dashboard.css`
- `tests/Feature/Admin/SettingsTest.php`
- `codex.md`

Verification:

- `php artisan test tests/Feature/Admin tests/Feature/Auth/AdminAuthenticationTest.php`: 65 tests passed, 356 assertions.
- `php artisan test tests/Feature/Admin/SettingsTest.php`: 8 tests passed, 55 assertions, including the new profile/settings and logout-shell assertions.
- Blade view caching, Admin route inspection, and `git diff --check` pass.
- No routes, middleware, controllers, services, database schema, API behavior, backup/audit/report/monitoring behavior, or non-Admin navigation were changed.
