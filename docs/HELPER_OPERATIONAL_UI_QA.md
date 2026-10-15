# Helper availability and review UI checks

This update clarifies the existing maximum of **2 started sessions per duty**
and **1 current assignment at a time**. It does not introduce two simultaneous
chats, change escalation decisions, or change consent and identity release rules.
No database migration or production data import is required.

Use separate browser profiles for Moderator, Helper, Seeker, and the responsible
Adviser. Use test accounts with a verified Helper, an active primary Adviser,
an applicable duty, and current Seeker consent. Existing testing-hours settings
remain in effect.

## 1. Workload and duty capacity

1. Moderator: open **Schedules**, then **Helper Workload**.
2. With more than 10 Helpers, use page 2. Search a name and filter by status.
3. Expect at most 10 rows, accurate totals, sessions used out of 2, remaining
   capacity, and a separate current-assignment indicator.
4. Leave a duty form partly filled while workload refreshes. Its input must remain.
5. Complete one session during the duty: remaining capacity becomes 1. Complete
   a second: it becomes 0 and another assignment/readiness submission is blocked.
6. Adviser: open **Schedule**. Only supervised Helpers appear; Moderator still
   sees the overall workload. Workload pagination does not truncate duty selectors.

## 2. Readiness and queue choices

1. Helper: open **Readiness Check** with an applicable duty and unused capacity.
2. Start the breathing exercise. There is no Skip button. Closing midway does
   not complete it. Finish all 3 rounds, click **Continue**, then submit readiness.
3. Low/moderate stress and other affirmative answers permit availability. Negative
   answers still save Not ready after breathing. The 30-minute retry interval stays.
4. Moderator: open **Incoming Queue** with a waiting request. A qualified, ready
   Helper with capacity appears in its selection. Eligibility is request-specific.
5. Change the Helper's availability in another browser. The selection updates
   within the next successful 5-second poll. An empty list explains why.
6. Select a Helper and wait: polling retains that choice while eligible and leaves
   the select alone while focused. Assignment rechecks eligibility on the server.
7. A future appointment must not block readiness today. A reservation within
   90 minutes blocks another assignment that could overlap it; an active chat or
   an offer due within the briefing window still blocks another readiness check.

## 3. Request-specific pre-session brief

1. Seeker: submit a request with an identifiable test concern and description.
2. Helper: accept/open that request's **Pre-Session Brief**.
3. Expect its concern, description and other concern, displayed read-only.
4. A later request from the same Seeker must not replace this brief's information.
   Missing linked data shows an explicit placeholder; other Helpers cannot open it.

## 4. Notifications and Adviser review

1. Check Seeker Home and Admin pages: there should be one top-right bell.
2. Helper: send a referral recommendation through the existing chat workflow.
3. Responsible Adviser: stay on another page. Expect a centered referral notice
   on the next successful notification update (2-second polling fallback).
4. Open another dialog first: the notice waits and appears after that dialog closes.
   Navigating away before acknowledgement must not permanently hide an unread notice.
5. **Acknowledge** only marks the notice read; it does not approve or resolve a case.
6. Open **Emergency review**: Professional selection stays visible. When blocked,
   its explanation identifies approval, consent, contact submission, ownership,
   or concluded-case restrictions. Use the linked referral to finish those steps.
7. Once existing handoff requirements are met, choose an available Professional,
   enter the reason, and use **Raise and notify Professional**. Accepted cases use
   the existing transfer action in the linked referral, preserving recipient acceptance.
8. Secondary documentation, original screening and history expand on demand.
   History shows latest first in Philippine Time. Check desktop and mobile widths.

The broader emergency/referral escalation workflow revision remains deferred.
Email delivery, WebSocket connectivity and the deployed browser layout still need
verification in the hosted environment; local automated tests do not prove those.
