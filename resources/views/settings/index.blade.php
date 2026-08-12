<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Settings</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green-50: #EAF8F0; --green-100: #DCF5E0; --green-200: #A8E0B0; --green-300: #6DCB80;
            --green-400: #30B650; --green-500: #04A052; --green-600: #038A45; --green-700: #027039;
            --green-800: #01562B; --green-900: #003D1E;
            --gray-50: #F9FAFB; --gray-100: #F3F4F6; --gray-200: #E5E7EB; --gray-300: #D1D5DB;
            --gray-400: #9CA3AF; --gray-500: #6B7280; --gray-600: #4B5563; --gray-700: #374151;
            --gray-800: #163B2D; --gray-900: #111827;
        }

        body { background: #F8FBF9; font-family: 'Inter', sans-serif; }

        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: var(--gray-100); }
        ::-webkit-scrollbar-thumb { background: var(--green-500); border-radius: 4px; }

        .main-content { margin-left: 260px; padding: 24px 32px 80px; min-height: 100vh; }

        .settings-card {
            background: white; border-radius: 22px; border: 1px solid var(--gray-200);
            padding: 22px 24px; display: flex; align-items: center; gap: 16px;
            text-decoration: none; transition: all 0.3s ease;
        }
        .settings-card:hover { transform: translateY(-4px); box-shadow: 0 12px 36px rgba(4,160,82,0.10); border-color: var(--green-300); }
        .settings-card .icon {
            width: 50px; height: 50px; border-radius: 14px; background: var(--green-50);
            display: flex; align-items: center; justify-content: center; font-size: 21px; color: var(--green-600);
            flex-shrink: 0;
        }
        .settings-card h3 { font-weight: 700; font-size: 15px; color: var(--gray-800); }
        .settings-card p { font-size: 13px; color: var(--gray-500); margin-top: 2px; }
        .settings-card .chevron { margin-left: auto; color: var(--gray-300); font-size: 16px; transition: all 0.3s ease; }
        .settings-card:hover .chevron { color: var(--green-500); transform: translateX(4px); }

        .info-strip { background: white; border: 1px solid var(--gray-200); border-radius: 18px; padding: 16px 20px; font-size: 13px; color: var(--gray-500); }

        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
        }
    </style>
</head>
<body>

    <main class="main-content">

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-extrabold text-gray-800">Settings</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">Manage your account, preferences, privacy, and appearance.</p>
                </div>
            </div>
            <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
        </div>

        <!-- Settings tabs -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <a href="{{ route('settings.account') }}" class="settings-card">
                <div class="icon"><i class="fas fa-user-cog"></i></div>
                <div>
                    <h3>Account</h3>
                    <p>Email, name, password, and account deletion.</p>
                </div>
                <i class="fas fa-chevron-right chevron"></i>
            </a>
            <a href="{{ route('settings.preferences') }}" class="settings-card">
                <div class="icon"><i class="fas fa-sliders-h"></i></div>
                <div>
                    <h3>Preferences</h3>
                    <p>Notifications and session preferences for matching.</p>
                </div>
                <i class="fas fa-chevron-right chevron"></i>
            </a>
            <a href="{{ route('settings.privacy') }}" class="settings-card">
                <div class="icon"><i class="fas fa-shield-alt"></i></div>
                <div>
                    <h3>Privacy</h3>
                    <p>Visibility, data usage, history, and exports.</p>
                </div>
                <i class="fas fa-chevron-right chevron"></i>
            </a>
            <a href="{{ route('settings.appearance') }}" class="settings-card">
                <div class="icon"><i class="fas fa-palette"></i></div>
                <div>
                    <h3>Appearance</h3>
                    <p>Dark mode, font size, and accessibility.</p>
                </div>
                <i class="fas fa-chevron-right chevron"></i>
            </a>
        </div>

        <!-- Account summary -->
        <div class="info-strip flex items-center gap-3 flex-wrap">
            <i class="fas fa-user-circle text-[#04A052] text-xl"></i>
            Signed in as <strong class="text-gray-700">{{ $user->email }}</strong>
            @if($user->role)
                <span class="bg-green-50 text-[#04A052] text-[11px] font-bold px-3 py-1 rounded-full uppercase tracking-wide">{{ $user->role }}</span>
            @endif
        </div>

        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            COMPASS · Peer support made safe.
        </div>

    </main>

    @include('partials.sidebar', [
        'active' => ['settings*'],
        'role'   => 'Help Seeker',
    ])

</body>
</html>