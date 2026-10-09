# COMPASS interface standardization

## Scope and reference

The existing Administrator interface is the reference: Inter, green accents,
white surfaces, outline SVG icons, a 264px sidebar, readable controls, restrained
borders and clear tables. This is a presentation change across all six roles.
Routes, queries, permissions, matching, readiness, scheduling, emergencies,
competency, consent, identity access, reporting and retention remain authoritative.

## Inspection findings

- The Administrator has its own layout, CSS and outline SVG component.
- Helper pages inherit `layouts.helper`; Adviser pages generally inherit
  `layouts.app`. There is no separate Adviser layout.
- Moderator, Professional, Seeker, account, request and session pages mix shared
  layouts with standalone HTML documents and local style blocks.
- Navigation uses shared sidebar CSS and both critical inline and module scripts.
  Toggle IDs and icon classes are JavaScript hooks, not disposable markup.
- Most screens already use Inter, but repeatedly load different weight sets.
  Font Awesome is loaded separately on many screens.
- Shared compact styles reduce some desktop controls to 30px. Individual pages
  use inconsistent control heights, corner radii and text sizes.
- Notifications, loading feedback and chat also generate icons in JavaScript;
  changing only Blade icons would leave those inconsistent.
- PDF reports and OTP email are delivery documents rather than screen layouts.
  Their print/email constraints must remain separate.
- Administrator theme/accent and user font-size, contrast and reduced-motion
  preferences must remain functional.

## Implementation sequence

1. Inventory every Blade view and identify screen layouts, inherited pages,
   embedded screens, print reports and email.
2. Extract shared design tokens and reusable screen assets. Load them after
   page-specific styles so the common primitives have one final definition.
3. Reuse the Administrator outline paths as the canonical SVG sprite. Render
   both server and JavaScript icons from that sprite, retaining control hooks.
4. Standardize existing navigation, headings, surfaces, controls, tables,
   statuses, feedback and dialogs. Preserve page composition and role content.
5. Review role pages and secondary screens, especially chat, embedded consent,
   authentication, resource tools and report tables. Keep Seeker pages welcoming.
6. Verify view compilation, render and workflow tests, icon coverage, JavaScript,
   stylesheet parsing and production build. Record visual QA separately.

## Responsive contract

- Desktop keeps the existing role-specific grids and navigation.
- At 768px and below, existing mobile navigation remains in use; common content
  grids/forms stack and tables scroll inside their containers.
- At 480px and below, page padding is 16px, controls are at least 44px high,
  headings wrap, dialogs fit the screen and action rows wrap.
- Chat, breathing tools, charts and embedded identity dialogs retain their
  specialized layout and behavior.

## Validation and boundaries

Tests use the isolated test database. No production records, credentials or
environment files are modified. Existing unrelated local edits are preserved.
No new framework or package dependency is introduced.

Browser inventory currently exposes no apps/tabs and the in-app browser is
unavailable. Therefore screenshot, browser-console and physical-device checks
must be reported as pending rather than claimed as completed.

The inventory and final validation results are recorded below as work progresses.

## Implemented

- One `partials.ui-assets` include on all 51 screen-document templates, including
  role layouts, standalone screens and embedded consent/identity screens.
- Administrator-derived tokens and screen primitives in `public/css/compass-ui.css`:
  typography, surfaces, controls, forms, statuses, tables, pagination, feedback,
  dialogs, navigation sizing, focus and mobile touch targets.
- Canonical outline sprite with 116 glyphs. `x-admin.icon`, `x-ui-icon`, legacy
  stored icon values and runtime notification/loading/chat controls share it.
  No screen depends on Font Awesome. CSS-generated font glyphs were replaced.
- Shared form components retain their attribute/prop contract. Functional
  icon-only controls have labels. Keyboard-only users have a skip link and
  sidebar Escape handling. Mobile zoom is enabled.
- Legacy Tailwind CDN screens use the existing production build. Tailwind scans
  existing JavaScript templates as well as Blade so runtime feedback stays styled.
- Tables retain existing responsive wrappers; previously uncontained tables get
  a keyboard-accessible scroll region. Existing hidden states stay authoritative.
- Seeker welcome hierarchy and landing headings stay welcoming; Helper chat
  composition stays compact. Adviser primary actions retain primary styling.
- Saved font-size, high-contrast and reduced-motion preferences are exposed as
  presentation-only metadata and applied consistently, without private profile data.
- Offline screen follows the shared primitives without decorative emoji. Shared
  assets are precached; versioned URLs match the precache. Offline HTML gets a
  content-derived cache revision.

No controller, route, model, migration or business workflow was changed. The
only application PHP change is the presentation-only legacy icon resolver.
Existing PDF/email delivery layouts and unrelated local edits are preserved.

## Validation results

- Full Laravel suite: **562 passed, 4,853 assertions** before the final saved
  accessibility-preference test was added.
- Final focused suite: **27 passed, 803 assertions**, including all six role
  rendering families, 59 authorized screens, shared assets, legacy icon safety,
  saved accessibility preferences, sidebar, Self-Help and authentication.
- All Blade views compile. Production Vite/PWA build passes.
- `node --experimental-vm-modules scripts/check-ui.mjs` checks the production
  styles plus fixture-rendered inline CSS/JavaScript and sprite references:
  **227 stylesheets, 160 inline scripts, 116 glyphs, 59 fixture screens**.
- JavaScript module syntax, PHP icon-resolver syntax and Git whitespace checks pass.
- Two stale presentation expectations were corrected (retired Administrator
  chart, old icon font). A login test fixture now ages its existing live device
  before expecting a later login; authentication logic was not weakened.

## Remaining visual acceptance checks

Actual browser screenshot, console and physical-device checks remain pending:
the computer-use inventory is empty and `iab` reports unavailable. Automated
rendering/syntax checks do not prove visual alignment or absence of overflow.
Review desktop, 768px and 480px for the six role dashboards and secondary pages,
especially reports, emergency screens, chat, resource tools and identity dialogs.
Validation was recorded before the October 9 publication request. Verify the
latest `origin/deploy` commit for publication. Live deployment remains unverified.

## View inventory

### Public entry-screen follow-up

Landing, login, live pseudonymous registration and password-recovery screens now
have a dedicated public presentation using the same tokens and outline icons.
The landing keeps its features, support process and account links; decorative
motion and sample wellness percentages were replaced with support information.
Footer terms/privacy use the existing documents rather than placeholder links.
The authentication shell has consistent branding, cards, spacing and mobile
controls. Registration keeps the terms gate, OTP modal, alias shuffle, form
fields and server endpoints; the nickname reminder stays visible after shuffle.
Retired email-based registration endpoints were not restored.

Follow-up checks: **49 passed, 961 assertions** across authentication, password
recovery/verification, registration/Resend OTP, single-device login and shared UI.
After removing duplicate guest-layout styles, the public entry contract passed
again (**1 test, 32 assertions**). Production build passes. Static fixture QA
checks **229 stylesheets, 165 scripts, 116 glyphs and 65 screens**. Browser visual
QA is still pending. No backend authentication/OTP/consent rule was changed.
These follow-up changes are included in the October 9 publication request to
`origin/deploy`; live deployment and visual acceptance remain separate checks.

214 Blade views inspected. Components inherit their host screen assets.

| View | Rendering family |
| --- | --- |
| `admin/audit-logs/index.blade.php` | Inherited: components.admin.layout |
| `admin/backup-restore/index.blade.php` | Inherited: components.admin.layout |
| `admin/dashboard.blade.php` | Inherited: components.admin.layout |
| `admin/reports/index.blade.php` | Inherited: components.admin.layout |
| `admin/resource-library/index.blade.php` | Inherited: components.admin.layout |
| `admin/roles-permissions/index.blade.php` | Inherited: components.admin.layout |
| `admin/settings/index.blade.php` | Inherited: components.admin.layout |
| `admin/system-health/index.blade.php` | Inherited: components.admin.layout |
| `admin/users/index.blade.php` | Inherited: components.admin.layout |
| `adviser/analytics.blade.php` | Inherited: layouts.app |
| `adviser/calendar.blade.php` | Inherited: layouts.app |
| `adviser/emergencies.blade.php` | Inherited: layouts.app |
| `adviser/emergency-detail.blade.php` | Inherited: layouts.app |
| `adviser/emergency-resources.blade.php` | Inherited: layouts.app |
| `adviser/evaluate.blade.php` | Inherited: layouts.app |
| `adviser/evaluations.blade.php` | Inherited: layouts.app |
| `adviser/helper-detail.blade.php` | Inherited: layouts.app |
| `adviser/helper-matching.blade.php` | Inherited: layouts.app |
| `adviser/helpers.blade.php` | Inherited: layouts.app |
| `adviser/notifications.blade.php` | Inherited: layouts.app |
| `adviser/partials/archive-action.blade.php` | Component / partial |
| `adviser/referral-detail.blade.php` | Inherited: layouts.app |
| `adviser/referrals.blade.php` | Inherited: layouts.app |
| `adviser/report-export.blade.php` | PDF document (separate print styles) |
| `adviser/reports.blade.php` | Inherited: layouts.app |
| `adviser/resources.blade.php` | Inherited: layouts.app |
| `adviser/schedule.blade.php` | Inherited: layouts.app |
| `adviser/screening-conversation.blade.php` | Inherited: layouts.app |
| `adviser/screenings.blade.php` | Inherited: layouts.app |
| `adviser/session.blade.php` | Inherited: layouts.app |
| `adviser/settings.blade.php` | Inherited: layouts.app |
| `adviser/training.blade.php` | Inherited: layouts.app |
| `adviser/transcript-content.blade.php` | Inherited: layouts.app |
| `adviser/transcript-review.blade.php` | Inherited: layouts.app |
| `auth/confirm-password.blade.php` | Inherited: layouts.guest |
| `auth/forgot-password.blade.php` | Inherited: layouts.guest |
| `auth/login.blade.php` | Inherited: layouts.auth |
| `auth/pseudonymous-register.blade.php` | Inherited: layouts.auth |
| `auth/register.blade.php` | Inherited: layouts.guest |
| `auth/reset-password.blade.php` | Inherited: layouts.guest |
| `auth/seeker-consent.blade.php` | Inherited: layouts.auth |
| `auth/seeker-register.blade.php` | Screen document |
| `auth/verify-email.blade.php` | Inherited: layouts.guest |
| `components/admin/activity-feed.blade.php` | Component / partial |
| `components/admin/activity-report.blade.php` | Component / partial |
| `components/admin/audit-log-table.blade.php` | Component / partial |
| `components/admin/backup-snapshot-row.blade.php` | Component / partial |
| `components/admin/chart-card.blade.php` | Component / partial |
| `components/admin/dialog.blade.php` | Component / partial |
| `components/admin/header.blade.php` | Component / partial |
| `components/admin/health-incident-list.blade.php` | Component / partial |
| `components/admin/icon.blade.php` | Component / partial |
| `components/admin/layout.blade.php` | Screen document |
| `components/admin/permission-switch.blade.php` | Component / partial |
| `components/admin/recent-logs.blade.php` | Component / partial |
| `components/admin/report-card.blade.php` | Component / partial |
| `components/admin/resource-card.blade.php` | Component / partial |
| `components/admin/service-health-card.blade.php` | Component / partial |
| `components/admin/settings-switch.blade.php` | Component / partial |
| `components/admin/sidebar.blade.php` | Component / partial |
| `components/admin/stat-card.blade.php` | Component / partial |
| `components/admin/status-card.blade.php` | Component / partial |
| `components/admin/user-role-badge.blade.php` | Component / partial |
| `components/admin/user-status-badge.blade.php` | Component / partial |
| `components/adviser-metrics.blade.php` | Component / partial |
| `components/application-logo.blade.php` | Component / partial |
| `components/auth-card.blade.php` | Component / partial |
| `components/auth-session-status.blade.php` | Component / partial |
| `components/brand-mark.blade.php` | Component / partial |
| `components/confirmation-modal.blade.php` | Component / partial |
| `components/danger-button.blade.php` | Component / partial |
| `components/dashboard-chart.blade.php` | Component / partial |
| `components/dashboard-overview.blade.php` | Component / partial |
| `components/dropdown-link.blade.php` | Component / partial |
| `components/dropdown.blade.php` | Component / partial |
| `components/guest-layout.blade.php` | Screen document |
| `components/helper-date-range.blade.php` | Component / partial |
| `components/input-error.blade.php` | Component / partial |
| `components/input-label.blade.php` | Component / partial |
| `components/modal.blade.php` | Component / partial |
| `components/nav-link.blade.php` | Component / partial |
| `components/primary-button.blade.php` | Component / partial |
| `components/responsive-nav-link.blade.php` | Component / partial |
| `components/role-activity-report.blade.php` | Component / partial |
| `components/secondary-button.blade.php` | Component / partial |
| `components/seeker-consent-modal.blade.php` | Component / partial |
| `components/seeker-step.blade.php` | Component / partial |
| `components/supervision-history.blade.php` | Component / partial |
| `components/text-input.blade.php` | Component / partial |
| `components/ui-icon.blade.php` | Component / partial |
| `concerns/index.blade.php` | Inherited: layouts.app |
| `dashboard/admin.blade.php` | Component / partial |
| `dashboard/adviser.blade.php` | Inherited: layouts.app |
| `dashboard/helper.blade.php` | Inherited: layouts.helper |
| `dashboard/moderator.blade.php` | Component / partial |
| `dashboard/professional.blade.php` | Component / partial |
| `dashboard/seeker.blade.php` | Screen document |
| `dashboard.blade.php` | Component / partial |
| `emails/otp.blade.php` | Email (separate delivery styles) |
| `emergency.blade.php` | Inherited: layouts.app |
| `helper/calendar.blade.php` | Inherited: layouts.helper |
| `helper/cases-show.blade.php` | Inherited: layouts.helper |
| `helper/cases.blade.php` | Inherited: layouts.helper |
| `helper/chat/show.blade.php` | Inherited: layouts.helper |
| `helper/chat.blade.php` | Inherited: layouts.helper |
| `helper/competency-detail.blade.php` | Inherited: layouts.helper |
| `helper/competency.blade.php` | Inherited: layouts.helper |
| `helper/feedback-detail.blade.php` | Inherited: layouts.helper |
| `helper/feedback.blade.php` | Inherited: layouts.helper |
| `helper/notes.blade.php` | Inherited: layouts.helper |
| `helper/notifications.blade.php` | Inherited: layouts.helper |
| `helper/onboarding.blade.php` | Inherited: layouts.helper |
| `helper/pre-session-assessment.blade.php` | Inherited: layouts.helper |
| `helper/profile.blade.php` | Inherited: layouts.helper |
| `helper/readiness-history.blade.php` | Inherited: layouts.helper |
| `helper/readiness.blade.php` | Inherited: layouts.helper |
| `helper/referral-status.blade.php` | Inherited: layouts.helper |
| `helper/report-export.blade.php` | PDF document (separate print styles) |
| `helper/reports.blade.php` | Inherited: layouts.helper |
| `helper/resources.blade.php` | Inherited: layouts.helper |
| `helper/self-help/breathing.blade.php` | Inherited: layouts.helper |
| `helper/self-help/grounding.blade.php` | Inherited: layouts.helper |
| `helper/self-help/hotlines.blade.php` | Inherited: layouts.helper |
| `helper/self-help/index.blade.php` | Inherited: layouts.helper |
| `helper/self-help/journal.blade.php` | Inherited: layouts.helper |
| `helper/settings.blade.php` | Inherited: layouts.helper |
| `helper/voice.blade.php` | Inherited: layouts.helper |
| `landing/index.blade.php` | Screen document |
| `layouts/app.blade.php` | Screen document |
| `layouts/auth.blade.php` | Screen document |
| `layouts/guest.blade.php` | Screen document |
| `layouts/helper.blade.php` | Screen document |
| `layouts/navigation.blade.php` | Component / partial |
| `layouts/partials/adviser-sidebar.blade.php` | Component / partial |
| `layouts/partials/helper-sidebar.blade.php` | Component / partial |
| `layouts/partials/moderator-sidebar.blade.php` | Component / partial |
| `layouts/partials/professional-sidebar.blade.php` | Component / partial |
| `layouts/partials/pwa-banner.blade.php` | Component / partial |
| `layouts/partials/pwa-meta.blade.php` | Component / partial |
| `layouts/partials/seeker-sidebar.blade.php` | Component / partial |
| `layouts/partials/sidebar-critical.blade.php` | Component / partial |
| `moderator/analytics.blade.php` | Screen document |
| `moderator/dashboard.blade.php` | Screen document |
| `moderator/emergency.blade.php` | Inherited: layouts.app |
| `moderator/manage.blade.php` | Screen document |
| `moderator/notifications.blade.php` | Screen document |
| `moderator/partials/distribution.blade.php` | Component / partial |
| `moderator/queue.blade.php` | Screen document |
| `moderator/reports.blade.php` | Inherited: layouts.app |
| `moderator/schedules.blade.php` | Screen document |
| `moderator/session-detail.blade.php` | Screen document |
| `moderator/sessions.blade.php` | Screen document |
| `moderator/settings.blade.php` | Screen document |
| `moderator/unassigned-referrals.blade.php` | Screen document |
| `notifications/archive.blade.php` | Inherited: layouts.app |
| `notifications/index.blade.php` | Screen document |
| `partials/emergency-notice.blade.php` | Component / partial |
| `partials/helper-sidebar.blade.php` | Component / partial |
| `partials/light-mode-notice.blade.php` | Component / partial |
| `partials/management-ui-styles.blade.php` | Component / partial |
| `partials/privacy-text.blade.php` | Component / partial |
| `partials/referral-appointments.blade.php` | Component / partial |
| `partials/referral-consent-terms.blade.php` | Component / partial |
| `partials/referral-identity-modal.blade.php` | Component / partial |
| `partials/referral-recommendation.blade.php` | Component / partial |
| `partials/referral-ui-styles.blade.php` | Component / partial |
| `partials/sidebar.blade.php` | Component / partial |
| `partials/terms-text.blade.php` | Component / partial |
| `partials/workflow-notice.blade.php` | Component / partial |
| `professional/case-detail.blade.php` | Screen document |
| `professional/cases.blade.php` | Screen document |
| `professional/dashboard.blade.php` | Screen document |
| `professional/emergency-identity.blade.php` | Component / partial |
| `professional/identity.blade.php` | Inherited: layouts.app |
| `professional/profile.blade.php` | Screen document |
| `professional/referral-detail.blade.php` | Screen document |
| `professional/referrals.blade.php` | Screen document |
| `professional/reports.blade.php` | Screen document |
| `professional/settings.blade.php` | Screen document |
| `profile/edit.blade.php` | Screen document |
| `profile/index.blade.php` | Screen document |
| `profile/partials/delete-user-form.blade.php` | Component / partial |
| `profile/partials/update-password-form.blade.php` | Component / partial |
| `profile/partials/update-profile-information-form.blade.php` | Component / partial |
| `request/concern.blade.php` | Inherited: layouts.app |
| `request/history.blade.php` | Screen document |
| `request/matching.blade.php` | Screen document |
| `request/partials/emergency-waiting.blade.php` | Component / partial |
| `request/preferences.blade.php` | Screen document |
| `request/screening.blade.php` | Screen document |
| `request/status.blade.php` | Inherited: layouts.app |
| `request/voice-consent.blade.php` | Inherited: layouts.app |
| `seeker/privacy.blade.php` | Screen document |
| `seeker/referrals.blade.php` | Screen document |
| `selfhelp/category.blade.php` | Screen document |
| `selfhelp/index.blade.php` | Screen document |
| `selfhelp/resource.blade.php` | Screen document |
| `session/chat.blade.php` | Screen document |
| `session/connection-status.blade.php` | Component / partial |
| `session/evaluation.blade.php` | Screen document |
| `session/history.blade.php` | Screen document |
| `session/identity-embedded.blade.php` | Screen document |
| `session/identity.blade.php` | Component / partial |
| `session/reconnections.blade.php` | Inherited: layouts.app |
| `session/referral-prompt.blade.php` | Component / partial |
| `session/support-embedded.blade.php` | Screen document |
| `session/thank-you.blade.php` | Screen document |
| `session/voice.blade.php` | Inherited: layouts.app |
| `settings/account.blade.php` | Screen document |
| `settings/appearance.blade.php` | Screen document |
| `settings/index.blade.php` | Screen document |
| `settings/preferences.blade.php` | Screen document |
| `settings/privacy.blade.php` | Screen document |
| `welcome.blade.php` | Screen document |
