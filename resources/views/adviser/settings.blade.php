@extends('layouts.app')

@section('title', 'COMPASS – Settings')

@push('styles')
<style>
        .settings-flash { background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; border-radius: 12px; padding: 12px 16px; font-size: 14px; font-weight: 500; margin-bottom: 16px; }
        .settings-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 24px; }
        .tab-btn { display: inline-flex; align-items: center; gap: 6px; min-height: 44px; padding: 0 20px; border-radius: 999px; font-size: 15px; font-weight: 600; cursor: pointer; border: 1px solid #E5E7EB; background: #fff; color: #6B7280; }
        .tab-btn.active { background: #EAF8F0; border-color: #04A052; color: #027039; }
        .settings-section { display: none; }
        .settings-section.active { display: block; }
        .settings-grid { display: grid; grid-template-columns: 1fr; gap: 16px; }
        @media (min-width: 769px) { .settings-grid.two { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .form-label { display: block; font-weight: 600; font-size: 14px; color: #374151; margin-bottom: 6px; }
        .form-input { width: 100%; min-height: 44px; padding: 0 14px; border-radius: 12px; border: 1px solid #E5E7EB; font-size: 15px; background: #fff; }
        .form-input:focus { outline: 2px solid #04A052; outline-offset: 1px; }
        .form-input.error { border-color: #DC2626; }
        .error-text { font-size: 13px; color: #DC2626; margin-top: 4px; }
        .toggle-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 0; border-bottom: 1px solid #F3F4F6; }
        .toggle-row:last-of-type { border-bottom: none; }
        .toggle-row p { margin: 0; }
        .toggle-label { font-size: 15px; font-weight: 600; color: #374151; }
        .toggle-desc { font-size: 13px; color: #6B7280; }
        .switch { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .switch .slider { position: absolute; cursor: pointer; inset: 0; background: #E5E7EB; border-radius: 24px; transition: 0.3s; }
        .switch .slider::before { content: ''; position: absolute; width: 18px; height: 18px; border-radius: 50%; background: #fff; left: 3px; bottom: 3px; transition: 0.3s; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
        .switch input:checked + .slider { background: #04A052; }
        .switch input:checked + .slider::before { transform: translateX(20px); }
        .switch input:focus-visible + .slider { outline: 2px solid #04A052; outline-offset: 2px; }
    </style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
                this.classList.add('active');
                document.querySelectorAll('.settings-section').forEach(function (s) { s.classList.remove('active'); });
                document.getElementById('section-' + this.getAttribute('data-tab')).classList.add('active');
            });
        });

        document.documentElement.classList.add('js-save-bar');
        // Reveal each form's sticky save bar once one of its fields changes.
        document.querySelectorAll('form[data-settings-form]').forEach(function (form) {
            const bar = form.querySelector('[data-save-bar]');
            if (!bar) return;
            const reveal = function () { bar.classList.add('is-visible'); };
            form.addEventListener('input', reveal);
            form.addEventListener('change', reveal);
        });
    });
</script>
@endpush

@section('content')
@php
    $activeTab = ($errors->has('current_password') || $errors->has('new_password')) ? 'password' : 'profile';
@endphp
<div class="adviser-page-content">
        <x-ui.page-header title="Settings" description="Manage your profile, password, and preferences" />

        @if(session('success'))
            <div class="settings-flash" role="status"> {{ session('success') }}</div>
        @endif

        <div class="settings-tabs" id="tabBar">
            <button type="button" class="tab-btn {{ $activeTab === 'profile' ? 'active' : '' }}" data-tab="profile"><x-ui-icon name="user" /> Profile</button>
            <button type="button" class="tab-btn {{ $activeTab === 'password' ? 'active' : '' }}" data-tab="password"><x-ui-icon name="lock" /> Password</button>
            <button type="button" class="tab-btn" data-tab="appearance"><x-ui-icon name="palette" /> Appearance</button>
            <button type="button" class="tab-btn" data-tab="notifications"><x-ui-icon name="bell" /> Notifications</button>
        </div>

        <!-- Profile -->
        <div class="settings-section {{ $activeTab === 'profile' ? 'active' : '' }}" id="section-profile">
            <x-ui.card title="Profile Information">
                <form class="form-maximized" data-settings-form method="POST" action="{{ route('adviser.settings.profile') }}">
                    @csrf
                    @method('PUT')
                    <div class="settings-grid two">
                        <div>
                            <label class="form-label" for="settingsFirstName">First Name</label>
                            <input id="settingsFirstName" type="text" name="first_name" required class="form-input {{ $errors->has('first_name') ? 'error' : '' }}" value="{{ old('first_name', $adviser?->first_name ?? '') }}">
                            @error('first_name') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label" for="settingsLastName">Last Name</label>
                            <input id="settingsLastName" type="text" name="last_name" required class="form-input {{ $errors->has('last_name') ? 'error' : '' }}" value="{{ old('last_name', $adviser?->last_name ?? '') }}">
                            @error('last_name') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label" for="settingsName">Display Name</label>
                            <input id="settingsName" type="text" name="name" required class="form-input {{ $errors->has('name') ? 'error' : '' }}" value="{{ old('name', $user->name) }}">
                            @error('name') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label" for="settingsEmail">Email</label>
                            <input id="settingsEmail" type="email" name="email" required class="form-input {{ $errors->has('email') ? 'error' : '' }}" value="{{ old('email', $user->email) }}">
                            @error('email') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="ui-save-bar{{ $errors->any() ? ' is-visible' : '' }}" data-save-bar><p class="ui-save-bar-text">You have unsaved changes.</p><button type="submit" class="btn-primary"><x-ui-icon name="save" /> Save Profile</button></div>
                </form>
            </x-ui.card>
        </div>

        <!-- Password -->
        <div class="settings-section {{ $activeTab === 'password' ? 'active' : '' }}" id="section-password">
            <x-ui.card title="Change Password">
                <form class="form-maximized" data-settings-form method="POST" action="{{ route('adviser.settings.password') }}">
                    @csrf
                    @method('PUT')
                    <div class="settings-grid two">
                        <div style="grid-column: 1 / -1;">
                            <label class="form-label" for="settingsCurrentPassword">Current Password</label>
                            <input id="settingsCurrentPassword" type="password" name="current_password" required class="form-input {{ $errors->has('current_password') ? 'error' : '' }}" autocomplete="current-password">
                            @error('current_password') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label" for="settingsNewPassword">New Password</label>
                            <input id="settingsNewPassword" type="password" name="new_password" required minlength="8" class="form-input {{ $errors->has('new_password') ? 'error' : '' }}" autocomplete="new-password">
                            @error('new_password') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label" for="settingsConfirmPassword">Confirm New Password</label>
                            <input id="settingsConfirmPassword" type="password" name="new_password_confirmation" required class="form-input" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="ui-save-bar{{ $errors->any() ? ' is-visible' : '' }}" data-save-bar><p class="ui-save-bar-text">You have unsaved changes.</p><button type="submit" class="btn-primary"><x-ui-icon name="key" /> Update Password</button></div>
                </form>
            </x-ui.card>
        </div>

        <!-- Appearance -->
        <div class="settings-section" id="section-appearance">
            <x-ui.card title="Appearance">
                @include('partials.light-mode-notice')
            </x-ui.card>
        </div>

        <!-- Notification Preferences -->
        <div class="settings-section" id="section-notifications">
            <x-ui.card title="Notification Preferences">
                <form class="form-maximized" data-settings-form method="POST" action="{{ route('adviser.settings.notifications') }}">
                    @csrf
                    @method('PUT')

                    @php
                        $prefs = [
                            'email_notifications' => ['Email Notifications', 'Receive updates via email'],
                            'push_notifications' => ['Push Notifications', 'Receive real-time browser alerts'],
                            'session_reminders' => ['Session Reminders', 'Get reminded about upcoming sessions'],
                            'marketing_emails' => ['Product Updates', 'Occasional news about COMPASS'],
                        ];
                    @endphp

                    @foreach($prefs as $field => [$label, $desc])
                        <div class="toggle-row">
                            <div>
                                <p class="toggle-label">{{ $label }}</p>
                                <p class="toggle-desc">{{ $desc }}</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="{{ $field }}" value="1" aria-label="{{ $label }}" {{ $user->{$field} ? 'checked' : '' }}>
                                <span class="slider"></span>
                            </label>
                        </div>
                    @endforeach

                    <div class="ui-save-bar{{ $errors->any() ? ' is-visible' : '' }}" data-save-bar><p class="ui-save-bar-text">You have unsaved changes.</p><button type="submit" class="btn-primary"><x-ui-icon name="bell" /> Save Preferences</button></div>
                </form>
            </x-ui.card>
        </div>
</div>
@endsection
