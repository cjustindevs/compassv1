# OTP delivery on Render

The on-screen OTP came from the controller's demo branch. That branch ran when the effective MAIL_MAILER was blank, log or array, even on production. The local .env uses smtp; that file does not establish the deployed service configuration. Render environment values and Laravel's cached configuration determine the deployed mailer.

The repository Render blueprint selects the Free plan. Render Free blocks outbound ports 25, 465 and 587: https://render.com/docs/free . Gmail SMTP on port 587 therefore cannot work on that plan. SMTP credentials alone cannot remove that restriction.

## Changes
- Removed browser OTP disclosure and plaintext OTP logging.
- Reject non-delivering mailers and log/array fallbacks outside automated tests. No successful verification state is issued and the resend cooldown is released on configuration failure.
- Registration AJAX uses same-origin relative URLs, avoiding wrong HTTP/localhost URLs behind a hosting proxy.
- Added `php artisan compass:diagnose-otp`. It prints effective settings without mail credentials. `--connect` tests direct SMTP connection/authentication without sending a message.
- The blueprint now asks for a mailer choice instead of hardcoding Gmail SMTP on Free. Existing Render services still require their Environment values to be changed explicitly.

## Complete the hosted setup
1. On Render Free: select an HTTPS transactional email provider and configure its Laravel driver, API key, and verified sender. The built-in resend configuration currently requires the resend/resend-php Composer package before it can be used. A provider's test sender may restrict recipients until a domain is verified.
2. On hosting permitting SMTP: set MAIL_MAILER=smtp, MAIL_HOST=smtp.gmail.com, MAIL_PORT=587, MAIL_SCHEME=smtp, the Gmail username and valid app password, and the authorized MAIL_FROM_ADDRESS. Do not paste secrets into chat or commit them.
3. Set APP_ENV=production, APP_DEBUG=false, APP_URL to the actual HTTPS site, and SESSION_SECURE_COOKIE=true.
4. Redeploy the corrected code. The existing docker/start.sh runs config:cache at runtime. If using a shell instead, run `php artisan config:clear` then `php artisan config:cache` after environment changes. Refresh the registration page.
5. Run `php artisan compass:diagnose-otp` on the deployed service if shell access is available. Never use tinker to dump the entire mail config because it contains credentials.
6. Test Send OTP with your own inbox. A mail-server acceptance is not proof of inbox delivery; check spam and the provider delivery log.

No hosted settings were changed, no provider account was created, and no email was sent by this repair. A live delivery test requires the chosen provider's configuration on Render.
