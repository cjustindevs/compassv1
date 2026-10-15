# Referral completion and popup QA

This update adds a separate completion review. Existing completed/closed referrals are not rewritten.

## Deployment

Run `php artisan migrate --force` with the deployed release before serving the new pages. The migration adds `referral_completion_reviews`; it does not change existing referral records. Render deployments using the existing startup migration step should run it there. No new environment variables are required. Do not use migrate:fresh or reset on the live database.

## Normal referral completion

1. Finish the existing Helper recommendation > Adviser approval > Seeker referral consent > assigned Professional acceptance/appointment flow.
2. Professional: open **Referrals > View referral** or **Active Cases > Open case**. Click **Submit completion for Adviser review**.
3. Enter an outcome summary, choose whether follow-up is needed, and submit. The referral stays accepted/in progress. A duplicate submission must not create another report or notification.
4. Assigned Adviser: open the notification or **Referral Queue > Completion reviews > Review completion**. Review the outcome and existing appointments/follow-up dates.
5. To return the case, enter instructions and click **Request follow-up**. Professional gets a notification, can document additional work and submit an updated report. The earlier report remains in history.
6. To conclude the referral, enter decision notes and click **Confirm referral completion**. Any scheduled appointments need a disposition and reason. Future appointments cannot be marked completed; cancel with a documented reason or request follow-up. Outstanding follow-up also needs a documented disposition.
7. Confirm the referral is completed, the Professional/Seeker/Helper receive status notifications, and another confirmation does not duplicate them. The original peer chat is not automatically closed.

## Emergency-linked completion

1. Use either a pre-session Yes emergency that continues queuing or an emergency raised during chat. Continue the existing staff review, professional selection, referral consent and acceptance flow.
2. Professional submits a completion report in the linked referral, as above. The assigned referral Adviser receives the completion review request. A different responsible emergency Adviser receives a separate emergency link without private report text.
3. Adviser confirms referral completion. The emergency must remain open until its responsible reviewer records the separate emergency resolution notes and resolves it.
4. Repeat with emergency resolution first: the referral must remain active until its own completion review is confirmed.
5. A pending report or a completed referral is never automatic evidence that the emergency has been resolved.

## Identity popup and Professional UI

- Seeker: accept an approved referral while staying in live chat. The identity form opens as one popup, with one header close control, an independently scrolling body, and visible footer actions.
- Enter required name/phone and identity disclosure consent; submit. Confirm validation, success, close/reopen and that a new referral can prompt separately.
- Open **View support details**, close it, then **Provide contact details**. Support details uses the outer Close button; identity uses its own close control. No nested notification bell or extra modal appears.
- Check desktop and narrow mobile widths. The Professional appointment form should use aligned date fields and a normal-sized Decline button. Referral summary cards should align at the top and wrap long content.

## Access, reassignment and stale pages

- Another Professional, unrelated Adviser, Helper, Seeker or Moderator must not submit/confirm a report they do not own.
- Withdraw referral consent before confirmation: confirmation must fail, preserving withdrawal and existing history.
- An outdated report cannot be confirmed after an updated report is submitted.
- If the referral Adviser is missing/inactive, Moderator/Admin gets an operational assignment notification. Open the assignment queue and assign an active Adviser. The queue must not show private report text.
- While completion review is pending, new Professional notes, status changes and appointments are blocked. Requesting follow-up restores continued-work actions.
- Completed records predating this update remain readable with no completion report requirement applied retroactively.

Automated coverage uses synthetic records in SQLite memory; it does not validate production email delivery or browser rendering. Browser visual QA is still required on the deployed release.
