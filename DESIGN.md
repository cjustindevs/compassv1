# COMPASS Design System

The rules every COMPASS screen follows. COMPASS is a calm, supportive tool for peer support and
referral work: screens should feel quiet, readable and predictable for all six roles (Seeker,
Helper, Adviser, Moderator, Professional, Administrator).

The tokens live in `public/css/compass-ui.css` (`:root`). The layering scale and toast stack live in
`public/css/compass-layout.css`. Chart styling lives in `resources/js/compass-chart-options.mjs`.
When this file and the CSS disagree, fix the CSS. When a new rule is needed, add it here first.

---

## 1. Theme

- **Light, green and white.** A pale green-white page (`#f8fbf9`), white cards and one brand green
  (`#04a052`).
- **Calm over loud.** The habits come from calm product systems (a strict spacing scale, few type
  sizes, soft and few shadows, hairline borders), but COMPASS keeps its own look. Never copy
  another brand's palette, logo, illustrations or layouts.
- **Information first.** Color carries status. Decoration (gradients, glows, tilted cards,
  oversized icons) is not used on operational screens.
- **One family:** Inter, with system fallbacks.

## 2. Colors and roles

### Brand and surface

| Token | Value | Role |
|---|---|---|
| `--compass-green` | `#04a052` | Primary actions, active navigation, focus accents, progress |
| `--compass-green-dark` | `#027039` | Hover on primary, links, secondary-button text, focus outline |
| `--compass-green-soft` | `#eaf8f0` | Selected rows, active nav background, soft info panels |
| `--compass-background` | `#f8fbf9` | Page background |
| `--compass-card` | `#ffffff` | Cards, panels, modals, inputs |
| `--compass-border` | `#e5e7eb` | Hairline borders on cards, inputs and tables |
| `--compass-divider` | `#eef1ef` | Row dividers inside a card (lighter than the card border) |
| `--compass-text` | `#1f2937` | Body text and headings |
| `--compass-muted` | `#6b7280` | Secondary text, captions, timestamps |
| `--compass-danger` | `#dc2626` | Errors and destructive actions |

### Risk and status (always shown with a text label, never color alone)

| Meaning | Badge (light) | Chart color (`STATUS`) |
|---|---|---|
| Low / resolved / completed / ready | green-50 background, green-700 text | `#0ca30c` |
| Moderate / pending / waiting / scheduled | amber-50, amber-700 | `#fab219` |
| High / escalated / declined / returned | orange-50, orange-700 | `#ec835a` |
| Emergency / critical / failed | red-50, red-700 (solid red-600 only for live emergencies) | `#d03b3b` |
| Cancelled / archived / unknown | gray-100, gray-600 | `#94a3b8` |

Toast accents: success is green, error is `--compass-danger`, warning is `#d97706`, info is
`#2563eb`.

Charts use a separate categorical palette (green, blue, orange, violet, yellow, magenta), checked
for adjacent-pair contrast in light and dark. Risk and status labels always keep their semantic
color, whatever else is in the chart.

### Dark mode and contrast

- Role screens are light-only. The **High contrast** setting (`.high-contrast`) darkens the text,
  muted text, borders and greens.
- The Administrator workspace has its own dark theme (`data-admin-theme="dark"`) and accent
  options. They are scoped to `admin-dashboard.css` and never leak into other roles.
- Charts read their colors from the page, so they follow the admin dark theme automatically.

## 3. Typography

Inter at 14px is the base. Use only these sizes:

| Token | Size | Use |
|---|---|---|
| `--text-2xs` | 11px | Uppercase overlines, chart ticks, timestamps |
| `--text-xs` | 12px | Badges, captions, helper text, table meta |
| `--text-sm` | 13px | Secondary text, table cells, notification bodies, labels |
| `--text-base` | 14px | Body text, buttons, inputs |
| `--text-md` | 16px | Card titles (h3), lead text, phone inputs (stops iOS zoom) |
| `--text-lg` | 18px | Section titles (h2), modal titles |
| `--text-xl` | 20px | Page title on phones |
| `--text-2xl` | 24px | Page title (h1) on desktop |
| `--text-3xl` | 28px | KPI and stat numbers |

- **Weights:** 400 for body, 500 for emphasis, 600 for labels, buttons and titles, 700 for page
  titles and numbers.
- **Line height:** 1.5 for text, 1.25–1.4 for headings and numbers.
- Tailwind's `text-xs`, `text-sm`, `text-base`, `text-lg`, `text-xl` and `text-2xl` match the scale.
  Avoid `text-3xl` and larger outside the landing page.
- Use `font-variant-numeric: tabular-nums` for numbers in tables and KPI tiles.
- The landing page (`landing-page.css`) is the only place that may use display or fluid (`clamp()`) sizes.

## 4. Spacing

All spacing is on a **4px base**. Use only these steps:

| Token | Value | Typical use |
|---|---|---|
| `--space-1` | 4px | Icon-to-text nudge, tight meta gaps |
| `--space-2` | 8px | Gap between chips, buttons in a toolbar |
| `--space-3` | 12px | Gap inside a card row, label-to-control |
| `--space-4` | 16px | Grid gaps, card padding on phones, toast stack offset |
| `--space-5` | 20px | Card padding (`--compass-space`) |
| `--space-6` | 24px | Page gutter (`--compass-page-padding`), gap between sections |
| `--space-8` | 32px | Large section breaks, empty-state padding |
| `--space-10` | 40px | Page bottom padding |

Odd values (6, 10, 14, 18, 22px) are only allowed **inside** a component to hit an exact control
height, such as 10px vertical button padding for a 42px button. Never use them between components.

## 5. Radius and elevation

| Token | Value | Use |
|---|---|---|
| `--radius-xs` | 6px | Badges, status chips, small tags |
| `--radius-sm` | 8px | Tabs, small icon buttons, row actions |
| `--radius-md` | 10px | Buttons, inputs, selects, toasts' inner controls (`--compass-control-radius`) |
| `--radius-lg` | 14px | Cards, panels, modals, dialogs (`--compass-radius`) |
| `--radius-pill` | 999px | Pills, avatars, progress tracks |

Shadows are rare and soft. There are three levels, and borders do most of the work:

| Token | Value | Use |
|---|---|---|
| `--shadow-1` | `0 1px 3px rgb(0 0 0 / 3%)` | Resting cards (`--compass-shadow`) |
| `--shadow-2` | `0 8px 24px rgb(22 59 45 / 12%)` | Dropdowns, popovers, toasts, the install sheet |
| `--shadow-3` | `0 18px 50px rgb(22 59 45 / 16%)` | Modals and dialogs |
| `--ring` | `0 0 0 3px rgb(4 160 82 / 15%)` | Input focus halo (with a green border) |

- **No colored glow shadows** on buttons or cards (for example `0 8px 28px rgba(4,160,82,.3)`).
- No shadow on hover. Hover changes the background or border color, never lifts or moves an element.
- Hairline borders are 1px `--compass-border`. Inner dividers use `--compass-divider`.

## 6. Components

**Buttons**
- Height is 42px on desktop and 44px on touch screens or ≤768px (`--compass-control-height`).
- Padding is 10px × 16px, radius is `--radius-md`, text is 14px at weight 600, and the icon gap is 8px.
- Variants:
  - Primary: solid green, white text.
  - Secondary/outline: white, green-dark text, hairline border.
  - Danger: red-50 background, red text, red-200 border.
- Icon-only buttons are at least 36px (44px on touch).
- Row actions inside dense tables may be 36px with 12–13px text.
- Use one primary button per area. Other actions are secondary.

**Cards and panels**
- `.card`, `.av-panel`, `.mo-card`, `.ui-panel`, `.hf-card`: white, hairline border,
  `--radius-lg`, `--shadow-1`, and 20px padding (16px on phones).
- Never nest a bordered card inside another bordered card. Use a divider or a soft
  `--compass-green-soft` or gray-50 block instead.

**KPI and stat tiles**
- A label (12–13px, muted), a number (`--text-3xl` or 24px in compact grids, weight 700,
  tabular) and an optional sample count or caption (12px).

**Chips and badges**
- 12px text at weight 600, 4px × 8px padding, `--radius-xs`. Use pills (`--radius-pill`) only for
  counters and filters.

**Tables**
- 13px cells, 12px × 14px padding, gray-50 header with 600 weight and gray-600 text,
  `--compass-divider` row lines.
- Wide tables scroll inside their card (`.ui-table-scroll`), never the page.

**Forms**
- A label (13px at weight 600) sits 6px above the control.
- Controls follow the button heights and radius. Phone inputs are 16px.
- Errors show in 13px red under the field, with `aria-invalid`.

**Modals and dialogs**
- One shared `.modal-overlay` / `<dialog>` style, `--radius-lg`, `--shadow-3`, max width
  `calc(100vw - 32px)`.
- The confirmation modal stacks above every other modal (`--z-confirm`).

**Toasts**
- One stack in the top-right corner, below the header (`.compass-toast-stack`). Each toast is 13px
  text with a 4px colored left edge, `--radius-lg`/12px corners and `--shadow-2`.
- Toasts never cover the chat composer or the bottom navigation.

**Charts**
- Use one component: `<x-analytics-chart>`, drawn with Chart.js through
  `resources/js/compass-charts.js`.
- Inter at 11px ticks, soft gridlines, rounded bars, the COMPASS palette, and a 1–5 axis for scores.
- When data is missing, show "No data for this period" instead of drawn zeros.
- Put an exact-values table under every chart, inside `<details>`.

**Layering** (`compass-layout.css`)
- The order, from lowest to highest: sticky 40, sidebar 100, dropdown 120, overlay 200,
  modal 1000, confirm 1100, toast 1200, system banner 1300, skip link 1400.
- Never write a raw z-index above 40. Use the `--z-*` tokens.

## 7. Layout

- **Sidebar:** 264px (`--sidebar-w`), 76px collapsed (`--sidebar-w-collapsed`), fixed on the left.
  It becomes a drawer at ≤768px. Never hardcode `margin-left: 260px`.
- **Page gutter:** `--compass-page-padding` is 24px on desktop, 20px at ≤768px and 16px at ≤480px.
  Add 40px of bottom padding.
- **Compact desktop grids:**
  - Content uses the full width beside the sidebar. No narrow centered columns on wide screens.
  - KPI rows use `repeat(auto-fill, minmax(190px, 1fr))`.
  - Dashboard charts sit three per row from 1500px.
- **Gaps:** 16px between cards in a grid and 24px between page sections.
- **Page header:** the title (h1) and a one-line muted subtitle on the left, actions on the right,
  wrapping instead of colliding.

## 8. Content rules

- **Scores are plain numbers on a 1–5 scale.** Show "4.2", not "4.2/5" or "84%". State the range
  once in the label or caption ("Competency (Scale 1–5)") with `App\Support\ScoreFormat`.
  Show "—" when there is no score.
- **Feedback is shown on the same 1–5 scale.** Legacy 1–10 ratings are normalized with
  `ScoreFormat::fromTen()`. Show the sample count next to an average ("4.1 · 12 ratings").
- **Concise messages.** Flash, toast and notification text is one or two short sentences. Say what
  happened and what to do next. Safety and consent disclaimers keep their meaning in full.
- **Empty states:** "No data for this period" for charts and reports. Lists use a short
  "No … yet" line with one next action at most.
- **Labels use sentence case** ("Case status", not "Case Status"). Role names stay capitalized
  (Seeker, Helper, Adviser, Moderator, Professional).
- Seekers are shown only by alias. Reports show aggregates, never identities.

## 9. Do's and Don'ts

**Do**
- Use the tokens (`var(--space-4)`, `var(--text-sm)`, `var(--radius-lg)`, `var(--shadow-1)`).
- Use one primary action per area and secondary buttons elsewhere.
- Rely on hairline borders and white space to separate content.
- Keep buttons at 42/44px and icon buttons at 36/44px.
- Give every status color a text label.
- Put charts in `<x-analytics-chart>` with an exact-values table.

**Don't**
- Don't add font sizes off the scale (9, 10, 15, 17, 19, 22, 26, 30px…).
- Don't add colored glow shadows, hover lifts (`translateY`) or stacked shadows.
- Don't hardcode z-index values, sidebar widths or page gutters.
- Don't nest bordered cards, or repeat a page title inside the first card.
- Don't show scores with "/5" or as percentages.
- Don't open a modal for a success message. Use an inline banner or a toast.
- Don't change workflows, permissions or data while fixing visuals.

## 10. Responsive behavior

| Width | Behavior |
|---|---|
| ≥1500px | Dashboard charts sit three per row. Content stretches beside the rail. |
| 1151–1499px | Full two-column page grids. |
| 769–1150px | The rail stays. Two-column page grids (`.mo-grid`, side columns) stack. |
| ≤768px | The rail becomes a drawer behind the menu button. Controls are 44px and inputs 16px. The gutter is 20px. |
| ≤480px | The gutter is 16px, the h1 is 20px, form rows stack and the table cell padding is 10px × 12px. |

- Chat and voice windows fill `100dvh` with the composer pinned.
- Pages never scroll sideways. Only tables and chip rows scroll inside their container.
- Respect `prefers-reduced-motion` and the in-app Reduced motion setting: no animations or
  transitions.

## 11. Agent prompt guide

When asking an AI agent (or a new teammate) to build or change a COMPASS screen, include:

> Follow `DESIGN.md`. Use the tokens in `public/css/compass-ui.css`: spacing `--space-*` (4px base),
> type `--text-*` (11/12/13/14/16/18/20/24/28 only), radius `--radius-*`, shadows `--shadow-1..3`,
> layers `--z-*`. Cards are white with a 1px `--compass-border` hairline, 14px radius and
> `--shadow-1`. Buttons are 42px (44px on touch), and there is one green primary per area. Scores
> are plain numbers on a 1–5 scale, with the range stated once in the label. Charts use
> `<x-analytics-chart>`. Do not change workflows, permissions or data.

**Quick checks before you commit a UI change:**
1. `node scripts/ui-screenshots.mjs --roles=<role> --widths=1920,1440,1024,390` reports no
   overlap, overflow or design-rule issues for the pages you touched.
2. Are there no new font sizes, shadows, radii or z-index values outside the tokens?
3. `npm run build`, `php artisan test --compact` and `node --test tests/js/*` pass.
