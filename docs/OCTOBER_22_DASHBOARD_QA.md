# October 22 dashboard, KPI and session UI revision

This consolidated implementation covers all six roles and all nine dashboard/UI
requests. Definitions are prepared for panel review; no external approval or
clinical outcome is claimed. No migration, seeder or demo data is added.

## Main indicators

| Role | Main numeric indicators |
| --- | --- |
| Administrator | Registered accounts, enabled accounts, new registrations, support requests, completed sessions, recorded security events |
| Moderator | Waiting requests, eligible available Helpers, active sessions, active emergencies, average waiting time, successful assignment rate |
| Adviser | Completed sessions, average waiting time, Helper first response, positive helpfulness feedback, competency /5, documentation completion |
| Helper | Completed sessions, competency /5, positive helpfulness feedback, documentation completion |
| Seeker | Completed sessions, confirmed upcoming appointments, referral decisions needed, feedback due |
| Professional | Pending referrals, active cases, upcoming appointments, awaiting Adviser review, confirmed completions, acceptance time |

Live workload/tasks remain independent of historical filters. Role scoping and
existing consent/Professional authorization remain enforced. Cards emphasize
numbers with short labels, definitions and sample sizes. Empty measurements show
**—**; real zero counts and measured zero durations stay zero. Rates with an empty
denominator never become 0%. Historical statuses are current records, not a
reconstructed snapshot of a past date.

## Definitions and evidence

1. **Completed sessions:** non-test, non-duplicate completed/evaluated sessions
   with actual start and valid end in the period. Never-started, missing-end,
   cancelled and future-ended records do not qualify.
2. **Waiting:** queue entry to actual start, selected by session start date.
   Ongoing/negative/missing waits are excluded. Moderator queue performance keeps
   outside-hours/cut-off requests separate; maximum wait uses the same cohort.
3. **Helper first response:** session start to earliest nonempty assigned Helper
   message or connected voice call. Pre-start, Seeker, future and after-end events,
   notification reads, ringing and acceptance without connection are excluded.
   The cohort uses session start date.
4. **Positive helpfulness:** Helpful/Very Helpful answers divided by valid original
   helpfulness answers, x100, selected by feedback submission. Latest feedback per
   concluded session counts once. Legacy composite scores cannot recreate the
   original answer and are excluded. This is reported helpfulness, not a clinical
   improvement measure.
5. **Feedback coverage:** sessions completed in the period with feedback recorded
   by the current cutoff divided by valid sessions completed in the period.
6. **Competency:** finalized current-rubric evaluations by evaluation date. The
   linked completed/evaluated session and reviewed report must agree with the
   evidence; each criterion is integer 1–5 and the weighted overall must agree.
   Corrections count once per report. KPI, comparison and report share
   `CompetencyEvidence`. Legacy/other-rubric history remains separate. Weights
   remain 25%, 25%, 20%, 20%, 10%. Growth needs two evaluations at different times.
7. **Documentation completion:** required ended sessions with both summary and
   reflection submitted divided by required ended sessions, x100. A valid start
   and end in the period are required. This is not an on-time rate.
8. **Session completion rate:** sessions started in the period with a valid
   completed/evaluated end by cutoff divided by sessions started in the period.
   A started cancellation remains in the denominator; recent starts may be active.
9. **Referral rate:** completed sessions with a recommendation divided by completed
   sessions. Multiple recommendations do not multiply a session. Approval rate is
   approved divided by reviewed recommendations, by review date; pending excluded.
10. **Assignment success:** final accepted eligible assignment with readiness/duty
    evidence and a scheduled/active/served session divided by requests processed
    through regular matching. Emergency, test and duplicate requests are excluded;
    pending and legacy records without processing evidence are disclosed separately.
11. **Utilization:** active-session time intersecting verified duty intervals
    divided by verified available duty time, x100. Overlaps merge before summing.
    Scheduled hours alone do not prove attendance.
12. **Emergency acknowledgment:** identification to recorded authorized acknowledgment
    with an actor; selected by identification date. Unacknowledged/invalid cases
    are reported separately. It does not measure external emergency-service arrival.
    Referral review time is separate and selected by review date.
13. **Professional acceptance:** notification to recorded acceptance, by acceptance
    date. Missing, negative and future intervals are excluded.
14. **Confirmed completions:** completed/closed referrals with a recorded Adviser
    confirmation and reviewer, selected by confirmation date. Submission alone
    remains awaiting review. Dashboard, completion chart, filtered table and CSV
    share this event. Legacy finished statuses without confirmation stay visible
    in received/history records and are disclosed separately.

Today, Last 7/30 days, All time and Custom use Asia/Manila calendar dates, with an
inclusive start and exclusive following midnight. Future events are excluded.
Semester needs real `ANALYTICS_SEMESTER_START`/`ANALYTICS_SEMESTER_END` dates.
Helper history includes archives. Adviser reports retain their History filter;
valid comparison evidence is selected by evaluation date, including archives.
Legacy converted experience scores remain separately defined for existing report
consumers; they are not the new helpfulness KPI.

## Layout, comparison and capacity

- Shared app content owns the 28px desktop / 16px mobile sidebar gutter. Nested
  Adviser pages do not add a second padding layer.
- Prominent numeric cards use consistent spacing and tabular numbers. Live tasks
  and current duty stay separate from filtered historical metrics.
- Compact horizontal comparison bars use a fixed 0–5 scale, numeric scores and
  evaluation counts. Details/evidence expand on demand. One evaluation is labelled
  limited evidence. Training links appear for a recorded skill below 2.5 or existing
  training. Perfect scores do not automatically prompt training.
- Shared capacity shows duty sessions x/2 and live assignments x/1, with At Capacity
  at the duty ceiling. Existing duty/readiness/matching methods remain authoritative.
- The destructive confirmation has explicit visibility, contrast and touch size.
  Voice uses the original End session form in a mobile bottom action area with
  safe-area spacing. Chat places its original form near the composer. There is one
  form/confirmation per action. Leaving a call still differs from ending the session.

## Intake review and self-help

Navigation uses the original **Screening Reviews** label and explains assigned screening
follow-ups versus Helper-requested reassessments. The original Yes/Prefer not to
say flow continues matching in parallel. An assigned active Adviser can document
Complete screening review with a note; this clears the task and creates a review/audit
event without replacing answers, unknown risk, classification, queue or session
progress. It does not resolve emergencies, approve referrals or disclose identity.
It cannot close a Helper-requested reassessment. Repeated completion is idempotent.
Existing follow-up/reassessment actions retain their own validation and access.

Opening self-help records access, not completion. Articles use Mark as read and
other resources Mark complete. Repeating completion keeps the first timestamp.
Old manual percentages remain stored but display Previously opened/in progress;
the arbitrary slider and percentage bars are removed. The compatibility progress
endpoint remains. Grounding counts actual completed steps out of five; breathing
retains exercise rounds and journaling retains its saved-entry workflow.

## Validation and release

Local PostgreSQL contains 17 sessions and lacks some existing queue-evidence
columns. It is not deployed Neon; no migration was run on it. Synthetic tests use
a freshly migrated isolated SQLite database. A read-only PostgreSQL CTE verifies
confirmation aggregation and Philippine bucket conversion. These checks cannot
validate current Neon records. Missing/inconsistent evidence reduces samples
instead of being fabricated. No browser is connected for visual QA in this run.

Before live acceptance:

1. Compare all six roles against authorized records and identical report dates,
   history and Helper filters; check n, genuine zero and missing denominators.
2. Test midnight-spanning sessions: completion by end, wait/response by start;
   reject future, test, duplicate and invalid intervals.
3. Verify pre-start/empty messages and ringing calls do not become response
   samples, while real Helper replies and connected audio do.
4. Compare categorical feedback with legacy composites; verify correction-once
   competency evidence, one-evaluation labels and training decisions.
5. Compare Adviser/Moderator capacity at 0/2, 1/2, 2/2 and one current assignment.
6. Complete a parallel intake twice; preserve answer/risk/progress and reject
   another Adviser or a Helper-requested reassessment.
7. Repeat request, matching, assignment, pre-session brief, text/voice session,
   referral consent, Professional completion confirmation and emergency resolution.
8. On desktop/phone, open/cancel/confirm End session. Check button visibility,
   safe areas, keyboard, composer, messages drawer and call leave versus session end.
9. Open self-help without completing, complete twice, check stable timestamp,
   legacy activity, grounding steps, breathing rounds and saved journal entries.
10. Check 320–430px widths, long names, collapsed/expanded sidebar, keyboard
    navigation and empty dashboards. After push/deployment, verify Neon counts
    and live phone/laptop audio. No new deployment is performed by this task.

## Verified results

- 830 backend tests verified with no remaining failures: the final full run passed
  829, followed by all 23 Professional tests passing after updating the legacy
  completion fixture to require actual Adviser confirmation. Production code did
  not change after that full run.
- 80 JavaScript tests passed.
- Production asset build passed.
- All Blade templates compiled successfully; changed PHP files passed syntax checks.
- `git diff --check` passed for application, views, assets, tests and documentation.
- Browser/phone visual checks, two-device live audio, and deployed Neon data
  reconciliation remain release checks. No push/deployment occurred in this task.
