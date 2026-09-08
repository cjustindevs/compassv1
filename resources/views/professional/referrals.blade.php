<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Referrals</title>

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

        .referral-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            border-bottom: 1px solid var(--gray-100);
            transition: background 0.2s;
        }
        .referral-item:hover { background: var(--gray-50); }
        .referral-item:last-child { border-bottom: none; }
        .referral-item .referral-info .alias { font-weight: 600; color: var(--gray-800); }
        .referral-item .referral-info .details { font-size: 13px; color: var(--gray-500); }
        .referral-item .referral-info .reason { font-size: 13px; color: var(--gray-600); }

        .priority-badge {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .priority-badge.low { background: var(--gray-200); color: var(--gray-600); }
        .priority-badge.moderate { background: #FEF3C7; color: #D97706; }
        .priority-badge.high { background: #FEE2E2; color: #DC2626; }
        .priority-badge.emergency { background: #FEE2E2; color: #DC2626; animation: pulse-risk 1.5s infinite; }
        @keyframes pulse-risk { 0%,100% { opacity: 1; } 50% { opacity: 0.6; } }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-badge.pending_professional { background: #FEF3C7; color: #D97706; }
        .status-badge.accepted { background: #DBEAFE; color: #1D4ED8; }
        .status-badge.in_progress { background: #E0E7FF; color: #4338CA; }
        .status-badge.completed { background: var(--green-50); color: var(--green-700); }
        .status-badge.closed { background: var(--gray-200); color: var(--gray-600); }
        .status-badge.declined { background: #FEE2E2; color: #DC2626; }

        .pipeline-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .pipeline-tab {
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid var(--gray-200);
            background: white;
            color: var(--gray-500);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .pipeline-tab:hover { border-color: var(--green-500); color: var(--green-700); }
        .pipeline-tab.active {
            background: linear-gradient(135deg, var(--green-500), var(--green-600));
            color: white;
            border-color: transparent;
        }
        .pipeline-tab .count {
            background: rgba(0,0,0,0.08);
            border-radius: 20px;
            padding: 1px 8px;
            font-size: 11px;
            font-weight: 700;
        }
        .pipeline-tab.active .count { background: rgba(255,255,255,0.25); }

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

        .btn-danger {
            background: #EF4444;
            color: white;
            padding: 8px 18px;
            border-radius: 20px;
            border: none;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-danger:hover { background: #DC2626; transform: scale(1.02); }

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(4px);
            z-index: 999;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: white;
            border-radius: 24px;
            max-width: 500px;
            width: 92%;
            padding: 32px;
            box-shadow: 0 40px 80px rgba(0,0,0,0.15);
            animation: modalSlide 0.3s ease-out;
            max-height: 90vh;
            overflow-y: auto;
        }
        @keyframes modalSlide { from { transform: scale(0.95) translateY(20px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }

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
        .flash-alert.error { border-left-color: #EF4444; }
        @keyframes flashIn { from { transform: translateY(-10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        @media (min-width: 769px) { .sidebar-overlay { display: none !important; } }
        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .referral-item { flex-direction: column; align-items: flex-start; gap: 10px; }
            .referral-item .actions { width: 100%; }
            .referral-item .actions .btn-primary,
            .referral-item .actions .btn-outline,
            .referral-item .actions .btn-danger { width: 100%; justify-content: center; }
            .modal-box { padding: 24px 20px; }
        }
        @media (max-width: 480px) {
            .card { padding: 16px; }
            .modal-box { padding: 20px 16px; }
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
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Referral Management</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        Review, accept, or decline referrals assigned to you
                    </p>
                </div>
            </div>
            <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
        </div>

        <!-- Pipeline Tabs -->
        <div class="pipeline-tabs">
            <a href="#pending" class="pipeline-tab active" data-tab="pending">
                <i class="fas fa-clock"></i> Pending <span class="count">{{ $pending->count() }}</span>
            </a>
            <a href="#accepted" class="pipeline-tab" data-tab="accepted">
                <i class="fas fa-check"></i> Accepted <span class="count">{{ $accepted->count() }}</span>
            </a>
            <a href="#in_progress" class="pipeline-tab" data-tab="in_progress">
                <i class="fas fa-spinner"></i> In Progress <span class="count">{{ $inProgress->count() }}</span>
            </a>
            <a href="#completed" class="pipeline-tab" data-tab="completed">
                <i class="fas fa-check-circle"></i> Completed <span class="count">{{ $completed->count() }}</span>
            </a>
            <a href="#declined" class="pipeline-tab" data-tab="declined">
                <i class="fas fa-times-circle"></i> Declined <span class="count">{{ $declined->count() }}</span>
            </a>
        </div>

        <!-- Pending Referrals -->
        <div class="card mb-6 tab-panel" id="panel-pending">
            <div class="card-header">
                <h3>Pending Referrals</h3>
                <span class="text-sm text-gray-400">Awaiting your decision</span>
            </div>

            @if($pending->isNotEmpty())
                <div class="space-y-1">
                    @foreach($pending as $referral)
                        <div class="referral-item">
                            <div class="referral-info">
                                <div class="alias">
                                    {{ $referral->session->seeker->generated_alias ?? 'Anonymous' }}
                                    <span class="priority-badge {{ $referral->priority_level }}">{{ ucfirst($referral->priority_level) }}</span>
                                    <span class="status-badge pending_professional">Pending</span>
                                </div>
                                <div class="details">
                                    Referral #{{ $referral->id }} · Helper: {{ optional($referral->helper)->first_name ?? 'Unknown' }}
                                    · Adviser: {{ optional($referral->adviser)->last_name ?? 'Unassigned' }}
                                    · {{ $referral->created_at->format('M d, Y') }}
                                </div>
                                <div class="reason">
                                    {{ \Illuminate\Support\Str::limit($referral->referral_reason, 70) }}
                                </div>
                            </div>
                            <div class="actions flex gap-2 flex-wrap">
                                <a href="{{ route('professional.referral.show', $referral->id) }}" class="btn-outline">
                                    <i class="fas fa-eye mr-1"></i> View
                                </a>
                                <form method="POST" action="{{ route('professional.referral.accept', $referral->id) }}">
                                    @csrf
                                    <button type="submit" class="btn-primary">
                                        <i class="fas fa-check mr-1"></i> Accept
                                    </button>
                                </form>
                                <button class="btn-danger" onclick="openDeclineModal({{ $referral->id }})">
                                    <i class="fas fa-times mr-1"></i> Decline
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 text-gray-400">
                    <i class="fas fa-check-circle text-5xl mb-3 block text-green-500"></i>
                    <p class="text-lg font-medium text-gray-600">No pending referrals</p>
                    <p class="text-sm">New referrals from advisers will appear here</p>
                </div>
            @endif
        </div>

        <!-- Accepted Referrals -->
        <div class="card mb-6 tab-panel hidden" id="panel-accepted">
            <div class="card-header">
                <h3>Accepted Referrals</h3>
                <span class="text-sm text-gray-400">Ready to start</span>
            </div>

            @if($accepted->isNotEmpty())
                <div class="space-y-1">
                    @foreach($accepted as $referral)
                        <div class="referral-item">
                            <div class="referral-info">
                                <div class="alias">
                                    {{ $referral->session->seeker->generated_alias ?? 'Anonymous' }}
                                    <span class="priority-badge {{ $referral->priority_level }}">{{ ucfirst($referral->priority_level) }}</span>
                                    <span class="status-badge accepted">Accepted</span>
                                </div>
                                <div class="details">
                                    Referral #{{ $referral->id }} · {{ $referral->created_at->format('M d, Y') }}
                                </div>
                                <div class="reason">
                                    {{ \Illuminate\Support\Str::limit($referral->referral_reason, 70) }}
                                </div>
                            </div>
                            <div class="actions flex gap-2 flex-wrap">
                                <a href="{{ route('professional.cases.show', $referral->id) }}" class="btn-outline">
                                    <i class="fas fa-eye mr-1"></i> View
                                </a>
                                <form method="POST" action="{{ route('professional.referral.start', $referral->id) }}">
                                    @csrf
                                    <button type="submit" class="btn-primary">
                                        <i class="fas fa-play mr-1"></i> Start Case
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 text-gray-400">
                    <p class="text-lg font-medium text-gray-600">No accepted referrals</p>
                    <p class="text-sm">Accept a pending referral to see it here</p>
                </div>
            @endif
        </div>

        <!-- In Progress Referrals -->
        <div class="card mb-6 tab-panel hidden" id="panel-in_progress">
            <div class="card-header">
                <h3>In Progress</h3>
                <span class="text-sm text-gray-400">Cases currently in your care</span>
            </div>

            @if($inProgress->isNotEmpty())
                <div class="space-y-1">
                    @foreach($inProgress as $referral)
                        <div class="referral-item">
                            <div class="referral-info">
                                <div class="alias">
                                    {{ $referral->session->seeker->generated_alias ?? 'Anonymous' }}
                                    <span class="priority-badge {{ $referral->priority_level }}">{{ ucfirst($referral->priority_level) }}</span>
                                    <span class="status-badge in_progress">In Progress</span>
                                </div>
                                <div class="details">
                                    Case #{{ $referral->id }} · Started {{ $referral->updated_at->format('M d, Y') }}
                                </div>
                                <div class="reason">
                                    {{ \Illuminate\Support\Str::limit($referral->referral_reason, 70) }}
                                </div>
                            </div>
                            <a href="{{ route('professional.cases.show', $referral->id) }}" class="btn-outline">
                                <i class="fas fa-arrow-right mr-1"></i> Open Case
                            </a>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 text-gray-400">
                    <p class="text-lg font-medium text-gray-600">No cases in progress</p>
                    <p class="text-sm">Start an accepted referral to begin</p>
                </div>
            @endif
        </div>

        <!-- Completed Referrals -->
        <div class="card mb-6 tab-panel hidden" id="panel-completed">
            <div class="card-header">
                <h3>Completed & Closed</h3>
                <span class="text-sm text-gray-400">{{ $completed->count() }} total</span>
            </div>

            @if($completed->isNotEmpty())
                <div class="space-y-1">
                    @foreach($completed as $referral)
                        <div class="referral-item">
                            <div class="referral-info">
                                <div class="alias">
                                    {{ $referral->session->seeker->generated_alias ?? 'Anonymous' }}
                                    <span class="status-badge {{ $referral->status }}">{{ ucfirst(str_replace('_', ' ', $referral->status)) }}</span>
                                </div>
                                <div class="details">
                                    Referral #{{ $referral->id }} ·
                                    Closed: {{ $referral->closed_date?->format('M d, Y') ?? $referral->updated_at->format('M d, Y') }}
                                </div>
                                <div class="reason">
                                    {{ \Illuminate\Support\Str::limit($referral->referral_reason, 70) }}
                                </div>
                            </div>
                            <a href="{{ route('professional.referral.show', $referral->id) }}" class="btn-outline">
                                <i class="fas fa-eye mr-1"></i> View
                            </a>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 text-gray-400">
                    <p class="text-lg font-medium text-gray-600">No completed referrals</p>
                    <p class="text-sm">Finished cases will be listed here</p>
                </div>
            @endif
        </div>

        <!-- Declined Referrals -->
        <div class="card mb-6 tab-panel hidden" id="panel-declined">
            <div class="card-header">
                <h3>Declined Referrals</h3>
                <span class="text-sm text-gray-400">{{ $declined->count() }} total</span>
            </div>

            @if($declined->isNotEmpty())
                <div class="space-y-1">
                    @foreach($declined as $referral)
                        <div class="referral-item">
                            <div class="referral-info">
                                <div class="alias">
                                    {{ $referral->session->seeker->generated_alias ?? 'Anonymous' }}
                                    <span class="status-badge declined">Declined</span>
                                </div>
                                <div class="details">
                                    Referral #{{ $referral->id }} · {{ $referral->created_at->format('M d, Y') }}
                                </div>
                                @if($referral->decline_reason)
                                    <div class="reason">
                                        <span class="text-red-500"><i class="fas fa-info-circle mr-1"></i></span>{{ \Illuminate\Support\Str::limit($referral->decline_reason, 70) }}
                                    </div>
                                @endif
                            </div>
                            <a href="{{ route('professional.referral.show', $referral->id) }}" class="btn-outline">
                                <i class="fas fa-eye mr-1"></i> View
                            </a>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 text-gray-400">
                    <p class="text-lg font-medium text-gray-600">No declined referrals</p>
                    <p class="text-sm">Referrals you decline will appear here</p>
                </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Every referral is an opportunity to change a life.
        </div>

    </main>

    <!-- Decline Modal -->
    <div class="modal-overlay" id="declineModal">
        <div class="modal-box">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                    <i class="fas fa-times text-red-600"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800">Decline Referral</h3>
            </div>
            <p class="text-gray-500 text-sm mb-4">Provide a reason for declining this referral. The adviser will be notified.</p>

            <form id="declineForm" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Decline Reason <span class="text-red-500">*</span></label>
                    <textarea name="decline_reason" class="w-full p-3 border border-gray-300 rounded-xl focus:border-red-500 focus:ring-2 focus:ring-red-200 outline-none" rows="4" placeholder="e.g. Case is outside my area of specialization..." required></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="button" class="btn-outline flex-1" onclick="closeDeclineModal()">Cancel</button>
                    <button type="submit" class="btn-danger flex-1">Decline Referral</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="{{ route('professional.dashboard') }}" class="nav-item">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>
        <a href="{{ route('professional.referrals') }}" class="nav-item active">
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

            // Pipeline tab switching
            const tabs = document.querySelectorAll('.pipeline-tab');
            const panels = document.querySelectorAll('.tab-panel');

            function activateTab(name) {
                tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === name));
                panels.forEach(p => p.classList.toggle('hidden', p.id !== 'panel-' + name));
            }

            tabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    activateTab(this.dataset.tab);
                    history.replaceState(null, '', '#' + this.dataset.tab);
                });
            });

            // Deep-link support (e.g. ?tab=declined or #declined)
            const initialTab = window.location.hash.replace('#', '') || 'pending';
            if (['pending', 'accepted', 'in_progress', 'completed', 'declined'].includes(initialTab)) {
                activateTab(initialTab);
            }

            document.querySelectorAll('.flash-alert').forEach(alert => {
                setTimeout(() => alert.remove(), 5000);
            });
        });

        function openDeclineModal(id) {
            document.getElementById('declineModal').classList.add('active');
            document.getElementById('declineForm').action = '{{ route('professional.referral.decline', ['id' => '__ID__']) }}'.replace('__ID__', id);
        }

        function closeDeclineModal() {
            document.getElementById('declineModal').classList.remove('active');
        }

        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
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