# October 15 workflow and privacy QA

Use separate browser profiles or devices for Seeker, Helper, Adviser, Moderator, and Professional. Use test accounts and simulated concerns. Deploy only after approval; no new migration or environment variable is required by this change.

## Preparation

- Schedule and verify a Helper, complete readiness, and keep the Helper signed in.
- Keep the responsible Adviser and a Moderator on visible, online pages. A blocking dialog must be closed before an emergency popup can open.
- Keep existing testing duty-hour settings; this update does not change them.

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
3. Adviser and active Moderator receive an unread notice and emergency popup. An empty sidebar badge becomes visible after the next feed update.
4. Both feed and emergency polling check about every five seconds on an active, online page. Popups wait for other dialogs to close.
5. Seeker continues Preferences, priority queuing, and matching without waiting for Adviser approval.
6. Assigned Helper receives the assignment plus an urgent safety notice. If their supervisor differs from the intake reviewer, that supervisor receives an authorized coordination link too.
7. Adviser opens Emergencies: original screening summary is visible and read-only. An inactive intake reviewer falls back to an active Adviser.
8. Repeat submission does not create another intake emergency or duplicate alerts. Confirm ordinary requests still match normally.

## 4. Prefer not to say and immutable screening

1. Seeker chooses Prefer not to say. The existing review hold stays in effect.
2. Adviser sends the existing one-question follow-up. Seeker responds on their waiting page.
3. Adviser reviews the saved response; original answers cannot be replaced by an Adviser or by a crafted form request.
4. A saved Yes/No response allows the existing review decision flow. A repeated Prefer not to say remains pending; no invented negative answer is recorded.
5. Classification confirmation and Adviser review notes are separate from the original Seeker response. Emergency actions remain in Emergencies.

## 5. Emergency during chat

1. Start an accepted Helper/Seeker chat. Helper submits an emergency report.
2. Adviser and Moderator receive notices; Adviser sees the case under Emergencies and can acknowledge and document coordination/instructions.
3. Helper sees the emergency state and Adviser updates. Moderator sees operational status only.
4. Repeat the same open report: no duplicate incident or alert. A new report after resolution creates a new active alert.
5. Where an intake alert already exists, a new chat report still creates a fresh staff notification and requires review.

## 6. Referral during chat and emergency handoff

1. Helper recommends referral. Adviser receives it, reviews it, and may return it for revision.
2. Helper revises/resubmits; approval cannot bypass an unanswered revision request.
3. After Adviser approval, Seeker accepts or declines referral consent.
4. Decline closes the ordinary referral flow. Accept leads to the existing identity/contact step.
5. Professional assignment and private access wait for the required consent and identity steps. If no Professional is available, the referral remains visibly pending.
6. Assigned Professional receives the referral, can review it and record the appropriate response. Another Professional cannot open it.
7. For a linked emergency, the Professional sees the emergency reference. Neither creating the referral nor accepting it automatically resolves the emergency.
8. Withdraw consent and verify further access/disclosure is blocked. Repeated clicks must not duplicate referrals.

## Verification limits

Automated HTTP tests cover database state, role authorization, recipient selection, polling endpoints, consent gates, and duplicate handling. JavaScript tests cover browser-side confirmation, live notices, and chat dialog behavior. Actual popup appearance, two-device delivery, and hosted realtime transport still need the manual checks above.

## Completed automated checks

- Full PHP suite: 720 tests passed, 6,764 assertions.
- JavaScript suite: 32 tests passed.
- Changed-file whitespace check passed.
- Hosted browser appearance and cross-device notifications still require the manual checks above.
