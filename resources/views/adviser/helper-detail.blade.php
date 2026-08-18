<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>Helper Detail – COMPASS</title>

    @vite(['resources/js/app.js', 'resources/js/adviser-notifications.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F8FBF9; }
        .main-content { margin-left: 260px; max-width: 900px; padding: 24px 32px 80px; min-height: 100vh; }
        .card { background: white; border-radius: 20px; padding: 24px; border: 1px solid #e5e7eb; box-shadow: 0 4px 20px rgba(0,0,0,0.01); }
        .competency-level { padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .competency-level.expert { background: #dcfce7; color: #166534; }
        .competency-level.advanced { background: #dbeafe; color: #1e40af; }
        .competency-level.intermediate { background: #fef3c7; color: #92400e; }
        .competency-level.beginner { background: #fee2e2; color: #991b1b; }
        .competency-level.trainee { background: #e5e7eb; color: #6b7280; }

        .flash-success { background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }

        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .card { padding: 16px; }
        }
    </style>
</head>
<body>

    @include('layouts.partials.adviser-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">

        <!-- Top Bar -->
        <div class="flex items-center gap-4 mb-6">
            <button class="hamburger" id="hamburgerBtn">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-800">Helper Detail</h1>
                <p class="text-sm text-gray-500 hidden sm:block">
                    Performance history and supervision
                </p>
            </div>
        </div>

        @if(session('success'))
            <div class="flash-success"><i class="fas fa-check-circle mr-1"></i> {{ session('success') }}</div>
        @endif

        <!-- Back Link -->
        <a href="{{ route('adviser.helpers') }}" class="inline-flex items-center gap-2 text-gray-500 hover:text-gray-700 mb-6 text-sm font-medium">
            <i class="fas fa-arrow-left"></i> Back to Helpers
        </a>

        <div class="card">

            <!-- Header -->
            <div class="flex flex-wrap items-center gap-4 mb-6 pb-4 border-b border-gray-200">
                <div class="w-16 h-16 rounded-full bg-green-500 flex items-center justify-center text-white text-2xl font-bold flex-shrink-0">
                    {{ substr($helper->first_name, 0, 1) }}{{ substr($helper->last_name, 0, 1) }}
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">{{ $helper->first_name }} {{ $helper->last_name }}</h1>
                    <p class="text-sm text-gray-500">
                        @if($helper->adviser)
                            Supervised by: {{ $helper->adviser->first_name }} {{ $helper->adviser->last_name }}
                        @else
                            No adviser assigned
                        @endif
                    </p>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    <span class="competency-level {{ strtolower($competencyHistory->first()->competency_level ?? 'Beginner') }}">
                        {{ $competencyHistory->first()->competency_level ?? 'Beginner' }}
                    </span>
                    <span class="text-sm text-gray-500">Score: {{ $competencyHistory->first()->overall_score ?? 'N/A' }}</span>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="p-4 bg-gray-50 rounded-xl text-center">
                    <p class="text-2xl font-bold text-gray-800">{{ $helper->sessions->count() }}</p>
                    <p class="text-xs text-gray-400">Active Sessions</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl text-center">
                    <p class="text-2xl font-bold text-gray-800">{{ $competencyHistory->count() }}</p>
                    <p class="text-xs text-gray-400">Evaluations</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl text-center">
                    <p class="text-2xl font-bold text-gray-800">
                        {{ $helper->isOnline() ? '✅' : '❌' }}
                    </p>
                    <p class="text-xs text-gray-400">Available</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-xl text-center">
                    <p class="text-2xl font-bold text-gray-800">
                        {{ $helper->latestReadiness?->assessment_result === 'ready' ? '🟢' : '🔴' }}
                    </p>
                    <p class="text-xs text-gray-400">Readiness</p>
                </div>
            </div>

            <!-- Supervision / Adviser Assignment -->
            <h3 class="font-semibold text-gray-700 mb-4">Supervision</h3>
            <form method="POST" action="{{ route('adviser.helper.assign', ['id' => $helper->id]) }}"
                  class="flex flex-wrap items-center gap-3 p-4 bg-gray-50 rounded-xl mb-6">
                @csrf
                <span class="text-sm text-gray-500">Assigned adviser:</span>
                <select name="adviser_id" class="border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:border-[#04A052]">
                    <option value="">Unassigned</option>
                    @foreach($advisers as $adviser)
                        <option value="{{ $adviser->id }}" {{ $helper->adviser_id === $adviser->id ? 'selected' : '' }}>
                            {{ $adviser->first_name }} {{ $adviser->last_name }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="bg-[#04A052] text-white text-sm font-semibold px-4 py-2 rounded-xl hover:bg-[#027039] transition">
                    <i class="fas fa-user-tag mr-1"></i> Assign
                </button>
            </form>

            <!-- Competency History -->
            <h3 class="font-semibold text-gray-700 mb-4">Competency History</h3>
            @if($competencyHistory->isNotEmpty())
                <div class="space-y-2">
                    @foreach($competencyHistory as $evaluation)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                            <div>
                                <p class="font-medium text-gray-800">{{ $evaluation->evaluation_period ?? $evaluation->created_at->format('M Y') }}</p>
                                <p class="text-sm text-gray-500">Adviser: {{ $evaluation->adviser->first_name ?? 'Unknown' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-gray-800">{{ round($evaluation->overall_score, 2) }} / 5.00</p>
                                <span class="competency-level {{ strtolower($evaluation->competency_level ?? 'Beginner') }}">
                                    {{ $evaluation->competency_level ?? 'Beginner' }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-400 text-sm">No competency evaluations yet.</p>
            @endif

        </div>

    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="{{ route('adviser.dashboard') }}" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
        <a href="{{ route('adviser.evaluations') }}" class="nav-item"><i class="fas fa-clipboard-list"></i><span>Evaluations</span></a>
        <a href="{{ route('adviser.referrals') }}" class="nav-item"><i class="fas fa-arrow-right"></i><span>Referrals</span></a>
        <a href="{{ route('adviser.helpers') }}" class="nav-item active"><i class="fas fa-users"></i><span>Helpers</span></a>
        <a href="{{ route('adviser.notifications') }}" class="nav-item"><i class="fas fa-bell"></i><span>Alerts</span></a>
    </nav>

    <style>
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
        .bottom-nav .nav-item.active { color: #04A052; }
        .bottom-nav .nav-item.active i { color: #04A052; }
        @media (max-width: 768px) { .bottom-nav { display: flex; } }
    </style>

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
        });
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>