# OTP delivery on Render Free: Resend HTTPS API

Production registration OTP now calls `POST https://api.resend.com/emails` through Laravel's HTTP client. It does not use SMTP, a different SMTP port, or Laravel's SDK-based Resend transport. No Composer dependency is needed. Other Laravel Mail users remain unchanged.

## Render Environment

```env
APP_ENV=production
APP_DEBUG=false
OTP_DEMO_MODE=false
OTP_DELIVERY_DRIVER=resend
MAIL_FROM_ADDRESS=otp@projectcompass.help
MAIL_FROM_NAME="COMPASS Support"
```

Add `RESEND_API_KEY` securely in Render Environment with your actual key. The examples contain only a blank placeholder. Do not paste keys into source, chat or screenshots. Previously exposed credentials should be revoked and replaced in the provider's dashboard.

OTP does not require `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION` or `MAIL_URL`. Remove obsolete Resend SMTP settings from Render if nothing else uses them. Preserve settings needed by unrelated Laravel Mail notifications. Production forces the HTTPS OTP path even if an old `MAIL_MAILER=smtp` or `OTP_DELIVERY_DRIVER=laravel` remains.

## Flow and security

- Existing six-digit random generation, ten-minute expiry, hash-only session storage, three verification attempts, single-use verification and 60-second resend cooldown remain.
- Existing `emails.otp` Blade content and subject are reused. The from address/name come from `config/mail.php`; the API key already comes from `config/services.php`.
- A HTTPS 2xx response with a nonempty message ID is required before storing the replacement OTP hash. A provider handoff is not proof of inbox delivery.
- Failed API attempts return the existing friendly 503, release the resend cooldown, and preserve an earlier valid code/verification. They never disclose a new code or fall back to demo or SMTP.
- Requests have a five-second connection and fifteen-second total timeout, certificate verification, redirects disabled, and an idempotency header. There is no automatic API retry to multiply ambiguous sends. Existing local/testing Laravel mail retries remain available explicitly.
- Production cannot display demo OTPs. Explicit demos remain local/testing only. The modal's current loading, disabled-button state and countdown remain; layout is unchanged.
- Safe diagnostic logs include HTTP status and controlled reason categories (authentication, permission/sender, provider limit, unavailable, rejected, malformed). Network errors record exception class only. Raw API response bodies, exception messages, recipients, OTPs and keys are never logged by this service.

## Original error

The error originated in `OTPController::sendOTP()`'s mail-transport exception handler. Previously Laravel Mail attempted SMTP, and connection failures produced the friendly message. Render Free blocks the common SMTP ports, so the earlier production SMTP setup was incompatible. The precise prior hosted exception was not supplied for this update. The new path uses HTTPS independently of all SMTP settings.

References: [Resend send-email API](https://resend.com/docs/api-reference/emails/send-email), [Render Free limitations](https://render.com/docs/free).

## Deploy and test (no Render Shell needed)

1. Set the environment values and API key in Render; redeploy the updated code.
2. Existing `docker/start.sh` runs `php artisan config:cache` and `php artisan view:cache` at startup. No migration, new package installation or manual Shell command is required.
3. Refresh registration and request one code to an inbox you control. Confirm the loading state, provider acceptance in Resend, inbox/spam delivery, successful verification and account creation.
4. Confirm a wrong/expired code fails, verified codes cannot be reused, and the resend countdown still works. If API delivery fails, consult only the sanitized service log and Resend dashboard; do not enable production debug.
5. Optional Shell command: `php artisan compass:diagnose-otp` reports key/sender presence without secrets. Its `--connect` option is SMTP-only and does not probe/send through the API.

Remaining delivery dependencies: valid sending key with permission for the verified domain, correct sender environment values, HTTPS/DNS connectivity, available provider quota, and recipient delivery/spam policies. No hosted credentials or real recipient were provided, so automated tests mock the API; live inbox delivery remains unverified.

## Files in this transition

- `app/Services/ResendOtpMail.php`: bounded HTTPS request, reused template, acceptance checks and safe diagnostics.
- `app/Http/Controllers/Auth/OTPController.php`: production HTTPS dispatch; existing OTP verification/storage and friendly error handling preserved.
- `app/Services/OtpMailConfiguration.php`, `config/otp.php`: production routing and explicit local/testing compatibility.
- `app/Console/Commands/DiagnoseOtpMail.php`: safe API configuration diagnostics.
- `.env.example`, `.env.render.example`, `render.yaml`: secret-free HTTPS configuration.
- `tests/Feature/Auth/ResendOtpTest.php`, `RegistrationTest.php`: HTTPS issuance, production privacy, errors, malformed responses, network failures and security regression tests.
- The previous uncommitted registration-view change keeps the small loading message and production demo notice guard. This replaces the prior SMTP proposal, not unrelated project work.
