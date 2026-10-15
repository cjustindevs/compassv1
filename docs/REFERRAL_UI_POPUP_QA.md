# Referral UI and once-only popup checks

## Changes

- Notification popups record presentation separately from unread state. A shown
  notification does not automatically reopen after polling, navigation or reload
  in the same browser. The record is scoped to the signed-in user and shared
  across tabs through browser storage. A new notification can still appear.
- Staff emergency popups share one key per emergency case, including duplicate
  notification rows. Presentation does not resolve a case or mark it read.
  A separate new emergency case has a new key. Clearing browser storage resets
  presentation history; another browser has its own presentation history.
- Popups wait while a sensitive form or confirmation is open.
- An open referral from another session no longer blocks a Helper recommendation
  for the current session. Same-session retries still reuse the existing row, and
  a progressed referral cannot be replaced by another recommendation for that session.
- Adviser review, Seeker consent, Professional assignment and identity release
  remain separate required steps.
- Referral detail clarification, recommendation and history now share the same
  page container. Emergency review uses balanced columns, aligned header actions
  and one explanation of the current Professional handoff requirement.
- Seeker decisions show a short overview, visible Agree/Decline controls and an
  expandable full recommendation. Appointments group date, time, format, response,
  instructions and actions. The Appendix O heading text is removed.
- Referral decisions have separate forms with explicit values. Shared confirmation
  preserves named submit-button values, including Decline's `0`.

## Manual checks

1. Sign in as the responsible Adviser. Trigger a referral notification. Let its
   popup appear, then refresh or navigate away without acknowledging. It must not
   reappear automatically; the unread item must remain in the bell. Trigger a new
   referral event and verify that its popup appears once.
2. Trigger an emergency. Acknowledge its popup, refresh, and check another tab.
   Duplicate notices for that case must stay quiet. Verify the emergency remains
   active until resolved through its own review action. Create a distinct new
   emergency case and verify that it can notify.
3. Open a referral form before triggering a notification in another browser.
   The new popup must wait until the form closes, without discarding form input.
4. Leave an older session's referral open. In a new assigned session for the same
   Seeker, submit a recommendation. Expect a separate session-linked referral and
   the older referral to remain unchanged. Retry the same recommendation for the
   new session: no duplicate referral or duplicate submission notice should result.
5. On Seeker **Referral decisions**, click **Decline referral**, cancel confirmation,
   then try again and confirm. Cancellation saves nothing; confirmation records
   refusal and redirects to self-help. An open emergency review stays active.
   Use a different pending referral to test Agree and the separate identity step.
6. On Adviser **Referral Queue**, open a referral returned for clarification. Check
   that clarification and version history align with the rest of the page. Open
   **Emergency review**, check both columns, Professional selection requirements,
   and expandable screening/history. On Seeker **Referrals and Appointments**, check
   appointment confirmation/rescheduling and contact/withdrawal actions.
7. Repeat the layout checks at desktop and narrow mobile widths. Verify there is
   no horizontal page scroll, clipped action button, or content outside its card.

No database migration, reset or deletion is required for these changes.
