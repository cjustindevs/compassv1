<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Active Cases</title>

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

        .case-table { width: 100%; border-collapse: collapse; }
        .case-table thead th {
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-400);
            padding: 10px 12px;
            border-bottom: 1px solid var(--gray-100);
            white-space: nowrap;
        }
        .case-table tbody td {
            padding: 14px 12px;
            border-bottom: 1px solid var(--gray-100);
            font-size: 13px;
            color: var(--gray-600);
            vertical-align: middle;
        }
        .case-table tbody tr { transition: background 0.15s; }
        .case-table tbody tr:hover { background: var(--gray-50); }
        .case-table tbody tr:last-child td { border-bottom: none; }
        .case-id { font-weight: 700; color: var(--green-700); }
        .case-alias { font-weight: 600; color: var(--gray-800); }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }
        .status-badge.accepted { background: #DBEAFE; color: #1D4ED8; }
        .status-badge.in_progress { background: #E0E7FF; color: #4338CA; }

        .priority-badge {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }
        .priority-badge.low { background: var(--gray-200); color: var(--gray-600); }
        .priority-badge.moderate { background: #FEF3C7; color: #D97706; }
        .priority-badge.high { background: #FEE2E2; color: #DC2626; }
        .priority-badge.emergency { background: #FEE2E2; color: #DC2626; animation: pulse-risk 1.5s infinite; }
        @keyframes pulse-risk { 0%,100% { opacity: 1; } 50% { opacity: 0.6; } }

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
        .flash-alert.info { border-left-color: #3B82F6; }
        @keyframes flashIn { from { transform: translateY(-10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        @media (min-width: 769px) { .sidebar-overlay { display: none !important; } }
        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .case-table thead { display: none; }
            .case-table, .case-table tbody, .case-table tr, .case-table td { display: block; width: 100%; }
            .case-table tbody tr {
                border: 1px solid var(--gray-100);
                border-radius: 12px;
                margin-bottom: 12px;
                padding: 8px 4px;
            }
            .case-table tbody td {
                border: none;
                padding: 8px 12px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 12px;
            }
            .case-table tbody td::before {
                content: attr(data-label);
                font-size: 10px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: var(--gray-400);
                flex-shrink: 0;
            }
            .case-table tbody td .open-btn { margin-left: auto; }
        }
        @media (max-width: 480px) {
            .card { padding: 16px; }
        }
    </style>
</head>
<body class="compass-compact">

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
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Active Cases</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        Cases currently under professional care ({{ $cases->count() }} total)
                    </p>
                </div>
            </div>
            <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
        </div>

        <!-- Cases Table -->
        <div class="card">
            @if($cases->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="case-table">
                        <thead>
                            <tr>
                                <th>Case ID</th>
                                <th>Seeker</th>
                                <th>Priority</th>
                                <th>Referral</th>
                                <th>Status</th>
                                <th>Last Session</th>
                                <th>Next Follow-up</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cases as $case)
                                @php
                                    $lastNote = $case->professionalNotes->first();
                                    $nextFollowUp = $case->professionalNotes
                                        ->whereNotNull('follow_up_date')
                                        ->where('follow_up_date', '>=', now()->toDateString())
                                        ->pluck('follow_up_date')
                                        ->max();
                                @endphp
                                <tr>
                                    <td data-label="Case ID"><span class="case-id">#{{ $case->id }}</span></td>
                                    <td data-label="Seeker"><span class="case-alias">{{ $case->session->seeker->generated_alias ?? 'Anonymous' }}</span></td>
                                    <td data-label="Priority">
                                        <span class="priority-badge {{ $case->priority_level }}">{{ ucfirst($case->priority_level) }}</span>
                                    </td>
                                    <td data-label="Referral">
                                        <span class="text-xs text-gray-400">#{{ $case->id }} · {{ $case->created_at->format('M d') }}</span>
                                    </td>
                                    <td data-label="Status">
                                        <span class="status-badge {{ $case->status }}">{{ $case->status === 'accepted' ? 'Accepted' : 'In Progress' }}</span>
                                    </td>
                                    <td data-label="Last Session">
                                        {{ optional($lastNote)->created_at?->format('M d, Y') ?? $case->updated_at->format('M d, Y') }}
                                    </td>
                                    <td data-label="Next Follow-up">
                                        {{ $nextFollowUp ? \Carbon\Carbon::parse($nextFollowUp)->format('M d, Y') : '—' }}
                                    </td>
                                    <td data-label="">
                                        <a href="{{ route('professional.cases.show', $case->id) }}" class="btn-outline open-btn">
                                            <i class="fas fa-arrow-right mr-1"></i> Open
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-16 text-gray-400">
                    <i class="fas fa-folder-open text-5xl mb-3 block text-green-500"></i>
                    <p class="text-lg font-medium text-gray-600">No active cases</p>
                    <p class="text-sm mb-4">Accept a referral to start a case</p>
                    <a href="{{ route('professional.referrals') }}" class="btn-primary">
                        <i class="fas fa-clipboard-list mr-1"></i> View Referrals
                    </a>
                </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Documented care, better outcomes.
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
        <a href="{{ route('professional.cases') }}" class="nav-item active">
            <i class="fas fa-folder-open"></i>
            <span>Cases</span>
        </a>
        <a href="{{ route('professional.reports') }}" class="nav-item">
            <i class="fas fa-chart-bar"></i>
            <span>Reports</span>
        </a>
        <a href="{{ route('professional.profile') }}" class="nav-item">
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

    @if(session('success'))
        <div class="flash-alert"><i class="fas fa-check-circle text-[#04A052] mr-2"></i>{{ session('success') }}</div>
    @endif
    @if(session('info'))
        <div class="flash-alert info"><i class="fas fa-info-circle text-blue-500 mr-2"></i>{{ session('info') }}</div>
    @endif

    @include('layouts.partials.pwa-banner')

</body>
</html>