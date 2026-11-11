# COMPASS UI redesign: user-friendly, consistent screens for every role

Date: 2026-11-11 · Status: draft for approval

## Intent and why
The adviser and panel found the UI confusing and messy ("magulo"). Earlier passes fixed overlaps and
put values on a scale, but the screens still feel crowded: small grey text, KPI numbers that do not
stand out, page actions and the bell in different places on every page, and pages that each carry
their own copy of the shell. This is a **big, user-friendly redesign of how screens look and are
arranged**, not of what they do. The Moderator reference screenshots (Manage Team, Reports, Helper
Workload, shared on Nov 11) set the target look for every role.

## Goals
- A first-time user of any role can tell, at a glance, what a page is for, what the main action is
  and where the numbers are.
- Every page in every role follows one page pattern and is built from shared building blocks.
- The look matches the Moderator references: larger and bolder type, big centered KPI numbers,
  roomy rows, clear filter bars, pill buttons with icons, bell in the header action row.
- Works on desktop, tablet (1024) and phone (390) without sideways scrolling or wrapping buttons.

## Non-goals
- No change to features, routes, forms, field names, permissions, workflows, KPI formulas, matching,
  consent or data. Every existing button, filter and link stays and does the same thing.
- No new theme. The default COMPASS theme (green `#04a052`, white cards, Inter) is the only theme
  for role screens; Settings → Appearance keeps its existing options (text size, high contrast,
  reduced motion) and is restyled like every other page. Admin keeps its own theme/accent options.
- No new JavaScript framework or CSS library. Tailwind, the existing CSS files and Blade stay.

## Anti-goals (a failure even if it "works")
- Any workflow that stops working or a control that disappears.
- Roles drifting apart again (one role big and bold, another small and grey).
- Pages that become so tall that the main work area is below the fold on a 1440×900 laptop.
- Information hidden only to look clean when users need it daily (e.g. queue priority, emergency state).

## Approaches considered
- **A. Shared building blocks first, Moderator pilot, then roll out per role (chosen).**
  Build reusable Blade components and CSS, rebuild the Moderator pages on them, review screenshots,
  then convert the other roles one commit per role. Most work up front; guarantees consistency and
  one place to change things later.
- B. Convert every page of every role at once. Faster to see change everywhere, but no checkpoint to
  confirm the look before 70+ pages change.
- C. CSS-only restyle. Fastest, but cannot fix arrangement, duplicates or buried primary actions,
  which are the real source of confusion.

## Design

### 1. New scale (updates DESIGN.md and the tokens)
| Use | Old | New |
|---|---|---|
| Body text, table cells, inputs | 13–14px | **15px** |
| Secondary text, captions | 12–13px | 13–14px (never grey-on-grey below 13px) |
| Section / card titles | 16–18px | **20px**, weight 700 |
| Page title (h1) | 24px | **28px**, weight 800 (24px on phones) |
| KPI numbers | 28px | **40px**, weight 800 (32px on phones) |
| Buttons and inputs | 42px | **44px**, 15px text, pill radius for buttons |
| Table rows | ~44px | **52px** min |
| Card padding | 20px | **24px** (16px on phones) |

Spacing stays on the 4px scale; shadows stay soft (`--shadow-1/2/3`); risk/status colors unchanged.
DESIGN.md and `DesignScaleTest` move to the new scale in the same commit as the tokens.

### 2. One page pattern
```
┌ Page title ─────────────── [secondary] [Primary action] [bell] ┐
│ One-line description                                           │
├ Filter bar: date (calendar icon) · selects (chevron) [Apply] [Reset] ┤
├ KPI cards: label on top, BIG NUMBER, optional small note       │
├ Main work area: workspace, lists, tables, charts               │
└ Details / history / definitions collapsed at the bottom        ┘
```
- The bell always sits at the right end of the header action row (as in the Reports reference),
  never floating above it.
- One primary (green) action per page header; everything else is secondary (white, green text).
- Long explanatory text moves into a one-line description or a collapsed "How this is counted".

### 3. Shared building blocks (Blade components + `public/css/compass-components.css`)
| Component | Replaces |
|---|---|
| `<x-ui.page-header title description>` with an `actions` slot; renders the bell | per-page top bars, hamburger + h1 copies |
| `<x-ui.filter-bar>` + `<x-ui.field>` (date, select, search) | per-page filter markup |
| `<x-ui.kpi-grid>` / `<x-ui.kpi label value tone note>` (tones: neutral, green, amber, blue, red) | `.mo-kpi`, `.stat-card`, `.co-kpi`, report summary tiles |
| `<x-ui.card title subtitle>` with header-actions slot | `.card`, `.mo-card`, `.av-panel`, `.ui-panel` copies |
| `<x-ui.table>` (scroll wrapper, roomy rows) + `<x-ui.pagination>` (pill buttons) | per-page tables; Laravel paginator restyled |
| `<x-ui.list-row avatar title subtitle badge>` with actions slot | helper/adviser rows, notification rows |
| `<x-ui.badge tone>` | the many badge/pill/status classes |
| `<x-ui.empty title text>` | per-page empty states |
Components only change markup and styling; every form keeps its action, method, names and IDs that
JavaScript or tests rely on.

### 4. One shell per role
Standalone Moderator pages (dashboard, queue, sessions, manage, notifications, session-detail,
settings, unassigned-referrals) move onto `layouts.app`, which already provides the sidebar,
toasts, notices, PWA and offline banners. Page-specific scripts move to `@push('scripts')`. The same
applies to standalone pages of the other roles during their rollout.

### 5. Moderator pilot pages
Manage Team, Reports and Helper Workload follow the references exactly; the remaining Moderator
pages (Dashboard, Incoming Queue, Active Sessions, Connection review, Schedules, Emergency Alerts,
Notifications, Settings, Session detail, Unassigned referrals, Queue performance) get the same page
pattern and components.

### 6. User-friendly improvements (all roles, existing data only)
1. **"Needs your attention" strip** at the top of every dashboard: large cards for work waiting on
   this user (e.g. open emergencies, queue waiting, evaluations due, referrals to review, sessions
   starting soon). Each links to the page that already handles it. Hidden when nothing is waiting.
   Uses counts the dashboards and sidebars already compute.
2. **Always-visible emergency strip** (Moderator, Adviser): while an emergency is open, a red strip
   under the header on every page shows the count and an Open link. Display only; alert,
   notification and escalation behavior is unchanged.
3. **Navigation:** sidebar groups by task (Work now / People / Records / Account) with waiting counts
   on the links; the collapsed rail shows tooltips. On phones a bottom bar holds the role's four
   most-used destinations plus "More" (opens the existing drawer). Same links, same routes.
4. **Detail pages** (session, referral, case, emergency, helper): a "← Back to …" link and a summary
   card (status, risk, people, times) above the long details.
5. **Active filter chips** under the filter bar (e.g. "Last 30 days ×", "Status: Pending ×"); each
   chip clears that one filter via the existing query parameters.
6. **Tables become stacked cards on phones** (≤768px) with the row's actions inside the card,
   instead of sideways scrolling. Desktop tables keep columns.
7. **Forms:** long forms are split into titled sections; errors sit next to their field; a sticky
   Save bar appears on long settings/evaluation forms while there are unsaved changes (submits the
   same form).
8. **Empty states with a next step:** one plain sentence and at most one useful action.
9. **Plain labels:** confusing on-screen labels are reworded (route names, field names and
   permissions unchanged). The full rename list is shown to the user for approval before it ships.
   Examples: "Connection review" → "Reconnection requests", "Unassigned Pool" → "Helpers without an
   Adviser".

### 6b. More references (shared Nov 12) for later roles
Saved in `.superpowers/references/` (local, not committed) next to the Moderator ones.
- **Seeker – Referrals and Appointments** (`ref-seeker-appointments.png`): one page with two tabs
  (Referrals / Appointments); appointments as a table (date and time, professional, details,
  location, status badge, row actions) with a green note strip under it.
- **Seeker – Appointment details** (`ref-seeker-appointment-details.png`): a dialog with a status
  badge, then titled sections (appointment, professional, schedule, location, purpose, notes from the
  professional) and one Close button.
- **Seeker – Identity disclosure consent** (`ref-seeker-identity-consent.png`): a dialog with a
  three-step progress line (Adviser approval → Referral consent → Identity disclosure), the plain
  privacy text, and a green "You have given consent" box with the consent date.
- **Adviser – Pending Evaluations** (`ref-adviser-pending-evaluations.png`): three KPI cards with
  icons, the 4-step evaluation process card, then an "Awaiting review" list. Each row shows the
  alias, the helper, the date and session type, a risk badge, the concern, step progress, and
  View Session / Evaluate actions. Rows have a risk-colored left edge, and a search, risk filter and
  sort sit above the list.

Rules for these references:
- Only data and actions that already exist appear. Where the picture shows something COMPASS does
  not have, the existing control takes its place, or the element is left out:
  - "Cancel" becomes the existing "Request another schedule" / confirm responses.
  - The "Available Time" chips show the one scheduled time.
  - "View on Map", the 24-hour cancellation note and the row "⋮" menu are left out unless the user
    asks for them.
- The spelling stays "Adviser".
- The project's identity-consent wording stays as it is.
- The Adviser search, risk filter and sort are proposed as filtering of the rows already on the page,
  with no server change. The user confirms this before the Adviser rollout.

### 7. Rollout order after the pilot is approved
Moderator → Adviser → Helper → Professional → Seeker → Admin (admin keeps its accent/dark options but
uses the same scale and components). One commit per role, screenshots per role before moving on.

## Constraints
- Laravel 12 Blade, Tailwind, Vite, Chart.js 4.5.1 (charts keep `<x-analytics-chart>`).
- Tests: `php artisan test` (SQLite), `node --test tests/js/*`, `npm run build`. Markup assertions in
  tests may be updated when wording/structure changes; behavior tests must pass unchanged.
- Known unrelated failure: `AdviserRefinementTest` notification archive.
- Commit each step separately, no Claude co-author, never commit `.env`,
  `AdviserNotificationController.php` or `public/sw.js`, never `migrate:fresh`, push only when asked.

## Verification
- Screenshot harness for each role at 1920/1440/1024/390: no overlap, overflow or design-rule issues.
- Before/after screenshot pairs for the three reference pages, shared for review after the pilot.
- Full PHP and JS suites and the build after every role.

## Decided by the user
- Approach A, Moderator first, references are the target, default theme only, big changes allowed
  to looks and arrangement, functionality unchanged.

## Calls made by the designer (check these)
1. The new scale numbers in section 1 (read from the references).
2. Moving standalone pages onto `layouts.app` as part of the redesign.
3. Admin is converted last and keeps its accent/dark options.
4. Settings → Appearance options stay as they are; "default theme" read as "no new themes".
5. The section 6 additions (attention strip, emergency strip, phone bottom bar, filter chips,
   phone card tables, sticky Save bar, plain labels), added after "improve it more".
6. Bottom-bar destinations per role are picked from the most-used links in each sidebar.

## Left to the builder
- Exact component APIs and CSS class names; how to map each page's existing markup into the slots.
- Which explanatory texts collapse into "How this is counted" sections.
