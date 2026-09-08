<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Referral #{{ $referral->id }}</title>

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
            .info-row { flex-direction: column; gap: 2px; }
            .info-row .value { text-align: left; }
            .modal-box { padding: 24px 20px; }
        }
    </style>
</head>
<body class="compass-compact">

    @include('layouts.partials.professional-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">
        @if ($referral->identity_disclosed && $referral->help_seeker_consent)
            <a href="{{ route('identity.show', $referral) }}" class="inline-block bg-green-700 text-white p-3 rounded mb-4">View released identity</a>
        @endif
        @if ($referral->session?->risk_level === 'emergency' && in_array(auth()->id(), config('identity_vault.emergency_responder_ids'), true))
            <form method="POST" action="{{ route('identity.emergency', $referral->session) }}" class="mb-4">
                @csrf
                <label>Life-threatening emergency justification <textarea name="reason" required minlength="20" maxlength="1000" class="block w-full"></textarea></label>
                <button class="bg-red-700 text-white p-3 rounded">Open emergency identity (audited)</button>
            </form>
        @endif

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">
                        Referral #{{ $referral->id }}
                        <span class="priority-badge {{ $referral->priority_level }} ml-2">{{ ucfirst($referral->priority_level) }}</span>
                        <span class="status-badge {{ $referral->status }} ml-1">{{ ucfirst(str_replace('_', ' ', $referral->status)) }}</span>
                    </h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        Submitted {{ $referral->created_at->format('M d, Y g:i A') }}
                    </p>
                </div>
            </div>
            <a href="{{ route('professional.referrals') }}" class="btn-outline">
                <i class="fas fa-arrow-left mr-1"></i> Back to Referrals
            </a>
        </div>

        <!-- Action Bar -->
        <div class="flex gap-3 mb-6 flex-wrap">
            @if($referral->status === \App\Models\Referral::STATUS_PENDING_PROFESSIONAL)
                <form method="POST" action="{{ route('professional.referral.accept', $referral->id) }}">
                    @csrf
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-check mr-1"></i> Accept Referral
                    </button>
                </form>
                <button class="btn-danger" onclick="openDeclineModal()">
                    <i class="fas fa-times mr-1"></i> Decline
                </button>
            @endif

            @if($referral->status === \App\Models\Referral::STATUS_ACCEPTED)
                <form method="POST" action="{{ route('professional.referral.start', $referral->id) }}">
                    @csrf
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-play mr-1"></i> Start Case
                    </button>
                </form>
            @endif

            @if(in_array($referral->status, \App\Models\Referral::ACTIVE_STATUSES, true))
                <a href="{{ route('professional.cases.show', $referral->id) }}" class="btn-outline">
                    <i class="fas fa-folder-open mr-1"></i> Open Case File
                </a>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Referral Information -->
            <div class="card">
                <div class="card-header">
                    <h3>Referral Information</h3>
                </div>
                <div class="info-row">
                    <span class="label">Seeker</span>
                    <span class="value">{{ $referral->session->seeker->generated_alias ?? 'Anonymous' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Recommended By (Helper)</span>
                    <span class="value">{{ optional($referral->helper)->full_name ?? 'Unknown' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Approved By (Adviser)</span>
                    <span class="value">{{ optional($referral->adviser)->full_name ?? 'Unassigned' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Area of Concern</span>
                    <span class="value">{{ optional($referral->session->concern)->concern_name ?? 'General' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Session Type</span>
                    <span class="value">{{ ucfirst($referral->session->session_type ?? 'chat') }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Identity Disclosed</span>
                    <span class="value">{{ $referral->identity_disclosed ? 'Yes' : 'No' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Seeker Consent</span>
                    <span class="value">{{ $referral->help_seeker_consent ? 'Given' : 'Not recorded' }}</span>
                </div>
                @if($referral->decline_reason)
                    <div class="info-row">
                        <span class="label">Decline Reason</span>
                        <span class="value text-red-500">{{ $referral->decline_reason }}</span>
                    </div>
                @endif
            </div>

            <!-- Referral Reason & Case Summary -->
            <div class="card">
                <div class="card-header">
                    <h3>Referral Reason</h3>
                </div>
                <p class="text-sm text-gray-600 leading-relaxed">
                    {{ $referral->referral_reason }}
                </p>

                <div class="mt-6 border-t border-gray-100 pt-4">
                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-3">Helper Session Notes</h4>
                    @if($sessionReports->isNotEmpty())
                        @foreach($sessionReports as $report)
                            <div class="bg-gray-50 rounded-xl p-4 mb-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-semibold text-gray-500">
                                        {{ $report->created_at->format('M d, Y') }}
                                    </span>
                                    <span class="text-xs text-gray-400">
                                        {{ $report->session_summary ? 'Summary recorded' : 'No summary' }}
                                    </span>
                                </div>
                                @if($report->session_summary)
                                    <p class="text-sm text-gray-600 leading-relaxed">{{ \Illuminate\Support\Str::limit($report->session_summary, 250) }}</p>
                                @endif
                            </div>
                        @endforeach
                    @else
                        <p class="text-sm text-gray-400">No helper session notes available.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Confidentiality and care, always.
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

            <form method="POST" action="{{ route('professional.referral.decline', $referral->id) }}">
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

        function openDeclineModal() {
            document.getElementById('declineModal').classList.add('active');
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
