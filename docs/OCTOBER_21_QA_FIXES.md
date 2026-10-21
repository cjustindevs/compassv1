# October 21 QA fixes

Implemented October 22, 2026. These changes require deployment before live validation.
No database migration or demo import is needed.

## Updated flow

Screening → Preferences → Queue → Helper acceptance → Pre-session brief → Live chat or voice → End session → Seeker evaluation / Helper notes.

- **No:** regular matching.
- **Yes:** saved High screening classification, emergency queue priority, and parallel staff safety coordination. Matching does not wait for Adviser approval.
- **Prefer not to say:** classification stays undetermined. Operational matching uses High priority and High-qualified Helpers while staff review runs separately. It is not saved as a Low or High screening answer.
- Existing blocked single-question requests resume at the remaining preference/concern step. They do not create another request.
- Resolving a safety alert does not revoke the single-question request's matching permission. Legacy complete assessments retain their existing rules.
- Adviser follow-up is available on request details and the matching page. A review cannot rewind a submitted or active request to preferences. Recording a review without approval does not falsely save approval.

## Manual validation

Use separate Seeker, Helper, Adviser, and Moderator accounts. Helpers still need verification, passed readiness, duty, qualification, consent, and capacity. Test during approved hours or the existing authorized testing configuration.

| Workflow | Clicks / trigger | Expected result |
| --- | --- | --- |
| Chat routing | Keep an old completed evaluation open, start a new request, have the Helper accept and start, then choose Open chat | The new room opens. Refresh and Back do not select the old evaluation. |
| Multiple tabs | Open an old session and a new live session in separate tabs; submit an old End/check-in action | The action cannot affect the new session. |
| Completion | End the live support session from either participant | Seeker evaluates that session; Helper opens that session's notes. Refreshing notifications does not redirect another room. |
| Cancelled request | Open a cancelled request's chat link | Request details/history opens instead of evaluation. |
| No intake | Submit No, choose preferences, find a Helper | Matching and session start work normally. |
| Yes intake | Submit Yes and choose preferences without touching the Adviser account | The request queues and can start. Staff alerts remain separate. Repeat after resolving its staff alert. |
| Undisclosed intake | Choose Prefer not to say and submit preferences without Adviser action | The request queues; a High-qualified Helper can accept. A lower-qualified Helper cannot bypass eligibility. Original answer and null classification remain saved. |
| Parallel follow-up | Adviser → Screening reviews → Send follow-up; Seeker → Request details → Send response; Adviser records review while chat is live | Response stays linked to the original intake. Chat, start time, assignment, and queue progress do not reset. |
| Empty conversation | Helper starts a session but nobody messages or connects voice | Referral and Emergency are disabled. Direct submissions are rejected too. |
| Immediate disclosure | Send one participant message, then Helper → Emergency | Emergency reporting becomes available without a minimum conversation duration. |
| Referral | Exchange messages, then Helper → Referral | Recommendation is available. Legitimate completed-session conversations can still be referred. |
| Voice conversation | Accept a voice call, then connect audio | Ringing/acceptance alone does not unlock actions. A connected call does, even without typed messages. Ending only the call does not end peer support. |
| Duty capacity | Start two sessions during a Helper's duty, completing the first before starting the second; Moderator → Schedules → Duty schedules | Displays 2/2 sessions and At Capacity. The Helper cannot receive a third duty session or two simultaneous sessions. Future duties remain Scheduled. |
| Queue reminder | Leave a request waiting for 15 minutes and allow the next scheduler tick | Moderator gets one important assignment notice with a link to the specific queue request. A resolved emergency alert does not suppress this waiting reminder. |
| Reminder retries | Refresh, retry the scheduler, read/archive the reminder, and use another tab | No duplicate reminder for the same recipient/request. A newly active Moderator can receive their own reminder. |
| Queue exit | Assign, cancel, or close the waiting request | Its waiting reminder is no longer actionable. The request's priority is not changed merely because it waited 15 minutes. |

Action buttons refresh from server state every five seconds. Queue reminders run through the existing minute scheduler, so delivery occurs on the next tick after the threshold. Failed matching offers retain the original queue entry timestamp.

## Validation completed

- 174 distinct PHP feature tests passed across routing, screening, matching, referrals, emergency handling, notification delivery, duty capacity, reconnection, and voice calls.
- 80 JavaScript tests passed, including old-session event isolation, action-button updates, timers, voice controls, and notification deduplication.
- Production assets built successfully; PHP syntax and Blade compilation checked.
- Automated database tests used isolated SQLite memory databases. These checks did not write to Neon.
- Live multi-device validation after deployment remains necessary; this run did not claim a new live audio or visual browser test.

Windows test runs must not share Blade-cache mutation concurrently with `view:cache`. A cache-file rename collision in one queue test was rechecked sequentially and passed.
