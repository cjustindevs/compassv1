# Moderator emergency resolution synchronization

## Deploy

Run the normal deployment migrations (`php artisan migrate --force` on Render startup). The new migration adds a nullable emergency-alert reference to notifications. No environment changes are required. No notifications, case history or clinical notes are deleted or marked read by the fix.

Historical emergency notices without a precise case reference remain in the notification center but cannot produce urgent popups. Old notices with a precise Adviser emergency link or the existing `Emergency #… requires staff attention` message are validated against the actual current case. New staff alerts and reminders carry an explicit alert ID.

## Verify with two browsers

1. Open a Moderator dashboard and Emergency Alerts page. In another browser sign in as the responsible Adviser.
2. Create an emergency through the existing pre-session Yes or ongoing-chat emergency flow. The Moderator popup, active list, sidebar badge and footer count should reflect the active case. The active list remains newest first.
3. Leave the Moderator emergency popup open. Have the Adviser resolve that exact emergency with notes. Within the next successful polling cycle (about 2 seconds plus request latency), its popup should close, its toast should disappear, and its active-list entry/count should update without a full page reload. The original chat can remain active and retain its historical risk classification.
4. Refresh, navigate away/back, or sign in again. The resolved case must not produce another urgent popup. Its original notification and resolution audit remain available as history.
5. Repeat with two active emergencies. Resolve one: only that case disappears. The other active emergency still appears and can receive attention.
6. Raise a genuinely new emergency after resolution in the same chat. It must have a new alert and trigger again. A historical resolved alert must not suppress it.
7. Dismiss a popup with multiple stored notices for the same current case. Existing duplicate popups should stop without marking the notices read. A genuinely new report/reminder can still notify.
8. Verify Professional selection/consent restrictions and that referral completion alone does not resolve the emergency. Confirm completion and emergency resolution remain separate actions.
9. Disconnect/reconnect or hide/restore the Moderator tab. Polling should pause while offline/hidden and resume when available. An unrelated sensitive form dialog must not be interrupted.

## Optional repair of old incident mirrors

The read paths already prioritize the actual emergency alert over its incident mirror. For database records that still have an open incident linked to a confirmed resolved emergency, preview:

```sh
php artisan emergencies:reconcile-resolved
```

Apply only that targeted repair:

```sh
php artisan emergencies:reconcile-resolved --apply
```

The repair preserves incident descriptions and existing resolution notes and appends an audit event. It leaves unrelated incidents and ambiguous legacy incidents in sessions with another open emergency unchanged. Running it again makes no further changes. It does not resolve any currently active emergency or complete any referral.
