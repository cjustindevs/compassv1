# Referral workflow verification — 2026-09-26

## Helper submission repair — 2026-09-27

The generic Helper 422 page came from a cross-session open-referral check using `abort(422)`. It now returns a validation message while preserving the existing referral; it does not silently create another open case. The seeker row is locked during creation to serialize concurrent submissions. Identical retries no longer repeat notifications, and revised recommendations preserve encrypted historical snapshots.

The initial Helper form now collects Appendix O recommendation fields (indicators, factual summary, observations, actions, receiving office, explanation status, urgency and remarks), separate from identity and seeker consent. The new `recommendation_form` column uses Laravel encrypted-array storage. Previously submitted summary-only referrals remain readable. Legacy clients may still send summary-only requests during rollout.

The visible consent-first Helper form has been removed. Adviser return actions use the existing clarification/revision workflow. Seeker chat prompts link to the full approved recommendation and consent page. Identity modal fields stay disabled until explicit disclosure consent is checked. Vault-authorized identity views include the approved recommendation; unauthorized pages never retrieve the vault data. Ordinary referral decline redirects to Self-Help.

Deployment requires `php artisan migrate --force` for `2026_09_27_000001_add_referral_recommendation_form.php`. This is additive; no existing records are deleted. Run before serving updated pages. Existing authorized professional assignment and appointment services remain in use. Off-platform external-professional transfer remains unavailable pending a documented recipient-verification and secure-sharing procedure; existing verified COMPASS professional accounts can be assigned through the authorized coordination workflow.

Validation: 76 focused referral, consent, identity-vault, appointment and Adviser tests passed (516 assertions); frontend build passed. The full run reported 412 passes and 9 failures: two expectations were updated for the requested return-for-revision and Self-Help behavior and pass in the focused run; seven moderator scheduling/queue failures remain outside this change. Full-system success is not claimed. The legacy summary-only submission endpoints and concluded-session eligibility remain backward compatible; enforcing the new form on every legacy caller and external transfer governance remain follow-up work.

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
