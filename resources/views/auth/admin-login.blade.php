<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Admin Portal | COMPASS</title>

    <style>
        :root {
            color-scheme: light;
            --background: #f8fafc;
            --card: #ffffff;
            --border: #e7eaf0;
            --field: #f6f7fa;
            --ink: #1b1d23;
            --muted: #60636b;
            --soft: #9ba4b6;
            --green: #00a85a;
            --green-dark: #008f4d;
            --danger: #b42318;
            --danger-bg: #fff5f4;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-width: 320px;
            min-height: 100vh;
            background:
                radial-gradient(circle at 15% 85%, rgba(219, 234, 254, 0.35), transparent 34rem),
                radial-gradient(circle at 88% 8%, rgba(220, 252, 231, 0.22), transparent 31rem),
                var(--background);
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        button,
        input {
            font: inherit;
        }

        a {
            color: inherit;
        }

        .page {
            display: flex;
            min-height: 100vh;
            flex-direction: column;
            padding: 32px 28px 24px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            align-self: flex-start;
            gap: 10px;
            color: #29313a;
            text-decoration: none;
        }

        .brand-mark {
            display: grid;
            width: 36px;
            height: 36px;
            place-items: center;
            border-radius: 11px;
            background: var(--green);
            color: #fff;
            font-size: 18px;
            font-weight: 800;
            box-shadow: 0 8px 22px rgba(0, 168, 90, 0.18);
        }

        .brand-name {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.08em;
        }

        .stage {
            display: grid;
            flex: 1;
            place-items: center;
            padding: 30px 0;
        }

        .login-card {
            display: flex;
            width: min(100%, 472px);
            min-height: 650px;
            flex-direction: column;
            padding: 46px 42px 34px;
            border: 1px solid var(--border);
            border-radius: 42px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 24px 70px rgba(30, 41, 59, 0.07);
        }

        .heading {
            margin-bottom: 38px;
            text-align: center;
        }

        .heading h1 {
            margin: 0;
            font-size: clamp(26px, 3vw, 30px);
            font-weight: 800;
            letter-spacing: -0.035em;
        }

        .heading p {
            margin: 12px 0 0;
            color: var(--muted);
            font-size: 16px;
        }

        .alert {
            margin: -14px 0 22px;
            padding: 12px 14px;
            border: 1px solid #fecdca;
            border-radius: 13px;
            background: var(--danger-bg);
            color: var(--danger);
            font-size: 13px;
            line-height: 1.45;
        }

        .field-group + .field-group {
            margin-top: 26px;
        }

        .label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 10px;
        }

        label {
            font-size: 14px;
            font-weight: 700;
        }

        .forgot-link {
            color: var(--green-dark);
            font-size: 13px;
            font-weight: 650;
            text-decoration: none;
        }

        .forgot-link:hover,
        .footer a:hover {
            text-decoration: underline;
        }

        .input-shell {
            position: relative;
        }

        .input-icon {
            position: absolute;
            top: 50%;
            left: 16px;
            width: 20px;
            height: 20px;
            color: var(--soft);
            pointer-events: none;
            transform: translateY(-50%);
        }

        .input-shell input {
            width: 100%;
            height: 61px;
            padding: 0 48px;
            border: 1px solid #eaecf0;
            border-radius: 17px;
            outline: none;
            background: var(--field);
            color: var(--ink);
            font-size: 16px;
            transition: border-color 150ms ease, box-shadow 150ms ease, background 150ms ease;
        }

        .input-shell input::placeholder {
            color: var(--soft);
        }

        .input-shell input:focus {
            border-color: rgba(0, 168, 90, 0.65);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(0, 168, 90, 0.1);
        }

        .input-shell.has-error input {
            border-color: #f04438;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 13px;
            display: grid;
            width: 36px;
            height: 36px;
            padding: 0;
            place-items: center;
            border: 0;
            border-radius: 9px;
            background: transparent;
            color: var(--soft);
            cursor: pointer;
            transform: translateY(-50%);
        }

        .password-toggle:hover,
        .password-toggle:focus-visible {
            background: #eceff3;
            color: #697386;
            outline: none;
        }

        .password-toggle svg {
            width: 20px;
            height: 20px;
        }

        .field-error {
            margin: 8px 2px 0;
            color: var(--danger);
            font-size: 12px;
        }

        .remember-row {
            display: flex;
            align-items: center;
            gap: 11px;
            margin: 24px 0 26px;
            color: #4e5158;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .remember-row input {
            width: 21px;
            height: 21px;
            margin: 0;
            border: 1px solid #d8dde6;
            border-radius: 6px;
            accent-color: var(--green);
        }

        .submit-button {
            width: 100%;
            min-height: 48px;
            border: 0;
            border-radius: 999px;
            background: var(--green);
            box-shadow: 0 12px 28px rgba(0, 168, 90, 0.2);
            color: #fff;
            cursor: pointer;
            font-size: 16px;
            font-weight: 750;
            transition: background 150ms ease, box-shadow 150ms ease, transform 150ms ease;
        }

        .submit-button:hover {
            background: var(--green-dark);
            box-shadow: 0 14px 32px rgba(0, 143, 77, 0.25);
            transform: translateY(-1px);
        }

        .submit-button:active {
            transform: translateY(0);
        }

        .admin-note {
            margin: auto 0 0;
            padding-top: 42px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.55;
            text-align: center;
        }

        .admin-note strong {
            color: var(--green-dark);
            font-weight: 700;
        }

        .footer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 24px;
            min-height: 30px;
            color: #92969d;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .footer a {
            text-decoration: none;
        }

        .operational {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: #1fc76a;
            box-shadow: 0 0 0 3px rgba(31, 199, 106, 0.1);
        }

        .separator {
            width: 1px;
            height: 14px;
            background: #d6d9df;
        }

        @media (max-width: 640px) {
            .page {
                padding: 20px 16px;
            }

            .brand {
                align-self: center;
            }

            .stage {
                padding: 28px 0;
            }

            .login-card {
                min-height: 0;
                padding: 36px 24px 30px;
                border-radius: 30px;
            }

            .heading {
                margin-bottom: 30px;
            }

            .admin-note {
                padding-top: 50px;
            }

            .footer {
                flex-wrap: wrap;
                gap: 10px 14px;
                text-align: center;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <a class="brand" href="{{ route('home') }}" aria-label="Return to the COMPASS home page">
            <span class="brand-mark" aria-hidden="true">C</span>
            <span class="brand-name">COMPASS</span>
        </a>

        <main class="stage">
            <section class="login-card" aria-labelledby="admin-login-title">
                <header class="heading">
                    <h1 id="admin-login-title">Admin Portal</h1>
                    <p>Please enter your details to continue</p>
                </header>

                @if ($errors->any())
                    <div class="alert" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.login.store') }}">
                    @csrf

                    <div class="field-group">
                        <div class="label-row">
                            <label for="username">Username</label>
                        </div>
                        <div class="input-shell @error('username') has-error @enderror">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4.8 20a7.2 7.2 0 0 1 14.4 0H4.8Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <input
                                id="username"
                                name="username"
                                type="text"
                                value="{{ old('username') }}"
                                placeholder="Username or email"
                                autocomplete="username"
                                required
                                autofocus
                            >
                        </div>
                        @error('username')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field-group">
                        <div class="label-row">
                            <label for="password">Password</label>
                            <a class="forgot-link" href="{{ route('password.request') }}">Forgot Password?</a>
                        </div>
                        <div class="input-shell @error('password') has-error @enderror">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M7.5 10V7.5a4.5 4.5 0 1 1 9 0V10M6 10h12v10H6V10Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                            >
                            <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false" data-password-toggle>
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M2.8 12s3.3-5 9.2-5 9.2 5 9.2 5-3.3 5-9.2 5-9.2-5-9.2-5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="12" cy="12" r="2.4" stroke="currentColor" stroke-width="1.8"/>
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="remember-row" for="remember">
                        <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                        <span>Remember me for 30 days</span>
                    </label>

                    <button class="submit-button" type="submit">Log In</button>
                </form>

                <p class="admin-note">
                    Restricted to <strong>authorized system administrators</strong>.<br>
                    All access attempts may be recorded for security.
                </p>
            </section>
        </main>

        <footer class="footer" aria-label="Portal status and support links">
            <span class="operational"><span class="status-dot" aria-hidden="true"></span>System operational</span>
            <span class="separator" aria-hidden="true"></span>
            <a href="{{ route('home') }}#contact">Privacy policy</a>
            <span class="separator" aria-hidden="true"></span>
            <a href="{{ route('home') }}#contact">Contact support</a>
        </footer>
    </div>

    <script>
        const passwordInput = document.getElementById('password');
        const passwordToggle = document.querySelector('[data-password-toggle]');

        passwordToggle?.addEventListener('click', () => {
            const passwordIsVisible = passwordInput.type === 'text';
            passwordInput.type = passwordIsVisible ? 'password' : 'text';
            passwordToggle.setAttribute('aria-label', passwordIsVisible ? 'Show password' : 'Hide password');
            passwordToggle.setAttribute('aria-pressed', String(!passwordIsVisible));
        });
    </script>
</body>
</html>
