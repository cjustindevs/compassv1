# COMPASS Open Issue List

Status date: 2026-09-25. This list captures items that remain open or are intentionally disabled after the R1–R7 alignment work. Priority: **High** = blocks/likely user-facing failure in production. **Medium** = operational or UX. **Low** = future hardening.

## High

| # | Issue | Detail | Where |
|---|-------|--------|-------|
| 1 | **SMTP password not set on Render** | Live OTP email requires `MAIL_PASSWORD` (Gmail App Password) in the Render dashboard env vars. Until set, OTP falls back to the demo on-screen display path; production may not mail codes. | Render dashboard env; `config/mail.php`, OTP flow |
| 2 | **Identity data requires the Identity Vault env** | `IDENTITY_VAULT_KEY` (32-byte base64) and the `identity_vault` database connection must be configured on Render; without them `IdentityVaultService::isAvailable()` is false and identity storage is unavailable. | Render dashboard env; `config/identity_vault.php` |

## Medium

| # | Issue | Detail | Where |
|---|-------|--------|-------|
| 3 | **Voice recording/transcripts disabled** | Appendix R is intentionally unimplemented: voice calling, recording, and automatic transcription are unavailable, and the privacy notice states this. Revisit if voice sessions are later introduced. | `seeker-consent.blade.php`, `session/*` |
| 4 | **Incident form has no file upload** | Appendix T "Supporting information" upload is not implemented; incidents use structured emergency flags (description + immediate action). | helper emergency modal, `EmergencyAlert` |
| 5 | **No re-consent flow after consent-first decline** | A seeker who declines the consent-request (status `closed`) must start a new referral to reconsider; the current modal shows status only. | `session/referral-prompt.blade.php` |
| 6 | **No printed/PDF exports of instruments** | Screening, evaluation, notes, and referral data are viewable in-app but not exported as the paper-appendices format. | reporting/admin |

## Low / Future hardening

| # | Issue | Detail |
|---|-------|--------|
| 7 | Duty shift times stored as nullable metadata only | Whole-day date rows govern on-duty status; legacy time window columns remain unused by matching (kept for the source document's times). |
| 8 | `reassessment_requested_at` duplication risk | Helper "Request adviser risk reassessment" and adviser screening review are separate surfaces; confirm one authoritative reviewer path in dry-runs. |
| 9 | Referral indicators merged into reason text | Appendix O indicator checkboxes are concatenated into `referral_reason` (client-side). If structured reporting on reasons is wanted later, promote to a separate column. |
| 10 | Legacy seeded skills values | Old seeders still reference `validation`/`problem_solving`/`crisis_intervention` skills; harmless for demo data, but refresh seeders if those rows ever feed evaluation analytics. |

## Recently fixed (for traceability)

- R1: whole-day (date-only) helper duty scheduling.
- R2: Moderator unassign helper now writes the history row's `reason` + `actor_id`; "Capacity: 15 each" label restored.
- R3: assigned adviser surfaced across the Helper module (dashboard, sidebar, profile, calendar).
- R4: 5-minute helper non-response → notify moderator + adviser, release recommendation, re-match excluding the helper, auto-flag after 3 consecutive misses (`NON_RESPONSE_LIMIT`).
- R5: post-session notes restored to the 5 appendix listener skills with server-side whitelist.
- R6: referral/identity gates — review notes required for approval, `canProvideIdentity()`, active-case-only identity link, clean 422 on release without stored identity.
- R7: evaluation competency renamed to "Referral Judgment"; Appendix O indicators added; `COMPLIANCE_MATRIX.md` published.