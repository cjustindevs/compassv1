@extends('layouts.app')

@section('title', 'COMPASS – Referral Detail')

@push('styles')
<style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F8FBF9; }

        .card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 20px rgba(0,0,0,0.01);
        }

        .priority-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .priority-badge.low { background: #e5e7eb; color: #6b7280; }
        .priority-badge.moderate { background: #fef3c7; color: #d97706; }
        .priority-badge.high { background: #fee2e2; color: #dc2626; }
        .priority-badge.emergency { background: #fee2e2; color: #dc2626; animation: pulse-risk 1.5s infinite; }
        @keyframes pulse-risk { 0%,100% { opacity: 1; } 50% { opacity: 0.6; } }

        .btn-primary {
            background: linear-gradient(135deg, #04A052, #038A45);
            color: white;
            font-weight: 600;
            padding: 12px 28px;
            border-radius: 50px;
            border: none;
            box-shadow: 0 4px 20px rgba(4,160,82,0.2);
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(4,160,82,0.3); }

        .btn-outline {
            background: transparent;
            color: #6b7280;
            font-weight: 600;
            padding: 12px 28px;
            border-radius: 50px;
            border: 1.5px solid #e5e7eb;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-outline:hover { background: #f3f4f6; }

        .btn-danger {
            background: #EF4444;
            color: white;
            padding: 12px 28px;
            border-radius: 50px;
            border: none;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-danger:hover { background: #DC2626; transform: translateY(-2px); }

        .flash-success { background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }
        .flash-error { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }

        @media (max-width: 768px) {
            .card { padding: 16px; }
            .btn-primary, .btn-outline, .btn-danger { padding: 10px 20px; font-size: 14px; width: 100%; justify-content: center; }
        }
    </style>
@endpush

@section('content')
    @if ($referral->session?->risk_level === 'emergency')
        <form class="form-maximized card mb-4" method="POST" action="{{ route('identity.emergency-review', $referral->session) }}">
            @csrf
            <label>Emergency identity access review
                <textarea name="notes" required minlength="20" maxlength="1000" class="block w-full" placeholder="Record your review of the emergency responder's access."></textarea>
            </label>
            <button class="btn-primary" type="submit">Record access review</button>
        </form>
    @endif
    @if ($referral->approved_at && $referral->help_seeker_consent && $referral->professional_id)
        <form class="form-maximized card mb-4" method="POST" action="{{ route('identity.release', $referral) }}">
            @csrf
            <p>Authorize release of the seeker's stored identity to the assigned psychology professional. You will not see the identity fields.</p>
            <button class="btn-primary" type="submit">Authorize identity release</button>
        </form>
    @endif
<div class="adviser-page-content">
        <!-- Top Bar -->
        <div class="flex items-center gap-4 mb-6">
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-800">Referral Detail</h1>
                <p class="text-sm text-gray-500 hidden sm:block">
                    Referral information and actions
                </p>
            </div>
        </div>

        @if(session('success'))
            <div class="flash-success"><i class="fas fa-check-circle mr-1"></i> {{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="flash-error"><i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}</div>
        @endif

        <!-- Back Link -->
        <a href="{{ route('adviser.referrals') }}" class="inline-flex items-center gap-2 text-gray-500 hover:text-gray-700 mb-6 text-sm font-medium">
            <i class="fas fa-arrow-left"></i> Back to Referral Queue
        </a>

        <div class="card">

            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-6 pb-4 border-b border-gray-200">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Referral Details</h1>
                    <p class="text-sm text-gray-500">Review referral recommendation</p>
                </div>
                <div>
                    <span class="priority-badge {{ $referral->priority_level }}">
                        {{ ucfirst($referral->priority_level) }}
                    </span>
                    <span class="ml-2 text-sm text-gray-500">#{{ $referral->id }}</span>
                </div>
            </div>

            <!-- Info Grid -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-gray-50 rounded-xl mb-6 text-sm">
                <div>
                    <span class="text-gray-400 text-xs uppercase tracking-wider">Seeker</span>
                    <p class="font-medium text-gray-800">{{ $referral->session->seeker->generated_alias ?? 'Anonymous' }}</p>
                </div>
                <div>
                    <span class="text-gray-400 text-xs uppercase tracking-wider">Helper</span>
                    <p class="font-medium text-gray-800">{{ $referral->helper->first_name ?? 'Unknown' }} {{ $referral->helper->last_name ?? '' }}</p>
                </div>
                <div>
                    <span class="text-gray-400 text-xs uppercase tracking-wider">Date</span>
                    <p class="font-medium text-gray-800">{{ $referral->created_at->format('M d, Y') }}</p>
                </div>
                <div>
                    <span class="text-gray-400 text-xs uppercase tracking-wider">Status</span>
                    <p class="font-medium text-gray-800">{{ ucfirst(str_replace('_', ' ', $referral->status)) }}</p>
                </div>
            </div>

            <!-- Referral Reason -->
            <div class="mb-6">
                <h4 class="font-semibold text-gray-700 text-sm mb-2">Reason for Referral</h4>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-gray-600">{{ $referral->referral_reason }}</p>
                </div>
            </div>

            <!-- Session Notes -->
            <div class="mb-6">
                <h4 class="font-semibold text-gray-700 text-sm mb-2">Session Notes</h4>
                <div class="p-4 bg-gray-50 rounded-xl">
                    <p class="text-gray-600">{{ $sessionReport->session_summary ?? 'No session summary available.' }}</p>
                    @if($sessionReport && $sessionReport->personal_reflection)
                        <p class="text-gray-500 text-sm mt-2"><strong>Reflection:</strong> {{ $sessionReport->personal_reflection }}</p>
                    @endif
                </div>
            </div>

            <!-- Consent Status -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 p-4 bg-gray-50 rounded-xl">
                <div>
                    <span class="text-gray-400 text-xs uppercase tracking-wider">Help Seeker Consent</span>
                    <p class="font-medium text-gray-800">
                        @if($referral->help_seeker_consent)
                            <i class="fas fa-check-circle text-green-500"></i> Granted
                        @else
                            <i class="fas fa-times-circle text-red-500"></i> Not Granted
                        @endif
                    </p>
                </div>
                <div>
                    <span class="text-gray-400 text-xs uppercase tracking-wider">Identity Disclosed</span>
                    <p class="font-medium text-gray-800">
                        @if($referral->identity_disclosed)
                            <i class="fas fa-check-circle text-green-500"></i> Yes
                        @else
                            <i class="fas fa-times-circle text-red-500"></i> No
                        @endif
                    </p>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap gap-3 pt-4 border-t border-gray-200">
                <a href="{{ route('adviser.session.show', $referral->session_id) }}" class="btn-outline">
                    <i class="fas fa-eye mr-2"></i> View Session
                </a>
                <a href="{{ route('adviser.referrals') }}" class="btn-outline">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
                <form class="inline" method="POST" action="{{ route('adviser.referral.approve', $referral->id) }}">
                    @csrf
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-check mr-2"></i> Approve Referral
                    </button>
                </form>
                <button class="btn-danger" onclick="openRejectModal({{ $referral->id }})">
                    <i class="fas fa-times mr-2"></i> Reject Referral
                </button>
            </div>

        </div>
</div>
    <!-- Reject Modal -->
    <div class="modal-overlay" id="rejectModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); z-index: 999; align-items: center; justify-content: center;">
        <div class="modal-box" style="background: white; border-radius: 24px; max-width: 480px; width: 92%; padding: 32px; box-shadow: 0 40px 80px rgba(0,0,0,0.15); animation: modalSlide 0.3s ease-out;">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                    <i class="fas fa-times text-red-600"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800">Reject Referral</h3>
            </div>
            <p class="text-gray-500 text-sm mb-4">Provide a reason for rejecting this referral.</p>

            <form class="form-maximized" id="rejectForm" method="POST" action="{{ route('adviser.referral.reject', $referral->id) }}">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Rejection Reason <span class="text-red-500">*</span></label>
                    <textarea name="rejection_reason" class="w-full p-3 border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none" rows="4" placeholder="Explain why this referral is being rejected..." required></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="button" class="btn-outline flex-1" onclick="closeRejectModal()">Cancel</button>
                    <button type="submit" class="btn-danger flex-1">Reject Referral</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .modal-overlay { display: none; }
        .modal-overlay.active { display: flex !important; }
        @keyframes modalSlide { from { transform: scale(0.95) translateY(20px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }
    </style>

    <script>
        function openRejectModal(id) {
            document.getElementById('rejectModal').classList.add('active');
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('active');
        }

        document.getElementById('rejectModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
});
    </script>
@endsection
