<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – My Profile</title>

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

        .profile-hero {
            background: linear-gradient(135deg, var(--green-600), var(--green-800));
            border-radius: 24px; padding: 36px; color: white; position: relative; overflow: hidden;
        }
        .profile-hero::before {
            content: ''; position: absolute; top: -60px; right: -60px; width: 220px; height: 220px;
            border-radius: 50%; background: rgba(255,255,255,0.06);
        }
        .profile-hero::after {
            content: ''; position: absolute; bottom: -80px; right: 80px; width: 160px; height: 160px;
            border-radius: 50%; background: rgba(255,255,255,0.05);
        }
        .avatar-lg {
            width: 96px; height: 96px; border-radius: 50%; background: rgba(255,255,255,0.18);
            border: 3px solid rgba(255,255,255,0.35); display: flex; align-items: center; justify-content: center;
            font-size: 34px; font-weight: 800; flex-shrink: 0; overflow: hidden;
        }
        .avatar-lg img { width: 100%; height: 100%; object-fit: cover; }

        .stat-card {
            background: white; border: 1px solid var(--gray-200); border-radius: 20px;
            padding: 20px; display: flex; align-items: center; gap: 14px; transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 32px rgba(4,160,82,0.08); border-color: var(--green-300); }
        .stat-card .icon {
            width: 48px; height: 48px; border-radius: 14px; background: var(--green-50);
            display: flex; align-items: center; justify-content: center; font-size: 20px; color: var(--green-600); flex-shrink: 0;
        }
        .stat-card .value { font-weight: 800; font-size: 22px; color: var(--gray-800); line-height: 1.1; }
        .stat-card .label { font-size: 12px; color: var(--gray-500); }

        .info-card {
            background: white; border: 1px solid var(--gray-200); border-radius: 20px; padding: 24px;
        }
        .info-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--gray-100); font-size: 14px; }
        .info-row:last-child { border-bottom: none; }
        .info-row .key { color: var(--gray-500); }
        .info-row .val { font-weight: 600; color: var(--gray-800); }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            font-weight: 600; font-size: 14px; padding: 11px 22px; border-radius: 12px;
            border: none; cursor: pointer; transition: all 0.25s ease; text-decoration: none;
        }
        .btn-primary { background: linear-gradient(135deg, var(--green-500), var(--green-600)); color: white; box-shadow: 0 4px 16px rgba(4,160,82,0.25); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(4,160,82,0.35); }
        .btn-outline { background: white; border: 1px solid var(--gray-300); color: var(--gray-600); }
        .btn-outline:hover { border-color: var(--green-500); color: var(--green-700); }

        .flash-banner {
            background: var(--green-500); color: white; border-radius: 14px; padding: 12px 18px;
            font-size: 14px; font-weight: 500; display: none; align-items: center; gap: 10px;
            box-shadow: 0 8px 28px rgba(4,160,82,0.3);
        }
        .flash-banner.show { display: flex; animation: slideDown 0.4s ease; }
        .flash-banner.error { background: #DC2626; box-shadow: 0 8px 28px rgba(220,38,38,0.3); }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .stars { color: #F59E0B; font-size: 13px; letter-spacing: 2px; }

        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .profile-hero { padding: 24px; }
        }
    </style>
</head>
<body>

    <main class="main-content">

        @if(session('success'))
            <div class="flash-banner show mb-4" id="flashBanner">
                <i class="fas fa-check-circle"></i>
                <span>{{ session('success') }}</span>
                <button class="ml-auto text-lg leading-none" onclick="this.parentElement.remove()">&times;</button>
            </div>
        @endif
        @if($errors->any())
            <div class="flash-banner show error mb-4">
                <i class="fas fa-exclamation-circle"></i>
                <span>{{ $errors->first() }}</span>
                <button class="ml-auto text-lg leading-none" onclick="this.parentElement.remove()">&times;</button>
            </div>
        @endif

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-extrabold text-gray-800">My Profile</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">Your identity, stats, and privacy at a glance.</p>
                </div>
            </div>
            <a href="{{ route('profile.edit') }}" class="btn btn-outline text-sm">
                <i class="fas fa-user-edit mr-1"></i> Edit profile
            </a>
        </div>

        <!-- Profile hero -->
        <div class="profile-hero mb-6">
            <div class="flex items-center gap-6 flex-wrap relative z-10">
                <div class="avatar-lg">
                    @if($user->avatar_path)
                        <img src="{{ asset($user->avatar_path) }}" alt="Profile picture">
                    @else
                        {{ $user->initials() }}
                    @endif
                </div>
                <div class="flex-1 min-w-[200px]">
                    <h2 class="text-2xl font-extrabold">{{ $user->helpSeeker?->generated_alias ?? $user->name }}</h2>
                    <p class="text-sm opacity-80 mt-1">
                        <i class="fas fa-circle text-[#7EE2A8] mr-2 text-[10px]"></i>Help Seeker
                    </p>
                    <div class="flex flex-wrap gap-2 mt-3 text-xs">
                        <span class="bg-white/15 px-3 py-1 rounded-full">
                            <i class="far fa-calendar-alt mr-1"></i>Member since {{ $user->created_at?->format('M Y') ?? '—' }}
                        </span>
                        <span class="bg-white/15 px-3 py-1 rounded-full">
                            @if($user->show_email)
                                <i class="far fa-envelope mr-1"></i>{{ $user->email }}
                            @else
                                <i class="fas fa-user-secret mr-1"></i>Email hidden
                            @endif
                        </span>
                        @if($user->helpSeeker?->gender)
                            <span class="bg-white/15 px-3 py-1 rounded-full">
                                <i class="fas fa-venus-mars mr-1"></i>{{ ucfirst($user->helpSeeker->gender) }}
                            </span>
                        @endif
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-4xl font-extrabold">{{ $stats['streak_days'] }}</div>
                    <p class="text-xs opacity-80">day streak <i class="fas fa-fire ml-1"></i></p>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <div class="stat-card">
                <div class="icon"><i class="fas fa-comments"></i></div>
                <div>
                    <div class="value">{{ $stats['total_sessions'] }}</div>
                    <div class="label">Total sessions</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-star"></i></div>
                <div>
                    <div class="value">{{ $stats['average_rating'] > 0 ? number_format($stats['average_rating'], 1) : '—' }}</div>
                    <div class="label">Average rating
                        @if($stats['average_rating'] > 0)
                            <span class="stars ml-1">{{ str_repeat('★', (int) round($stats['average_rating'])) }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="far fa-clock"></i></div>
                <div>
                    <div class="value">{{ $stats['total_minutes'] }}</div>
                    <div class="label">Minutes in sessions</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="value">{{ $stats['completed_sessions'] }}</div>
                    <div class="label">Completed sessions</div>
                </div>
            </div>
        </div>

        <!-- Details -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="info-card">
                <h3 class="font-bold text-gray-800 mb-2"><i class="fas fa-id-badge text-[#04A052] mr-2"></i>Account details</h3>
                <div class="info-row"><span class="key">Alias</span><span class="val">{{ $user->helpSeeker?->generated_alias ?? '—' }}</span></div>
                <div class="info-row"><span class="key">Email</span><span class="val">{{ $user->show_email ? $user->email : 'Hidden (privacy on)' }}</span></div>
                <div class="info-row"><span class="key">Role</span><span class="val">Help Seeker</span></div>
                <div class="info-row"><span class="key">Gender</span><span class="val">{{ $user->helpSeeker?->gender ? ucfirst($user->helpSeeker->gender) : '—' }}</span></div>
                <div class="info-row"><span class="key">Age</span><span class="val">{{ $user->helpSeeker?->age ?? '—' }}</span></div>
            </div>
            <div class="info-card">
                <h3 class="font-bold text-gray-800 mb-2"><i class="fas fa-sliders-h text-[#04A052] mr-2"></i>Preferences</h3>
                <div class="info-row"><span class="key">Preferred language</span><span class="val">{{ $user->preferred_language ?? 'English' }}</span></div>
                <div class="info-row"><span class="key">Communication mode</span><span class="val">{{ ucfirst($user->preferred_communication_mode ?? 'chat') }}</span></div>
                <div class="info-row"><span class="key">Session duration</span><span class="val">{{ $user->session_duration_preference ?? '30' }} min</span></div>
                <div class="info-row"><span class="key">Session reminders</span><span class="val">{{ $user->session_reminders ? 'On' : 'Off' }}</span></div>
                <div class="info-row"><span class="key">Show email to helpers</span><span class="val">{{ $user->show_email ? 'Visible' : 'Hidden' }}</span></div>
            </div>
        </div>

        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Making progress takes courage. You're doing great.
        </div>

    </main>

    @include('partials.sidebar', [
        'active' => ['profile*'],
        'role'   => 'Help Seeker',
    ])

</body>
</html>