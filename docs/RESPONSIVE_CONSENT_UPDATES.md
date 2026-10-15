# COMPASS UX & Consent Update Notes

Date: 2026-09-25. Session scope: consent gate, registration flow, terms/privacy copy, and responsive layout. Branch: `deploy`.

## 1. Consent gate for support requests (no more raw 409)

**Reported bug**: a seeker got a raw `409 Conflict` ("Server returned a '409 Conflict'") when requesting support without first accepting the Terms & Condition / Privacy Notice.

**Cause**: `ConsentService::requireGeneral()` aborted with 409 inside `SeekerWorkflowService::screen()` when the seeker clicked "request support" before consent documents were accepted.

**Fix**:
- Added `ConsentService::isFull(HelpSeeker): bool` — true only when both `privacy_policy` and `informed_consent` are valid for the current consent `VERSION`.
- `RequestSupportController::processScreening()`, `processConcern()`, and `processPreferences()` now pre-check consent. When consent is missing they redirect to `request.screening` with an `open_consent` session flag and a friendly info flash ("Please review and accept the current Terms and Condition and Privacy Notice before requesting support.") instead of throwing 409.
- The consent modal auto-opens from that page (`session('open_consent')` → `components/seeker-consent-modal.blade.php`).

## 2. Registration flow reordered — Terms & Consent FIRST

`resources/views/auth/pseudonymous-register.blade.php` now has two steps:

1. **Step 1 — Terms & Condition + Privacy Notice (verbatim)**: a scrollable document panel with a scroll-to-bottom gate. The "Agree and continue" button is disabled until the full documents are read and the agreement checkbox is ticked. Only then does Step 2 appear.
2. **Step 2 — registration inputs**: email verification (OTP), alias, age, gender, preferred language, password. The form submits `agree_privacy`/`agree_terms`, and `SeekerOnboardingController::store()` validates them via `accepted`.

On successful registration:
- Both `privacy_policy` and `informed_consent` `ConsentRecord`s are recorded with the current `ConsentService::VERSION` via `ConsentService::decide()` inside the same request.
- The seeker is redirected straight to `request.screening` (the separate `seeker.consent` step is no longer the target after registration).
- Because both current-purpose consents already exist, the consent modal does not auto-force the accept step and support requests are not blocked behind a 409.

## 3. Terms & Condition + Privacy Notice text replaced (verbatim, no summarization)

The exact supplied wording now lives in two shared partials loaded on every consent surface:

- `resources/views/partials/terms-text.blade.php` — starts "By proceeding, you agree to participate in Compass, a peer-support platform…" and ends with the Emergency Button / local crisis hotlines sentence. All paragraphs preserved verbatim (duty-to-protect, 6:00–11:00 PM operational window, 90-minute voice-call cap, RA 10173, etc.).
- `resources/views/partials/privacy-text.blade.php` — starts "Welcome to COMPASS, a web based platform for Project Dial-A-Friend." and closes with the "By selecting 'I Agree', you acknowledge…" sentence. All paragraphs preserved verbatim (Identity Vault isolation, encrypted transcripts, optional voice consent, retention schedule).

Used by:
- `resources/views/auth/pseudonymous-register.blade.php` (Step 1 panel)
- `resources/views/components/seeker-consent-modal.blade.php` (Terms and Privacy modal body)
- `resources/views/auth/seeker-consent.blade.php` (Privacy and Consent page details blocks)

The previous abbreviated/summarized "Terms of support" / "Privacy Notice" copy in those surfaces was replaced.

## 4. Responsive layout & collapsible sidebar

**Problem**: the sidebar collapse toggle was only shown at `min-width: 1025px`, so on tablet/medium screens (768–1024px) the sidebar stayed permanently expanded and certain responsive UI features broke.

**Fix**:
- `.sidebar-toggle` now appears at all desktop/tablet sizes: breakpoint lowered from `1025px` to `769px` in both `resources/css/sidebar.css` and `resources/css/responsive.css`. The collapse control therefore works at every width ≥ 769px (expanded ↔ icon rail), and ≤ 768px the sidebar becomes the slide-in drawer (unchanged).
- `resources/views/seeker/privacy.blade.php` had conflicting inline `.main-content { margin-left: 260px }` plus duplicate `.hamburger` / `.sidebar-overlay` / `.bottom-nav` rules. These were removed so the shared sidebar CSS (`partials.sidebar` + `@vite app.js`) governs layout; the page keeps only its content-card padding rules.
- Other pages' inline `margin-left: 260px` rules are safely overridden by the higher-specificity `.sidebar.collapsed ~ .main-content` and the `!important` mobile rules in the shared system.

## 5. Tests

- `tests/Feature/Auth/RegistrationTest.php`:
  - `test_two_verified_seekers_can_register_from_the_same_ip` and `test_registration_persists_the_otp_verification_time` now expect the redirect to `request.screening` and include the `agree_privacy`/`agree_terms` fields; the first also asserts both consent records (`privacy_policy` + `informed_consent`, `consent_given`, current version) are created.
  - New `test_registration_requires_accepting_terms_and_privacy` — storing without the agreement flags is rejected with `agree_privacy`/`agree_terms` errors.
- `tests/Feature/CompassImplementationTest.php` — pseudonymous registration now redirects to `request.screening` and asserts the versioned consent records exist for the new seeker.
- `tests/Feature/SeekerWorkflowSecurityTest.php` — posts while consent is missing (including after withdrawal) now assert `assertRedirect(request.screening)` + `assertSessionHas('open_consent', true)` instead of 409.
- Full suite green: **329 passed / 2441 assertions**. `npm run build` rebuilt the bundle and service worker successfully.

## Files touched

- `resources/views/partials/terms-text.blade.php`, `resources/views/partials/privacy-text.blade.php` (new, verbatim copy)
- `resources/views/auth/pseudonymous-register.blade.php` (terms-first two-step flow, gate JS + CSS, hidden agree fields)
- `resources/views/components/seeker-consent-modal.blade.php`, `resources/views/auth/seeker-consent.blade.php` (verbatim partials in consent surfaces)
- `app/Services/ConsentService.php` (`isFull()`)
- `app/Http/Controllers/RequestSupportController.php` (consent pre-check redirect on all support POST endpoints)
- `app/Http/Controllers/SeekerOnboardingController.php` (agree validation, consent recording, redirect to `request.screening`)
- `resources/css/sidebar.css`, `resources/css/responsive.css` (toggle breakpoint 1025px → 769px)
- `resources/views/seeker/privacy.blade.php` (removed conflicting inline sidebar/main-content CSS)
- `tests/Feature/Auth/RegistrationTest.php`, `tests/Feature/CompassImplementationTest.php`, `tests/Feature/SeekerWorkflowSecurityTest.php`
- Built assets: `public/build/*`, `public/sw.js`

## Status

All requested changes implemented and verified with the automated suite and production asset build.