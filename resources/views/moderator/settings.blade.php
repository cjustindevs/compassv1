<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>COMPASS – Settings</title>

    @vite(['resources/js/app.js', 'resources/js/moderator-notifications.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F8FBF9; }

        .main-content {
            margin-left: 260px;
            max-width: 900px;
            padding: 24px 32px 80px;
            min-height: 100vh;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #E5E7EB;
            box-shadow: 0 4px 20px rgba(0,0,0,0.01);
        }
        .card h3 { font-weight: 700; font-size: 16px; color: #1F2937; margin-bottom: 16px; }

        .tab {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            color: #6B7280;
            background: white;
            border: 1.5px solid #E5E7EB;
            cursor: pointer;
            transition: all 0.2s;
        }
        .tab.active { background: #EAF8F0; border-color: #04A052; color: #027039; }

        .form-label { display: block; font-weight: 600; font-size: 13px; color: #374151; margin-bottom: 6px; }
        .form-input {
            width: 100%;
            padding: 10px 14px;
            border-radius: 12px;
            border: 1.5px solid #E5E7EB;
            outline: none;
            font-size: 14px;
            background: white;
        }
        .form-input:focus { border-color: #04A052; box-shadow: 0 0 0 3px rgba(4,160,82,0.08); }
        .form-input.error { border-color: #DC2626; }

        .btn-primary {
            background: linear-gradient(135deg, #04A052, #038A45);
            color: white;
            padding: 10px 22px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary:hover { background: #027039; }

        .toggle { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .toggle .slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: #E5E7EB;
            border-radius: 24px;
            transition: 0.3s;
        }
        .toggle .slider:before {
            content: "";
            position: absolute;
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background: white;
            border-radius: 50%;
            transition: 0.3s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .toggle input:checked + .slider { background: #04A052; }
        .toggle input:checked + .slider:before { transform: translateX(20px); }

        .radio-group { display: flex; gap: 10px; }
        .radio-option {
            flex: 1;
            border: 1.5px solid #E5E7EB;
            border-radius: 14px;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 13px;
            font-weight: 600;
            color: #6B7280;
        }
        .radio-option:hover { border-color: #04A052; }
        .radio-option.selected { border-color: #04A052; background: #EAF8F0; color: #027039; }

        .flash-success { background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }
        .flash-error { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }
        .error-text { font-size: 12px; color: #DC2626; margin-top: 4px; }

        .hamburger { display: none; background: none; border: none; font-size: 24px; color: #374151; cursor: pointer; padding: 4px; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.25); z-index: 99; }
        .sidebar-overlay.active { display: block; }

        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.94);
            backdrop-filter: blur(16px);
            border-top: 1px solid #E5E7EB;
            padding: 6px 0 env(safe-area-inset-bottom, 6px);
            z-index: 200;
            justify-content: space-around;
        }
        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #9CA3AF;
            text-decoration: none;
            font-size: 10px;
            font-weight: 500;
            padding: 4px 12px;
        }
        .bottom-nav .nav-item i { font-size: 20px; }
        .bottom-nav .nav-item.active { color: #04A052; }
        .bottom-nav .nav-item.active i { color: #04A052; }

        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .card { padding: 16px; }
        }
    </style>
</head>
<body>

    @include('layouts.partials.moderator-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">

        <div class="flex items-center gap-4 mb-6">
            <button class="hamburger" id="hamburgerBtn">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-800">Settings</h1>
                <p class="text-sm text-gray-500 hidden sm:block">Manage your account and preferences</p>
            </div>
        </div>

        @if(session('success'))
            <div class="flash-success"><i class="fas fa-check-circle mr-1"></i> {{ session('success') }}</div>
        @endif

        <div class="flex flex-wrap gap-2 mb-6" id="tabBar">
            <button type="button" class="tab active" data-tab="profile">Profile</button>
            <button type="button" class="tab" data-tab="password">Password</button>
            <button type="button" class="tab" data-tab="appearance">Appearance</button>
            <button type="button" class="tab" data-tab="notifications">Notifications</button>
        </div>

        <!-- Profile -->
        <div class="card mb-6 tab-panel" id="panel-profile">
            <h3>Profile Information</h3>
            <form method="POST" action="{{ route('moderator.settings.profile') }}">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
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
                <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Save Profile</button>
            </form>
        </div>

        <!-- Password -->
        <div class="card mb-6 tab-panel" id="panel-password" style="display: none;">
            <h3>Change Password</h3>
            <form method="POST" action="{{ route('moderator.settings.password') }}">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 gap-4 mb-4">
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
                <button type="submit" class="btn-primary"><i class="fas fa-key mr-1"></i> Update Password</button>
            </form>
        </div>

        <!-- Appearance -->
        <div class="card mb-6 tab-panel" id="panel-appearance" style="display: none;">
            <h3>Appearance</h3>
            <form method="POST" action="{{ route('moderator.settings.appearance') }}">
                @csrf
                @method('PUT')
                <div class="space-y-5 mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-semibold text-sm text-gray-700">Dark Mode</p>
                            <p class="text-xs text-gray-400">Use a dark theme across COMPASS</p>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="dark_mode" value="1" {{ $user->dark_mode ? 'checked' : '' }}>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-semibold text-sm text-gray-700">High Contrast</p>
                            <p class="text-xs text-gray-400">Increase contrast for readability</p>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" name="high_contrast" value="1" {{ $user->high_contrast ? 'checked' : '' }}>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div>
                        <p class="font-semibold text-sm text-gray-700 mb-3">Font Size</p>
                        <div class="radio-group" id="fontSizeGroup">
                            @foreach(['small' => 'A', 'medium' => 'A', 'large' => 'A'] as $size => $label)
                                <button type="button" class="radio-option {{ $user->font_size === $size ? 'selected' : '' }}" data-size="{{ $size }}"
                                        style="font-size: {{ $size === 'small' ? 13 : ($size === 'large' ? 17 : 15) }}px;">
                                    {{ $label }} · {{ ucfirst($size) }}
                                </button>
                            @endforeach
                        </div>
                        <input type="hidden" name="font_size" id="fontSizeInput" value="{{ $user->font_size ?? 'medium' }}">
                    </div>
                </div>
                <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Save Appearance</button>
            </form>
        </div>

        <!-- Notifications -->
        <div class="card mb-6 tab-panel" id="panel-notifications" style="display: none;">
            <h3>Notification Preferences</h3>
            <form method="POST" action="{{ route('moderator.settings.notifications') }}">
                @csrf
                @method('PUT')
                <div class="space-y-5 mb-6">
                    @php
                        $prefs = [
                            'email_notifications' => ['Email Notifications', 'Receive updates via email'],
                            'push_notifications' => ['Push Notifications', 'Receive real-time alerts in the browser'],
                            'session_reminders' => ['Session Reminders', 'Reminders before scheduled sessions'],
                            'marketing_emails' => ['Marketing Emails', 'Program news and announcements'],
                        ];
                    @endphp
                    @foreach($prefs as $key => [$label, $desc])
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-semibold text-sm text-gray-700">{{ $label }}</p>
                                <p class="text-xs text-gray-400">{{ $desc }}</p>
                            </div>
                            <label class="toggle">
                                <input type="checkbox" name="{{ $key }}" value="1" {{ $user->$key ? 'checked' : '' }}>
                                <span class="slider"></span>
                            </label>
                        </div>
                    @endforeach
                </div>
                <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Save Preferences</button>
            </form>
        </div>

    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="{{ route('moderator.dashboard') }}" class="nav-item">
            <i class="fas fa-th-large"></i><span>Dashboard</span>
        </a>
        <a href="{{ route('moderator.queue') }}" class="nav-item">
            <i class="fas fa-hourglass-half"></i><span>Queue</span>
        </a>
        <a href="{{ route('moderator.sessions') }}" class="nav-item">
            <i class="fas fa-comments"></i><span>Sessions</span>
        </a>
        <a href="{{ route('moderator.emergency') }}" class="nav-item">
            <i class="fas fa-exclamation-triangle"></i><span>Emergency</span>
        </a>
        <a href="{{ route('moderator.analytics') }}" class="nav-item">
            <i class="fas fa-chart-line"></i><span>Analytics</span>
        </a>
    </nav>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const hamburger = document.getElementById('hamburgerBtn');

            function toggleSidebar() {
                sidebar.classList.toggle('closed');
                overlay.classList.toggle('active');
            }

            function closeSidebar() {
                sidebar.classList.add('closed');
                overlay.classList.remove('active');
            }

            hamburger.addEventListener('click', toggleSidebar);
            overlay.addEventListener('click', closeSidebar);

            window.addEventListener('resize', function () {
                if (window.innerWidth > 768) closeSidebar();
            });

            // Tabs
            document.querySelectorAll('#tabBar .tab').forEach(function (tab) {
                tab.addEventListener('click', function () {
                    document.querySelectorAll('#tabBar .tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
                    this.classList.add('active');
                    document.getElementById('panel-' + this.dataset.tab).style.display = '';
                });
            });

            // Font size selector
            document.querySelectorAll('#fontSizeGroup .radio-option').forEach(function (option) {
                option.addEventListener('click', function () {
                    document.querySelectorAll('#fontSizeGroup .radio-option').forEach(o => o.classList.remove('selected'));
                    this.classList.add('selected');
                    document.getElementById('fontSizeInput').value = this.dataset.size;
                });
            });
        });
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>
