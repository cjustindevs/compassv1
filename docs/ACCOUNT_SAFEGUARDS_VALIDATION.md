# Account safeguards: deployment and manual validation

These changes preserve nickname sign-in, role permissions, case assignment,
readiness, screening review, session documentation, and the existing Laravel
password-reset token system. They change account recovery and account security.

## Deployment

1. Deploy the changed PHP, Blade, JavaScript, and migration files together.
2. In the project root, run `php artisan migrate --force` on the deployment
   environment if the deployment startup has not already applied migrations.
   The new migration is
   `2026_10_12_000002_add_private_recovery_email_to_users.php`.
3. Build frontend assets with the existing `npm run build` deployment step.
4. Use the existing Resend API key and verified sender configuration. No new
   provider, API key, or package is required for recovery mail.
5. Existing API tokens require a fresh login after this update. Untracked tokens
   are rejected; the newly issued tokens participate in the device-session rule.

Existing Seekers have no recovery email to backfill. They must sign in and verify
an address under **Account Settings → Private recovery email**. A Seeker who has
already lost access and never linked an email still requires institutional
ownership review; this update does not guess an address or bypass verification.

## Manual validation

Use test accounts and an inbox you control. Do not post OTP codes, reset links,
API tokens, or private addresses in shared logs or screenshots.

### New Seeker registration and recovery

1. Register a Seeker and verify an email through the existing OTP process.
2. Finish registration. Confirm the nickname and request workflow still work.
3. Sign out, open **Forgot password**, and enter the verified private email.
4. Check inbox and spam. Open the reset link. The prefilled sign-in address should
   be the nickname address, not the private delivery email.
5. Set a new password. Sign in using the same nickname and new password.
6. Try the used reset link again. It must fail. The old password must fail too.
7. Check the Helper's case view and account/API responses. The private recovery
   address must not appear.

### Existing Seeker recovery setup

1. Sign in to an existing Seeker account and open Account Settings.
2. Enter an email and an incorrect current password. Sending must fail.
3. Enter the correct password, send a code, and verify it. The address must only
   become linked after successful verification; the nickname must stay unchanged.
4. Confirm an incorrect, expired, or already-used code cannot save an address.
   Three incorrect attempts require a new code.
5. Sign out and complete the same Forgot password test using the linked email.
6. Replace the recovery address through the same password-and-OTP process.
   Previously issued reset links must become invalid, and future links must go
   to the replacement address.
7. Verify that an address already linked to another account cannot be reused.

### Password-reset privacy and staff compatibility

1. Request a link for a known account, an unknown address, and repeat a request
   during the cooldown. The public confirmation must be the same.
2. Verify a staff account still receives its reset link at its account email and
   can sign in with the new password.
3. Verify an inactive account does not receive a reset link and remains inactive.

### Staff account closure and managed profile fields

1. Open Account Settings/Profile as each staff role. Account closure should direct
   the user to Administrator deactivation instead of self-deletion.
2. Attempting either self-delete endpoint with a staff account must fail without
   removing its account, assignments, or records.
3. Verify existing Administrator deactivation still enforces its own-account,
   last-active-Administrator, and active-Helper assignment restrictions.
4. Open Helper Account Settings. Email must be read-only, with a link to the
   permitted Helper profile. Direct shared profile/account update requests must
   not alter the Helper's managed fields. Password changes should still work.

### Login attempts and web/API sessions

1. Enter incorrect passwords using a Seeker nickname and its equivalent internal
   email. They must share the same failed-attempt limit, including the supported
   `@compasslocal` spelling. Different accounts must retain separate limits.
2. Sign in through the browser, then try API login for the same account. The
   second device grant must be refused. Test API-first/browser-second too.
3. Call `POST /api/logout` with a test bearer token. Subsequent requests using
   that token must return 401; a new login must work.
4. Change or reset the password, then test the old API token. It must return 401.
5. Deactivate and reactivate a test account using Administrator controls. The old
   API token must stay invalid after reactivation.

## Automated validation

Run `php vendor/phpunit/phpunit/phpunit --do-not-cache-result` for PHP coverage.
Run each `tests/js/*.test.cjs` file with Node, then run the frontend build.
Automated tests use an isolated SQLite database and mocked email delivery; they
do not confirm actual Render deployment or inbox delivery. Complete the manual
checks on the deployed application before calling live validation complete.
