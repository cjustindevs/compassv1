# COMPASS audio calls — free demo setup

Voice is disabled by default. Code installation alone does not enable calling. These steps configure the existing Render service and a Metered TURN free account; no second Render service or paid telephony account is needed.

## Re-enable the existing Render and Metered setup (October 20)

The installed feature already uses WebRTC audio, Laravel Reverb signaling, and Metered TURN. Reuse the existing Render service and Metered account. Preserve the existing Reverb application credentials, Metered developer secret, Laravel `APP_KEY`, database settings, and mail settings.

In **Render → COMPASS service → Environment**, confirm:

```dotenv
APP_URL=https://compassv1-lyfm.onrender.com
VOICE_CALLS_ENABLED=true
RUN_REVERB=true
RUN_SCHEDULER=true
BROADCAST_CONNECTION=reverb

REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS=compassv1-lyfm.onrender.com

VOICE_WS_HOST=compassv1-lyfm.onrender.com
VOICE_WS_PORT=443
VOICE_WS_SCHEME=https
```

Keep `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `METERED_DOMAIN`, and `METERED_SECRET_KEY` populated with the values already configured for these accounts. `METERED_DOMAIN` is the Metered project hostname, not the Render hostname. Add any currently used custom frontend hostname to the allowed-origins list. Do not add another set of keys or put private secrets in `VITE_*` variables.

On Render, the app now uses the service's `RENDER_EXTERNAL_HOSTNAME` for public secure WebSockets and adds it to Reverb's allowed origins. Render supplies this hostname through its [default environment variables](https://render.com/docs/environment-variables). This prevents a copied environment from connecting the new website to the old service's socket. Keep `APP_URL` current for generated links. Startup and `voice:status` print a non-secret `Voice signaling host` line so the selected hostname can be checked. Outside Render, the explicit `VOICE_WS_*` settings still apply.

Save and deploy the environment changes. Look for `Starting Reverb on internal port 8080` and successful `Voice relay credentials prepared` startup messages. Allow two minutes for newly prepared TURN credentials before choosing Voice in Preferences. If Shell is available, `php artisan voice:status` should report `ready`. A working WebSocket alone does not prove TURN readiness or bidirectional audio.

Start a **new** Seeker request, select **Voice call** in Preferences, and accept live-audio participation. Existing Chat requests remain Chat requests. The Helper must have duty and readiness, accept the assignment, and start the session. Open the same session on two devices, then **Call → Accept call → allow microphone**. Verify Ringing, Connecting, On call, Mute, and Leave call using the live acceptance checklist below.

The local `.env` is separate from Render Environment. Enabling a local flag or editing `.env.example` does not update the deployed service.

References: [Render public WebSockets use WSS and the service's shared port](https://render.com/docs/websocket), [Reverb server and public connection settings](https://laravel.com/docs/12.x/reverb), [Metered expiring credentials](https://www.metered.ca/docs/turnserver-guides/expiring-turn-credentials/).

## 1. Create the free TURN account

1. Open https://www.metered.ca/stun-turn/ and choose the free TURN trial. At the time of implementation it advertises 500 MB per month (incoming plus outgoing traffic) and no credit card requirement.
2. Confirm the free allowance in your own dashboard. Do not select an upgrade, add a payment method, or enable automatic top-up for this demo.
3. In **Developers**, copy your Metered domain (for example `your-project.metered.live`) and **Secret key**. The integration needs the developer secret, not the API key of an individual TURN credential.
4. Keep those values private. Put the secret directly into Render Environment; do not paste it into chat, commit it, or put it in a `VITE_*` variable.

References: [free-plan pricing](https://www.metered.ca/stun-turn/), [developer domain and secret](https://www.metered.ca/docs/turnserver-guides/expiring-turn-credentials/).

## 2. Generate Reverb credentials

In the VS Code terminal, run each command separately:

```powershell
php -r "echo bin2hex(random_bytes(16)), PHP_EOL;"
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Use the first output as `REVERB_APP_KEY` and the second as `REVERB_APP_SECRET`. The app key is public; the app secret stays server-side. If PHP is not on PATH, use `& 'C:\xampp\php\php.exe' -r ...` instead.

Keep the existing Laravel `APP_KEY` unchanged. It encrypts account data and the temporary TURN credential pool.

## 3. Add Render environment variables

Open the existing COMPASS service → **Environment**. Add or update these entries. Replace the example domain and placeholders with your actual values. Do not create duplicate entries.

```dotenv
APP_URL=https://compassv1-lyfm.onrender.com
VOICE_CALLS_ENABLED=true
RUN_REVERB=true
RUN_SCHEDULER=true
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=compass-voice
REVERB_APP_KEY=FIRST_GENERATED_VALUE
REVERB_APP_SECRET=SECOND_GENERATED_VALUE
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS=YOUR_SERVICE.onrender.com

VOICE_WS_HOST=YOUR_SERVICE.onrender.com
VOICE_WS_PORT=443
VOICE_WS_SCHEME=https

METERED_DOMAIN=YOUR_PROJECT.metered.live
METERED_SECRET_KEY=YOUR_PRIVATE_DEVELOPER_SECRET
```

- `REVERB_HOST/PORT/SCHEME` are the **internal** Laravel → Reverb connection. Internal HTTP on port 8080 is intentional.
- `VOICE_WS_*` is the **public** browser → Render connection. Public clients use WSS on port 443. Never use localhost here on Render.
- `REVERB_ALLOWED_ORIGINS` contains hostnames, without `https://` or paths. Add any real custom frontend hostname as a comma-separated value.
- The browser reads the public socket configuration from rendered HTML. These steps do not require new `VITE_*` variables or exposing secrets in frontend builds.
- Preserve `APP_URL=https://YOUR_SERVICE.onrender.com` and the existing database, session, mail, and identity-vault settings.

## 4. Deploy and wait for relay preparation

After the code is pushed and the variables are saved, deploy the updated Docker service. Its existing startup migration step applies:

`database/migrations/2026_10_13_000001_create_voice_call_tables.php`

Startup launches Reverb, prepares expiring TURN credentials, and keeps the existing scheduler running. The scheduler checks preparation every five minutes and rotates the pool approximately every fifteen minutes. Credentials expire after two hours and are encrypted in the database.

**Wait at least two minutes after the first successful preparation.** Metered documents that new credentials can take up to two minutes to propagate. Preferences shows Voice in a disabled state until a usable pool exists; chat remains available.

Look for these messages in Render logs:

- `Starting Reverb on internal port 8080.`
- `Voice relay credentials prepared. Newly created credentials need two minutes to propagate.`

If preparation fails, the web app still starts and keeps text chat available. Check the domain, developer secret, free-account API access, and remaining quota. The command never prints secrets or provider response bodies.

Preferences always shows Text chat and Voice call. Voice remains disabled until the relay is usable; during propagation it asks you to refresh in two minutes. Seeing the Voice option does not establish that audio is working.

When voice remains unavailable, search startup logs for `Voice relay readiness` and `Voice relay preparation failed`. Readiness codes distinguish `disabled`, `configuration_missing`, `migration_missing`, `preparing`, `relay_unavailable`, and `ready`. Configuration diagnostics list only environment variable names. Provider failures show only the preparation step and HTTP status, never keys or response bodies. If dashboard shell access is available, `php artisan voice:status` gives the same safe readiness check.

If a shell is available, you can run `php artisan voice:prepare` to retry. Render free services may not offer dashboard shell access; the automatic startup and scheduler steps handle preparation without it.

Reference: [Metered credential propagation](https://www.metered.ca/docs/turn-rest-api/post-create-credential/), [Render WebSockets](https://render.com/docs/websocket).

## 5. Local setup, if needed

For local testing only, add the same credentials and voice flag to your local `.env`, with:

```dotenv
VOICE_WS_HOST=127.0.0.1
VOICE_WS_PORT=8080
VOICE_WS_SCHEME=http
REVERB_ALLOWED_ORIGINS=127.0.0.1,localhost
```

Then run in the project terminal:

```powershell
php artisan migrate --path=database/migrations/2026_10_13_000001_create_voice_call_tables.php
php artisan config:clear
npm run build
php artisan voice:prepare
```

Run `php artisan reverb:start --host=0.0.0.0 --port=8080` in another terminal and `php artisan schedule:work` in another. Use localhost/127.0.0.1 for microphone testing; a plain HTTP LAN-IP page is not a secure browser context. Two-device testing is easiest on Render's HTTPS site.

## 6. Live acceptance test

Use separate Seeker and Helper accounts on two devices. Use test conversations, not sensitive real cases.

1. Seeker completes the existing screening/review process. In Session Preferences, choose **Voice call** and accept **live audio participation**. Recording/transcription consent is not requested.
2. Helper follows the existing readiness, duty, assignment acceptance, and Start Session process. Open the session room on both devices.
3. Verify the room has voice controls and working text chat. Only click Call after the connection is ready.
4. Caller selects **Call** and allows microphone access. Recipient selects **Accept call** and allows microphone access. The status must say Connected only after the browser's actual connection succeeds.
   The panel shows Ringing for the caller, Incoming call for the recipient, Connecting while establishing audio, and On call with elapsed time once the local audio connection succeeds. Reconnecting is separate from On call. Decline/Leave hides the timer and releases the microphone.
5. Confirm audio works in both directions. Use **Play audio** if the browser blocks automatic playback. Headphones help prevent feedback.
6. Test Mute/Unmute, Decline, unanswered-call timeout, Leave Call, and another call attempt. Leaving/declining must keep the same active support session, timer, and text chat.
7. Test with one device on Wi-Fi and the other on mobile data. Test your target Android/iPhone browsers; calls require the session page to remain open and browser background suspension can interrupt them.
8. Deny microphone permission and verify the message explains how to retry or continue typing. Switch the network briefly and check reconnection/fallback.
9. End the support session using the existing **End** action. Both microphones must stop. Helper documentation and Seeker evaluation must follow the existing workflow. Retries must not duplicate completion notifications.
10. Check that unrelated users cannot join the call. A second tab must not keep a disconnected call alive. Inspect the microphone indicator after leaving the page.
11. Check Metered usage after a short call before doing longer tests. The implementation uses relay-only audio for participant network privacy, so all audio consumes TURN allowance.

Optional browser developer checks: the public socket uses `wss://YOUR_SERVICE.onrender.com/app/...`; authorization uses `/broadcasting/auth`; voice requests use `/api/voice/...`. SDP and ICE are sent only through authenticated signaling, never stored as chat messages or call recordings. Temporary TURN credentials necessarily reach the browser; master secrets must not.

## 7. Free limits and troubleshooting

- Render free services sleep after fifteen minutes without incoming traffic and share a monthly allowance of 750 instance hours per workspace. A cold start can delay opening the app. Open both pages and let them finish loading before calling. Do not add a paid instance just for this demo.
- Metered advertises 500 MB monthly on its free trial. This is a bandwidth allowance, not a guaranteed number of calls or hours. The app does not purchase plans or top-ups, and its counters are not a billing cap. Your provider dashboard is the source of truth for usage and billing controls.
- If the provider rejects credentials, expires the trial, or requires payment for the needed API access, leave Voice disabled and tell the project team. Do not treat STUN-only connectivity as a reliable replacement or silently remove the relay privacy setting.
- A WebSocket error usually means `RUN_REVERB`, the public hostname/port, allowed origins, or matching app credentials are wrong. A relay failure with working WebSockets points toward TURN preparation, credentials, account limits, or the network.
- Voice is browser-to-browser only. This feature does not dial telephone numbers. No audio recording, storage, or automatic audio transcription is implemented. Call metadata is stored separately from session completion; typed fallback messages use the existing text transcript rules.
- To disable new audio calls, set `VOICE_CALLS_ENABLED=false` and redeploy. Existing session rooms retain text chat. Recheck connected clients after redeployment; calls stop when state polling reports the disabled feature or ended session.

Reference: [Render free limits](https://render.com/docs/free).

## Voice room and notification update

Voice sessions now open a dedicated room with an in-page Messages drawer, separate call/session controls, and retained referral, emergency, consent, and identity actions. A missed WebSocket connection can recover through authenticated two-second polling; encrypted negotiation packets expire after two minutes and are deleted when the call ends. No audio is stored. Initial socket setup has a 20-second timeout and Retry; API requests have a 15-second timeout. Historical notifications stay in the inbox without being replayed as popups, and the unread action summary stays inside the header bell's dropdown. See [the updated acceptance checklist](VOICE_ROOM_NOTIFICATION_QA.md).

## Earlier verification status (before this update)

Automated checks cover call ownership, private signaling, consent, stale attempts, relay-only/audio-only validation, credential encryption/propagation, microphone cleanup, call-vs-session separation, and existing chat/session regressions. A successful automated test or build does **not** establish real network audio quality. Live two-device verification awaits your free account configuration.

Passed during implementation: the full PHP regression run (691 tests / 6,512 assertions), final voice/session lifecycle checks after the call-metadata cleanup (56 tests / 742 assertions), all 23 JavaScript tests, the production Vite/PWA build, and the normalized Render startup script's shell syntax check. The Docker/Nginx runtime and actual browser audio connection still need verification on Render after configuration.
