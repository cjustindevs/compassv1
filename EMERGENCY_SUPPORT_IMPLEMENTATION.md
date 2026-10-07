# Emergency temporary support integration

Emergency screening now opens a priority support queue alongside the independent emergency alert. Helpers remain subject to the existing competency-level-4, readiness, active supervision, duty, conflict, and capacity checks. Existing RELAX_DUTY_HOURS behavior still applies in configured test environments; emergency override cannot bypass checks.

Helper offers, acceptance, and screening review remain separate. Declines and expired offers preserve the emergency and exclude the previous Helper from future matching for that request. Moderators receive connection-failure coordination notifications. Open emergencies are protected from stale-request cancellation and queue removal.

The Seeker can view emergency review status on /emergency and connection status on /request/matching. Adviser screening review remains accessible as the connection state changes. An existing active chat is preserved when an emergency is raised. Professional referral acceptance does not itself close the chat.

One additive migration adds counseling_sessions.support_emergency_alert_id, linking replacement chats to the original emergency without moving or duplicating its review history. Run php artisan migrate --force during deployment. No production changes or deployment performed.

Validation: 67 targeted tests passed (953 assertions); Blade compilation and emergency route listing passed. Frontend build checked separately.

Timed backup reminders are implemented through the existing scheduler. EMERGENCY_REMINDER_MINUTES defaults to 0 (disabled); set it to the institution-approved interval. Immediate alerts remain enabled. Reminders go to active Moderators and the assigned active Adviser, are deduplicated, and stop after acknowledgment or resolution. Emergency replacement uses the existing Seeker choice, Moderator offer, and Helper acceptance workflow, preserving the original review and excluding old messages. Professional acceptance does not automatically close peer chat. No historical queue backfill was added. Browser/mobile manual verification remains required.

Manual verification: submit Emergency screening; confirm Adviser review and Moderator alert; with an eligible level-4 Helper confirm offer/accept/start; decline another offer and verify another eligible Helper is considered; with none available verify open review and status/resources remain; confirm a different Seeker cannot see the case; verify professional acceptance leaves peer chat intact.

## Completion checks
Emergency reminder tests verify opt-in configuration, deduplication, and stopping after acknowledgment. Reconnection tests verify the original alert/Adviser is retained, old messages are excluded, and a declined replacement is not reoffered. Timed reminder configuration is optional; immediate emergency alerts do not depend on it. Migration has only been exercised against the isolated test database, not the user's application database.

Latest targeted verification: 70 tests passed, 973 assertions. Four outdated moderator escalation/duty fixtures were corrected to honor existing authorization and logged-in readiness rules; all four passed (36 assertions). The earlier full run found six failures: these four plus two old emergency-no-queue expectations. Do not represent that earlier run as a clean full suite.
