@extends('layouts.app')

@section('title', 'COMPASS – Referral Queue')

@push('styles')
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
        @media (max-width: 768px) {
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
@endpush

@section('content')
<div class="adviser-page-content">
        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
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
</div>
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

    <script>
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
@endsection
