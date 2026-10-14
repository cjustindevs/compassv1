# October QA revisions — deployment and manual checks

Voice calls are outside this revision. Their code and environment flags are not changed.

## Deployment

- Deploy the reviewed changes after committing/pushing. Render's existing startup runs migrations automatically.
- For local testing, apply `php artisan migrate --path=database/migrations/2026_10_14_000001_add_staff_mail_and_duty_series.php`, then `php artisan config:clear` and `npm run build`.
- During the agreed testing phase use `ENFORCE_DUTY_HOURS=false` and `RELAX_DUTY_HOURS=false`. Duty dates, institutional approval, current readiness, capacity and conflict checks remain required. When service-hour enforcement is later enabled, new timed duties open readiness 30 minutes before start and become assignable within the shift. Legacy all-day duties retain their historical behavior.
- Keep `RUN_SCHEDULER=true`. The scheduler sends staff email from an encrypted outbox every minute, retrying failed deliveries up to five attempts. Existing Resend sender/API configuration is reused; no new paid service is introduced. Inbox delivery still needs live verification.
- Reverb settings for text chat are independent of the voice feature flag. The notification bell also polls owned alerts every five seconds if a socket is absent or fails. Chat catches up automatically every three seconds during connection/channel failure, with a periodic safety fetch even when the socket is connected.

## Manual acceptance checks

1. Use separate Helper and Seeker browsers/devices. Send messages in both directions without refreshing. Disconnect/reconnect a network; confirm missing messages appear once and the typed draft survives. While reading earlier messages, incoming updates should not repeatedly force scrolling to the bottom.
2. Create Helper, Moderator, Adviser and Professional accounts as Admin. Confirm each correct role profile exists and receives a neutral account-created email. Passwords, safety answers and session data must not appear in the email. Seeker must be unavailable as an Admin-created role, including direct form submission.
3. As Admin, Edit Profile and refresh to verify persistence. Attempt a role/status change through the profile endpoint: it must be rejected. Deactivate and reactivate another account; verify access changes and audit history. Reactivation must not make a Helper automatically ready/available. Self-deactivation and the last active Admin remain protected.
4. Schedule an offline, approved Helper who has not passed readiness. Create a single day, then a recurring MWF series. Verify only selected dates exist, duplicate scheduling rolls back, notifications are stored, and email is received. Check upcoming duty on the Helper dashboard.
5. An unscheduled Helper must see the no-duty notice instead of a readiness form; a forged submission must also fail. A scheduled Helper may check readiness at any clock time during testing, but only on the applicable duty date. Failed readiness keeps them unavailable; an expired check must not activate a later duty.
6. Cancel a duty with linked readiness. It must not return a server error, delete readiness history, or remove another Helper's duty. The Helper must become unavailable immediately. Cancel only one recurring occurrence, then future occurrences. Editing/removing current duty while an assignment is active must be blocked with a readable message. Editing duty requires a fresh readiness check; re-scheduling a cancelled date must not reactivate its old readiness.
7. Submit the single safety answer Yes. It must continue to Preferences and the high-priority queue without waiting for Adviser approval. Adviser/Moderator receive safety alerts; the selected Helper must still satisfy all eligibility rules. The recorded classification stays high, with no fabricated current-plan answer and no automatic referral. Later Adviser review must not reset an already assigned/live session to Preferences.
8. Submit Prefer not to say. It must retain the existing Adviser review/follow-up hold. No assumed Yes/No answer or queue bypass. Verify original responses and review history remain intact.
9. Start a safety-flagged support session. The Helper receives one referral-recommendation notice; no referral consent/approval is manufactured. Verify actual referral Agree/Decline/Withdraw confirmations and saved statuses. Approved referral details show the original pre-session summary to authorized staff.
10. Check the top-right bell and popups in every role. New assignments, duties, referrals and emergencies must appear once. Navigate between pages; old popups should not replay. Open a confirmation dialog; notifications should wait without being lost. Accounts must never receive another user's feed.
11. Seekers must not see the reviewing Adviser's name/email. Verify privacy paragraph spacing and keyboard-accessible password eye buttons on login/register/reset forms.
12. Verify percentage and five-point legacy competency records use the same normalized five-point display, e.g. 82% becomes 4.1/5. Check weighted rubric examples, unchanged-score growth, missing data, and Philippine-day/month boundaries. Compare totals against the underlying completed/evaluated sessions and recorded assessments, not seeded UI placeholders.

## Preserved behavior

- Helper duties are cancelled rather than physically deleted, retaining foreign-key history. Re-scheduling a cancelled date preserves its prior values in the audit record and leaves old readiness inactive.
- Normal requests and Prefer not to say retain their existing consent/review requirements. The expedited branch is limited to the versioned single-question Yes answer with a stored open safety alert.
- Email failures do not undo account creation. Popups do not mark a review, emergency, or referral resolved.
- Production network behavior, email inbox delivery, and mobile layout still need the manual checks above after deployment.

## Local verification

- Full PHP regression run: 706 tests passed, 6,618 assertions.
- JavaScript suite: 28 tests passed.
- Follow-up scheduling/security regression run: 82 tests passed. After the final Helper warning-display fix, all 11 October QA tests passed with 92 assertions.
- Frontend production build passed. No live Render, email-inbox, or visual mobile acceptance test has been performed in this revision.
