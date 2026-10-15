# October 15 escalation and operations revision QA

## Deployment

- This revision does not enable or change voice calls.
- Deploy the code and run `php artisan migrate --force`. The existing Render
  container entrypoint already runs main-database migrations at startup.
- The additive migration creates `referral_professional_transfers`. It does not
  replace accounts, sessions, referrals, consents, or existing history.
- Keep `RUN_SCHEDULER=true`. The new `unassigned-request-reminders` task checks
  once per minute; it must run for the 15-minute reminder to arrive.
- Keep the current mail provider configuration. This revision introduces no paid
  service. Email inbox delivery still needs verification on the hosted system.
- Existing testing-hours configuration is retained. Scheduled duty, current
  passed readiness, competency and capacity remain assignment requirements.
- Render's PHP upload limit is 5 MB per file and the request-body limit is 6 MB.
  Avatar validation retains its stricter 2 MB image/type limit. The current
  application has no S3 file-upload endpoint; this revision does not introduce one.

Use separate browser profiles/devices for Seeker, Helper, Moderator, Adviser,
and Professional. Use synthetic records. Confirm that the Helper is verified,
supervised, scheduled for today and qualified for the request's risk level.

## 1. Pre-session emergency and quick assignment

1. Seeker: open **Request Help**, complete consent and submit the existing
   positive safety-answer screening. Continue preferences and matching.
2. Moderator and active Advisers: expect a centered emergency alert on the next
   successful notification check. Staff notices poll every two seconds and do not
   wait for the scheduler's 15-minute reminder.
3. Moderator: click **Open emergency queue**. The alert opens its operational case.
   From the list, **Open / Assign Helper** opens the same coordination screen.
4. Select an eligible Helper and click **Assign Helper**.
5. Expect **Awaiting Helper acceptance**, an assignment notification to the Helper,
   and one assigned request. Automatic matching may assign before the manual
   action; in that case the staff view must display the existing assignment.
6. Helper: accept the assignment and use the existing start-session action.
7. Verify that Moderator cannot open the conversation or transcript, including
   through a copied direct session URL.

Negative checks: an unready/off-duty/unqualified Helper must not appear as an
eligible choice. A forged submission must fail. Two staff members assigning the
same request must not create two assignments or consume two Helpers.

## 2. No eligible Helper and prolonged waiting

1. Use a new emergency with no eligible Helper currently available.
2. Moderator: open its coordination screen and click **Request Adviser Escalation**.
3. Every active Adviser receives an emergency coordination notification. Repeating
   the same action does not duplicate this coordination request.
4. Adviser: open the authorized review or coordinate with the responsible reviewer.
   This revision does not create Adviser-to-Seeker chat, bypass screening or
   consent, or manufacture a Helper recommendation.
5. Leave a request unassigned for at least 15 minutes and allow the scheduler's
   next minute tick. Expect one additional centered waiting reminder to staff.
6. Verify that another scheduler tick does not repeat that waiting-period reminder.
7. Repeat with a request assigned/cancelled before the threshold: no stale reminder
   should be sent. An acknowledged emergency can still need an unassigned reminder.

If no Helper can connect, Adviser coordination remains available, but the ordinary
Professional referral continues to require the existing recommendation and consent
steps. This is not a newly added direct clinical takeover path.

## 3. Emergency during chat

1. Start a normal live chat.
2. Helper: use the existing **Emergency** action and record the reason/actions.
3. Verify centered alerts reach active Advisers and authorized Moderators, with a
   working case link. The conversation continues where the existing rules permit.
4. Adviser: record acknowledgment and coordination instructions. Merely opening
   a bell notification must not count as clinical acknowledgment or resolve it.
5. Confirm that the report, review actions and timestamps are retained.

## 4. Ordinary referral through Professional completion

1. Helper: during live chat, choose **Referral**, complete the recommendation form,
   and submit it to the Adviser.
2. Adviser: open the centered referral notification, review, and approve.
3. Seeker: accept or decline referral consent in the existing live-chat prompt.
4. If accepted, provide contact details through the separate identity-disclosure
   consent prompt. Accepting referral alone must not expose identity.
5. Adviser: open the referral. Use **Assign a professional**, select a recipient,
   enter the reason, and choose **Assign and notify Professional** if assignment
   has not already occurred through the existing automatic coordination path.
6. Professional: open the notification, schedule and accept using the existing
   appointment form, then **Start Case** when appropriate.
7. Adviser: authorize only the necessary identity fields through the existing
   identity-release form. Only the assigned authorized recipient can read them.
8. Professional: submit the completion report with outcome/follow-up information.
9. Adviser: open the centered completion-review notification. Confirm completion
   or request further action; address outstanding appointments/follow-up first.
10. Verify the Professional receives the review result. A completion submission
    alone must not silently close the referral or its linked emergency.

Also test Seeker refusal/withdrawal, Helper revision requests, double submission,
an unavailable Professional, and attempts by unrelated accounts.

## 5. Emergency referral and the restored selector

1. During an active emergency, the responsible Adviser opens **Professional handoff**.
2. If no recommendation exists, choose **Request Helper recommendation**.
3. Complete the recommendation, Adviser approval and Seeker consent/contact steps.
4. The emergency view displays the linked referral and **Raise to Professional**.
5. Choose a recipient, provide the reason, and click **Raise and notify Professional**.
6. The recipient must receive an urgent centered notice and an authorized referral
   link. Professional assignment does not grant identity access by itself.
7. While the assignment is still pending acceptance, the selector remains available
   as **Change Professional**. The referral detail provides the same action.

## 6. Pending reassignment and accepted-case transfer

### Before Professional acceptance

1. Adviser: choose a different recipient under **Change Professional**, enter the
   reason, and submit.
2. The new recipient receives a notification. The previous recipient is informed
   of the transfer and loses case access.
3. Verify retained recommendation/consent/history and reset identity-release status.
   The Adviser must authorize necessary identity fields for the new recipient.

### After Professional acceptance/start

1. Adviser: open the referral and **Transfer Professional responsibility**.
2. Choose a different available Professional, provide the reason, and submit.
3. The current Professional remains responsible and retains authorized access
   while the new recipient decides. Before acceptance, the recipient sees the
   transfer reason and reference, not private case/identity records.
4. Recipient: open the centered transfer notification and choose **Accept responsibility**
   or **Decline transfer**.
5. Acceptance changes the owner, updates future scheduled appointments, notifies
   the Adviser/Seeker/previous Professional, and requires a new identity release.
6. A conflicting appointment or appointment currently underway prevents acceptance.
7. Decline retains the original owner. Adviser can cancel a pending transfer and
   request a different recipient. Duplicate requests/responses must be rejected.
8. Pending completion review prevents transfer acceptance. Adviser confirmation
   of completion cancels outstanding transfer requests. Closed/completed referrals
   cannot be transferred through this action.

## 7. Active and Completed emergency tabs

1. Moderator: open **Emergency Alerts**. **Active** is the default tab.
2. Verify newest emergency first, eligible assignment actions, and accurate counts.
3. End the chat or acknowledge the notification: the emergency must remain Active.
4. Responsible Adviser: formally resolve the emergency with the required notes.
5. Moderator: expect it to disappear from Active, appear under **Completed**, and
   stop generating active pop-ups. Counts update without a manual page reload.
6. Open the completed record. Verify operational timestamps and assignment only;
   clinical resolution notes and transcripts remain restricted.
7. Historical completed emergencies remain available even when the same support
   session later has a distinct new emergency. That new incident must notify staff.

## 8. Readiness interval

1. Use a scheduled, verified Helper with no live assignment or exhausted capacity.
2. Submit **High stress** or another Not ready answer. Expect saved Not ready status
   and unavailable operational status.
3. Immediately retry readiness or open another tab. The page displays the next
   check time/countdown and the form is disabled; a forged POST is rejected too.
4. At 29 minutes, another submission remains blocked.
5. At 30 minutes, refresh readiness. A new valid check may be submitted, subject
   to current duty/capacity/account rules.
6. Repeat with an initially passed check. It has the same saved-check interval.
7. Invalid input/failed saves do not start a new interval. Setting Unavailable
   remains possible at any time from the existing availability controls.

## 9. Shared scheduling and workload

1. Moderator and Adviser: open **Schedules**. Both have **Duty Scheduling** and
   **Support Sessions**, recurring controls, edit/removal and the workload table.
2. Adviser sees only supervised Helpers; copied IDs for other Helpers must fail.
3. Create single and recurring duties; verify matching rows in the other authorized
   staff view, Helper dashboard and Helper notifications/email outbox.
4. Edit an occurrence and cancel one/future occurrences. Check readiness invalidation,
   retained history, duplicate-date validation and unrelated Helpers remaining intact.
5. Open an edit in two browser tabs; save one then submit the older form. The stale
   edit should ask for a refresh rather than overwriting the newer change.
6. Verify **Helper Workload** is in Duty Scheduling and removed from Active Sessions.
   Current status/assignments/capacity update every 15 seconds without resetting forms.
7. Check past archived-duty restore in Adviser history and operational session
   appointments (including unassigned appointments in the Moderator scope).

## 10. Dashboards, competency, accounts and reports

- Exercise Today/7 days/30 days/Custom/Reset filters. Historical values and trends
  use the selected Philippine dates; live counters/current account totals do not.
- Adviser **Average Adviser Response Time** uses explicit first acknowledgment or
  referral review timestamps, once per support case. Notification reads do not
  count. Check measured-case count, No data, and separate Awaiting response.
- Compare criterion breakdowns and normalized 1-5 competency values under Helper
  View Details; a legacy 82/100 score must display 4.1/5, not 82/5. Inspect the
  Adviser comparison chart and missing-evaluation states.
- Verify summaries/reflections/evaluations remain available only to authorized roles.
- Edit an Admin-managed profile and refresh/re-login. Create each staff role once;
  retrying delivery must not create another user. Verify real inbox receipt on Render.
- Apply combined Helper/category report filters, switch pages and Reset. Totals,
  records and exports must agree within the same authorized scope.
- Check desktop/mobile layout and pop-up keyboard navigation. Important action
  notices are centered and do not auto-expire; minor updates use side notices.

Automated tests use isolated SQLite test databases and synthetic fixtures. They do
not verify Neon concurrency or actual email/WebSocket delivery. Run the live
cross-role checks above after deployment before signing off QA.

## Local verification

- Full PHP suite: 764 passed, 7,454 assertions.
- JavaScript suite: 59 passed.
- Production asset build: passed.
- Hosted email/notification delivery, Neon concurrency and visual mobile QA still
  require the manual checks above.
