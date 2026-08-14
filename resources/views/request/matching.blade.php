<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>COMPASS – Finding a Helper</title>

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Real-time seeker notifications (Echo + Reverb) -->
    @vite(['resources/js/app.js', 'resources/js/seeker-notifications.js'])

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
            --gray-900: #111827;
            --red-500: #EF4444;
            --orange-500: #F59E0B;
            --yellow-500: #EAB308;
        }

        body { background: #F8FBF9; }

        /* ─── Sidebar ─── */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-right: 1px solid rgba(4,160,82,0.06);
            box-shadow: 4px 0 40px rgba(0,0,0,0.02);
            z-index: 100;
            transition: transform 0.35s cubic-bezier(0.4,0,0.2,1);
            display: flex;
            flex-direction: column;
            padding: 24px 16px 20px;
        }
        .sidebar.closed { transform: translateX(-100%); }

        .sidebar .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 24px;
            border-bottom: 1px solid rgba(4,160,82,0.06);
            margin-bottom: 20px;
        }
        .sidebar .logo .icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, #38C172, #038A45);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 20px;
            box-shadow: 0 4px 16px rgba(4,160,82,0.2);
        }
        .sidebar .logo span {
            font-weight: 700;
            font-size: 20px;
            color: var(--green-700);
        }

        .sidebar .nav { flex: 1; overflow-y: auto; }
        .sidebar .nav .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 14px;
            border-radius: 12px;
            color: var(--gray-500);
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            margin-bottom: 2px;
        }
        .sidebar .nav .nav-item i { width: 20px; text-align: center; font-size: 16px; color: var(--gray-400); }
        .sidebar .nav .nav-item:hover { background: var(--green-50); color: var(--gray-800); }
        .sidebar .nav .nav-item:hover i { color: var(--green-500); }
        .sidebar .nav .nav-item.active {
            background: var(--green-50);
            color: var(--green-700);
            font-weight: 600;
        }
        .sidebar .nav .nav-item.active i { color: var(--green-500); }
        .sidebar .nav .nav-item .badge {
            margin-left: auto;
            background: var(--green-500);
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
        }

        .sidebar .user-section {
            border-top: 1px solid rgba(4,160,82,0.06);
            padding-top: 16px;
            margin-top: auto;
        }
        .sidebar .user-section .user-card {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sidebar .user-section .user-card .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #38C172, #038A45);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
        }
        .sidebar .user-section .user-card .info .name {
            font-weight: 600;
            font-size: 14px;
            color: var(--gray-800);
        }
        .sidebar .user-section .user-card .info .role {
            font-size: 12px;
            color: var(--gray-400);
        }
        .sidebar .user-section .logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 12px;
            padding: 8px 12px;
            border-radius: 10px;
            color: var(--gray-500);
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
            background: transparent;
            width: 100%;
        }
        .sidebar .user-section .logout-btn:hover {
            background: #FEE2E2;
            color: #DC2626;
        }
        .sidebar .user-section .logout-btn i { width: 20px; text-align: center; }

        .main-content {
            margin-left: 260px;
            padding: 24px 40px 80px;
            min-height: 100vh;
        }

        /* ─── Card Styles ─── */
        .form-card {
            background: white;
            border-radius: 24px;
            padding: 32px 36px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 4px 20px rgba(0,0,0,0.01);
        }

        .step-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
        }
        .step-dot {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            transition: all 0.3s ease;
            border: 2px solid var(--gray-200);
            color: var(--gray-400);
        }
        .step-dot.active {
            background: var(--green-500);
            color: white;
            border-color: var(--green-500);
        }
        .step-dot.done {
            background: var(--green-100);
            color: var(--green-600);
            border-color: var(--green-300);
        }
        .step-line {
            flex: 1;
            height: 2px;
            background: var(--gray-200);
            border-radius: 2px;
        }
        .step-line.done { background: var(--green-300); }

        .risk-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .risk-badge.low { background: var(--green-100); color: var(--green-700); }
        .risk-badge.moderate { background: #FEF3C7; color: #D97706; }
        .risk-badge.high { background: #FEE2E2; color: #DC2626; }
        .risk-badge.emergency { background: #FEE2E2; color: #DC2626; }

        .resource-card {
            background: var(--gray-50);
            border-radius: 16px;
            padding: 20px 24px;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .resource-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.04);
            border-color: var(--green-300);
        }
        .resource-card .icon {
            font-size: 28px;
            margin-bottom: 8px;
        }
        .resource-card h4 {
            font-weight: 600;
            font-size: 15px;
            color: var(--gray-800);
        }
        .resource-card p {
            font-size: 13px;
            color: var(--gray-500);
            margin-top: 2px;
        }
        .resource-card .duration {
            font-size: 12px;
            color: var(--gray-400);
            margin-top: 8px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--green-500), var(--green-600));
            color: white;
            font-weight: 600;
            padding: 14px 36px;
            border-radius: 50px;
            border: none;
            box-shadow: 0 4px 20px rgba(4,160,82,0.2);
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(4,160,82,0.3);
        }

        .btn-outline {
            background: transparent;
            color: var(--gray-600);
            font-weight: 600;
            padding: 14px 28px;
            border-radius: 50px;
            border: 1.5px solid var(--gray-200);
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--gray-300);
        }

        /* ─── Bottom Nav ─── */
        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.94);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-top: 1px solid var(--gray-200);
            padding: 6px 0 env(safe-area-inset-bottom, 6px);
            z-index: 200;
            justify-content: space-around;
        }
        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0px;
            color: var(--gray-400);
            text-decoration: none;
            font-size: 10px;
            font-weight: 500;
            padding: 4px 12px;
            transition: all 0.2s ease;
        }
        .bottom-nav .nav-item i { font-size: 20px; }
        .bottom-nav .nav-item.active { color: var(--green-500); }
        .bottom-nav .nav-item.active i { color: var(--green-500); }

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

        /* ─── Responsive ─── */
        @media (min-width: 769px) {
            .sidebar-overlay { display: none !important; }
        }
        @media (max-width: 1024px) {
            .main-content { padding: 20px 24px 80px; }
            .form-card { padding: 24px 20px; }
        }
        @media (max-width: 768px) {
            .sidebar { width: 280px; padding: 16px; }
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .form-card { padding: 20px 16px; border-radius: 16px; }
            .btn-primary, .btn-outline { padding: 12px 24px; font-size: 14px; width: 100%; justify-content: center; }
            .step-dot { width: 28px; height: 28px; font-size: 11px; }
        }
        @media (max-width: 480px) {
            .form-card { padding: 16px 12px; }
            .step-dot { width: 24px; height: 24px; font-size: 10px; }
            .resource-card { padding: 16px; }
        }
    </style>
</head>
<body>

    @include('partials.sidebar', [
        'active' => ['request.matching*'],
        'role'   => 'Help Seeker',
    ])

    <!-- ══════════════════════════════════════════════ -->
    <!-- MAIN CONTENT                                 -->
    <!-- ══════════════════════════════════════════════ -->

    <main class="main-content">

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Request Peer Support</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        <span class="text-green-600">Step 3 of 3</span> · Matching
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
            </div>
        </div>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step-dot done">✓</div>
            <div class="step-line done"></div>
            <div class="step-dot done">✓</div>
            <div class="step-line done"></div>
            <div class="step-dot active">3</div>
        </div>

        <!-- ─── MATCHING CARD ─── -->
        <div class="form-card">

            <!-- Live reassignment state (filled in real time by seeker-notifications.js) -->
            <div id="matchingLiveState" class="hidden"></div>

            <!-- Risk Summary -->
            <div class="mb-4 p-4 bg-gray-50 rounded-xl flex items-center justify-between flex-wrap gap-3">
                <div>
                    <span class="text-sm font-medium text-gray-600">Risk Classification:</span>
                    @php $riskLevel = $session->risk_level ?? session('risk_level', 'low'); @endphp
                    <span class="risk-badge {{ $riskLevel }}">{{ ucfirst($riskLevel) }}</span>
                </div>
                <span class="text-xs text-gray-400">Updated in real time</span>
            </div>

            <!-- ============================================ -->
            <!-- SESSION ACTIVE (helper accepted)             -->
            <!-- ============================================ -->
            @if($session->isActive())
                <div class="text-center py-4">
                    <div class="text-5xl mb-3">🎉</div>
                    <h2 class="text-2xl font-bold text-gray-800">Session started!</h2>
                    <p class="text-gray-500 mt-2">A helper accepted your request. Your session is now active.</p>

                    <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
                        <a href="{{ route($session->session_type === 'voice' ? 'session.voice' : 'session.chat') }}" class="btn-primary">
                            <i class="fas fa-comment mr-2"></i> Go to Chat
                        </a>
                        <a href="{{ route('seeker.dashboard') }}" class="btn-outline w-full sm:w-auto">
                            <i class="fas fa-home mr-2"></i> Dashboard
                        </a>
                    </div>
                </div>

            <!-- ============================================ -->
            <!-- HELPER ASSIGNED (waiting for acceptance)      -->
            <!-- ============================================ -->
            @elseif($session->isHelperAssigned())
                <div class="text-center py-4">
                    <div class="text-5xl mb-3">⏳</div>
                    <h2 class="text-2xl font-bold text-gray-800">Waiting for helper to accept</h2>
                    <p class="text-gray-500 mt-2 max-w-md mx-auto">
                        A helper has been notified about your request. Please wait while they review it.
                    </p>

                    @if($availableHelper)
                        <div class="mt-6 p-4 bg-green-50 rounded-xl border border-green-200 max-w-md mx-auto">
                            <div class="flex items-center gap-4">
                                <div class="w-14 h-14 rounded-full bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center text-white text-xl font-bold flex-shrink-0">
                                    {{ substr($availableHelper->first_name ?? 'H', 0, 1) }}
                                </div>
                                <div class="text-left">
                                    <p class="font-semibold text-gray-800">{{ $availableHelper->full_name }}</p>
                                    <p class="text-sm text-gray-500">{{ $availableHelper->specializations ?: 'Peer support' }}</p>
                                    <span class="text-xs text-green-600 font-medium">Level {{ $availableHelper->competency_level }} Peer Helper</span>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="mt-5 flex justify-center">
                        <div class="animate-pulse flex space-x-2">
                            <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                            <div class="w-3 h-3 bg-green-500 rounded-full animation-delay-200"></div>
                            <div class="w-3 h-3 bg-green-500 rounded-full animation-delay-400"></div>
                        </div>
                    </div>

                    <p class="text-sm text-gray-400 mt-4">
                        📋 Request {{ $session->reference_number }} · Submitted {{ $session->created_at?->diffForHumans() }}
                    </p>
                    <p class="text-sm text-gray-400 mt-1">
                        You can close this page and check back later — your request is saved.
                    </p>

                    <!-- Resources while waiting -->
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <h3 class="font-semibold text-gray-800 mb-4">While waiting, try these</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            @foreach($resources as $resource)
                                <div class="resource-card" onclick="window.location.href='{{ $resource['link'] }}'">
                                    <div class="icon">{{ $resource['icon'] }}</div>
                                    <h4>{{ $resource['title'] }}</h4>
                                    <p>{{ $resource['description'] }}</p>
                                    <span class="duration"><i class="fas fa-clock"></i> {{ $resource['duration'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-6 pt-6 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-center gap-4">
                        <form method="POST" action="{{ route('request.matching.decline') }}" onsubmit="return confirm('Decline this helper? You will be placed back in the queue.')">
                            @csrf
                            <button type="submit" class="btn-outline w-full">
                                <i class="fas fa-times mr-2"></i> Decline &amp; Stay in Queue
                            </button>
                        </form>
                    </div>
                </div>

            <!-- ============================================ -->
            <!-- IN QUEUE (no helper available yet)            -->
            <!-- ============================================ -->
            @else
                <div id="noHelperSection" class="text-center py-4">
                    <div class="text-5xl mb-4">🔍</div>
                    <h2 class="text-xl font-bold text-gray-800">Looking for a helper...</h2>
                    <p class="text-gray-500 mt-2 max-w-md mx-auto">
                        You're in the queue. You will be notified the moment a trained peer helper accepts your request.
                    </p>

                    <div class="mt-4 text-sm text-gray-500">
                        <p>📋 Request {{ $session->reference_number }} · Submitted {{ $session->created_at?->diffForHumans() }}</p>
                        <p class="mt-1 text-green-600">
                            🟢 {{ $availableHelperCount }} {{ $availableHelperCount === 1 ? 'helper is' : 'helpers are' }} online now
                        </p>
                        <p class="text-gray-400 mt-1">You can close this page. Your request is saved and you can come back anytime.</p>
                    </div>

                    <p class="text-xs text-gray-400 mt-2">
                        <i class="fas fa-info-circle mr-1"></i> This page checks again automatically every 30 seconds.
                    </p>

                    <!-- ============================================ -->
                    <!-- RESOURCES WHILE WAITING                     -->
                    <!-- ============================================ -->
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <h3 class="font-semibold text-gray-800 mb-4">In the meantime, try these</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            @foreach($resources as $resource)
                                <div class="resource-card" onclick="window.location.href='{{ $resource['link'] }}'">
                                    <div class="icon">{{ $resource['icon'] }}</div>
                                    <h4>{{ $resource['title'] }}</h4>
                                    <p>{{ $resource['description'] }}</p>
                                    <span class="duration"><i class="fas fa-clock"></i> {{ $resource['duration'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- ============================================ -->
                    <!-- ACTIONS                                     -->
                    <!-- ============================================ -->
                    <div class="mt-6 pt-6 border-t border-gray-200 flex flex-col sm:flex-row items-center justify-center gap-4">
                        <a href="{{ route('seeker.dashboard') }}" class="btn-outline w-full sm:w-auto">
                            <i class="fas fa-home mr-2"></i> Return to Dashboard
                        </a>
                        <button class="btn-primary w-full sm:w-auto" onclick="window.location.reload()">
                            <i class="fas fa-redo mr-2"></i> Check Status
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-3">
                        Leaving this page keeps your place in the queue — you can come back anytime.
                    </p>
                </div>
            @endif

        </div>

        <!-- Emergency Banner -->
        <div class="mt-6 p-4 bg-red-50 rounded-xl border border-red-200 flex items-center gap-4 flex-wrap">
            <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
            <div>
                <p class="text-sm font-medium text-red-700">Need immediate help?</p>
                <p class="text-xs text-red-600">Use the Emergency button for urgent support.</p>
            </div>
            <a href="{{ route('emergency') }}" class="px-6 py-2 bg-red-500 text-white text-sm font-semibold rounded-full hover:bg-red-600 transition ml-auto">Emergency</a>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            You are not alone. We are here for you.
        </div>

    </main>

    <!-- ══════════════════════════════════════════════ -->
    <!-- JAVASCRIPT                                   -->
    <!-- ══════════════════════════════════════════════ -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ── Auto re-check for an available helper while waiting ──
            // The server queries the helpers table; when one becomes
            // available the session is assigned and this page reloads.
            const noHelperSection = document.getElementById('noHelperSection');
            if (noHelperSection) {
                setTimeout(function() {
                    window.location.reload();
                }, 30000);
            }
        });
    </script>

</body>
</html>