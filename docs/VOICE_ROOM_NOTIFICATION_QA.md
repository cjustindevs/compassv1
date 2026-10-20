# Voice room and notification checks - October 20, 2026

## Changes

- Voice sessions use a dedicated Seeker/Helper room. Existing chat links choose the correct room from the saved session type.
- Messages open in the same page; opening or closing them does not stop microphone capture or replace the call connection.
- Call duration and support-session time remain separate. Leave call keeps the session open. End session requires confirmation and follows existing documentation/evaluation steps.
- Helper Referral/Emergency actions and Seeker referral/identity consent remain available in the voice room. The optional condition check-in starts collapsed.
- A private socket connection has a 20-second timeout. Authenticated signal polling can prepare and connect the call independently of that socket. API requests abort after 15 seconds, including a stalled response body. Polling preserves call errors and cannot cancel microphone setup with a stale response.
- One notification bell sits in an explicit workspace header slot or an existing page header. The engine never inserts a bell into body or the page content grid. The unread action summary is inside the dropdown. Landing/authentication pages do not mount the notification engine. Minor updates stay in the inbox during voice sessions.
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

1. Render must run its existing startup migrations and asset build. Keep the current account keys and database intact. Public WSS now uses Render's current `RENDER_EXTERNAL_HOSTNAME` on port 443, and that hostname is included in Reverb's allowed origins. Internal Reverb stays on port 8080. Confirm the startup line `Voice signaling host: compassv1-lyfm.onrender.com.` and keep `APP_URL` set to the current website for generated links.
2. Start a new Seeker request, choose Voice call, and accept voice participation. The assigned Helper completes the existing duty/readiness, accepts the case, and starts the session.
3. Open the same session on two devices using the current website. Both must see the dedicated room and one header bell. Call becomes enabled after authenticated call readiness; a missed socket subscription must not block the authenticated fallback.
4. Call, accept, and allow the microphone. Verify audio in both directions, On call, elapsed time, Mute/Unmute, and optional Play audio.
5. Open Messages, send messages both ways, then close Messages. Audio must continue. Reopen Messages and verify the draft/history remain.
6. Test a blocked connection: within 20 seconds show a useful error and Retry, rather than an indefinite spinner. Retry after restoring connectivity. Deny microphone access and verify text messages remain usable.
7. Submit a Helper referral or emergency while on the call. Confirm the related staff alert, existing review flow, and Seeker consent/identity dialogs still work without ending audio. Close consent dialogs without submitting and reopen them from the support card.
8. Trigger a new important notice, dismiss with Later, then refresh, change pages, and open another tab/device. It must not pop up again. It remains unread in the inbox until opened/read. Confirm old unread history and resolved emergencies stay quiet.
9. A real new emergency report for the same active case must alert once again. Routine reminders must not keep reopening it. Resolving the case removes active urgent prompts but preserves history.
10. Leave call and start another call in the same session. Then End session and verify microphones stop, Helper documentation opens, and Seeker evaluation follows the existing process.

Desktop/mobile visual checks and real audio on Wi-Fi/mobile data require browser/device testing; a passing automated suite is not a live audio test.

## Follow-up: audio stays Connecting after acceptance

A public check of `compassv1-lyfm.onrender.com` found that its rendered broadcast configuration still named the old `compassv1.onrender.com` socket host. Call acceptance could be saved in shared Neon while the new app published negotiation packets to a different Reverb instance from the one receiving browser subscriptions. The public socket configuration now prefers the trusted current Render service hostname over copied settings; Reverb also accepts that hostname. Local socket settings remain unchanged outside Render.

Offer/answer/ICE delivery previously relied entirely on Reverb with no recovery for missed packets. The call now also retrieves its authorized incoming packets through state polling every two seconds. WebSocket and polling copies use the same packet ID and a serialized client queue, so the browser applies each signal once. Reverb delivery failure no longer discards a saved packet or holds the database session lock during a broadcast request.

The fallback stores only encrypted negotiation data, never audio, in `voice_signals`. Packets expire after two minutes, become inaccessible at expiry, and are removed by minute-based cleanup or when the call ends. Requests remain limited to the active session participants, consent checks, and relay-only audio validation. SDP keeps its final CRLF for browser compatibility. Deploy the new additive migration through the existing Render startup step; do not reset the database or run a destructive migration command.

Provider checks on October 20 confirmed active existing Metered credentials, relay allocations, and bidirectional synthetic audio over TURN/TCP and TURN/TLS. A local diagnostic using the actual JavaScript call client and native WebRTC exchanged synthetic audio with WebSockets disabled in approximately 7-8 seconds. This checks client transport behavior; it does not replace testing Safari/Chrome on two real devices. The native Windows test addon reported a shutdown failure after exchanging audio, so that diagnostic is not counted as a clean end-to-end test-suite pass.

The evaluation page now has an explicit header bell slot. The thank-you page has a separate header and a full-width content container, avoiding the flex-row squeeze in the reported screenshot. Both use smaller spacing and consistent mobile margins.

## Verification completed locally

- Full PHP regression suite: 810 passed, 7,833 assertions.
- Final focused voice checks: 20 passed, 193 assertions, including the two hostname/origin regressions added after the full suite.
- All JavaScript suites: 99 passed, including authenticated fallback, duplicate packet handling, notification placement, and media cleanup.
- Production Vite/PWA build and Blade template compilation passed.
- No connected browser was available for visual or microphone testing. The public hostname mismatch was verified directly; a socket HTTP upgrade alone does not prove private subscription or live audio. Live browser audio remains unverified until the two-device acceptance test.
- Render's existing startup migration step applies the additive `voice_signals` migration after deployment. No reset or reseeding is required.
