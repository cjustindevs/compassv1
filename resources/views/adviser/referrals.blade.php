<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>COMPASS – Referral Queue</title>

    @vite(['resources/js/app.js', 'resources/js/adviser-notifications.js'])
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
            --red-500: #EF4444;
            --yellow-500: #F59E0B;
            --blue-500: #3B82F6;
            --green-400: #34D399;
        }

        body { background: #F8FBF9; }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(20px);
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
        .sidebar .logo span { font-weight: 700; font-size: 20px; color: var(--green-700); }

        .sidebar .nav { flex: 1; overflow-y: auto; }
        .sidebar .nav .nav-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-400);
            padding: 12px 14px 6px;
        }
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
        .sidebar .user-section .user-card .info .name { font-weight: 600; font-size: 14px; color: var(--gray-800); }
        .sidebar .user-section .user-card .info .role { font-size: 12px; color: var(--gray-400); }
        .sidebar .user-section .user-stats {
            display: flex;
            justify-content: space-around;
            padding: 12px 0;
            border-bottom: 1px solid rgba(4,160,82,0.06);
            margin-bottom: 12px;
        }
        .sidebar .user-section .user-stats .stat { text-align: center; }
        .sidebar .user-section .user-stats .stat .value { display: block; font-weight: 700; font-size: 14px; color: var(--gray-800); }
        .sidebar .user-section .user-stats .stat .label { font-size: 10px; color: var(--gray-400); text-transform: uppercase; letter-spacing: 0.03em; }
        .sidebar .user-section .logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
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
        .sidebar .user-section .logout-btn:hover { background: #FEE2E2; color: #DC2626; }
        .sidebar .user-section .logout-btn i { width: 20px; text-align: center; }

        .main-content {
            margin-left: 260px;
            padding: 24px 32px 80px;
            min-height: 100vh;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px 24px;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,0.04); }
        .stat-number { font-size: 28px; font-weight: 800; color: var(--gray-800); }
        .stat-label { font-size: 13px; color: var(--gray-500); }

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
        .referral-item .actions { display: flex; gap: 8px; flex-wrap: wrap; }

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
        .status-badge.pending { background: #FEF3C7; color: #D97706; }
        .status-badge.approved { background: #DBEAFE; color: #1D4ED8; }
        .status-badge.completed { background: var(--green-100); color: var(--green-700); }
        .status-badge.declined { background: #FEE2E2; color: #DC2626; }

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

        @media (min-width: 769px) { .sidebar-overlay { display: none !important; } }
        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .sidebar { width: 280px; padding: 16px; }
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .stat-number { font-size: 22px; }
            .grid-cols-3 { grid-template-columns: 1fr; }
            .referral-item { flex-direction: column; align-items: flex-start; gap: 10px; }
            .referral-item .actions { width: 100%; }
            .referral-item .actions .btn-primary,
            .referral-item .actions .btn-outline,
            .referral-item .actions .btn-danger { width: 100%; justify-content: center; }
            .modal-box { padding: 24px 20px; }
        }
        @media (max-width: 480px) {
            .grid-cols-3 { grid-template-columns: 1fr; }
            .stat-number { font-size: 18px; }
            .card { padding: 16px; }
            .modal-box { padding: 20px 16px; }
        }
    </style>
</head>
<body>

    @include('layouts.partials.adviser-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Referral Queue</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        Review and manage referral recommendations from helpers
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Pending Review</span>
                    <span class="text-2xl">📋</span>
                </div>
                <div class="stat-number">{{ $totalPending }}</div>
                <span class="text-xs text-gray-400">Awaiting your decision</span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Emergency Cases</span>
                    <span class="text-2xl">🚨</span>
                </div>
                <div class="stat-number">{{ $emergencyCount }}</div>
                <span class="text-xs text-gray-400">Require immediate attention</span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Approved</span>
                    <span class="text-2xl">✅</span>
                </div>
                <div class="stat-number">{{ $totalApproved }}</div>
                <span class="text-xs text-gray-400">Waiting for professional</span>
            </div>
        </div>

        <!-- Pending Referrals -->
        <div class="card mb-6">
            <div class="card-header">
                <h3>Referral Pipeline</h3>
                <span class="text-sm text-gray-400">{{ $totalPending }} pending</span>
            </div>

            @if($pendingReferrals->isNotEmpty())
                <div class="space-y-1">
                    @foreach($pendingReferrals as $referral)
                        <div class="referral-item">
                            <div class="referral-info">
                                <div class="alias">
                                    {{ $referral->session->seeker->generated_alias ?? 'Anonymous' }}
                                    <span class="priority-badge {{ $referral->priority_level }}">
                                        {{ ucfirst($referral->priority_level) }}
                                    </span>
                                    <span class="status-badge pending">New</span>
                                </div>
                                <div class="details">
                                    Helper: {{ $referral->helper->first_name ?? 'Unknown' }}
                                    · {{ $referral->created_at->format('M d, Y') }}
                                </div>
                                <div class="reason text-sm text-gray-600">
                                    {{ \Illuminate\Support\Str::limit($referral->referral_reason, 60) }}
                                </div>
                            </div>
                            <div class="actions">
                                <a href="{{ route('adviser.referral.show', $referral->id) }}" class="btn-outline">
                                    <i class="fas fa-eye mr-1"></i> View
                                </a>
                                <button class="btn-primary" onclick="openApproveModal({{ $referral->id }})">
                                    <i class="fas fa-check mr-1"></i> Approve
                                </button>
                                <button class="btn-danger" onclick="openRejectModal({{ $referral->id }})">
                                    <i class="fas fa-times mr-1"></i> Reject
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 text-gray-400">
                    <i class="fas fa-check-circle text-5xl mb-3 block text-green-500"></i>
                    <p class="text-lg font-medium text-gray-600">No pending referrals</p>
                    <p class="text-sm">All referrals have been reviewed</p>
                </div>
            @endif
        </div>

        <!-- Approved Referrals -->
        @if($approvedReferrals->isNotEmpty())
            <div class="card mb-6">
                <div class="card-header">
                    <h3>Approved Referrals</h3>
                    <span class="text-sm text-gray-400">Waiting for professional</span>
                </div>
                @foreach($approvedReferrals as $referral)
                    <div class="referral-item">
                        <div class="referral-info">
                            <div class="alias text-gray-600">
                                {{ $referral->session->seeker->generated_alias ?? 'Anonymous' }}
                                <span class="status-badge approved">Approved</span>
                            </div>
                            <div class="details text-gray-400">
                                Helper: {{ $referral->helper->first_name ?? 'Unknown' }}
                                · {{ $referral->created_at->format('M d, Y') }}
                                @if($referral->professional)
                                    · Assigned to: {{ $referral->professional->first_name ?? '' }}
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('adviser.referral.show', $referral->id) }}" class="btn-outline">
                            <i class="fas fa-eye mr-1"></i> View
                        </a>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Quality supervision leads to quality support.
        </div>

    </main>

    <!-- Approve Modal -->
    <div class="modal-overlay" id="approveModal">
        <div class="modal-box">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                    <i class="fas fa-check text-green-600"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800">Approve Referral</h3>
            </div>
            <p class="text-gray-500 text-sm mb-4">Approve this referral and assign it to a psychology professional.</p>

            <form id="approveForm" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Assign to Professional</label>
                    <select name="professional_id" class="form-input w-full p-3 border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none">
                        <option value="">Select a professional...</option>
                        @foreach($professionals as $professional)
                            <option value="{{ $professional->id }}">
                                {{ $professional->first_name }} {{ $professional->last_name }}
                                @if($professional->specialization) - {{ $professional->specialization }} @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                    <textarea name="notes" class="form-input w-full p-3 border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none" rows="3" placeholder="Any additional notes..."></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="button" class="btn-outline flex-1" onclick="closeApproveModal()">Cancel</button>
                    <button type="submit" class="btn-primary flex-1">Approve Referral</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal-overlay" id="rejectModal">
        <div class="modal-box">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                    <i class="fas fa-times text-red-600"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800">Reject Referral</h3>
            </div>
            <p class="text-gray-500 text-sm mb-4">Provide a reason for rejecting this referral.</p>

            <form id="rejectForm" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Rejection Reason <span class="text-red-500">*</span></label>
                    <textarea name="rejection_reason" class="form-input w-full p-3 border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none" rows="4" placeholder="Explain why this referral is being rejected..." required></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="button" class="btn-outline flex-1" onclick="closeRejectModal()">Cancel</button>
                    <button type="submit" class="btn-danger flex-1">Reject Referral</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="{{ route('adviser.dashboard') }}" class="nav-item">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>
        <a href="{{ route('adviser.evaluations') }}" class="nav-item">
            <i class="fas fa-clipboard-list"></i>
            <span>Evaluations</span>
        </a>
        <a href="{{ route('adviser.referrals') }}" class="nav-item active">
            <i class="fas fa-arrow-right"></i>
            <span>Referrals</span>
        </a>
        <a href="{{ route('adviser.helpers') }}" class="nav-item">
            <i class="fas fa-users"></i>
            <span>Helpers</span>
        </a>
        <a href="{{ route('adviser.notifications') }}" class="nav-item">
            <i class="fas fa-bell"></i>
            <span>Alerts</span>
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

            document.querySelectorAll('.bottom-nav .nav-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    document.querySelectorAll('.bottom-nav .nav-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                });
            });

            document.querySelectorAll('.sidebar .nav .nav-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    document.querySelectorAll('.sidebar .nav .nav-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                    if (window.innerWidth <= 768) closeSidebar();
                });
            });
        });

        function openApproveModal(id) {
            document.getElementById('approveModal').classList.add('active');
            document.getElementById('approveForm').action = '{{ route('adviser.referral.approve', ['id' => '__ID__']) }}'.replace('__ID__', id);
        }

        function closeApproveModal() {
            document.getElementById('approveModal').classList.remove('active');
        }

        function openRejectModal(id) {
            document.getElementById('rejectModal').classList.add('active');
            document.getElementById('rejectForm').action = '{{ route('adviser.referral.reject', ['id' => '__ID__']) }}'.replace('__ID__', id);
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('active');
        }

        // Close modals on overlay click
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>