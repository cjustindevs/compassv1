<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Professional Profile</title>

    @vite(['resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green-50: #EAF8F0;
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
        }

        body { background: #F8FBF9; }

        .main-content { margin-left: 260px; padding: 24px 32px 80px; min-height: 100vh; }

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
        }
        .card-header h3 { font-weight: 700; font-size: 16px; color: var(--gray-800); }

        .profile-hero {
            background: linear-gradient(135deg, #038A45, #04A052);
            border-radius: 20px;
            padding: 28px;
            color: white;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .profile-avatar {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            border: 3px solid rgba(255,255,255,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 800;
            flex-shrink: 0;
        }
        .profile-name { font-size: 24px; font-weight: 800; }
        .profile-role { font-size: 14px; opacity: 0.9; }

        .availability-pill {
            margin-left: auto;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .availability-pill .dot { width: 10px; height: 10px; border-radius: 50%; background: white; }
        .availability-pill.online { background: rgba(255,255,255,0.25); }
        .availability-pill.offline { background: rgba(0,0,0,0.2); }
        .availability-pill.offline .dot { background: #D1D5DB; }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 10px 0;
            border-bottom: 1px solid var(--gray-100);
        }
        .info-row:last-child { border-bottom: none; }
        .info-row .label { font-size: 12px; color: var(--gray-400); text-transform: uppercase; letter-spacing: 0.03em; }
        .info-row .value { font-size: 14px; font-weight: 500; color: var(--gray-700); text-align: right; }

        .btn-primary {
            background: linear-gradient(135deg, var(--green-500), var(--green-600));
            color: white;
            font-weight: 600;
            padding: 10px 22px;
            border-radius: 20px;
            border: none;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-primary:hover { background: var(--green-700); transform: scale(1.02); }

        .btn-outline {
            background: transparent;
            color: var(--gray-600);
            padding: 8px 18px;
            border-radius: 20px;
            border: 1px solid var(--gray-200);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-outline:hover { background: var(--gray-50); border-color: var(--gray-300); }

        .hamburger {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: var(--gray-700);
            cursor: pointer;
            padding: 4px;
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.25);
            z-index: 99;
        }
        .sidebar-overlay.active { display: block; }

        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.94);
            backdrop-filter: blur(16px);
            border-top: 1px solid var(--gray-200);
            padding: 6px 0 env(safe-area-inset-bottom, 6px);
            z-index: 200;
            justify-content: space-around;
        }
        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--gray-400);
            text-decoration: none;
            font-size: 10px;
            font-weight: 500;
            padding: 4px 12px;
        }
        .bottom-nav .nav-item i { font-size: 20px; }
        .bottom-nav .nav-item.active { color: var(--green-500); }
        .bottom-nav .nav-item.active i { color: var(--green-500); }

        .toggle-switch { position: relative; display: inline-block; width: 52px; height: 28px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background: var(--gray-300);
            border-radius: 28px;
            transition: 0.3s;
        }
        .toggle-slider::before {
            content: '';
            position: absolute;
            width: 22px;
            height: 22px;
            left: 3px;
            bottom: 3px;
            background: white;
            border-radius: 50%;
            transition: 0.3s;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }
        .toggle-switch input:checked + .toggle-slider { background: var(--green-500); }
        .toggle-switch input:checked + .toggle-slider::before { transform: translateX(24px); }

        .flash-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            background: white;
            border-left: 4px solid var(--green-500);
            border-radius: 12px;
            padding: 14px 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-800);
            animation: flashIn 0.3s ease-out;
        }
        @keyframes flashIn { from { transform: translateY(-10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        @media (min-width: 769px) { .sidebar-overlay { display: none !important; } }
        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .info-row { flex-direction: column; gap: 2px; }
            .info-row .value { text-align: left; }
            .availability-pill { margin-left: 0; }
        }
    </style>
</head>
<body>

    @include('layouts.partials.professional-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Profile</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        Manage your professional information and availability
                    </p>
                </div>
            </div>
        </div>

        <!-- Profile Hero -->
        <div class="profile-hero mb-6">
            <div class="profile-avatar">{{ $professional->initials }}</div>
            <div>
                <div class="profile-name">{{ $professional->full_name }}</div>
                <div class="profile-role">
                    {{ $professional->specialization ?? 'Psychology Professional' }}
                    @if($professional->license_number) · License {{ $professional->license_number }} @endif
                </div>
            </div>
            <span class="availability-pill {{ $professional->is_available ? 'online' : 'offline' }}" id="availabilityPill">
                <span class="dot"></span>
                {{ $professional->is_available ? 'Available' : 'Unavailable' }}
            </span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Edit Profile -->
            <div class="card">
                <div class="card-header">
                    <h3>Edit Profile</h3>
                </div>

                <form method="POST" action="{{ route('professional.profile.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Full Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}"
                               class="w-full p-3 border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}"
                               class="w-full p-3 border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Specialization</label>
                        <input type="text" name="specialization" value="{{ old('specialization', $professional->specialization) }}"
                               class="w-full p-3 border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none"
                               placeholder="e.g. Clinical Psychology">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">License Number</label>
                        <input type="text" name="license_number" value="{{ old('license_number', $professional->license_number) }}"
                               class="w-full p-3 border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none"
                               placeholder="e.g. PSY-2024-001">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone', $professional->phone) }}"
                               class="w-full p-3 border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none"
                               placeholder="e.g. +63 912 345 6789">
                    </div>

                    <button type="submit" class="btn-primary w-full justify-center">
                        <i class="fas fa-save mr-1"></i> Save Changes
                    </button>
                </form>
            </div>

            <!-- Availability & Account Info -->
            <div class="space-y-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Availability</h3>
                    </div>
                    <p class="text-sm text-gray-500 mb-4">
                        When available, advisers can assign new referrals to you.
                    </p>
                    <form method="POST" action="{{ route('professional.profile.availability') }}">
                        @csrf
                        <input type="hidden" name="is_available" value="0">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm font-semibold text-gray-700">Accept new referrals</div>
                                <div class="text-xs text-gray-400">Turn off while you are at full capacity</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="is_available" value="1" id="availabilityToggle"
                                       {{ $professional->is_available ? 'checked' : '' }}>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <button type="submit" class="btn-outline w-full justify-center mt-4">
                            <i class="fas fa-sync mr-1"></i> Update Availability
                        </button>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Account Information</h3>
                    </div>
                    <div class="info-row">
                        <span class="label">Account ID</span>
                        <span class="value">#{{ $user->id }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Professional ID</span>
                        <span class="value">#{{ $professional->id }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Member Since</span>
                        <span class="value">{{ $user->created_at->format('M d, Y') }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Email Verified</span>
                        <span class="value">{{ $user->email_verified_at ? 'Yes' : 'No' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Your credentials matter.
        </div>

    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="{{ route('professional.dashboard') }}" class="nav-item">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>
        <a href="{{ route('professional.referrals') }}" class="nav-item">
            <i class="fas fa-clipboard-list"></i>
            <span>Referrals</span>
        </a>
        <a href="{{ route('professional.cases') }}" class="nav-item">
            <i class="fas fa-folder-open"></i>
            <span>Cases</span>
        </a>
        <a href="{{ route('professional.reports') }}" class="nav-item">
            <i class="fas fa-chart-bar"></i>
            <span>Reports</span>
        </a>
        <a href="{{ route('professional.profile') }}" class="nav-item active">
            <i class="fas fa-user-circle"></i>
            <span>Profile</span>
        </a>
    </nav>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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

            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) closeSidebar();
            });

            document.querySelectorAll('.flash-alert').forEach(alert => {
                setTimeout(() => alert.remove(), 5000);
            });
        });
    </script>

    @if($errors->any())
        <div class="flash-alert" style="border-left-color:#EF4444;">
            <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>{{ $errors->first() }}
        </div>
    @endif
    @if(session('success'))
        <div class="flash-alert"><i class="fas fa-check-circle text-[#04A052] mr-2"></i>{{ session('success') }}</div>
    @endif

    @include('layouts.partials.pwa-banner')

</body>
</html>