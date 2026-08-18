<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Settings</title>

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
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Settings</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        Manage your availability and notification preferences
                    </p>
                </div>
            </div>
        </div>

        <!-- Availability -->
        <div class="card mb-6">
            <div class="card-header">
                <h3>Availability</h3>
            </div>
            <form method="POST" action="{{ route('professional.profile.availability') }}">
                @csrf
                <input type="hidden" name="is_available" value="0">
                <div class="flex items-center justify-between py-2">
                    <div>
                        <div class="text-sm font-semibold text-gray-700">Accept new referrals</div>
                        <div class="text-xs text-gray-400">Advisers can only assign referrals to available professionals</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="is_available" value="1"
                               {{ optional(auth()->user()->psychologyProfessional)->is_available ? 'checked' : '' }}>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <button type="submit" class="btn-primary mt-4">
                    <i class="fas fa-sync mr-1"></i> Save Availability
                </button>
            </form>
        </div>

        <!-- Notification Preferences -->
        <div class="card mb-6">
            <div class="card-header">
                <h3>Notification Preferences</h3>
            </div>
            <div class="space-y-4">
                <div class="flex items-center justify-between py-2">
                    <div>
                        <div class="text-sm font-semibold text-gray-700">New referral alerts</div>
                        <div class="text-xs text-gray-400">Email when a new referral is assigned to you</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" checked disabled>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="flex items-center justify-between py-2">
                    <div>
                        <div class="text-sm font-semibold text-gray-700">Case updates</div>
                        <div class="text-xs text-gray-400">Email when an adviser responds to your case updates</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" checked disabled>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <p class="text-xs text-gray-400">
                    <i class="fas fa-info-circle mr-1"></i>
                    Fine-grained notification preferences are managed from the general
                    <a href="{{ route('settings') }}" class="text-[#04A052] font-semibold hover:underline">account settings</a> page.
                    Professional alerts (referrals, case updates) are always delivered in-app.
                </p>
            </div>
        </div>

        <!-- Privacy Note -->
        <div class="card">
            <div class="card-header">
                <h3>Clinical Privacy</h3>
            </div>
            <p class="text-sm text-gray-500 leading-relaxed">
                All case documentation and intervention notes are confidential clinical records.
                Seeker identities remain protected through aliases unless explicit consent was given
                (<i>identity_disclosed</i>). Never share clinical records outside the COMPASS platform.
            </p>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Confidentiality is our foundation.
        </div>

    </main>

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

    @if(session('success'))
        <div class="flash-alert"><i class="fas fa-check-circle text-[#04A052] mr-2"></i>{{ session('success') }}</div>
    @endif

    @include('layouts.partials.pwa-banner')

</body>
</html>