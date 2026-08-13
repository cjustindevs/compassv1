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

Administrator authentication follows the repository's established source of truth: the authenticated record is in `users`, and a system administrator is identified by `users.role = admin`. The separate `system_administrators` table currently represents optional profile data and is not populated by `DatabaseSeeder`, so admin login must not require a row in that table unless the data model and seeding strategy are intentionally changed.

## Completed: dedicated administrator login

A standalone, responsive administrator portal was added based on the supplied UI reference.

Routes:

- `GET /admin/login` (`admin.login`) renders the form and is guest-only.
- `POST /admin/login` (`admin.login.store`) validates and authenticates an administrator.
- `GET /admin/dashboard` (`admin.dashboard`) now requires both `auth` and the new `admin` middleware.

Authentication behavior:

- The `Username` field accepts either the user's account name or email, case-insensitively.
- Only records with `users.role = admin` can authenticate through this portal.
- Invalid credentials and valid non-admin credentials return the same generic error.
- Login attempts are limited to five per normalized username/IP key.
- Successful login regenerates the session before redirecting to `admin.dashboard`.
- `Remember me for 30 days` configures the web guard's remember duration to 30 days.
- The existing shared `POST /logout` route remains the logout endpoint.

UI behavior:

- The page is self-contained in Blade with inline responsive CSS and SVG icons; it does not depend on a compiled Vite manifest.
- It includes accessible labels, error states, password visibility control, reduced-motion handling, the COMPASS brand link, system status, privacy, and contact links.
- Administrators are not offered self-registration because the portal is restricted to provisioned accounts.

## Files added

- `app/Http/Controllers/Auth/AdminAuthenticatedSessionController.php`
- `app/Http/Requests/Auth/AdminLoginRequest.php`
- `app/Http/Middleware/EnsureUserIsAdministrator.php`
- `resources/views/auth/admin-login.blade.php`
- `tests/Feature/Auth/AdminAuthenticationTest.php`
- `codex.md`

## Files updated

- `routes/web.php`: added the admin login routes and protected the admin dashboard.
- `bootstrap/app.php`: registered the `admin` middleware alias.
- `database/migrations/2026_08_12_140000_extend_counseling_sessions_session_status.php`: retained the PostgreSQL constraint implementation and added a schema-builder path for SQLite/other drivers. This allows the existing SQLite in-memory feature tests to migrate successfully.

No new database migration was required for admin login. Existing administrator accounts continue to work when their `users.role` value is `admin`.

Local development database state as of 2026-08-13:

- A single administrator account was created directly in `compass_db` with user ID `2`, name `Admin User`, and email `admin@example.com`.
- Its role is `admin`, its email is marked verified, and its stored password hash was checked successfully against the user-requested development password.
- The plaintext password is intentionally not recorded in this handoff file. This database-only account creation does not affect fresh installations; use a dedicated idempotent admin seeder or provisioning command if repeatable setup is needed later.

## Verification state

Focused verification is green:

- `php artisan test tests/Feature/Auth/AdminAuthenticationTest.php`
- Result after the dashboard coverage was added: 8 tests passed, 35 assertions.
- Covered: rendering, email login, account-name login, rejecting non-admin users, rejecting invalid passwords, denying non-admin dashboard access, and allowing admin dashboard access.
- PHP syntax checks pass for the new controller, request, and middleware.
- Laravel registers all three expected admin routes.
- Laravel Pint passes for the changed PHP implementation.
- `git diff --check` passes.

Full-suite state after fixing SQLite migration compatibility:

- 25 tests pass and 7 tests fail.
- Several legacy auth view tests fail because `node_modules` and `public/build/manifest.json` are absent, so views that use `@vite` cannot render.
- One legacy login test expects `/dashboard`, while the current shared login controller redirects directly to a randomly generated user's role dashboard.
- Two legacy profile tests expect `/profile`, while the current profile controller redirects to `/profile/edit`.
- These failures are outside the dedicated admin-login implementation; its focused suite passes completely.

## Existing follow-up concerns

- Install frontend dependencies locally and run `npm run build` before evaluating legacy Vite-backed views. At the time of this handoff, `npm run build` resolves Vite from `/Users/lorraine/node_modules` because this repository has no local `node_modules`, then fails with a permission error.
- Reconcile the legacy login/profile assertions with current redirect behavior.
- Only the admin dashboard now has role-specific authorization. The seeker, helper, adviser, moderator, and professional dashboard routes still use authentication without role middleware and should receive equivalent protection in a future authorization pass.
- The README states MySQL/MariaDB even though the active environment and the PostgreSQL-specific constraint migration target PostgreSQL. The README also ends with an unclosed code fence.
- Helper matching remains vulnerable to concurrent double-assignment because selection and status updates are not protected by a transaction or row lock.

## Suggested next starting point

For further administrator work, start from `routes/web.php`, `AdminAuthenticatedSessionController`, `AdminLoginRequest`, and `resources/views/auth/admin-login.blade.php`. Preserve `users.role = admin` as the login authorization rule unless an explicit account-model migration is planned. Run the focused admin authentication test after every related change.

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
- Guest requests to `/admin/*` are directed to `/admin/login`; authenticated non-admin access remains a 403 through `EnsureUserIsAdministrator`.

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
- The repository-wide result at this stage was 32 passed and 7 failed (117 assertions). These were the same unrelated failures recorded above: missing Vite output in five guest-auth views, one stale shared-login redirect expectation, and two stale profile redirect expectations.

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
- Current repository-wide result: 36 passed and 7 failed (141 assertions). The seven unchanged failures are outside the Admin portal: five Vite-manifest-dependent guest-auth screens, one outdated shared-login redirect assertion, and two outdated profile redirect assertions.

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
- Current repository-wide result: 41 passed and 7 failed (170 assertions). The unchanged failures remain outside the Admin portal: five Vite-manifest-dependent guest-auth screens, one outdated shared-login redirect assertion, and two outdated profile redirect assertions.

Remaining Resource Library integration:

- Add an explicit subject taxonomy/relationship if counseling staff need authoritative Mental Health, Stress, Depression, Anxiety, Meditation, and Emergency classifications instead of tag-based derivation.
- Add verified Contact resources through the existing content pipeline before displaying any crisis directory or hotline information.
- Add server-side search/filter pagination if the published library becomes too large for one server-rendered collection.
- The existing shared save action increments `saved_count` after `firstOrCreate`; a future bookmark hardening pass should only increment when a bookmark was newly created and should consider transaction-safe counter updates.
