# UI Redesign — Foundation + Moderator Pilot Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the larger, user-friendly COMPASS scale, the shared UI building blocks, and a fully converted Moderator role that matches the Nov 11 reference screens.

**Architecture:** Anonymous Blade components under `resources/views/components/ui/` styled by one new stylesheet `public/css/compass-components.css` (loaded by `partials.ui-assets`). Moderator pages move onto `layouts.app` and are rebuilt from the components. Navigation additions (bottom bar, emergency strip) are partials included by `layouts.app`. Later roles get their own plans after the pilot review (spec §7).

**Tech Stack:** Laravel 12 Blade, Tailwind (existing), plain CSS, vanilla JS, Chart.js 4.5.1 via `<x-analytics-chart>`, PHPUnit (SQLite), `node --test`, Playwright harness `scripts/ui-screenshots.mjs`.

**Spec:** `docs/superpowers/specs/2026-11-11-compass-ui-redesign-design.md`

## Global Constraints
- No change to routes, controller logic, form `action`/`method`/field `name`s, permissions, workflows, KPI formulas or data. IDs and `data-*` hooks used by JS (`#emergencyBadge`, `#queueBadge`, `#sessionBadge`, `#notifBadge`, `#liveCount`, `#queueCount`, `#emergencyCount`, `[data-workload-*]`, `[data-helper-workload]`, `#moderatorEmergencyConfig`, `#dutyForm`, `[data-duty-remove]`, `data-confirm*`) must survive.
- Default COMPASS theme only: green `#04a052` / dark `#027039`, white cards, Inter. No new themes; Settings → Appearance keeps text size, high contrast and reduced motion.
- New scale (spec §1): body 15px; captions 13–14px; section/card title 20px/700; h1 28px/800 (24px ≤480px); KPI number 40px/800 (32px ≤768px); buttons and inputs 44px, 15px text, pill buttons; table rows ≥52px; card padding 24px (16px ≤768px). Allowed font sizes: 11, 12, 13, 14, 15, 16, 18, 20, 24, 28, 32, 40px.
- Breakpoints: ≤480, ≤768, 769–1150, ≥1500 (DESIGN.md §10).
- Label renames allowed in this pilot (approved in spec): "Connection review" → "Reconnection requests"; "Unassigned Pool" → "Helpers without an Adviser". No other renames without asking.
- Commit each task separately; no Claude co-author trailer; never commit `.env`, `app/Http/Controllers/Adviser/AdviserNotificationController.php`, `public/sw.js`; never `migrate:fresh`; push only when the user says so.
- Known unrelated failure: `AdviserRefinementTest` notification archive.

## Review Focus
1. **Live counters after the move to `layouts.app`:** sidebar badges and footer stats still update via their IDs (moderator-emergency.js / live-notifications.js), and the bell appears exactly once in the page header.
2. **Emergency strip:** shows on every Moderator page while `$emergencyCount > 0`, never for Seekers/Helpers/Professionals, and does not duplicate the existing staff emergency popup.
3. **Phone tables:** stacked-card mode keeps every row action (forms, confirm dialogs) usable at 390px; no horizontal page overflow.
4. **Filter chips:** removing one chip keeps every other query parameter (tab, overview, archive, workload_*), and Reset still clears all.
5. **Helper Workload partial is shared with the Adviser schedule page:** restyling it must keep `[data-workload-*]` hooks and work on `adviser.schedule`.

Each line has its test in the owning task (Tasks 2, 4, 3, 2, 7).

---

### Task 1: New scale — tokens, base typography, DESIGN.md, scale test

**Files:**
- Modify: `public/css/compass-ui.css` (`:root` tokens; base `body`, h1/h2/h3, `.btn*`, form controls, `--compass-control-height`)
- Modify: `DESIGN.md` §3 Typography, §6 Components (buttons 44px pill, rows 52px, card padding 24px), §7 KPI tiles, §11 prompt
- Modify: `tests/Unit/DesignTokensTest.php`, `tests/Unit/DesignScaleTest.php`

**Interfaces:**
- Produces tokens: `--text-2xs:11px --text-xs:12px --text-sm:13px --text-caption:14px --text-base:15px --text-md:16px --text-lg:18px --text-xl:20px --text-2xl:24px --text-3xl:28px --text-4xl:32px --text-kpi:40px --control-h:44px --row-h:52px --card-pad:24px --radius-pill:999px` (existing space/radius/shadow tokens unchanged; `--compass-control-height: var(--control-h)`).

- [ ] **Step 1: Update tests first**
  - `DesignTokensTest::test_type_scale_matches_design_md` expected map becomes the 12 tokens above with their px values.
  - `DesignScaleTest::TYPE_SCALE` becomes `[11, 12, 13, 14, 15, 16, 18, 20, 24, 28, 32, 40]`; detector test unchanged.
- [ ] **Step 2: Run** `php artisan test --compact tests/Unit/DesignTokensTest.php tests/Unit/DesignScaleTest.php` — Expected: FAIL (`--text-base should be 15px`, missing `--text-kpi`).
- [ ] **Step 3: Implement** tokens and base rules in `compass-ui.css`: non-admin `body` 15px; h1/.page-title 28px/800 (24px ≤480); h2 20px/700; h3/.section-title 18px/700; buttons min-height `var(--control-h)`, 15px, radius `var(--radius-pill)`; inputs 44px, 15px; table cells 14px with `min-height` rows 52px (`td,th{padding:14px 16px}`); `.card`-family padding `var(--card-pad)` (16px ≤768). Update DESIGN.md tables to the same numbers and the `11/…/40` list.
- [ ] **Step 4: Run** the same two tests — Expected: PASS. Then `npm run build` — Expected: exit 0.
- [ ] **Step 5: Commit** `git add public/css/compass-ui.css DESIGN.md tests/Unit/DesignTokensTest.php tests/Unit/DesignScaleTest.php && git commit -m "Move COMPASS to the larger, easier-to-read type scale"`

### Task 2: Core building blocks — header, card, KPI, badge, empty, filter bar, chips

**Files:**
- Create: `resources/views/components/ui/{page-header,card,kpi-grid,kpi,badge,empty,filter-bar,field,filter-chips}.blade.php`
- Create: `public/css/compass-components.css`; Modify: `resources/views/partials/ui-assets.blade.php` (load it after `compass-ui.css`, before `compass-layout.css`)
- Modify: `public/js/live-notifications.js:19` (slot preference)
- Test: `tests/Feature/UiComponentsTest.php`, `tests/js/live-notifications.test.cjs`

**Interfaces (props → markup contract):**
- `<x-ui.page-header :title :description="null" :back-href="null" :back-label="null">` + `{{ $actions }}` slot → `<header class="ui-page-header" data-page-header>` with `h1`, `p.ui-page-desc`, optional `a.ui-back-link` ("← {back-label}"), `div.ui-page-actions` holding the actions slot then `<div data-notification-slot data-page-slot></div>`.
- `<x-ui.card :title="null" :subtitle="null" :id="null">` + `$actions` slot → `section.ui-card` (`h2.ui-card-title`, `p.ui-card-sub`, `div.ui-card-actions`).
- `<x-ui.kpi-grid>` → `div.ui-kpis`; `<x-ui.kpi :label :value :tone="'neutral'" :note="null" :href="null">` (tones `neutral|green|amber|blue|red`) → `div.ui-kpi.ui-kpi--{tone}` (or `a` when `href`) with `span.ui-kpi-label` above `strong.ui-kpi-value` (40px, centered) and optional `small.ui-kpi-note`.
- `<x-ui.badge :tone="'neutral'">` tones `neutral|green|amber|orange|red|blue` → `span.ui-badge.ui-badge--{tone}`.
- `<x-ui.empty :title :text="null">` + optional slot (one action) → `div.ui-empty`.
- `<x-ui.filter-bar :action="null">` (GET form, `$slot` fields, `$buttons` slot defaulting to Apply) → `form.ui-filter-bar`; `<x-ui.field :label :for>` wraps a control with `label` + chevron/calendar decoration via `data-control="select|date|search"`.
- `<x-ui.filter-chips :chips :reset-href>` where `chips` = `array<string label, string removeHref>` → `div.ui-chips` of `a.ui-chip` ("label ×"), plus "Clear all" when ≥2.
- Helper: `App\Support\FilterChips::from(Request $request, array $labels, array $keep = []): array` — for each non-empty query key in `$labels` returns `['label' => "{$labels[$key]}: {value}", 'href' => url()->current().'?'.http_build_query(request()->except($key))]`. Create `app/Support/FilterChips.php`.
- live-notifications.js: `const slot = document.querySelector('[data-page-slot]') || document.querySelector('[data-notification-slot]');`

- [ ] **Step 1: Write failing tests** in `UiComponentsTest` (use `Blade::render`):
  - `test_page_header_renders_title_actions_and_single_bell_slot`: contains `data-page-header`, `<h1`, the title, the actions HTML, exactly one `data-page-slot`.
  - `test_kpi_puts_label_above_value_and_applies_tone`: label position before value in HTML; class `ui-kpi--red`.
  - `test_filter_chips_remove_one_filter_and_keep_the_rest`: request `?case_status=waiting&priority=high&tab=safety` with labels `['case_status'=>'Status','priority'=>'Priority']` → chip "Priority: high" href contains `case_status=waiting` and `tab=safety` but not `priority=`; two chips plus "Clear all".
  - `test_empty_state_shows_one_action`.
  - JS: in `live-notifications.test.cjs` add `bell mounts in the page header slot when the layout also has a slot` (DOM with `[data-notification-slot]` in an app bar first and `[data-page-slot]` later → bell inside the later).
- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/UiComponentsTest.php` and `node --test tests/js/live-notifications.test.cjs` — Expected: FAIL (components/class not found; bell in first slot).
- [ ] **Step 3: Implement** the components, `FilterChips`, CSS (KPI label 14px/600 dark, value 40px/800 centered, tinted backgrounds per tone using the risk/status palette in DESIGN.md §2; page header flex-wrap with actions right; pill buttons; selects get a chevron background, date fields a calendar icon via `data-control`), and the JS slot change.
- [ ] **Step 4: Run** both commands — Expected: PASS.
- [ ] **Step 5: Commit** `git commit -m "Add shared page header, card, KPI, badge, empty state, filter bar and chips"`

### Task 3: Data building blocks — table with phone cards, list row, pagination

**Files:**
- Create: `resources/views/components/ui/{table,list-row}.blade.php`, `resources/views/vendor/pagination/compass.blade.php`
- Modify: `public/css/compass-components.css`
- Test: `tests/Feature/UiComponentsTest.php`

**Interfaces:**
- `<x-ui.table :stack="true">` wraps a normal `<table>` in `div.ui-table` (`overflow-x:auto` on desktop). Cells carry `data-label="Column"`; with `stack` at ≤768px rows render as cards (`tr{display:grid}` + `td::before{content:attr(data-label)}`), action cells (`td.ui-actions`) span full width.
- `<x-ui.list-row :initials :title :subtitle="null">` + `$badge` and `$actions` slots → `div.ui-list-row` (44px circular avatar, title 15px/700, subtitle 14px muted, badge + actions right; stacks at ≤480).
- Pagination: `$paginator->links('vendor.pagination.compass')` renders "Showing X–Y of Z {noun}" left and pill page buttons right (prev/next chevrons, active page solid green). Noun passed via `->links('vendor.pagination.compass', ['noun' => 'helpers'])`.

- [ ] **Step 1: Write failing tests:** `test_table_stack_mode_marks_wrapper_for_phone_cards` (class `ui-table--stack`); `test_list_row_renders_avatar_title_and_actions`; `test_pagination_view_shows_range_and_pills` (LengthAwarePaginator 19 items, 10 per page, page 1 → "Showing 1–10 of 19 helpers", an `aria-current="page"` "1", link to page 2).
- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/UiComponentsTest.php` — Expected: FAIL.
- [ ] **Step 3: Implement** components, pagination view and CSS.
- [ ] **Step 4: Run** — Expected: PASS.
- [ ] **Step 5: Commit** `git commit -m "Add shared table, list row and pagination with phone card layout"`

### Task 4: Moderator navigation — grouped sidebar, tooltips, phone bottom bar, emergency strip

**Files:**
- Modify: `resources/views/layouts/partials/moderator-sidebar.blade.php` (groups: **Work now** = Dashboard, Incoming Queue, Active Sessions, Reconnection requests, Emergency Alerts; **People** = Manage, Schedules; **Records** = Reports; **Account** = Notifications, Settings; every `.nav-item` gets `title` = its label; keep all IDs/badges)
- Create: `resources/views/layouts/partials/bottom-nav.blade.php`, `resources/views/partials/emergency-strip.blade.php`
- Modify: `resources/views/layouts/app.blade.php` (include emergency strip above `<main>`, bottom nav before `</body>`; mobile app bar keeps hamburger only when the page renders `x-ui.page-header`), `app/Providers/AppServiceProvider.php` (register both partials with `SidebarComposer`), `public/css/compass-components.css`
- Test: `tests/Feature/ModeratorNavigationTest.php`

**Interfaces:**
- Bottom nav items for moderator (only role defined in this plan): Dashboard (`moderator.dashboard`), Queue (`moderator.queue`, badge `$queueCount`), Emergency (`moderator.emergency`, badge `$emergencyCount`, red), More (button with `data-open-sidebar` → toggles existing drawer like `#hamburgerBtn`). Shown only ≤768px; `body` gets `padding-bottom: 72px + safe area` there. Partial renders nothing for roles without items.
- Emergency strip: for roles `moderator|adviser` when `$emergencyCount > 0`: `div.ui-emergency-strip[role=status]` "{n} active emergency/emergencies" + "Open" link (`moderator.emergency` / `adviser.emergencies`). Hidden on the emergency page itself.

- [ ] **Step 1: Write failing tests** (moderator user via existing fixtures, e.g. as in `ModeratorReferenceLayoutTest`):
  - `test_sidebar_groups_and_renamed_label` — sees "Work now", "People", "Reconnection requests"; doesn't see "Connection review"; still sees `id="emergencyBadge"`.
  - `test_bottom_nav_lists_moderator_destinations` — sees `ui-bottom-nav`, the three routes and `data-open-sidebar`.
  - `test_emergency_strip_only_when_emergencies_are_active` — none → no `ui-emergency-strip`; create one active emergency (as in `ModeratorReferenceLayoutTest` emergency fixtures) → strip with "1 active emergency" on `moderator.reports`, absent on `moderator.emergency`.
  - `test_seeker_pages_have_no_emergency_strip_or_moderator_bottom_nav`.
- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/ModeratorNavigationTest.php` — Expected: FAIL.
- [ ] **Step 3: Implement.** `data-open-sidebar` reuses the drawer toggle in `resources/js/sidebar.js` (add the selector to its click binding).
- [ ] **Step 4: Run** the test, then `php artisan test --compact --filter=Moderator` — Expected: PASS (update only assertions that pinned the old "Connection review" label).
- [ ] **Step 5: Commit** `git commit -m "Group the Moderator sidebar and add a phone bottom bar and emergency strip"`

### Task 5: Moderator Dashboard on the shared shell with "Needs your attention"

**Files:**
- Modify: `resources/views/moderator/dashboard.blade.php` (convert to `@extends('layouts.app')`; page header; attention strip; KPI grid; existing charts/cards kept and wrapped in `x-ui.card`)
- Create: `resources/views/components/ui/attention.blade.php`
- Test: `tests/Feature/ModeratorPilotPagesTest.php`

**Interfaces:**
- `<x-ui.attention :items>` where items = `array<array{count:int,label:string,href:string,tone:string}>`; renders only items with `count > 0`; renders nothing when all zero. Moderator items: emergencies (`$emergencyCount`, red, `moderator.emergency`), waiting in queue (`$queueCount`, amber, `moderator.queue`), reconnection requests (existing pending count from the dashboard controller data if present; otherwise omit — do not add queries beyond existing composer/controller data).

- [ ] **Step 1: Failing tests:** `test_dashboard_uses_shared_shell_and_header` (sees `data-page-header`, one `data-page-slot`, `ui-kpis`); `test_attention_strip_lists_only_waiting_work` (queue item present after creating a waiting `QueueRequest`; absent with none).
- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/ModeratorPilotPagesTest.php` — Expected: FAIL.
- [ ] **Step 3: Implement**; move page `<style>`/`<script>` into `@push('styles')`/`@push('scripts')`; delete the duplicated sidebar include/PWA includes that `layouts.app` now provides.
- [ ] **Step 4: Run** the file plus `php artisan test --compact --filter="Moderator|DashboardOverview"` — Expected: PASS.
- [ ] **Step 5: Commit** `git commit -m "Rebuild the Moderator dashboard on the shared shell with a Needs your attention strip"`

### Task 6: Manage Team to the reference

**Files:** Modify `resources/views/moderator/manage.blade.php` (to `layouts.app`; `x-ui.page-header` "Manage Team"; `x-ui.kpi-grid` Total Helpers (neutral), Helpers without an Adviser (amber), Advisers (blue), Slots Used (green); Advisers card with capacity bars; Assignment Workspace card with `x-ui.list-row` + level badge + Unassign; All Helpers table via `x-ui.table :stack` + pagination view). Test: `tests/Feature/ModeratorPilotPagesTest.php`.

- [ ] **Step 1: Failing test** `test_manage_team_matches_reference_structure`: sees "Helpers without an Adviser", `ui-kpi--amber`, `ui-list-row`, `ui-table--stack`, and every existing assign/unassign form action route still present.
- [ ] **Step 2: Run** — Expected: FAIL.
- [ ] **Step 3: Implement** (keep all forms, names, `data-confirm*`).
- [ ] **Step 4: Run** the file plus `php artisan test --compact --filter="ModeratorAdviser|ModeratorRefinement"` — Expected: PASS.
- [ ] **Step 5: Commit** `git commit -m "Rebuild Manage Team on the shared building blocks"`

### Task 7: Reports and Helper Workload to the reference

**Files:** Modify `resources/views/moderator/reports.blade.php` (header with Queue Performance + Export Filtered CSV + bell; `x-ui.filter-bar` with date/selects + chips via `FilterChips::from` for `case_status`, `emergency_status`, `priority`; KPI grid Total Cases green, Completed green, Pending amber, Emergencies red; sections as `x-ui.card`), `resources/views/partials/helper-workload.blade.php` (card + filter bar + `x-ui.table :stack` + compass pagination with noun "helpers"; keep every `data-workload-*` attribute and `#helperWorkload`). Test: `tests/Feature/ModeratorPilotPagesTest.php`.

- [ ] **Step 1: Failing tests:** `test_reports_header_filters_chips_and_kpis` (`?priority=high` → chip "Priority: high"; `ui-filter-bar`; `ui-kpi--red`); `test_helper_workload_keeps_hooks_on_moderator_and_adviser_schedule` (both `moderator.schedules` and `adviser.schedule` contain `data-helper-workload`, `data-workload-body`, `data-workload-pages`, "Showing").
- [ ] **Step 2: Run** — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the file plus `php artisan test --compact --filter="ModeratorReferenceLayout|RoleReportsNavigation|ModeratorReadinessScheduling"` and `node --test tests/js/*.cjs tests/js/*.mjs` — Expected: PASS (update only wording assertions that pinned removed text).
- [ ] **Step 5: Commit** `git commit -m "Rebuild Moderator Reports and Helper Workload to the reference design"`

### Task 8: Queue, Active Sessions and Session detail

**Files:** Modify `resources/views/moderator/{queue,sessions,session-detail}.blade.php` (to `layouts.app`; page header; filter bar + chips where filters exist; tables via `x-ui.table :stack`; priority/status via `x-ui.badge`; session detail gets `back-href=route('moderator.sessions')` "Back to Active Sessions" and a summary `x-ui.card` with status, risk, Helper, Seeker alias, start/end). Test: `tests/Feature/ModeratorPilotPagesTest.php`.

- [ ] **Step 1: Failing tests:** `test_queue_and_sessions_use_shared_table` (`ui-table--stack` on both); `test_session_detail_has_back_link_and_summary` (`ui-back-link`, "Back to Active Sessions").
- [ ] **Step 2: Run** — Expected: FAIL.
- [ ] **Step 3: Implement** (keep queue assign/remove forms and queue-helper-options hooks).
- [ ] **Step 4: Run** the file plus `php artisan test --compact --filter="ModeratorIncomingQueue|ModeratorQueueRemoval|EscalationOperations"` and `node --test tests/js/queue-helper-options.test.cjs` — Expected: PASS.
- [ ] **Step 5: Commit** `git commit -m "Rebuild Moderator Queue, Active Sessions and Session detail"`

### Task 9: Remaining Moderator pages

**Files:** Modify `resources/views/moderator/{emergency,schedules,unassigned-referrals,notifications,settings}.blade.php`, `resources/views/moderator/partials/emergency-content.blade.php`, `resources/views/session/reconnections.blade.php` (moderator branch only: header "Reconnection requests"), `resources/views/queue-performance.blade.php`. Settings: sections as `x-ui.card`; sticky Save bar `div.ui-save-bar` shown via a small inline script when any field in that form changes (submits the same form). Test: `tests/Feature/ModeratorPilotPagesTest.php`.

- [ ] **Step 1: Failing tests:** `test_remaining_moderator_pages_use_page_header` (each route returns `data-page-header` exactly once); `test_settings_forms_have_save_bar` (`ui-save-bar` inside each settings form, form actions unchanged).
- [ ] **Step 2: Run** — Expected: FAIL.
- [ ] **Step 3: Implement** (emergency page keeps "Emergency Workflow", "Priority Distribution" and `#moderatorEmergencyConfig`).
- [ ] **Step 4: Run** the file plus `php artisan test --compact --filter="Moderator|QueuePerformance|UiStandardization"` — Expected: PASS.
- [ ] **Step 5: Commit** `git commit -m "Bring the remaining Moderator pages onto the shared design"`

### Task 10: Pilot verification and review stop

- [ ] **Step 1:** `npm run build` — Expected: exit 0. `php artisan view:cache` — Expected: "cached successfully".
- [ ] **Step 2:** `node scripts/ui-screenshots.mjs --roles=moderator --widths=1920,1440,1024,390 --out=.ui-preview/pilot` — Expected: `0 issue(s)` (excluding intended `modal-open`). Fix and re-run until clean; commit fixes as `Fix Moderator pilot layout issues found by screenshots`.
- [ ] **Step 3:** `php artisan test --compact` — Expected: only the known `AdviserRefinementTest` failure. `node --test tests/js/*.cjs tests/js/*.mjs` — Expected: all pass. `node scripts/ui-screenshots.mjs --widths=1440,390 --out=.ui-preview/pilot-all` — other roles show no new overflow/overlap from Task 1's scale.
- [ ] **Step 4:** Stop and show the user before/after pairs for Manage Team, Reports and Helper Workload (1440 and 390). Do not start other roles until the user approves; each later role gets its own plan.
