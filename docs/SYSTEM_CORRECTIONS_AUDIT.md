# October 5 corrections ? work in progress

The user subsequently authorized committing verified corrections and pushing to deploy. No production reset or destructive migration is authorized.

## Sources and conflicts
- User master prompt: September 29 corrections supplied October 5.
- Existing COMPASS-v4 extraction: storage/logs/compass-v4.txt. Its introduction cites WHO adolescence ages 10?19; this is background, not an eligibility policy.
- WHO verified: https://www.who.int/health-topics/adolescent-health/ defines adolescence as 10?19. Do not infer admission eligibility from this classification. Approved eligibility clarification requested.
- Existing registration/onboarding uses 13?99; settings 13?120. Age changes pending policy confirmation.
- Existing Seeker cancellation conflicts with new requirements. Normal cancellation will be forbidden. Withdrawal of consent must remain possible and stop dependent processing; this is distinct from normal request cancellation.
- Existing RELAX_DUTY_HOURS bypasses readiness. New explicit requirement takes precedence: readiness must always be enforced.
- Current emergency escalation automatically creates ordinary referrals. Dedicated emergency records already exist, so automatic referral creation must be removed without deleting legacy records.
- Single-device denial vs replacement requires approved policy; not inferred from unrelated auth code.

## Inventory / pending work
Concern management, emergency popup/rejection/resolution, Helper self-declared date-only duty, Moderator filtering, emoji/help-marker cleanup, conditional Other descriptions, admin deactivation protection, analytics/report separation, identifier audit, schedule/email delivery, archive UI, and complete regression audit remain to be implemented or verified. This report is not a completion claim.

## Implemented corrections in this release
- Mandatory current passed readiness, including testing mode; expired/failed/missing checks denied.
- Normal Seeker cancellation endpoint denied; cancellation forms removed; consent withdrawal retained.
- Notification archive timestamp, default-query exclusion, retained history page, owner-scoped archiving and audit.
- Active concern management for Adviser/Admin; historical names protected; inactive concerns excluded and rejected during new requests.
- Emergency automatic ordinary-referral creation removed; dedicated staff notifications/popup with persistent dismissal.
- Adviser rejection is an append-only review action plus a separate decision, actor/time/reason; no false resolution. Helper case displays reason.
- Moderator emergency resolution denied, including shared emergency incident resolution/closure bypass.
- Helper date-only duty declaration requires readiness; Moderator availability filter uses backend eligibility.
- Separate Adviser Reports and Analytics with existing scoped formulas.
- Admin deactivation now has a backend transaction and checks active assignments, documentation, referrals and emergencies.

## Verification checkpoint
Earlier targeted security/reconnection run: 33 passed, 440 assertions. Latest corrections suite: 11 tests passing (within combined run); combined Escalation run passed after correcting the appointment fixture.
Full suite baseline during this revision: 423 passed, 16 failed; legacy URL redirect assertions, obsolete emergency-auto-referral expectations, appointment fixtures and a version-history count need review. This is NOT a clean full-suite result.
No production migrations run. New additive migrations: notification archive/popup dismissal; concern activation; emergency review decision.

## Still outstanding
Approved age/service eligibility and single-device policy clarification; complete Other-field audit; email delivery via authorized Vault access; existing legacy emergency-referral reconciliation; full raw-ID/emoji/help-marker sweep; scheduling/report pagination and deactivation concurrency review; full tests/static analysis and browser verification. This release is a verified subset of the master correction request, not a claim that every master requirement is complete.

## October 5 follow-up
- Corrected regression fixtures for encrypted case/chat URLs, required appointment details, current recorded referral consent, and append-only clarification history.
- Emergency retries preserve the original narrative and do not repeat staff notifications. Adviser broadcasts go only to the assigned active Adviser and omit the private narrative.
- Emergency popup now loads through both role sidebars, including standalone pages. Concern navigation retains an icon when collapsed.
- Restored the complete preference submission form while removing only its nested cancellation form.
- Added the missing Moderator availability filter control.
- Targeted regression run: 45 passed, 317 assertions. Full-suite final result recorded after execution below.
- Production asset build and route listing passed. No production migration was executed from this workspace.

## Deployment and manual checks
Render's existing docker/start.sh runs additive main database migrations before starting the web process. This release adds three migrations for notification archiving, concern activation, and emergency review decisions. Do not use migrate:fresh.
After deployment, verify the three migration names in Render logs; test the Adviser/Moderator popup once and after dismissal; submit a concern with Other; verify Helper readiness and duty declaration; archive a notification and view its history; test the Moderator availability filter. Check desktop and mobile layouts in real browsers.
Unrelated OTP/authentication/configuration edits and tool-directory deletions are excluded from this correction commit.

Final full suite: 444 passed (3204 assertions). Changed PHP syntax checks passed. Route listing and Blade compilation passed. npm run build passed. Local PHP prints a duplicate openssl extension warning; no test failed. Browser/device and hosted PostgreSQL verification remain outstanding.
