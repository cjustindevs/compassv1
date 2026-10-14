# Account, duty, and in-chat workflow repair QA

These changes require both `2026_10_15` migrations. Render's startup should run migrations before serving the new code. For local testing run `php artisan migrate`, then `npm run build`. Keep the agreed testing flags `ENFORCE_DUTY_HOURS=false`, `RELAX_DUTY_HOURS=false`, and `RUN_SCHEDULER=true` on Render. No new mail or voice provider is required.

## Email and account creation

1. As Admin, create a staff account using a real accessible email. Submit once, and test double-clicking. Expect one account, one role profile, one welcome-email outbox entry. Replaying the same form receipt must return success without changing the password or making another account. Using that receipt for different details must fail visibly.
2. Check the directory email status. **Email queued** means the scheduler has not completed delivery. **Accepted by email provider** means the sending service accepted the request; it does not prove inbox placement. Check Resend's delivered/bounced status and inbox/spam for the matching address. The reported account-created messages in the supplied Resend list were delivered.
3. A delivery failure must show a non-sensitive reason. Admin can queue a retry of a failed account email without recreating the account. A message already accepted by the provider cannot be repeatedly sent through this retry action. Reserved `.local`, `.test`, `.invalid`, and `.example` addresses are retained as test accounts but not sent email. Correct misspelled recipient addresses rather than creating another person with the same name.
4. Test an email already used in a staff profile but missing from the account directory. Creation must show a readable profile-link error and create no new account. Existing suspected duplicates are not deleted or merged by this revision; compare account IDs, profile links, and emails before cleanup.
5. Inspect Admin actions at desktop and mobile widths. Edit Profile and Deactivate/Reactivate should remain aligned. Reactivate opens a confirmation dialog. Verify cancelled confirmation changes nothing.
6. Log out as Moderator. Visit Schedules while signed out and log in again. Expect Dashboard, not the stale Schedules destination.

## Duty display

7. Schedule a verified Helper for today and another future date. Today belongs under **Today's duty**; future dates belong under **Upcoming duties**. During testing, today applies all day even if the displayed planned hours have elapsed; the card explains this. With clock enforcement enabled, it distinguishes scheduled, current, and ended duty.
8. With the Helper dashboard open, edit or cancel a duty from the Moderator account. Allow up to 15 seconds for the displayed dates to update without refreshing. Scheduling, readiness, and current assignment remain separate eligibility checks.

## Referral during chat

9. Start a normal Helper/Seeker chat in separate browsers. Helper opens Recommend Referral. Submit missing indicators/required fields: the dialog must remain open, errors visible, answers retained. Complete the recommendation and submit. Expect one referral, Helper confirmation, Adviser notification and queue entry; chat stays live.
10. Adviser returns the recommendation for revision. Helper reopens the dialog and sees the requested clarification with a response form. Submit the response, then Adviser approves. Seeker receives the complete recommendation and an Agree/Decline decision. Consent before approval must be blocked. Accepting leads to the separate secure identity/contact step, then Professional coordination. Declining does not end chat or resolve any emergency.

## Emergency during chat and Professional handoff

11. Helper flags an emergency during the active chat. Expect a saved incident, a chat status banner, Adviser/Moderator notifications, and an Adviser Emergencies badge that updates without a socket. Notification opens the responsible case. The case shows the chat report and recorded immediate action. No referral is automatically created; original intake answers/classification remain intact.
12. Repeat the emergency submit: expect no duplicate incident or notification. Repeat with a session that already has an intake alert: the distinct chat report still sends a new stored alert. An old intake acknowledgment must not mark the new chat report reviewed. Adviser records acknowledgment/instructions/coordination; chat status updates. Resolving records the outcome and preserves live chat. A subsequent new incident creates a new alert without overwriting the resolved one.
13. From the emergency detail, Adviser requests an urgent recommendation. Helper opens the notification and submits it. Expect a linked urgent referral. Adviser reviews it, Seeker decides consent, and the separate identity step is completed. Only then can an available Professional be assigned/notified. The Professional referral detail shows the linked emergency reference. Another Professional must not access it. Professional acceptance does not automatically resolve the emergency.
14. Test while WebSockets are unavailable: stored notifications and workflow polling should continue. A failed request retains typed form answers. Dialogs should scroll internally with visible action buttons, support Escape/Cancel, return focus to the opener, and avoid opening another workflow dialog on top of an unfinished form.

Pre-session Yes priority queuing and Prefer not to say follow-up/review hold are preserved. Re-test both before approving deployment. No production records or environment secrets were changed during implementation; live inbox delivery and mobile appearance still require the checks above.

## Automated verification

The full PHP suite passed 714 tests with 6,695 assertions before the final legacy-incident repair. The subsequent focused run passed 96 tests with 746 assertions; the final nine repair tests passed with 84 assertions after adding corrected-email retry coverage. JavaScript passed 32 tests, including modal validation, duplicate submission, revision state, and unconfirmed response handling. The production frontend build passed. Browser screenshot verification was unavailable in this session.
