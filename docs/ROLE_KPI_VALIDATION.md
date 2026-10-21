# Previous role-specific dashboard KPI baseline

Superseded by [October 22 dashboard QA](OCTOBER_22_DASHBOARD_QA.md).
The definitions and verification counts below describe the earlier revision,
not the current KPI implementation.

This revision reads actual COMPASS records. It does not change screening,
queue priority, consent, scheduling eligibility, or emergency/referral actions.
It adds no database migration or paid monitoring service.

## Dashboard changes

| Role | Main indicators |
| --- | --- |
| Seeker | Current waiting/request status, session status, latest approved referral, feedback due |
| Helper | Completed sessions, average competency /5, satisfaction /5, pending documentation |
| Moderator | Waiting requests, eligible available Helpers, active sessions, average waiting time |
| Adviser | Completed sessions, average Helper response time, satisfaction /5, average competency /5 |
| Professional | Pending referrals, accepted/ongoing referrals, confirmed completions, awaiting Adviser completion review |
| Administrator | Enabled accounts, current monitoring status, recorded security events, backup-provider status |

Live workload and emergencies remain independent of historical filters. Most
historical session performance uses the **activity cohort**: end time, otherwise
start time, submission time, legacy creation date, then creation timestamp.
Counts describe the selected records in their current state, not a reconstruction
of their status on a past date. Helper history includes archived sessions; Adviser
reports default to unarchived cases and expose the existing History filter.

## Definitions

- Helper response: request submission (legacy creation date) to the assigned
  Helper's first sent message. Other senders, missing/negative intervals, and
  future timestamps do not contribute.
- Waiting time: recorded queue entry to actual session start. Still-waiting
  requests do not become zero-minute samples.
- Session duration: actual start to end for completed/evaluated sessions.
- Completion rate: completed started sessions divided by started sessions in the
  same activity cohort. A cancelled session that actually started stays in the
  denominator; a never-started request does not.
- Referral rate: completed sessions with at least one recommendation divided by
  completed sessions. Multiple recommendations do not multiply a session.
- Approval rate: approved recommendations divided by reviewed recommendations;
  pending reviews are excluded.
- Satisfaction: latest recorded feedback per completed session in the cohort,
  submitted in the selected period. Valid 1–10 scores convert with
  `1 + (score - 1) * 4 / 9` to the displayed 1–5 scale.
- Average workload: completed sessions assigned to serving Helpers divided by
  Helpers with at least one started session in the activity cohort. This is not
  the live assignment-capacity table; that table remains in Schedules.
- Scheduled duty hours: active scheduled shifts overlapping the Philippine date
  range. These are planned hours, not verified attendance or hours rendered.
- Documentation completion: required ended sessions with both summary and
  reflection submitted divided by required ended sessions. This is not an
  on-time percentage. Corrections can replace submission timestamps, so the UI
  does not infer original submission punctuality from the latest record.
- Competency: valid finalized stored evaluation ratings normalized to 1–5.
  Growth requires two or more evaluations on the current rubric. The five
  assessment weights remain 25%, 25%, 20%, 20%, 10%; weights are not scores.
- Emergency rate: unique emergency-flagged sessions divided by sessions in the
  same cohort. Multiple reports/alert mirrors count once.
- Adviser response remains separate: first emergency acknowledgment or referral
  review per case. Notification reads and Helper messages do not count as an
  Adviser action.
- Empty samples show **No data**, and average/rate displays expose sample counts.

## Date filters and configuration

Today, Last 7 days, Last 30 days, All time, and Custom use Asia/Manila calendar
days. Ranges over ten years use yearly buckets in shared analytics charts. SQL boundaries are inclusive at the start and exclusive at the following
midnight; future events are excluded. Existing 90-day/year presets remain
supported for report compatibility.

Semester is disabled until the actual academic dates are configured:

```dotenv
ANALYTICS_SEMESTER_START=YYYY-MM-DD
ANALYTICS_SEMESTER_END=YYYY-MM-DD
```

Use valid real dates; do not save the placeholder values. The semester's end is
capped at today. Custom dates work without this configuration. After changing
environment configuration, redeploy/rebuild the Laravel config cache as usual.

Admin current health displays **Not monitored** while SystemHealthMonitor is a
preview adapter. It does not claim historical uptime. Backup status is **Not
configured** until BackupCatalog has real provider records. The failed-login
indicator explicitly counts recorded Admin failures; other failed logins are not
fully audited, so the UI does not claim a system-wide total. No external provider
or secrets are needed for this revision.

## Manual QA

1. Log in separately as each of the six roles. Check four main cards and the
   relevant action/status panels on desktop and a narrow mobile viewport.
2. Helper/Adviser: select the same dates, open **View records / View reports** and
   compare completed sessions, response time, satisfaction, competency and duty
   hours. In Adviser Reports, use **Helper Performance** and keep History and
   Helper filters consistent. Helper report CSV/PDF includes the shared metrics.
3. Create a request at 7:00 PM, queue entry at 7:02, session start at 7:10 and first
   Helper message at 7:05 in isolated test fixtures. Response must be 5 minutes;
   waiting must be 8 minutes. A Seeker reply at 7:01 must not change response.
4. Finish one of two started sessions: completion rate is 50%. Give that session
   two referral recommendations: session referral rate is 100%, not 200%.
5. Submit feedback and finalize a competency evaluation. Verify /5 labels,
   sample counts, and that another Helper/Adviser's unrelated records are absent.
6. Check a user with no evaluations/messages/feedback: **No data** and
   **Insufficient history** should appear. Do not infer readiness or competency
   eligibility from these dashboard values.
7. Moderator: waiting/available/active counters remain live under old date filters.
   Open the waiting-time card's Reports link and expand **Waiting time and Helper
   workload**. With the same case/priority/history filters its numbers agree.
   The operational case table retains its submission-date basis and is labeled
   separately from these activity-cohort metrics.
8. Professional: submit a completion report. **Awaiting Adviser Review** increases,
   but Completed does not. Adviser confirmation moves it to completed; emergency
   resolution still requires the separate authorized action.
9. Seeker: only that Seeker's latest approved referral/status/feedback tasks appear.
   Opening request/session links must preserve the existing screening/consent gates.
10. Check Today around Philippine midnight, All time for older records, Custom
    start/end boundaries, Reset, and Semester before/after valid configuration.
11. Admin: check recorded security/audit events against logs. Do not treat preview
    System Health data or an empty backup adapter as a production measurement.
12. Repeat normal request, emergency request, live chat, referral approval,
    disclosure, Professional completion, and Adviser resolution to verify their
    action buttons and permissions still work.

Automated tests use synthetic records and an isolated SQLite database. They do
not replace hosted Neon, email delivery, WebSocket, or visual mobile QA.

## Verification results

- Full PHP regression suite: 772 passed, 7,506 assertions.
- JavaScript suite: 59 passed.
- Production asset build: passed.
- Includes eight KPI tests for known calculations, cross-role scope, dashboard /
  report agreement, duty-hour boundaries, empty samples, competency growth,
  Philippine dates, semester/all-time selection, truthful monitoring status,
  Seeker privacy and Professional completion review.
