@extends('layouts.app')

@section('title', 'COMPASS – Settings')

@push('styles')
    <meta name="user-id" content="{{ auth()->id() }}">
    @vite(['resources/js/moderator-notifications.js'])
    <style>
        .settings-flash { border-radius: 12px; padding: 12px 16px; font-size: 14px; font-weight: 500; margin-bottom: 16px; background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; }
        .settings-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 24px; }
        .tab { min-height: 44px; padding: 0 20px; border-radius: 999px; font-size: 15px; font-weight: 600; color: var(--compass-muted, #6B7280); background: var(--bg-card, #fff); border: 1px solid var(--compass-border, #E5E7EB); cursor: pointer; }
        .tab.active { background: #EAF8F0; border-color: var(--compass-green, #04A052); color: #027039; }
        .settings-grid { display: grid; grid-template-columns: 1fr; gap: 16px; margin-bottom: 16px; }
        @media (min-width: 769px) { .settings-grid.two { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .form-label { display: block; font-weight: 600; font-size: 14px; color: var(--compass-text, #374151); margin-bottom: 6px; }
        .form-input { width: 100%; min-height: 44px; padding: 0 14px; border-radius: 12px; border: 1px solid var(--compass-border, #E5E7EB); font-size: 15px; background: var(--bg-card, #fff); }
        .form-input:focus { outline: 2px solid var(--compass-green, #04A052); outline-offset: 1px; }
        .form-input.error { border-color: #DC2626; }
        .error-text { font-size: 13px; color: #DC2626; margin-top: 4px; }
        .toggle { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .toggle .slider { position: absolute; cursor: pointer; inset: 0; background: var(--compass-border, #E5E7EB); border-radius: 24px; transition: 0.3s; }
        .toggle .slider:before { content: ""; position: absolute; height: 18px; width: 18px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: 0.3s; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
        .toggle input:checked + .slider { background: var(--compass-green, #04A052); }
        .toggle input:checked + .slider:before { transform: translateX(20px); }
        .toggle input:focus-visible + .slider { outline: 2px solid var(--compass-green, #04A052); outline-offset: 2px; }
        .pref-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 0; border-bottom: 1px solid var(--compass-divider, #F3F4F6); }
        .pref-row p { margin: 0; }
        .pref-row .pref-label { font-size: 15px; font-weight: 600; color: var(--compass-text, #374151); }
        .pref-row .pref-desc { font-size: 13px; color: var(--compass-muted, #6B7280); }
        .radio-group { display: flex; gap: 10px; }
        .radio-option { flex: 1; border: 1px solid var(--compass-border, #E5E7EB); border-radius: 14px; padding: 12px; text-align: center; cursor: pointer; font-size: 14px; font-weight: 600; color: var(--compass-muted, #6B7280); }
        .radio-option.selected { border-color: var(--compass-green, #04A052); background: #EAF8F0; color: #027039; }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('#tabBar .tab').forEach(function (tab) {
                tab.addEventListener('click', function () {
                    document.querySelectorAll('#tabBar .tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
                    this.classList.add('active');
                    document.getElementById('panel-' + this.dataset.tab).style.display = '';
                });
            });

            document.querySelectorAll('#fontSizeGroup .radio-option').forEach(function (option) {
                option.addEventListener('click', function () {
                    document.querySelectorAll('#fontSizeGroup .radio-option').forEach(o => o.classList.remove('selected'));
                    this.classList.add('selected');
                    document.getElementById('fontSizeInput').value = this.dataset.size;
                });
            });

            document.documentElement.classList.add('js-save-bar');
            // Reveal each form's sticky save bar once one of its fields changes.
            document.querySelectorAll('form[data-settings-form]').forEach(function (form) {
                const bar = form.querySelector('[data-save-bar]');
                if (!bar) return;
                const reveal = () => bar.classList.add('is-visible');
                form.addEventListener('input', reveal);
                form.addEventListener('change', reveal);
            });
        });
    </script>
@endpush

@section('content')
    <x-ui.page-header title="Settings" description="Manage your account and preferences" />

    @if(session('success'))
        <div class="settings-flash" role="status"> {{ session('success') }}</div>
    @endif

    <div class="settings-tabs" id="tabBar">
        <button type="button" class="tab active" data-tab="profile">Profile</button>
        <button type="button" class="tab" data-tab="password">Password</button>
        <button type="button" class="tab" data-tab="appearance">Appearance</button>
        <button type="button" class="tab" data-tab="notifications">Notifications</button>
    </div>

    <x-ui.card title="Profile Information" class="tab-panel" id="panel-profile">
        <form method="POST" data-settings-form action="{{ route('moderator.settings.profile') }}">
                @csrf
                @method('PUT')
                <div class="settings-grid two">
                    <div>
                        <label class="form-label">Display Name</label>
                        <input type="text" name="name" class="form-input {{ $errors->has('name') ? 'error' : '' }}" value="{{ old('name', $user->name) }}" required>
                        @error('name') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input {{ $errors->has('email') ? 'error' : '' }}" value="{{ old('email', $user->email) }}" required>
                        @error('email') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" class="form-input" value="{{ old('first_name', $moderator->first_name) }}" required>
                    </div>
                    <div>
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" class="form-input" value="{{ old('last_name', $moderator->last_name) }}" required>
                    </div>
                    <div>
                        <label class="form-label">Assigned Shift</label>
                        <input type="text" name="assigned_shift" class="form-input" value="{{ old('assigned_shift', $moderator->assigned_shift) }}" placeholder="e.g. Morning Shift">
                    </div>
                </div>
                <div class="ui-save-bar{{ $errors->any() ? ' is-visible' : '' }}" data-save-bar><p class="ui-save-bar-text">You have unsaved changes.</p><button type="submit" class="btn-primary"><x-ui-icon name="save" class="mr-1" /> Save Profile</button></div>
            </form>
    </x-ui.card>

    <x-ui.card title="Change Password" class="tab-panel" id="panel-password" style="display: none;">
        <form method="POST" data-settings-form action="{{ route('moderator.settings.password') }}">
                @csrf
                @method('PUT')
                <div class="settings-grid">
                    <div>
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-input {{ $errors->has('current_password') ? 'error' : '' }}" required>
                        @error('current_password') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-input {{ $errors->has('new_password') ? 'error' : '' }}" required>
                        @error('new_password') <p class="error-text">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="new_password_confirmation" class="form-input" required>
                    </div>
                </div>
                <div class="ui-save-bar{{ $errors->any() ? ' is-visible' : '' }}" data-save-bar><p class="ui-save-bar-text">You have unsaved changes.</p><button type="submit" class="btn-primary"><x-ui-icon name="key" class="mr-1" /> Update Password</button></div>
            </form>
    </x-ui.card>

    <x-ui.card title="Appearance" class="tab-panel" id="panel-appearance" style="display: none;">
        @include('partials.light-mode-notice')
    </x-ui.card>

    <x-ui.card title="Notification Preferences" class="tab-panel" id="panel-notifications" style="display: none;">
        <form method="POST" data-settings-form action="{{ route('moderator.settings.notifications') }}">
                @csrf
                @method('PUT')
                <div>
                    @php
                        $prefs = [
                            'email_notifications' => ['Email Notifications', 'Receive updates via email'],
                            'push_notifications' => ['Push Notifications', 'Receive real-time alerts in the browser'],
                            'session_reminders' => ['Session Reminders', 'Reminders before scheduled sessions'],
                            'marketing_emails' => ['Marketing Emails', 'Program news and announcements'],
                        ];
                    @endphp
                    @foreach($prefs as $key => [$label, $desc])
                        <div class="pref-row">
                            <div>
                                <p class="pref-label">{{ $label }}</p>
                                <p class="pref-desc">{{ $desc }}</p>
                            </div>
                            <label class="toggle">
                                <input type="checkbox" name="{{ $key }}" value="1" {{ $user->$key ? 'checked' : '' }}>
                                <span class="slider"></span>
                            </label>
                        </div>
                    @endforeach
                </div>
                <div class="ui-save-bar{{ $errors->any() ? ' is-visible' : '' }}" data-save-bar><p class="ui-save-bar-text">You have unsaved changes.</p><button type="submit" class="btn-primary"><x-ui-icon name="save" class="mr-1" /> Save Preferences</button></div>
            </form>
    </x-ui.card>
@endsection
