@extends('layouts.app')

@section('title', 'COMPASS – Referral Queue')

@push('styles')
<style>
    .rq-reason { color: var(--compass-muted, #6B7280); font-size: 14px; overflow-wrap: anywhere; }
    .rq-sub { display: block; font-size: 13px; color: var(--compass-muted, #6B7280); }
    .rq-link-row { display: flex; flex-wrap: wrap; gap: 8px; }
    .rq-foot { text-align: center; }
    .rq-pager > * + * { margin-top: 12px; }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); align-items: center; justify-content: center; }
    .modal-overlay.active { display: flex; }
    .modal-box { background: white; border-radius: 24px; max-width: 500px; width: 92%; padding: 32px; box-shadow: 0 40px 80px rgba(0,0,0,0.15); animation: modalSlide 0.3s ease-out; max-height: 90vh; overflow-y: auto; }
    @keyframes modalSlide { from { transform: scale(0.95) translateY(20px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }
    @media (max-width: 768px) { .modal-box { padding: 24px 20px; } }
    @media (max-width: 480px) { .modal-box { padding: 20px 16px; } }
</style>
@endpush

@section('content')
@php
    $priorityTone = ['low' => 'neutral', 'moderate' => 'amber', 'high' => 'red', 'emergency' => 'red'];
@endphp
<div class="referral-queue-page">
    <x-ui.page-header title="Referral Queue" description="Review and manage referral recommendations from helpers" />

    @if($completionPending->isNotEmpty())
        <x-ui.card title="Completion reviews pending" subtitle="Professional reports awaiting your final decision.">
            <div class="rq-link-row">
                @foreach($completionPending as $completionReferral)
                    <a class="btn-outline" href="{{ route('adviser.referral.show',$completionReferral->case_reference) }}#completionReview">Review completion #{{ $completionReferral->id }}</a>
                @endforeach
            </div>
        </x-ui.card>
    @endif

    <x-ui.kpi-grid>
        <x-ui.kpi label="Pending Review" :value="$totalPending" tone="amber" note="Awaiting your decision" />
        <x-ui.kpi label="Emergency Cases" :value="$emergencyCount" tone="red" note="Require immediate attention" />
        <x-ui.kpi label="Approved" :value="$totalApproved" tone="green" note="Waiting for professional" />
    </x-ui.kpi-grid>

    <x-ui.card title="Referral Pipeline" :subtitle="$totalPending.' pending'">
        @if($pendingReferrals->isNotEmpty())
            <x-ui.table :stack="true" class="ui-table--wide">
                <thead>
                    <tr><th>Seeker</th><th>Priority</th><th>Status</th><th>Helper</th><th>Date</th><th>Reason</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach($pendingReferrals as $referral)
                        <tr>
                            <td data-label="Seeker">
                                {{ $referral->session->seeker->generated_alias ?? 'Anonymous' }}
                                <span class="rq-sub">Session #{{ $referral->session_id }}</span>
                            </td>
                            <td data-label="Priority"><x-ui.badge :tone="$priorityTone[$referral->priority_level] ?? 'neutral'">{{ ucfirst($referral->priority_level) }}</x-ui.badge></td>
                            <td data-label="Status"><x-ui.badge tone="amber">@if($referral->reviewed_at || $referral->approved_at || $referral->professional_id)Review recorded @elseif($referral->clarification_requested_at && ! $referral->clarification_received_at)Awaiting Helper revision @elseif($referral->clarification_received_at)Revision submitted @else New @endif</x-ui.badge></td>
                            <td data-label="Helper">{{ $referral->helper->first_name ?? 'Unknown' }}</td>
                            <td data-label="Date">{{ $referral->created_at->format('M d, Y') }}</td>
                            <td data-label="Reason" class="rq-reason">{{ \Illuminate\Support\Str::limit($referral->referral_reason, 60) }}</td>
                            <td class="ui-actions">
                                <a href="{{ route('adviser.referral.show', $referral->case_reference) }}" class="btn-outline">
                                    <x-ui-icon name="eye" class="mr-1" /> View
                                </a>
                                @if(! $referral->reviewed_at && ! $referral->approved_at && ! $referral->professional_id && ! ($referral->clarification_requested_at && ! $referral->clarification_received_at))
                                <button class="btn-primary" onclick="openApproveModal({{ $referral->id }})">
                                    <x-ui-icon name="check" class="mr-1" /> Approve
                                </button>
                                <button class="btn-danger" onclick="openRejectModal({{ $referral->id }})">
                                    <x-ui-icon name="close" class="mr-1" /> Return for Revision
                                </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @else
            <x-ui.empty title="No pending referrals" text="All referrals have been reviewed" />
        @endif
        {{ $pendingReferrals->links('vendor.pagination.compass', ['noun' => 'referrals']) }}
    </x-ui.card>

    @if($approvedReferrals->isNotEmpty())
        <x-ui.card title="Approved Referrals" subtitle="Waiting for professional">
            <x-ui.table :stack="true" class="ui-table--wide">
                <thead>
                    <tr><th>Seeker</th><th>Status</th><th>Helper</th><th>Date</th><th>Professional</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach($approvedReferrals as $referral)
                        <tr>
                            <td data-label="Seeker">
                                {{ $referral->session->seeker->generated_alias ?? 'Anonymous' }}
                                <span class="rq-sub">Session #{{ $referral->session_id }}</span>
                            </td>
                            <td data-label="Status"><x-ui.badge tone="blue">Approved</x-ui.badge></td>
                            <td data-label="Helper">{{ $referral->helper->first_name ?? 'Unknown' }}</td>
                            <td data-label="Date">{{ $referral->created_at->format('M d, Y') }}</td>
                            <td data-label="Professional">{{ $referral->professional ? ($referral->professional->first_name ?? '') : '—' }}</td>
                            <td class="ui-actions">
                                <a href="{{ route('adviser.referral.show', $referral->case_reference) }}" class="btn-outline">
                                    <x-ui-icon name="eye" class="mr-1" /> View
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            {{ $approvedReferrals->links('vendor.pagination.compass', ['noun' => 'referrals']) }}
        </x-ui.card>
    @endif

    <p class="rq-reason rq-foot">Quality supervision leads to quality support.</p>
</div>
    <!-- Approve Modal -->
    <div class="modal-overlay av-referral-modal" id="approveModal">
        <div class="modal-box av-referral-dialog">
            <div class="flex items-center gap-3 mb-4">

                <h3 class="text-xl font-bold text-gray-800">Approve Referral</h3>
            </div>
            <p class="text-gray-500 text-sm mb-4">Approve this recommendation and request the Help Seeker's consent. Professional assignment follows their decision.</p>

            <form class="form-maximized" id="approveForm" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Review reason</label>
                    <textarea name="review_notes" required maxlength="1000" class="form-input w-full p-3 border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none" rows="3" placeholder="Any additional notes..."></textarea>
                </div>
                <div class="flex gap-3 av-referral-actions">
                    <button type="button" class="btn-outline flex-1" onclick="closeApproveModal()">Cancel</button>
                    <button type="submit" class="btn-primary flex-1">Approve Referral</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal-overlay av-referral-modal" id="rejectModal">
        <div class="modal-box av-referral-dialog">
            <div class="flex items-center gap-3 mb-4">

                <h3 class="text-xl font-bold text-gray-800">Return for Revision</h3>
            </div>
            <p class="text-gray-500 text-sm mb-4">Explain what the Helper must revise before you can approve this referral.</p>

            <form class="form-maximized" id="rejectForm" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Revision comments <span class="text-red-500">*</span></label>
                    <textarea name="rejection_reason" class="form-input w-full p-3 border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none" rows="4" placeholder="Explain the required corrections..." required></textarea>
                </div>
                <div class="flex gap-3 av-referral-actions">
                    <button type="button" class="btn-outline flex-1" onclick="closeRejectModal()">Cancel</button>
                    <button type="submit" class="btn-danger flex-1">Return for Revision</button>
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
<div class="rq-pager">{{ $completedReferrals->links('vendor.pagination.compass', ['noun' => 'referrals']) }}</div>
@endsection
