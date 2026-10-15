# October 15 workflow and privacy QA

Use separate browser profiles or devices for Seeker, Helper, Adviser, Moderator, and Professional. Use test accounts and simulated concerns. Deploy only after approval; no new migration or environment variable is required by this change.

## Preparation

- Schedule and verify a Helper, complete readiness, and keep the Helper signed in.
- Keep the responsible Adviser and a Moderator on visible, online pages. A blocking dialog must be closed before an emergency popup can open.
- Keep existing testing duty-hour settings; this update does not change them.

## Duty and ordinary session baseline

1. Moderator schedules a verified Helper for today's Philippine date. Confirm the Helper receives an in-system notice, sees today's duty separately from upcoming duty, and receives the duty email when using a deliverable address and the mail worker is running.
2. A Helper without applicable duty cannot pass readiness or enter matching. With today's duty, pass readiness and set Available. Testing may run at any time when `ENFORCE_DUTY_HOURS=false`; duty and readiness are still required.
3. A Seeker choosing No proceeds through Preferences, the ordinary queue, Helper acceptance, and session start. Neither an emergency nor a Professional referral is created automatically.
4. Open the Helper/Seeker chat in separate browser profiles. Messages should appear without refreshing. Keep the same test accounts for the normal and emergency referral checks below.

## 1. Newest-first records

1. Submit two requests at different times. Check Screening reviews, notifications, referrals, and emergency records as applicable.
2. The newest appears first, including records with matching timestamps (newer record ID wins).
3. Check Active Sessions and activity/history displays. Chat messages and calendar timelines remain chronological. Assignment priority and FIFO matching are unchanged.

## 2. Moderator privacy

1. Open Active Sessions, Emergency Alerts, Reports, and Support Sessions in Schedules.
2. Operational status, references, aliases, assigned staff, and timestamps remain available. No private session-content link is offered.
3. Paste `/moderator/sessions/SESSION_ID` directly. Expect HTTP 403 without conversation, clinical report, or evaluation content.
4. Transcript download and message APIs must also refuse Moderator access. Existing authorized Adviser transcript-purpose requirements remain in force.

## 3. Pre-session safety alert

1. Seeker answers Yes to the single safety question and supplies the required concern details.
2. The original screening remains High under the existing single-question rules; an operational emergency alert is separate.
3. Every active Adviser and active Moderator receive an unread notice and emergency popup. An empty sidebar badge becomes visible after the next feed update.
4. Staff feed and emergency popup polling check every two seconds on an active, online page. This is a polling interval, not a guaranteed end-to-end delivery time. Popups wait for other dialogs to close.
5. Seeker continues Preferences, priority queuing, and matching without waiting for Adviser approval.
6. Assigned Helper receives the assignment plus an urgent safety notice. If their supervisor differs from the intake reviewer, that supervisor receives an authorized coordination link too.
7. Every Adviser can open active cases in Emergencies. The responsible reviewer/supervisor sees the read-only screening and private review actions; other Advisers see the responsible reviewer, Helper, and coordination status. An inactive intake reviewer falls back to an active Adviser.
8. Repeat submission does not create another intake emergency or duplicate alerts. Confirm ordinary requests still match normally.

## 4. Prefer not to say and immutable screening

1. Seeker chooses Prefer not to say. The existing review hold stays in effect.
2. Adviser sends the existing one-question follow-up. Seeker responds on their waiting page.
3. Adviser reviews the saved response; original answers cannot be replaced by an Adviser or by a crafted form request.
4. A saved Yes/No response allows the existing review decision flow. A repeated Prefer not to say remains pending; no invented negative answer is recorded.
5. Classification confirmation and Adviser review notes are separate from the original Seeker response. Emergency actions remain in Emergencies.

## 5. Emergency during chat

1. Start an accepted Helper/Seeker chat. Helper submits an emergency report.
2. Every active Adviser and Moderator receive notices. The responsible Adviser sees the case under Emergencies and can acknowledge and document coordination/instructions.
3. Helper sees the emergency state and Adviser updates. Moderator sees operational status only.
4. Repeat the same open report: no duplicate incident or alert. A new report after resolution creates a new active alert.
5. Where an intake alert already exists, a new chat report still creates a fresh staff notification and requires review.

## 6. Referral during chat and emergency handoff

1. Helper recommends referral. Adviser receives it, reviews it, and may return it for revision.
2. Helper revises/resubmits; approval cannot bypass an unanswered revision request.
3. After Adviser approval, Seeker accepts or declines referral consent.
   During a live chat, the Seeker can accept in the referral popup and then receives the separate Identity Disclosure Consent popup in that same chat. No page navigation is required.
4. Decline closes the ordinary referral flow. Accept leads to the existing identity/contact step.
5. Professional assignment and private access wait for the required consent and identity steps. If no Professional is available, the referral remains visibly pending.
6. Assigned Professional receives the referral, can review it and record the appropriate response. Another Professional cannot open it.
7. For a linked emergency, the Professional sees the emergency reference. Neither creating the referral nor accepting it automatically resolves the emergency.
8. Withdraw consent and verify further access/disclosure is blocked. Repeated clicks must not duplicate referrals.

## Verification limits

Automated HTTP tests cover database state, role authorization, recipient selection, polling endpoints, consent gates, and duplicate handling. JavaScript tests cover browser-side confirmation, live notices, and chat dialog behavior. Actual popup appearance, two-device delivery, and hosted realtime transport still need the manual checks above.

## Completed automated checks

- Full PHP suite including all-Adviser delivery, live-chat identity disclosure, and selected Professional emergency handoff: 732 tests passed, 7,055 assertions.
- JavaScript suite: 44 tests passed.
- Changed-file whitespace check passed.
- Eight Adviser action tests cover pending states, real Helper requests, selected/unavailable Professionals, reviewer access, Professional popups, and complete ordinary/emergency referral handoffs through actual HTTP actions and encrypted identity storage.
- Hosted browser appearance and cross-device notifications still require the manual checks above.

## All-Adviser emergency delivery and referral modal follow-up

- New emergency notices are persisted during submission for every active Adviser and Moderator. They skip synchronous per-recipient socket broadcasts so an unreachable broadcast host cannot delay each emergency notice. Routine notification broadcasts remain unchanged.
- Keep two or more Adviser accounts open in separate browser profiles. Submit a new single-question Yes screening. Without refreshing, check each bell, popup, and emergency badge; polling runs every two seconds while pages are visible and online. Open the popup link from each account. Unassigned Advisers see coordination details and cannot edit private records or resolve another reviewer's case.
- Dismiss the popup in one account. It must stay dismissed after navigation and recovery runs. Closed or archived referenced cases must not keep appearing as urgent popups.
- Existing open cases automatically receive missing notices on the next `emergency-review-reminders` scheduler run (normally every minute), even if timed reminders are disabled. To reconcile immediately after deployment, run `php artisan emergencies:sync-notifications` in the Render shell. Repeating this command does not recreate dismissed/archived notices.
- No new migrations or environment variables are required. Keep the scheduler enabled for catch-up delivery.
- Open the Helper referral dialog on desktop and at a 360px mobile width. Check that the intro and reason indicators wrap, every field fits, content scrolls vertically, and Cancel/Submit are fully visible. Confirm successful submission, validation errors retaining drafts, and revision submission as before.
- Computer Use could not verify the browser URL and stopped before visual inspection. Modal layout still needs this manual check; no hosted browser timing measurement has been made.

## Adviser Professional referral actions

- Emergency Review has a top Professional referral button leading to the Professional handoff section.
- Before Helper connection/session start, the page explains Waiting for Helper connection and keeps the request button disabled. Priority queuing continues independently.
- Once the Helper session is active, the responsible reviewer/supervisor can enter instructions and click Request Helper recommendation. This notifies the Helper to submit an urgent recommendation; it does not bypass approval, consent, or identity steps.
- After the request, show Waiting for Helper recommendation. After submission, show Continue Professional referral. If the linked referral belongs to another authorized reviewer, show that reviewer rather than an inaccessible link.
- Referral Details shows Next step for Professional referral at the top: review/approve, waiting for revision, waiting for Seeker consent, waiting for contact details, choose Professional, review identity release, or handoff recorded.
- Choose Professional jumps to the existing assignment form; Assign and notify Professional opens confirmation and submits the real assignment action. Cancel must leave the assignment unchanged. Successful submission confirms the in-system notice; repeating the same assignment must not duplicate it. Review identity release jumps to an expanded permissions form. Only the assigned referral Adviser can authorize release.
- A disabled/pending action must state its unmet prerequisite. Professional acceptance and emergency resolution remain separate.
- Test the full chain twice: an ordinary chat referral, then an emergency-linked chat referral. Use separate referral consent and identity-disclosure consent. Saving contact details must not assign a Professional or release identity automatically. Assignment notifies only the selected Professional; that Professional cannot read identity before the separate release. Accept and schedule an appointment, then confirm it as the Seeker. Chat stays active, and any linked emergency stays open until separately resolved.

## Live-chat identity disclosure follow-up

1. Keep the Seeker's live chat open while the Helper recommends a referral and the Adviser approves it.
2. In the chat's referral popup, read the terms, check referral consent, and click Accept referral. Confirm the decision.
3. Identity Disclosure Consent opens over the same live chat. Check its separate consent checkbox to reveal the contact fields, enter sample name and phone, and submit securely.
4. Close or choose Not now before submitting. It must not reopen on every polling tick. The Provide contact details button in the chat support card can reopen it when ready.
5. After a successful submission, the popup confirms the save, the contact button disappears, and closing the popup returns to the same conversation. The Adviser receives the existing coordination notification. Assignment and identity release remain separate actions.
6. If another dialog is open, the automatic identity popup waits. Test a failed consent save, declining/withdrawing consent, and a Helper account: none may open the identity form improperly. A revoked or concluded referral closes an already-open identity popup when the next status update arrives.
7. Status checks run every two seconds while visible and online, with an immediate check on returning to the tab or reconnecting. This is a polling interval, not a hosted delivery guarantee.

Automated follow-up checks: 60 PHP workflow/privacy tests passed with 633 assertions; the full JavaScript suite passed all 41 tests, including eight new live-chat referral popup tests. The latest combined full regression results are listed above. Browser appearance and cross-device timing still need the live checks here.


## Emergency Review: selected Professional and urgent popup

1. Keep an eligible Professional and a second Professional signed in using separate browser profiles. Open the responsible Adviser's Emergency Review.
2. Request Helper recommendation is the preparation action: it notifies the Helper, not a Professional. Once the Helper submits, review and approve the linked recommendation.
3. The Seeker accepts referral consent and completes the separate identity-disclosure popup during the live chat. Emergency Review explains the pending prerequisite until each is complete.
4. Return to Emergency Review and open Professional referral. Under Raise to Professional, select the recipient, enter a handoff reason, click Raise and notify Professional, and confirm. Only active, available accounts with the Professional role appear.
5. The chosen Professional receives an urgent bell notice and an emergency popup without refreshing. Click Review assigned referral: it opens the linked referral with the emergency reference. The unselected Professional receives nothing and cannot open the referral. The popup/feed check every two seconds while visible and online; this is not an end-to-end delivery guarantee.
6. Adviser sees the selected Professional and handoff status. The handoff is recorded in emergency action history. Personal information stays inaccessible until the assigned referral Adviser separately authorizes release.
7. Repeating the handoff cannot duplicate the same assignment or recipient notification. Inactive/unavailable recipients, unrelated Advisers, incomplete consent/contact submission, and resolved emergencies must be rejected without partial assignment or notices.
8. If the Professional declines/reassignment removes their access, consent is withdrawn, or the linked emergency is resolved, an old urgent notice must not continue triggering that Professional's popup. Dismissal belongs only to the notification recipient; the inbox record remains available.
9. This handoff assigns Professional review of the session's referral; it does not invite the Professional into the Helper/Seeker live chat. Professional acceptance/appointment and emergency resolution remain separate actions.

No additional migration or environment setting is needed for this follow-up. Hosted popup appearance and multi-device delivery still require manual verification after deployment.

Combined verification after the selected Professional handoff: 732 PHP tests passed with 7,055 assertions; all 44 JavaScript tests passed, including popup routing, dismissal failure, and dialog/visibility deferral. Changed-file whitespace checks passed.
