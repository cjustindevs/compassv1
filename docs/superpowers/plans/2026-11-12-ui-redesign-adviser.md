# UI Redesign — Adviser Rollout Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Roll the approved COMPASS redesign out to the Adviser role: grouped navigation, a phone bottom bar and emergency strip, a "Needs your attention" dashboard, the Pending Evaluations reference page, and every other Adviser page on the shared components. Looks and arrangement change; behavior does not.

**Architecture:** All 27 `resources/views/adviser/*.blade.php` views, `dashboard/adviser.blade.php` and `concerns/index.blade.php` already extend `layouts.app`, so no shell move is needed. Pages are rebuilt from `resources/views/components/ui/*` and `public/css/compass-components.css`. The bottom bar and emergency strip are generalized to take per-role items from one small helper (`App\Support\RoleNav`) instead of being duplicated. The Adviser emergency count reuses the query the Adviser emergencies page already runs, exposed as a model scope.

**Tech Stack:** Laravel 12 Blade, Tailwind (existing), plain CSS, vanilla JS, `<x-analytics-chart>`, PHPUnit (SQLite), `node --test`, Playwright harness `scripts/ui-screenshots.mjs`.

**Spec:** `docs/superpowers/specs/2026-11-11-compass-ui-redesign-design.md` (§2–§7, §6b). **Precedent:** `docs/superpowers/plans/2026-11-11-ui-redesign-foundation-moderator.md` (done, approved for desktop).

## Global Constraints
- No change to routes, controller logic, form `action`/`method`/field `name`s, permissions, workflows, KPI formulas or data. The only logic edit allowed is the read-only emergency count reuse in `SidebarComposer::adviserData()` (Task 2).
- Hooks that must survive (IDs, `data-*`): `#evalBadge #pendingReviewsBadge #referralBadge #emergencyBadge #notifBadge #totalHelpers #activeSessions #pendingReviews [data-adviser-badge] [data-adviser-user-id]`, `#adviserDutyForm #dutySubmit #dutyReset [data-shift-edit]`, `[data-helper-workload] [data-workload-*]`, `[data-live-note-form] #lwCoach #lwNote #lwTranscript`, `#approveForm #rejectForm #reviseForm #approveModal #rejectModal #reviseModal #professionalAssignment #professionalIdentityRelease #professionalTransfer`, `#resourceForm #resourceModal #openAddModal #categorySelect`, `#calGrid #dayPanel #eventModal #openEventModal`, screenings `[data-filter]`, settings `[data-tab] #section-*`, evaluate `[data-target] .rating-btn #overallScore #competencyLevelDisplay #conversationModal #evaluationDeclaration` (+ the `selectRating` / `openConversation` globals), emergency detail `#reviewAction #resolutionNotes #handoffReason #coordinatorId` and `data-focus-label`, `data-confirm*`, `data-label` on stacked cells.
- Default COMPASS theme only. Scale (spec §1): body 15px; captions 13–14px; card title 20px/700; h1 28px/800 (24px ≤480px); KPI 40px/800 (28px ≤768px, two per row — compact phone rules); controls 44px, pill buttons; rows ≥52px; card padding 24px (16px ≤768px). Allowed font sizes: 11, 12, 13, 14, 15, 16, 18, 20, 24, 28, 32, 40px (`DesignScaleTest` scans adviser views and `adviser-*.css`; every new rule must stay on the scale).
- **Desktop look = the Moderator pilot look, only through the shared components.** No Adviser-only restyling of cards, KPIs, badges, tables or buttons.
- **Phone:** the compact phone block at the end of `compass-components.css` (2 KPIs per row, label and value side by side in stacked tables, 2-column filter bar) is binding. Pages must not add phone CSS that undoes it (no per-page `.stat-number{font-size}` overrides, no `grid-template-columns:1fr` on `.ui-kpis`).
- **The bell stays in the top app bar** (`layouts.app` `[data-notification-slot]`). `<x-ui.page-header>` renders no bell. Do not add one. Each converted page renders exactly one `data-page-header` and one `<h1>` (several views today carry two `<h1>`).
- **Sidebar grouping** (reference): EVALUATION = Dashboard, Screening Reviews, Pending Evaluations, Manage Helpers, Live Sessions, Referral Queue, Emergencies; RECORDS = Reports, Calendar, Helper Schedules, Transcripts, Resources, Areas of Concern; ACCOUNT = Notifications, Settings. Every existing link, route, ID and badge is kept; every `.nav-item` gets `title`. Reference capitalisation ("Live Sessions", "Areas of Concern") is used for sidebar labels only.
- Renames: none are applied unless the spec already approved them (spec approved only the two Moderator renames). Candidates are in "Proposed renames" below.
- Commit each task separately; no Claude co-author or attribution lines; never commit `.env`, `app/Http/Controllers/Adviser/AdviserNotificationController.php` (uncommitted local changes, read-only for this plan) or `public/sw.js`; stage files by explicit path, never `git add -A`; never `migrate:fresh`; never push.
- Known unrelated failure: `AdviserRefinementTest` notification archive.
- Shared files to keep working for other roles: `partials/helper-workload` (moderator schedules), `adviser/training` (also served to Helpers via `$isAdviser`), `concerns/index` (Adviser and Admin), `components/evaluation-steps` (also under the Helper design-scale area), `x-performance-cards`, `x-report-charts`, `x-dashboard-overview`.

## Review Focus
1. **Moderator output is unchanged** after `bottom-nav` / `emergency-strip` are generalized (Task 2), and Seekers/Helpers/Professionals/Admin still get neither.
2. **Adviser emergency count** equals the "Active" count on `adviser.emergencies`, is computed once per request, and the strip never shows on `adviser.emergencies*`.
3. **Live counters** (`#evalBadge`, `#emergencyBadge`, `#notifBadge`, footer stats) still update via `adviser-notifications.js` / `live-notifications.js`; the bell appears once.
4. **Pending Evaluations search/filter/sort** touches only the rows already on the page (15 per page); Evaluate and View Session links and pagination are unchanged.
5. **Evaluate form** posts the same fields; rating buttons, hidden inputs and the declaration still work; the sticky save bar never hides the submit button on phones.
6. **Detail pages with modals and confirm dialogs** (referral, emergency) keep every form, ID and `data-confirm*` working.
7. **Shared partials** (`helper-workload`, `training`, `concerns`) still render for Moderator, Helper and Admin.

Each line has its test in Tasks 2, 2, 2/3, 5, 6, 9/10, 12.

## Proposed renames (need user approval; NOT applied by any task)
| Where | Now | Proposed |
|---|---|---|
| Dashboard panel | "Session documentation" | "Sessions to evaluate" |
| Evaluations KPI | "Pending Reviews" | "Pending Evaluations" (matches the sidebar; the reference keeps "Pending Reviews") |
| Emergencies page h1 | "Emergency Management" | "Emergencies" (matches sidebar) |
| Transcripts page h1 | "Transcript access" | "Transcripts" |
| Schedule h1 | "Helper schedules" | "Helper Schedules" (capitalisation only) |
| `adviser.session` page | "Session documentation" | "Session details" |
| Helper matching page | "Matching Profile: …" | "Matching settings: …" |
| Reports | "Calculation definitions" | "How this is counted" |
| Evaluate page | "Evaluate Helper" + "Competency Evaluation" (two titles) | one title "Evaluate Helper" (structural, not a rename) |
| Dashboard / reports | "Supervised Helpers" | "My Helpers" |

---

### Task 1: Harness coverage and before shots (no app change)

**Files:**
- Modify: `scripts/ui-screenshots.mjs`
- Output (not committed): `.ui-preview/adviser-before/`

**Interfaces:**
- The harness `pages.adviser` list covers 18 list pages; detail pages are missing. Add a `discover` map: key = page label, value = `[listPage, hrefRegex]`. After login the harness fetches `listPage`, takes the first href matching the regex and screenshots it; if none, it logs `skip: <label> (no data)` and continues. Entries: `adviser/evaluate/*` (from `adviser/evaluations`, `/adviser\/evaluate\/\d+/`), `adviser/helper/*` (`adviser/helpers`), `adviser/referral/*` (`adviser/referrals`), `adviser/emergencies/*` (`adviser/emergencies`), `adviser/live/*` (`adviser/live`), `adviser/session/*` (`adviser/evaluations`), `adviser/screenings/*/conversation` (`adviser/screenings`), `adviser/transcripts/*` is a POST flow, so excluded. Also add `adviser/emergency-resources` equivalent via `adviser/resources?section=emergency`, and `adviser/training` already present.

- [ ] **Step 1:** Edit the harness as above; keep the non-adviser roles untouched.
- [ ] **Step 2: Run** `node --check scripts/ui-screenshots.mjs` — Expected: exit 0.
- [ ] **Step 3: Capture before shots** with the app running and demo data seeded: `node scripts/ui-screenshots.mjs --roles=adviser --widths=1440,390 --out=.ui-preview/adviser-before` — Expected: finishes; skipped pages are listed. The 18 list pages already exist at four widths in `.ui-preview/design-after/adviser/`; this run adds the detail pages and a current-state baseline. Do not change any app file before this step.
- [ ] **Step 4: Commit** `git add scripts/ui-screenshots.mjs && git commit -m "Screenshot harness: cover Adviser detail pages"`

### Task 2: Adviser navigation — grouped sidebar, per-role bottom bar and emergency strip, composer emergency count

**Files:**
- Create: `app/Support/RoleNav.php`, `tests/Unit/RoleNavTest.php`, `tests/Concerns/BuildsAdviserWorld.php`, `tests/Feature/AdviserNavigationTest.php`
- Modify: `app/Models/EmergencyAlert.php` (new scope), `app/Http/ViewComposers/SidebarComposer.php` (`adviserData()` adds `emergencyCount`), `app/Providers/AppServiceProvider.php` (also register `dashboard.adviser` with the composer; the per-request memo means no extra queries), `resources/views/layouts/partials/adviser-sidebar.blade.php`, `resources/views/layouts/partials/bottom-nav.blade.php`, `resources/views/partials/emergency-strip.blade.php`, `resources/views/layouts/app.blade.php` (drop the two `role === 'moderator'` wrappers; the partials gate themselves)
- Test: the files above

**Interfaces:**
- Emergency query found: `AdviserEmergencyController::index` already computes `EmergencyAlert::visibleForAdviser($adviser->id)->whereNotIn('status',['resolved','closed'])->count()` as `$activeCount`, and `NotificationController` line ~146 computes the same for `workflow.emergency_count`. Add `EmergencyAlert::scopeActiveForAdviser(Builder $q, int $adviserId)` = `visibleForAdviser($adviserId)->whereNotIn('status',['resolved','closed'])`. `adviserData()` uses it: `$emergencyCount = $adviserId ? EmergencyAlert::activeForAdviser($adviserId)->count() : 0`. Do not edit the two existing call sites.
- `RoleNav::bottomItems(string $role): array` returns `list<array{route:string, match:string|list<string>, label:string, icon:string, badge:?string, tone:?string}>`; `badge` names a composer variable. Moderator: Dashboard `moderator.dashboard`; Queue `moderator.queue*` badge `queueCount` icon `hourglass`; Emergency `moderator.emergency*` badge `emergencyCount` tone `danger`. Adviser: Dashboard `adviser.dashboard`; Pending Evaluations `adviser.evaluations*`+`adviser.evaluate*` badge `evalBadge` icon `clipboard`; Referral Queue `adviser.referrals*`+`adviser.referral.*` badge `referralBadge` icon `arrow-right`; Emergencies `adviser.emergencies*` badge `emergencyCount` tone `danger` icon `warning`. Other roles: `[]`. A More button (`data-open-sidebar`, unchanged) is appended by the partial when the list is non-empty.
- `RoleNav::emergencyStrip(string $role): ?array{route:string, hideOn:string}` — moderator `['moderator.emergency','moderator.emergency*']`, adviser `['adviser.emergencies','adviser.emergencies*']`, else `null`.
- Partials loop the arrays; markup and classes (`ui-bottom-nav`, `ui-bottom-nav-item`, `ui-bottom-nav-badge--danger`, `ui-emergency-strip`) stay identical so the moderator HTML does not change.
- Sidebar: three `.nav-section` groups per the Global Constraints; add `title` to each `.nav-item` (the Dashboard, Helpers, Referral Queue, Emergencies, Reports, Calendar, Helper Schedules, Transcripts, Resources and Areas of Concern items have none today); `#emergencyBadge` renders the initial `{{ $emergencyCount }}` and is `hidden` when 0 (JS still toggles it).

- [ ] **Step 1: Failing tests.**
  - Pin the moderator output first: write `RoleNavTest::test_moderator_items_match_the_original_bottom_bar` asserting the three routes, labels, icons, badge names and the `danger` tone.
  - `RoleNavTest`: adviser items are exactly Dashboard, Pending Evaluations, Referral Queue, Emergencies in that order; `bottomItems('seeker')`, `('helper')`, `('professional')`, `('admin')` are `[]`; `emergencyStrip('seeker') === null`.
  - `BuildsAdviserWorld` trait: `adviser()` (User role adviser + `Adviser` row, as in `AdviserEvaluationQueueTest::makeReport`), `helperFor(Adviser)`, `documentedReport(Adviser, status='completed', risk='low')`, `activeEmergency(?Adviser)` (as in `ModeratorNavigationTest::activeEmergency`).
  - `AdviserNavigationTest`: `test_sidebar_groups_titles_and_hooks` (on `adviser.reports`: sees "Evaluation", "Records", "Account", `id="emergencyBadge"`, `data-adviser-badge="pendingReviewsBadge"`, every route URL still present, and a `title="Referral Queue"`); `test_bottom_nav_lists_adviser_destinations` (the four routes + `data-open-sidebar`, and not `moderator.queue`); `test_emergency_strip_for_adviser_matches_the_active_tab_count` (none: no `ui-emergency-strip`; after one active emergency: "1 active emergency" on `adviser.reports`, absent on `adviser.emergencies` and `adviser.emergencies.show`; the number equals the "Active" tab count on the emergencies page; a resolved alert is not counted); `test_other_roles_get_no_strip_or_bottom_nav` (seeker, helper as in `ModeratorNavigationTest`); `test_adviser_emergency_count_is_computed_once_per_request` (query log: one `emergency_alerts` count query from the composer for `adviser.reports`).
- [ ] **Step 2: Run** `php artisan test --compact tests/Unit/RoleNavTest.php tests/Feature/AdviserNavigationTest.php` — Expected: FAIL (class `RoleNav` missing; no adviser strip).
- [ ] **Step 3: Implement** the scope, composer change, `RoleNav`, partials, `layouts/app.blade.php` includes and the sidebar. In the strip partial pick the route from `RoleNav::emergencyStrip(auth()->user()->role)`; in `bottom-nav` read badge values from `$$item['badge']` via `${…} ?? 0`.
- [ ] **Step 4: Run** the two test files, then `php artisan test --compact --filter="ModeratorNavigation|ModeratorPilotPages|EmergencyAllAdvisers|AdviserEvaluationQueue"` and `node --test tests/js/*.mjs tests/js/*.cjs` — Expected: PASS (moderator tests unchanged).
- [ ] **Step 5: Commit** `git add app/Support/RoleNav.php app/Models/EmergencyAlert.php app/Http/ViewComposers/SidebarComposer.php app/Providers/AppServiceProvider.php resources/views/layouts resources/views/partials/emergency-strip.blade.php tests/Unit/RoleNavTest.php tests/Concerns tests/Feature/AdviserNavigationTest.php && git commit -m "Group the Adviser sidebar; add Adviser phone bottom bar and emergency strip"`

### Task 3: Adviser dashboard — page header and "Needs your attention"

**Files:** Modify `resources/views/dashboard/adviser.blade.php`; Create `tests/Feature/AdviserRedesignPagesTest.php` (this file holds all later page tests).

**Interfaces:**
- `<x-ui.page-header title="Adviser dashboard" description="Your supervised Helpers and authorized case updates.">` with the existing "View reports" link (same `AnalyticsPeriod` query) as the one primary action in `actions`.
- `<x-ui.attention :items>` above `<x-dashboard-overview part="summary">`, counts from composer variables `$evalBadge` (amber, "Evaluations due", `adviser.evaluations`), `$screeningBadge` (blue, "Screening reviews", `adviser.screenings`), `$referralBadge` (blue, "Referrals to review", `adviser.referrals`), `$emergencyCount` (red, "Emergencies open", `adviser.emergencies`). The component hides zero items; nothing renders when all are 0. No new queries.
- Panels become `x-ui.card` (`title`, link in `actions`); tables become `x-ui.table :stack` with `data-label` cells; the existing `av-badge` becomes `x-ui.badge`; empty rows become `x-ui.empty`. Keep `<x-dashboard-overview>` calls and `<x-helper-capacity>` as they are; remove `av-page` wrapper padding only where `x-ui.card` replaces it.

- [ ] **Step 1: Failing tests** in `AdviserRedesignPagesTest`: `test_dashboard_has_header_and_one_bell_slot` (`data-page-header`, one `<h1`, one `data-notification-slot`, zero `data-page-slot`); `test_attention_strip_lists_only_waiting_work` (empty adviser: no "Needs your attention"; after `documentedReport` + `activeEmergency`: "Evaluations due" linking `adviser.evaluations`, "Emergencies open" linking `adviser.emergencies`, no "Referrals to review"); `test_attention_counts_match_sidebar_badges` (strip number equals the `evalBadge` number).
- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/AdviserRedesignPagesTest.php` — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the file plus `php artisan test --compact --filter="AdviserRefinement|DashboardOverview|RoleKpi"` — Expected: PASS (known notification-archive failure aside).
- [ ] **Step 5: Commit** `git add resources/views/dashboard/adviser.blade.php tests/Feature/AdviserRedesignPagesTest.php && git commit -m "Rebuild the Adviser dashboard with a Needs your attention strip"`

### Task 4: Pending Evaluations to the reference

**Files:** Modify `resources/views/adviser/evaluations.blade.php`, `resources/views/components/ui/kpi.blade.php`, `resources/views/components/evaluation-steps.blade.php`, `public/css/compass-components.css`; Test: `tests/Feature/UiComponentsTest.php`, `tests/Feature/AdviserRedesignPagesTest.php`.

**Interfaces:**
- `<x-ui.kpi … :icon="null">`: new optional icon name; when set renders `span.ui-kpi-icon` with `<x-ui-icon>` left of the label/value (reference layout: icon tile, label, big number, note). With `icon` null the output is byte-identical to today (Moderator unaffected). Phone: icon hidden ≤480px to keep the 2-per-row rule.
- `<x-evaluation-steps :current :compact :variant>`: add `variant="stepper"` for the reference's horizontal process card (numbered circles joined by a line, done = check, current highlighted); default markup unchanged for `evaluate.blade.php`. Add `variant="dots"` for the row progress (four small connected circles, no titles). Keep `aria-label="Evaluation sequence"` and `aria-current="step"`.
- Page: `x-ui.page-header title="Pending Evaluations" description="Review session documentation and evaluate helper competency."`; `x-ui.kpi-grid` with Pending Reviews (`$totalPending`, green, icon `clipboard`, note "Sessions awaiting review"), High Risk (`$highRiskPending`, red, icon `warning`, "Require immediate attention"), Total Feedback Given (`$feedbackCount`, green, icon `check-circle`, "Evaluations completed"); `x-ui.card` "Evaluation Process" holding the stepper (`current=3`).
- "Awaiting review" `x-ui.card` with a `x-ui.badge` "{n} sessions" in the title area, then one `div.ui-eval-row[data-eval-row]` per `$pendingReports` item: left edge colored by risk (`--low/--moderate/--high/--emergency` modifier on `ui-eval-row`), avatar, alias, "helper · date · type", `x-ui.badge` risk (low green, moderate amber, high/emergency red), "Concern: …" plus the existing "Referral Recommended" note, `variant="dots"` steps with caption "Awaiting your evaluation", and two actions: **View Session** (outline, `adviser.session.show`) and **Evaluate** (primary, `adviser.evaluate`). No row "⋮" menu. Rows stack at ≤768px with full-width buttons.
- Keep: `{{ $pendingReports->links() }}` (switch to `links('vendor.pagination.compass', ['noun' => 'sessions'])`), the "All caught up!" empty state with `$heldReports` / `$undocumentedReports` sentences (`x-ui.empty`), the "Recent reviews" / "Archived reviews" links (`?history=archived`), the "Recently Completed" / archived list with `@include('adviser.partials.archive-action')` exactly as is, the "Quality supervision leads to quality support." footer line. Delete the page's `<style>` block (it redefines `:root` colors, `.card`, `.btn-primary`, and shrinks `.stat-number` on phones).

- [ ] **Step 1: Failing tests.** `UiComponentsTest::test_kpi_icon_is_optional` (no `ui-kpi-icon` without icon; present with icon; label still before value). `AdviserRedesignPagesTest`: `test_pending_evaluations_matches_reference` (using `documentedReport` ×2, one high risk: `ui-kpi--red`, `ui-kpi-icon`, "Evaluation Process", "Awaiting review", "2 sessions", two `data-eval-row`, `ui-eval-row--high`, "Awaiting your evaluation", both link URLs `adviser.evaluate` / `adviser.session.show`, no `⋮`); `test_pending_evaluations_empty_state_keeps_held_and_undocumented_notes` (reuse the scenarios of `AdviserEvaluationQueueTest`); `test_pending_evaluations_keeps_history_links_and_archive_action`.
- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/UiComponentsTest.php tests/Feature/AdviserRedesignPagesTest.php` — Expected: FAIL.
- [ ] **Step 3: Implement** (CSS in `compass-components.css`: `.ui-eval-row` 4px risk-colored left border, 52px+ rows, new rules only on the font scale).
- [ ] **Step 4: Run** both files plus `php artisan test --compact --filter="AdviserEvaluationQueue|AdviserCompletion|ConfirmedSupervisionCorrection|DesignScale"` — Expected: PASS.
- [ ] **Step 5: Commit** `git add resources/views/adviser/evaluations.blade.php resources/views/components/ui/kpi.blade.php resources/views/components/evaluation-steps.blade.php public/css/compass-components.css tests/Feature/UiComponentsTest.php tests/Feature/AdviserRedesignPagesTest.php && git commit -m "Rebuild Pending Evaluations to the Adviser reference design"`

### Task 5: Pending Evaluations search, risk filter and sort (client-side; droppable)

Self-contained. If the user says no, skip this task; Task 4 stands alone and nothing later depends on it.

**Files:** Create `public/js/eval-list-filter.js`, `tests/js/eval-list-filter.test.cjs`; Modify `resources/views/adviser/evaluations.blade.php` (controls, row `data-*`, `@push('scripts')`), `public/css/compass-components.css`; Test: `tests/Feature/AdviserRedesignPagesTest.php`.

**Interfaces:**
- Rows get `data-eval-row data-risk="low|moderate|high|emergency" data-time="{{ created_at->timestamp }}" data-alias="…" data-search="{{ lowercased alias helper concern }}"`.
- Controls above the list, no server change, no form: search `#evalSearch` ("Search by help seeker, helper, or concern…"), risk `#evalRisk` (All Risk Levels / High (includes emergency) / Moderate / Low), sort `#evalSort` (Newest First default, Oldest First, Highest Risk First, Alias A–Z). Built with `x-ui.field` styling but markup-only (outside any `<form>`).
- `public/js/eval-list-filter.js` exports (CommonJS guard like the other `public/js` modules tested from `tests/js/*.cjs`) `filterRows(rows:[{risk,time,alias,search}], {q, risk, sort}) => number[]` (ordered indices to show) and, in the browser, wires the controls: hides non-matching rows, reorders by moving DOM nodes, updates "Showing x of y on this page" and toggles `#evalNoMatch` ("No sessions on this page match."). Pagination links and counts from the server are untouched; the note says the filter applies to this page only.
- `Highest Risk First` order: emergency, high, moderate, low, ties by newest.

- [ ] **Step 1: Failing tests.** JS (`eval-list-filter.test.cjs`): search matches alias/helper/concern case-insensitively; `risk=high` keeps high and emergency; each sort order; empty query returns all in original order. PHP: `test_pending_evaluations_has_client_side_controls_and_no_server_filter` (`#evalSearch #evalRisk #evalSort`, `data-eval-row`, script `eval-list-filter.js` present; `?q=x&risk=high` leaves the row count unchanged).
- [ ] **Step 2: Run** `node --test tests/js/eval-list-filter.test.cjs` and `php artisan test --compact tests/Feature/AdviserRedesignPagesTest.php` — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** both — Expected: PASS.
- [ ] **Step 5: Commit** `git add public/js/eval-list-filter.js tests/js/eval-list-filter.test.cjs resources/views/adviser/evaluations.blade.php public/css/compass-components.css tests/Feature/AdviserRedesignPagesTest.php && git commit -m "Pending Evaluations: search, risk filter and sort for the rows on the page"`

### Task 6: Evaluate form — titled sections, errors by field, sticky save bar

**Files:** Modify `resources/views/adviser/evaluate.blade.php`; Test: `tests/Feature/AdviserRedesignPagesTest.php`.

**Interfaces:**
- One `x-ui.page-header title="Evaluate Helper" description="Competency evaluation form" back-href=route('adviser.evaluations') back-label="Back to Pending Evaluations"` (replaces the two `<h1>` and the inline "Back to Evaluations" link). Header action: **View conversation** button keeps `onclick="openConversation()"`; `#conversationModal` unchanged.
- Summary card (spec §6.4) above the form: Seeker alias, Helper, Date, Concern, risk `x-ui.badge`, then `<x-evaluation-steps variant="stepper" :current>`.
- Titled `x-ui.card` sections: "Session record" (summary, result, follow-up, reflection, skills, documentation), "Referral and risk review", "Evidence and rating guide" (the descriptors `<details>` stays collapsed), "Your evaluation" (the form: five rating groups with `data-target`, `.rating-btn`, hidden inputs `active_listening empathy ethical_practices referral_accuracy respect_professionalism`, `#overallScore`, `#competencyLevelDisplay`, comments/follow-up date/correction reason/declaration `#evaluationDeclaration`).
- Errors: after each field add `@error('name')<p class="ui-field-error" role="alert">{{ $message }}</p>@enderror` for every posted field; keep the top flash blocks. Form `action`, `method`, `@csrf` and all `name`s unchanged; fields stay inside the one `<form>`.
- Save bar following the moderator settings pattern: inside the form, `div.ui-save-bar{{ $errors->any() ? ' is-visible' : '' }}[data-save-bar]` with the existing submit button as its button (same text, `type="submit"`); `@push('scripts')` adds `js-save-bar` to `<html>` and reveals the bar on `input`/`change` and when a rating button is clicked (hidden inputs do not fire `input`, so also listen for clicks on `.rating-btn`). Existing inline `<script>` (selectRating, openConversation, overall score) is kept as is.

- [ ] **Step 1: Failing tests:** `test_evaluate_form_has_sections_save_bar_and_unchanged_contract` (one `<h1`, "Back to Pending Evaluations", `ui-save-bar`, the form action URL from `adviser.evaluate.store`, all five `data-target` groups and `name="active_listening"`…`name="respect_professionalism"`, `#evaluationDeclaration`, `openConversation`); `test_evaluate_validation_errors_show_next_to_fields_and_open_the_save_bar` (POST an empty form, follow redirect: `ui-save-bar is-visible` and `ui-field-error`).
- [ ] **Step 2: Run** the file — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the file plus `php artisan test --compact --filter="AdviserCompletion|AdviserCompetencyComparison|ConfirmedSupervisionCorrection|AdviserEvaluationQueue"` — Expected: PASS.
- [ ] **Step 5: Commit** `git add resources/views/adviser/evaluate.blade.php tests/Feature/AdviserRedesignPagesTest.php && git commit -m "Evaluate form: titled sections, errors by field and a sticky save bar"`

### Task 7: Screening Reviews and Screening conversation

**Files:** Modify `resources/views/adviser/screenings.blade.php`, `resources/views/adviser/screening-conversation.blade.php`, (partials `adviser/partials/screening-follow-up.blade.php`, `emergency-screening-summary.blade.php` only if they carry page-level wrappers).

**Interfaces:** `x-ui.page-header` "Screening Reviews" (description from the existing intro line); filter pills `data-filter="all|pre-session|reassessment"` become a tab row of links/buttons that keep the `data-filter` attributes and the existing `@push('scripts')` behavior; each review `x-ui.card` with risk `x-ui.badge`; review/follow-up forms (`adviser.screenings.review`, `.follow-up`) keep action, method, names and confirm attributes; empty state `x-ui.empty`. Conversation page: `back-href=route('adviser.screenings') back-label="Back to Screening Reviews"` and a summary card (alias, risk, submitted date) above the messages.

- [ ] **Step 1: Failing tests:** `test_screenings_page_header_filters_and_forms` (one `data-page-header`, three `data-filter`, form action `adviser.screenings.review` still present for a pending screening, as in `AdviserScreeningNoticeTest`); `test_screening_conversation_has_back_link_and_summary` (`ui-back-link`, "Back to Screening Reviews").
- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/AdviserRedesignPagesTest.php` — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the file plus `php artisan test --compact --filter="AdviserScreeningNotice|AdviserPrivacy|AdviserRefinement"` — Expected: PASS.
- [ ] **Step 5: Commit** `git add resources/views/adviser/screenings.blade.php resources/views/adviser/screening-conversation.blade.php resources/views/adviser/partials tests/Feature/AdviserRedesignPagesTest.php && git commit -m "Rebuild Screening Reviews and the screening conversation"`

### Task 8: Manage Helpers, Helper detail, Add a Helper and Matching

**Files:** Modify `resources/views/adviser/{helpers,helper-detail,helper-create,helper-matching}.blade.php`.

**Interfaces:**
- `helpers`: header "Manage Helpers" with primary **Add a Helper** (`adviser.helpers.create`) and secondary Export (`adviser.helpers.export`); the four `.stat-card`s map one-to-one to `x-ui.kpi` (Total Helpers neutral, Available green, Advanced / Expert blue, and the existing fourth card); filters via `x-ui.filter-bar` + `FilterChips::from` for each existing query key; table via `x-ui.table :stack` with `data-label`; `x-ui.pagination`-style `links('vendor.pagination.compass', ['noun' => 'helpers'])`; reassign form `adviser.helpers.reassign` and its confirm attributes unchanged. Delete the page `<style>` that redefines `.stat-number` (phone override).
- `helper-detail`: one h1 (helper name) with `back-href=route('adviser.helpers') back-label="Back to Manage Helpers"`; summary `x-ui.card` (readiness, availability, level, assigned adviser, workload); sections become `x-ui.card`; forms `adviser.helper.review.complete`, `.verify`, `.competency.update`, `.assign`, `adviser.helpers.reassign` keep names; ids `#helper-review-title #qualification_evidence #review_resolution` kept.
- `helper-create`: `back-href` "Back to Manage Helpers", title "Add a Helper"; fields in titled `x-ui.card` sections (Account, Qualification evidence, After creation); IDs `#hcFirst #hcLast #hcEmail #hcAccount #hcEvidence #hcProof #hcAfter` and errors next to fields; sticky save bar not needed (short form).
- `helper-matching`: `back-href` "Back to Manage Helpers", one h1, summary card, matching table via `x-ui.table :stack`, form `adviser.helper.competency.update` unchanged.

- [ ] **Step 1: Failing tests:** `test_manage_helpers_uses_shared_blocks` (`ui-kpis`, `ui-table--stack`, "Add a Helper" link to `adviser.helpers.create`, `?q=…` filter yields a chip if the page has a search key); `test_helper_detail_and_matching_have_back_link_and_summary`; `test_add_helper_form_keeps_ids_and_action` (action `adviser.helpers.store`, `id="hcEmail"`).
- [ ] **Step 2: Run** the file — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the file plus `php artisan test --compact --filter="AdviserHelperAccount|ModeratorAdviserModule|AdviserRefinement|AdviserCompetencyComparison"` — Expected: PASS.
- [ ] **Step 5: Commit** `git add resources/views/adviser/helpers.blade.php resources/views/adviser/helper-detail.blade.php resources/views/adviser/helper-create.blade.php resources/views/adviser/helper-matching.blade.php tests/Feature/AdviserRedesignPagesTest.php && git commit -m "Rebuild Manage Helpers, Helper detail, Add a Helper and Matching"`

### Task 9: Referral Queue and Referral detail

**Files:** Modify `resources/views/adviser/referrals.blade.php`, `resources/views/adviser/referral-detail.blade.php`, `resources/views/adviser/partials/referral-next-step.blade.php` (wrapper only).

**Interfaces:**
- `referrals`: header "Referral Queue"; `.stat-card`s → `x-ui.kpi` (Pending Review amber, Emergency Cases red, Approved green, plus any existing card); list/table via `x-ui.table :stack` with priority `x-ui.badge`; approve/reject modals `#approveModal #approveForm #rejectModal #rejectForm` and their JS are untouched (only the wrapping page markup and the delete of the page `<style>` copy change); pagination `links('vendor.pagination.compass', ['noun' => 'referrals'])`.
- `referral-detail`: single h1 "Referral Detail" with `back-href=route('adviser.referrals') back-label="Back to Referral Queue"`; summary `x-ui.card` (status badge, priority, Seeker alias, Helper, Professional, dates, case reference) above the long sections; each existing section becomes `x-ui.card`; all forms keep action/method/names/IDs/`data-confirm*`: identity emergency review (`identity.emergency-review`), `professionalAssignment`/`adviser.referral.assign`, `professionalTransfer`/`adviser.referral.transfer`, `professionalIdentityRelease`/`identity.release`, `approveForm`, `rejectForm`, `reviseForm`.

- [ ] **Step 1: Failing tests:** `test_referral_queue_uses_shared_blocks_and_keeps_modals` (`ui-kpi--red`, `ui-table--stack`, `id="approveModal"`, `id="rejectForm"`); `test_referral_detail_has_back_link_summary_and_forms` (use a pending-adviser referral as in `ConsentReferralEmergencyTest`/`AdviserProfessionalActionUiTest`: `ui-back-link` "Back to Referral Queue", one `<h1`, `id="approveForm"` with action `adviser.referral.approve`, `id="reviseForm"`).
- [ ] **Step 2: Run** the file — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the file plus `php artisan test --compact --filter="AdviserProfessionalActionUi|ConsentReferralEmergency|ReferralDeliveryWorkflow|ReferralCompletionReview|ReferralIdentityForm|AdviserPrivacy"` — Expected: PASS.
- [ ] **Step 5: Commit** `git add resources/views/adviser/referrals.blade.php resources/views/adviser/referral-detail.blade.php resources/views/adviser/partials/referral-next-step.blade.php tests/Feature/AdviserRedesignPagesTest.php && git commit -m "Rebuild the Referral Queue and Referral detail"`

### Task 10: Emergencies, emergency detail, coordination and emergency contacts

**Files:** Modify `resources/views/adviser/{emergencies,emergency-detail,emergency-coordination,emergency-resources}.blade.php`, `resources/views/adviser/partials/{emergency-handoff,emergency-screening-summary}.blade.php` (wrappers only), `public/css/emergency-workspace.css` (drop only rules whose markup is removed).

**Interfaces:**
- `emergencies`: header "Emergency Management" (rename not applied) with `x-ui.kpi-grid` replacing `<x-performance-cards>` (Active red, Resolved green, Total neutral); tabs Active/Resolved keep their `tab=` links and counts; search keeps `#emergencySearch name="q"` and the hidden `tab`; filter chip for `q`; table via `x-ui.table :stack` with `data-label`; status `x-ui.badge`; `links('vendor.pagination.compass', ['noun'=>'cases'])`; the `emergency-workspace.css` link stays.
- `emergency-detail`: one h1 "Emergency review" with `back-href=route('adviser.emergencies') back-label="Back to Emergencies"`; summary card (case #, status badge, risk, coordinator, Seeker alias, Helper, detected/acknowledged times); sections `x-ui.card`; every form (`emergencies.action` ×2, `.resolve`, `.coordinator`, `.referral-request`, `.professional`) and IDs/`data-focus-label` unchanged.
- `emergency-coordination` (notice page for non-owning advisers): header plus summary card, back link to `adviser.emergencies`. `emergency-resources`: header, `x-ui.card` per directory section, form `adviser.emergency-resources.save` unchanged.

- [ ] **Step 1: Failing tests:** `test_emergencies_list_uses_kpis_tabs_and_search` (`ui-kpi--red`, `id="emergencySearch"`, `ui-table--stack`, "Active" tab link); `test_emergency_detail_has_back_link_summary_and_keeps_forms` (`ui-back-link` "Back to Emergencies", one `<h1`, form action `adviser.emergencies.resolve`, `id="resolutionNotes"`, `id="handoffReason"`); `test_emergency_strip_is_hidden_on_emergency_pages_but_shown_elsewhere` (already in Task 2; re-run).
- [ ] **Step 2: Run** the file — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the file plus `php artisan test --compact --filter="EmergencyAllAdvisers|EmergencyResolutionSync|ConsentReferralEmergency|EscalationOperations|October15SafetyPrivacy"` — Expected: PASS.
- [ ] **Step 5: Commit** `git add resources/views/adviser/emergency-*.blade.php resources/views/adviser/emergencies.blade.php resources/views/adviser/partials public/css/emergency-workspace.css tests/Feature/AdviserRedesignPagesTest.php && git commit -m "Rebuild Emergencies, emergency detail, coordination and contacts"`

### Task 11: Live sessions, Live session, Session documentation, Transcripts and Transcript content

**Files:** Modify `resources/views/adviser/{live-sessions,live-session,session,transcript-review,transcript-content}.blade.php`.

**Interfaces:**
- `live-sessions`: header "Live sessions" (rename "Live Sessions" is sidebar-only) with secondary **Refresh**; cards become `x-ui.card`/`x-ui.list-row` grid with a red "Live" `x-ui.badge`, risk badge, **Watch live** primary; `x-ui.empty` when none. Delete the page `<style>`.
- `live-session`: `back-href=route('adviser.live.index') back-label="Back to Live sessions"`, summary card (Helper, Seeker alias, risk, started time); keep `[data-live-note-form]`, `#lwCoach #lwNote #lwTranscript`, the feed polling script and `adviser.live.notes` / `adviser.live.feed` URLs.
- `session` (`adviser.session.show`): `back-href` "Back to Dashboard" (keep target), summary card, revisions list in `x-ui.card`.
- `transcript-review`: header "Transcript access", table via `x-ui.table :stack`, each access form `adviser.transcript.access` (class `at-access-form`) and its script unchanged. `transcript-content`: `back-href=route('adviser.transcripts') back-label="Back to Transcripts"`, summary card (reference, Helper, Seeker alias, verified state), verify form `adviser.transcript.verify` unchanged.

- [ ] **Step 1: Failing tests:** `test_live_sessions_page_and_watch_page` (as `AdviserLiveSessionTest` builds an active chat: `data-page-header`, "Watch live", on `adviser.live.show` `ui-back-link`, `data-live-note-form`, `id="lwNote"`); `test_transcript_pages_have_header_and_back_link` (`adviser.transcripts` has one `data-page-header`; `adviser.session.show` has `ui-back-link`).
- [ ] **Step 2: Run** the file — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the file plus `php artisan test --compact --filter="AdviserLiveSession|AdviserPrivacy|ChatWorkflowRepairs|AdviserRefinement"` — Expected: PASS.
- [ ] **Step 5: Commit** `git add resources/views/adviser/live-sessions.blade.php resources/views/adviser/live-session.blade.php resources/views/adviser/session.blade.php resources/views/adviser/transcript-review.blade.php resources/views/adviser/transcript-content.blade.php tests/Feature/AdviserRedesignPagesTest.php && git commit -m "Rebuild Live sessions, Session documentation and Transcripts"`

### Task 12: Reports, Calendar, Schedule, Resources, Training, Areas of Concern, Notifications and Settings

**Files:** Modify `resources/views/adviser/{reports,calendar,schedule,resources,training,notifications,settings}.blade.php`, `resources/views/concerns/index.blade.php`, `public/css/adviser-refinement.css` (remove only rules whose classes no longer appear in any view: verify each with `Grep` before deleting; keep `av-button`, `av-note`, `ew-*` and anything still referenced).

**Interfaces:**
- `reports`: header with Export (`adviser.reports.export`) and Analytics links; tabs row keeps `tab=` links; the filter `<form>` becomes `x-ui.filter-bar` (same names `period from to helper_id concern_id case_status referral_status history competency_metric emergency_status priority`, Apply + Reset), plus `FilterChips::from` for those keys; `<dl class="av-stats">` becomes `x-ui.kpi-grid` (same four labels per tab); tables `x-ui.table :stack`; charts keep `<x-report-charts>` / `<x-analytics-chart>`; "Calculation definitions" stays a collapsed `<details>` (rename only if approved).
- `calendar`: header "Calendar" with primary **Add event** keeping `#openEventModal`; `.stat-card`s → `x-ui.kpi` (Sessions, Evaluations, Events); `#calGrid #dayPanel #dayPanelList #dayPanelTitle #eventModal #closeEventModal #closeDayPanel #eventDateInput` and its script untouched.
- `schedule`: header; duty form `#adviserDutyForm` in an `x-ui.card`; duty table `x-ui.table :stack` keeping `[data-shift-edit]` buttons and `#dutySubmit #dutyReset`; `partials/helper-workload` is rendered as the Moderator pilot left it (no change here; only verify).
- `resources`: header with primary **Add resource** keeping `#openAddModal`; `.stat-card`s → `x-ui.kpi` (Total, Published, Total Views…); `#resourceForm #resourceModal #categorySelect #closeModal #modalTitle` and archive confirm attributes unchanged. `training`: header; works for Helper too (`$isAdviser`), so render with `x-ui.card`/`x-ui.table` without Adviser-only wording changes; forms `adviser.training.store/update` and `helper.training.update` unchanged.
- `concerns/index`: header "Areas of concern"; list `x-ui.table :stack`; ids `#addConcernTitle #categoriesTitle #categoryStatus #newConcernName #newConcernDescription`; must render for Admin as well.
- `notifications`: header with Archived/Inbox and "Mark all read" actions as today; the type filter `x-ui.filter-bar` (`type`, hidden `history`); rows become `x-ui.list-row` inside `.ui-notification-list` keeping the `read` / `destroy` / `restore` forms and `av-notification-unread`/emergency states as modifier classes; `links('vendor.pagination.compass', ['noun'=>'notifications'])`.
- `settings`: header; keep tabs `[data-tab]` and `#section-profile|password|appearance|notifications`; each section `x-ui.card`; each of the four forms (`adviser.settings.profile|password|appearance?|notifications` — keep whichever actions exist) gets a `ui-save-bar[data-save-bar]` revealed on change and after errors, using the moderator settings script pattern (`form[data-settings-form]`); errors next to fields.

- [ ] **Step 1: Failing tests:** `test_reports_has_filter_bar_chips_and_kpis` (`?tab=safety&priority=high` → chip "Priority: high", `ui-filter-bar`, `ui-kpis`); `test_calendar_resources_schedule_keep_hooks` (`id="calGrid"`, `id="openAddModal"`, `id="adviserDutyForm"` + `data-helper-workload`, `data-workload-body`); `test_settings_forms_have_save_bars_and_keep_actions` (a `ui-save-bar` inside each form for `adviser.settings.profile`, `.password`, `.notifications`; three tabs still `data-tab`); `test_notifications_and_concerns_use_page_header` (concerns also OK for an admin user); `test_training_still_renders_for_helper` (helper view of the training route returns 200 with `data-page-header`).
- [ ] **Step 2: Run** the file — Expected: FAIL.
- [ ] **Step 3: Implement** (do each page, then the CSS pruning last).
- [ ] **Step 4: Run** the file plus `php artisan test --compact --filter="Adviser|RoleReportsNavigation|RoleKpi|UiStandardization|ModeratorReadinessScheduling|HelperModule|AdminSidebarParity"` and `node --test tests/js/*.mjs tests/js/*.cjs` — Expected: PASS (known notification-archive failure aside).
- [ ] **Step 5: Commit** `git add resources/views/adviser resources/views/concerns/index.blade.php public/css/adviser-refinement.css tests/Feature/AdviserRedesignPagesTest.php && git commit -m "Rebuild the remaining Adviser pages on the shared design"`

### Task 13: Verification, screenshots and review stop

- [ ] **Step 1:** `npm run build` — Expected: exit 0. `php artisan view:cache` — Expected: "Blade templates cached successfully". `php artisan view:clear` afterwards.
- [ ] **Step 2:** `node scripts/ui-screenshots.mjs --roles=adviser --widths=1920,1440,1024,390 --out=.ui-preview/adviser` — Expected: `0 issue(s)` excluding intended `modal-open`; fix and re-run, committing fixes as `Fix Adviser layout issues found by screenshots`. Check at 390 specifically: bottom bar does not cover the save bar or page end, 2 KPIs per row, stacked tables show label and value side by side, one bell, no horizontal overflow.
- [ ] **Step 3:** `php artisan test --compact` — Expected: only the known `AdviserRefinementTest` failure. `node --test tests/js/*.cjs tests/js/*.mjs` — Expected: all pass. `node scripts/ui-screenshots.mjs --roles=moderator,helper,seeker,professional,admin --widths=1440,390 --out=.ui-preview/adviser-others` — Expected: no new issues (confirms the generalized nav left other roles unchanged).
- [ ] **Step 4:** Stop and show the user before/after pairs: Pending Evaluations, Dashboard, Evaluate form, Referral Queue, Emergencies (1440 and 390), plus the sidebar and bottom bar. Before shots: `.ui-preview/design-after/adviser/` (18 list pages) and `.ui-preview/adviser-before/` (detail pages, Task 1); after shots: `.ui-preview/adviser/`. Ask the user to decide the Task 5 filter (kept or dropped) and the renames table. Do not start the Helper role until the user approves.
