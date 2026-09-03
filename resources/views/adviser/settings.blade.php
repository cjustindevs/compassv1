@extends('layouts.app')

@section('title', 'COMPASS – Settings')

@push('styles')
<style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green-50: #EAF8F0;
            --green-100: #D0F0D8;
            --green-500: #04A052;
            --green-600: #038A45;
            --green-700: #027039;
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #163B2D;
            --red-500: #EF4444;
            --yellow-500: #F59E0B;
            --blue-500: #3B82F6;
        }

        body { background: #F8FBF9; }

        .card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 4px 20px rgba(0,0,0,0.01);
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            gap: 12px;
            flex-wrap: wrap;
        }
        .card-header h3 { font-weight: 700; font-size: 16px; color: var(--gray-800); }

        .btn-primary {
            background: linear-gradient(135deg, var(--green-500), var(--green-600));
            color: white;
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 20px;
            border: none;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary:hover { background: var(--green-700); transform: scale(1.02); }

        .form-input {
            width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200);
            border-radius: 12px; font-size: 13px; outline: none; transition: border-color 0.15s;
        }
        .form-input:focus { border-color: var(--green-500); }
        .form-label { font-size: 12px; font-weight: 600; color: var(--gray-600); margin-bottom: 6px; display: block; }

        .toggle-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 0; border-bottom: 1px solid var(--gray-100); gap: 12px;
        }
        .toggle-row:last-child { border-bottom: none; }
        .toggle-label { font-size: 13px; font-weight: 600; color: var(--gray-700); }
        .toggle-desc { font-size: 12px; color: var(--gray-400); margin-top: 2px; }

        .switch { position: relative; display: inline-block; width: 42px; height: 24px; flex-shrink: 0; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .switch .slider {
            position: absolute; cursor: pointer; inset: 0;
            background: var(--gray-300); border-radius: 24px; transition: 0.2s;
        }
        .switch .slider::before {
            content: ''; position: absolute; width: 18px; height: 18px;
            border-radius: 50%; background: white; left: 3px; top: 3px; transition: 0.2s;
        }
        .switch input:checked + .slider { background: var(--green-500); }
        .switch input:checked + .slider::before { transform: translateX(18px); }

        .tab-btn { padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid var(--gray-200); background: white; color: var(--gray-500); transition: all 0.2s; }
        .tab-btn.active { background: var(--green-50); border-color: var(--green-500); color: var(--green-700); }

        .flash-success { background: var(--green-50); color: var(--green-700); border: 1px solid var(--green-100); border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }
        .flash-error { background: #FEF2F2; color: var(--red-500); border: 1px solid #FECACA; border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }

        .settings-section { display: none; }
        .settings-section.active { display: block; }
    </style>
@endpush

@section('content')
<div class="adviser-page-content">
        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Settings</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        Manage your profile, password, and preferences
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
            </div>
        </div>

        @if(session('success'))
            <div class="flash-success"><i class="fas fa-check-circle mr-1"></i> {{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="flash-error">
                <i class="fas fa-exclamation-circle mr-1"></i> {{ $errors->first() }}
            </div>
        @endif

        <!-- Tabs -->
        <div class="flex items-center gap-2 mb-6 flex-wrap">
            <button class="tab-btn active" data-tab="profile"><i class="fas fa-user mr-1"></i> Profile</button>
            <button class="tab-btn" data-tab="password"><i class="fas fa-lock mr-1"></i> Password</button>
            <button class="tab-btn" data-tab="appearance"><i class="fas fa-palette mr-1"></i> Appearance</button>
            <button class="tab-btn" data-tab="notifications"><i class="fas fa-bell mr-1"></i> Notifications</button>
        </div>

        <!-- Profile -->
        <div class="settings-section active" id="section-profile">
            <div class="card">
                <div class="card-header">
                    <h3>Profile Information</h3>
                </div>
                <form method="POST" action="{{ route('adviser.settings.profile') }}">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" required class="form-input" value="{{ old('first_name', $adviser?->first_name ?? '') }}">
                        </div>
                        <div>
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" required class="form-input" value="{{ old('last_name', $adviser?->last_name ?? '') }}">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="form-label">Display Name</label>
                            <input type="text" name="name" required class="form-input" value="{{ old('name', $user->name) }}">
                        </div>
                        <div>
                            <label class="form-label">Email</label>
                            <input type="email" name="email" required class="form-input" value="{{ old('email', $user->email) }}">
                        </div>
                    </div>
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Profile</button>
                </form>
            </div>
        </div>

        <!-- Password -->
        <div class="settings-section" id="section-password">
            <div class="card">
                <div class="card-header">
                    <h3>Change Password</h3>
                </div>
                <form method="POST" action="{{ route('adviser.settings.password') }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-4">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" required class="form-input" autocomplete="current-password">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" required minlength="8" class="form-input" autocomplete="new-password">
                        </div>
                        <div>
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="new_password_confirmation" required class="form-input" autocomplete="new-password">
                        </div>
                    </div>
                    <button type="submit" class="btn-primary"><i class="fas fa-key"></i> Update Password</button>
                </form>
            </div>
        </div>

        <!-- Appearance -->
        <div class="settings-section" id="section-appearance">
            <div class="card">
                <div class="card-header">
                    <h3>Appearance</h3>
                </div>
                @include('partials.light-mode-notice')
            </div>
        </div>

        <!-- Notification Preferences -->
        <div class="settings-section" id="section-notifications">
            <div class="card">
                <div class="card-header">
                    <h3>Notification Preferences</h3>
                </div>
                <form method="POST" action="{{ route('adviser.settings.notifications') }}">
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
                                <div class="toggle-label">{{ $label }}</div>
                                <div class="toggle-desc">{{ $desc }}</div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="{{ $field }}" value="1" {{ $user->{$field} ? 'checked' : '' }}>
                                <span class="slider"></span>
                            </label>
                        </div>
                    @endforeach

                    <div class="mt-6">
                        <button type="submit" class="btn-primary"><i class="fas fa-bell"></i> Save Preferences</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Quality supervision leads to quality support.
        </div>
</div>
    <!-- Bottom Navigation -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
// ── Tab switching ──
            document.querySelectorAll('.tab-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.querySelectorAll('.tab-btn').forEach(function (b) {
                        b.classList.remove('active');
                    });
                    this.classList.add('active');
                    document.querySelectorAll('.settings-section').forEach(function (s) {
                        s.classList.remove('active');
                    });
                    document.getElementById('section-' + this.getAttribute('data-tab')).classList.add('active');
                });
            });
        });
    </script>
@endsection
