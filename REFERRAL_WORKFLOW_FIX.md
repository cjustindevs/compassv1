# Referral workflow verification — 2026-09-26

## Adviser queue follow-up

The referral queue, dashboard pending list/count and sidebar badge now share the same Adviser scope. This includes directly assigned referrals, supervised Helpers' referrals, and previously unassigned referrals for sessions explicitly assigned to that reviewer. Unrelated Advisers remain blocked.

New Helper recommendations use an active supervising Adviser, falling back to the session's explicitly assigned active reviewer. Missing assignments produce an actionable validation error instead of silently creating an orphan referral. An open referral in another session no longer produces a misleading submission-success response for the current session.

Existing referrals with neither a supervising Adviser nor an explicit session/referral reviewer still require an authorized assignment; this patch does not expose them to all Advisers or invent supervision relationships. No migration is needed.

Follow-up verification: full suite passed with 390 tests and 2827 assertions, including session-reviewer routing, notification delivery, legacy queue visibility, unrelated-Adviser denial and missing-active-reviewer validation.

## Corrected failures

- The Adviser detail page closed its review JavaScript with `</style>`, preventing review modal functions from being parsed.
- Approval redirected to a queue page that did not render the flashed success message. A shared accessible status/error dialog now covers the Adviser shell, Helper shell, seeker referral page and professional referral/case pages.
- Expected workflow conflicts (already reviewed, unanswered clarification, missing identity submission) now return browser forms to validation notices. Authorization failures remain blocked and unexpected server errors are not hidden.
- Approval now persists a Helper notification as well as attempting broadcasting.
- Session status polling selects referral-purpose consent, rather than accidentally reading a later identity-disclosure decision. Helper notice wording now follows Adviser-review-first order.

## Verification and limits

Regression coverage includes browser approval and redirect, rendered inline JavaScript parsing, duplicate review protection, approval notifications, consent-purpose isolation and seeker status notices. Existing referral, identity-vault and appointment tests exercise downstream workflows. Tests use the configured isolated testing database.

Final local results: `php artisan test --compact` — 388 passed, 2813 assertions. `npm run build` passed. Blade view compilation and referral route listing passed. Live multi-account browser validation remains required after Render deploys the commit.

The exact hosted submission error was not supplied, so these verified defects do not establish that every hosting-specific failure is resolved. No schema changes are required by this patch. Existing production migrations and identity-vault configuration must already be present.

## Live validation after deployment

1. Helper submits a recommendation during an active session.
2. Assigned Adviser opens the referral, approves with a reason and sees a status notice.
3. Seeker sees the consent request, accepts or declines personally; acceptance opens identity submission.
4. After identity submission, an available assigned Professional accepts the referral and sees a notice.
5. Try a repeated Adviser approval and approval awaiting Helper clarification: both must show a reason without changing the recorded decision.

If a different hosted error remains, record its exact message, route and time, plus the corresponding sanitized Laravel log entry.
