# Voice room and notification checks - October 20, 2026

## Changes

- Voice sessions use a dedicated Seeker/Helper room. Existing chat links choose the correct room from the saved session type.
- Messages open in the same page; opening or closing them does not stop microphone capture or replace the call connection.
- Call duration and support-session time remain separate. Leave call keeps the session open. End session requires confirmation and follows existing documentation/evaluation steps.
- Helper Referral/Emergency actions and Seeker referral/identity consent remain available in the voice room. The optional condition check-in starts collapsed.
- Initial signaling waits at most 20 seconds before showing Retry. API requests abort after 15 seconds, including a stalled response body. Polling preserves authorization/transport errors and cannot cancel microphone setup with a stale response.
- One notification bell sits in the workspace header. Landing/authentication pages do not mount the notification engine. Minor updates stay in the inbox during voice sessions.
- Popups require an authenticated server claim. A recipient/event can be presented once across tabs, devices, and hostnames. Important fresh events use the centered dialog; minor events use side toasts outside the voice room.
- Notices older than five minutes stay in history without automatic popups. Old unread action updates remain linked from the workspace header/inbox.
- The additive migration marks existing notices as already presented and records historical delivery keys. It preserves notification records, unread status, referrals, and emergencies. Emergency reminders do not replay an already presented incident; a genuinely new in-chat emergency report has its own event key.
- Later dismisses the popup without marking it read or completing a case. Opening a notification marks only that notification read. Emergency alert dismissal does not acknowledge or resolve the business case.

## Local automated checks

Use the existing test configuration with SQLite in memory. Never load online Neon credentials for these commands.

```powershell
php artisan test --compact
php artisan view:cache
npm run build
```

Run each file in `tests/js` directly with Node. Coverage includes private voice-channel authorization, both role views, microphone cleanup, initial subscription timeout, Retry, late responses, server popup ownership/deduplication/freshness, historical migration preservation, emergency resolution, consent, and message-drawer behavior.

## Live acceptance after deployment

1. Render must run its existing startup migrations and asset build. Keep the current account keys and database intact. Confirm `APP_URL`, `VOICE_WS_HOST`, and `REVERB_ALLOWED_ORIGINS` use the actual deployment hostname; public WSS uses port 443 and internal Reverb stays on port 8080.
2. Start a new Seeker request, choose Voice call, and accept voice participation. The assigned Helper completes the existing duty/readiness, accepts the case, and starts the session.
3. Open the same session on two devices. Both must see the dedicated room and one header bell. Call becomes enabled only after the private subscription succeeds.
4. Call, accept, and allow the microphone. Verify audio in both directions, On call, elapsed time, Mute/Unmute, and optional Play audio.
5. Open Messages, send messages both ways, then close Messages. Audio must continue. Reopen Messages and verify the draft/history remain.
6. Test a blocked connection: within 20 seconds show a useful error and Retry, rather than an indefinite spinner. Retry after restoring connectivity. Deny microphone access and verify text messages remain usable.
7. Submit a Helper referral or emergency while on the call. Confirm the related staff alert, existing review flow, and Seeker consent/identity dialogs still work without ending audio. Close consent dialogs without submitting and reopen them from the support card.
8. Trigger a new important notice, dismiss with Later, then refresh, change pages, and open another tab/device. It must not pop up again. It remains unread in the inbox until opened/read. Confirm old unread history and resolved emergencies stay quiet.
9. A real new emergency report for the same active case must alert once again. Routine reminders must not keep reopening it. Resolving the case removes active urgent prompts but preserves history.
10. Leave call and start another call in the same session. Then End session and verify microphones stop, Helper documentation opens, and Seeker evaluation follows the existing process.

Desktop/mobile visual checks and real audio on Wi-Fi/mobile data require browser/device testing; a passing automated suite is not a live audio test.

## Verification completed locally

- Full PHP regression suite: 806 passed, 7,800 assertions.
- Final focused voice, popup delivery, and emergency resolution checks: 28 passed, 268 assertions (includes a new completion-notification regression added after the full run).
- All JavaScript suites: 94 passed; the affected voice/dialog suites also passed again after final transport changes.
- Production Vite/PWA build and Blade template compilation passed.
- No connected browser was available for visual or microphone testing. A public live-site diagnostic timed out; it did not verify a current WebSocket connection. Live audio remains unverified until the two-device acceptance test.
- Changes remain local until committed, pushed, and deployed. The new notification migration runs through the existing Render startup migration step after deployment.
