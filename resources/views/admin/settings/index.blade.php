<x-admin.layout
    title="Settings"
    page-title="Settings"
    page-subtitle="Personal workspace and account security"
    active-nav="settings"
    search-placeholder="Search sessions, helpers, resources, users..."
    :admin="$admin"
>
    <section
        class="admin-settings-page"
        data-settings-page
        data-settings-preference-url="{{ route('admin.settings.preference.update') }}"
        data-settings-default-theme="{{ $admin->dark_mode ? 'dark' : 'light' }}"
    >
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <span>COMPASS</span><span aria-hidden="true">/</span><strong>Settings</strong>
        </nav>

        <header class="settings-page-heading">
            <h1>Settings</h1>
            <p>Personalize your workspace and safeguard your account.</p>
        </header>

        @if (session('status') === 'password-updated')
            <div class="admin-flash admin-flash-success" role="status">
                <x-admin.icon name="check-circle" :size="18" />
                Password updated successfully.
            </div>
        @endif

        <div class="settings-layout">
            <nav class="settings-section-navigation" aria-label="Settings sections" data-settings-navigation>
                <a class="is-active" href="#settings-appearance" data-settings-nav="settings-appearance" aria-current="location">
                    <x-admin.icon name="sun" :size="19" /> Appearance
                </a>
                <a href="#settings-notifications" data-settings-nav="settings-notifications">
                    <x-admin.icon name="bell" :size="19" /> Notifications
                </a>
                <a href="#settings-privacy" data-settings-nav="settings-privacy">
                    <x-admin.icon name="shield" :size="19" /> Privacy
                </a>
                <a href="#settings-security" data-settings-nav="settings-security">
                    <x-admin.icon name="lock" :size="19" /> Security
                </a>
                <a href="#settings-language" data-settings-nav="settings-language">
                    <x-admin.icon name="globe" :size="19" /> Language
                </a>
            </nav>

            <div class="settings-content">
                <section class="settings-card" id="settings-appearance" tabindex="-1" data-settings-section>
                    <header><h2>Appearance</h2></header>

                    <div class="settings-row settings-row-theme">
                        <div class="settings-row-copy">
                            <strong>Theme</strong>
                            <span>Choose light, dark, or match system.</span>
                        </div>
                        <div class="settings-segmented" role="radiogroup" aria-label="Theme preference">
                            <button type="button" role="radio" aria-checked="true" data-theme-choice="light">
                                <x-admin.icon name="sun" :size="15" /> Light
                            </button>
                            <button type="button" role="radio" aria-checked="false" data-theme-choice="dark">
                                <x-admin.icon name="moon" :size="15" /> Dark
                            </button>
                            <button type="button" role="radio" aria-checked="false" data-theme-choice="system">
                                <x-admin.icon name="monitor" :size="15" /> System
                            </button>
                        </div>
                    </div>

                    <div class="settings-row">
                        <div class="settings-row-copy">
                            <strong>Accent color</strong>
                            <span>Applies across dashboards and highlights.</span>
                        </div>
                        <div class="settings-accent-options" role="radiogroup" aria-label="Accent color">
                            @foreach ([
                                'green' => ['Green', '#12b76a'],
                                'cyan' => ['Cyan blue', '#2aa8ef'],
                                'mint' => ['Secondary green', '#3cc568'],
                                'orange' => ['Orange', '#f79009'],
                                'red' => ['Red', '#e93e5b'],
                            ] as $value => [$label, $color])
                                <button
                                    type="button"
                                    role="radio"
                                    aria-checked="{{ $value === 'green' ? 'true' : 'false' }}"
                                    aria-label="{{ $label }} accent"
                                    title="{{ $label }} accent"
                                    style="--settings-swatch: {{ $color }}"
                                    data-accent-choice="{{ $value }}"
                                >
                                    <span aria-hidden="true"></span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="settings-row">
                        <div class="settings-row-copy">
                            <strong>Reduced motion</strong>
                            <span>Disable non-essential animations.</span>
                        </div>
                        <x-admin.settings-switch setting="reduced_motion" label="Reduced motion" storage="local" />
                    </div>
                </section>

                <section class="settings-card" id="settings-notifications" tabindex="-1" data-settings-section>
                    <header><h2>Notifications</h2></header>

                    <div class="settings-row">
                        <div class="settings-row-copy">
                            <strong>Email notifications</strong>
                            <span>Assignments, evaluations, and emergency alerts.</span>
                        </div>
                        <x-admin.settings-switch
                            setting="email_notifications"
                            label="Email notifications"
                            storage="backend"
                            :checked="$admin->email_notifications"
                        />
                    </div>

                    <div class="settings-row">
                        <div class="settings-row-copy">
                            <strong>In-app sounds</strong>
                            <span>Play a soft chime on new messages.</span>
                        </div>
                        <x-admin.settings-switch setting="in_app_sounds" label="In-app sounds" storage="local" :checked="true" />
                    </div>

                    <div class="settings-row">
                        <div class="settings-row-copy">
                            <strong>Emergency SMS</strong>
                            <span>Text me when an emergency case is flagged.</span>
                            <small>No verified administrator phone or SMS provider is configured.</small>
                        </div>
                        <div class="settings-row-action">
                            <span class="settings-unavailable-badge">Unavailable</span>
                            <x-admin.settings-switch
                                setting="emergency_sms"
                                label="Emergency SMS"
                                storage="unavailable"
                                :disabled="! $emergencySmsAvailable"
                            />
                        </div>
                    </div>
                </section>

                <section class="settings-card" id="settings-privacy" tabindex="-1" data-settings-section>
                    <header><h2>Privacy</h2></header>

                    <div class="settings-row">
                        <div class="settings-row-copy">
                            <strong>Profile email visibility</strong>
                            <span>Allow your email to appear where account access permits.</span>
                        </div>
                        <x-admin.settings-switch
                            setting="show_email"
                            label="Profile email visibility"
                            storage="backend"
                            :checked="$admin->show_email"
                        />
                    </div>

                    <div class="settings-row">
                        <div class="settings-row-copy">
                            <strong>Anonymized analytics</strong>
                            <span>Allow anonymized account activity to improve COMPASS.</span>
                        </div>
                        <x-admin.settings-switch
                            setting="allow_data_research"
                            label="Anonymized analytics participation"
                            storage="backend"
                            :checked="$admin->allow_data_research"
                        />
                    </div>

                    <div class="settings-policy-note" role="note">
                        <x-admin.icon name="shield" :size="18" />
                        <p>Required audit, security, emergency, and institutional records are governed by system policy and cannot be disabled here.</p>
                    </div>
                </section>

                <section class="settings-card" id="settings-security" tabindex="-1" data-settings-section>
                    <header><h2>Security</h2></header>

                    <div class="settings-row">
                        <div class="settings-row-copy">
                            <strong>Password</strong>
                            <span>{{ $passwordChangedLabel }}</span>
                        </div>
                        <button class="admin-button admin-button-secondary settings-compact-button" type="button" data-dialog-open="change-admin-password-dialog">
                            Change
                        </button>
                    </div>

                    <div class="settings-row">
                        <div class="settings-row-copy">
                            <strong>Two-factor authentication</strong>
                            <span>Adds a code from your authenticator app.</span>
                            <small>An authenticator-app 2FA backend is not installed.</small>
                        </div>
                        <div class="settings-row-action">
                            <span class="settings-unavailable-badge">Not configured</span>
                            <x-admin.settings-switch
                                setting="two_factor_authentication"
                                label="Two-factor authentication"
                                storage="unavailable"
                                :disabled="! $twoFactorAvailable"
                            />
                        </div>
                    </div>

                    <div class="settings-row">
                        <div class="settings-row-copy">
                            <strong>Active sessions</strong>
                            <span>
                                You are signed in on {{ $activeSessions->count() }}
                                {{ Str::plural('device', $activeSessions->count()) }}.
                            </span>
                            @if ($sessionLoadFailed)
                                <small>Other session records are temporarily unavailable.</small>
                            @endif
                        </div>
                        <button class="admin-button admin-button-secondary settings-compact-button" type="button" data-dialog-open="active-admin-sessions-dialog">
                            Review
                        </button>
                    </div>
                </section>

                <section class="settings-card" id="settings-language" tabindex="-1" data-settings-section>
                    <header><h2>Language</h2></header>

                    <div class="settings-row">
                        <div class="settings-row-copy">
                            <strong>Interface language</strong>
                            <span>Language used for administrator navigation and controls.</span>
                            <small>Additional translations have not been installed.</small>
                        </div>
                        <label class="admin-field settings-language-control">
                            <span class="sr-only">Interface language</span>
                            <select disabled>
                                <option selected>{{ $interfaceLanguage }}</option>
                            </select>
                        </label>
                    </div>
                </section>
            </div>
        </div>

        <div class="admin-toast is-warning" data-settings-toast role="alert" aria-live="assertive" hidden>
            <span class="admin-toast-icon"><x-admin.icon name="alert-circle" :size="19" /></span>
            <span data-settings-toast-message></span>
        </div>
    </section>

    <x-admin.dialog
        id="change-admin-password-dialog"
        title="Change password"
        description="Use your current password to securely choose a new one."
        :open-on-load="$errors->updatePassword->isNotEmpty()"
    >
        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            @method('PUT')
            <div class="admin-dialog-body">
                @if ($errors->updatePassword->isNotEmpty())
                    <div class="settings-form-errors" role="alert">
                        <strong>Unable to update password</strong>
                        <ul>
                            @foreach ($errors->updatePassword->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <label class="admin-field">
                    <span>Current password</span>
                    <input name="current_password" type="password" autocomplete="current-password" required>
                </label>
                <label class="admin-field">
                    <span>New password</span>
                    <input name="password" type="password" autocomplete="new-password" required>
                </label>
                <label class="admin-field">
                    <span>Confirm new password</span>
                    <input name="password_confirmation" type="password" autocomplete="new-password" required>
                </label>
            </div>
            <footer class="admin-dialog-footer">
                <button class="admin-button admin-button-secondary" type="button" data-dialog-close>Cancel</button>
                <button class="admin-button admin-button-primary" type="submit">Update password</button>
            </footer>
        </form>
    </x-admin.dialog>

    <x-admin.dialog
        id="active-admin-sessions-dialog"
        title="Active sessions"
        description="Devices currently associated with your COMPASS account."
    >
        <div class="admin-dialog-body active-session-list">
            @if ($sessionLoadFailed)
                <div class="settings-session-warning" role="alert">
                    <x-admin.icon name="alert-circle" :size="18" />
                    Other session records could not be loaded. Your current session is shown below.
                </div>
            @endif

            @foreach ($activeSessions as $session)
                <article class="active-session-row">
                    <span class="active-session-icon" aria-hidden="true"><x-admin.icon name="monitor" :size="20" /></span>
                    <div>
                        <strong>{{ $session['device'] }}</strong>
                        <time datetime="{{ $session['lastActiveIso'] }}" title="{{ $session['lastActiveTitle'] }}">
                            {{ $session['lastActiveLabel'] }}
                        </time>
                    </div>
                    @if ($session['current'])
                        <span class="current-session-badge">Current session</span>
                    @else
                        <span class="settings-unavailable-badge">Read only</span>
                    @endif
                </article>
            @endforeach

            <div class="report-operation-note" role="note">
                <x-admin.icon name="lock" :size="18" />
                <p>Session revocation is not implemented. No Sign out control is shown until a protected backend action exists.</p>
            </div>
        </div>
        <footer class="admin-dialog-footer">
            <button class="admin-button admin-button-primary" type="button" data-dialog-close>Done</button>
        </footer>
    </x-admin.dialog>
</x-admin.layout>
