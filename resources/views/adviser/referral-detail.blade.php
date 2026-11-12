@extends('layouts.app')

@section('title', 'COMPASS – Referral Detail')

@push('styles')
<style>
    .referral-detail-page :is(p,dd){ overflow-wrap: anywhere; }
    .rd-flash { border-radius: 12px; padding: 12px 16px; font-size: 14px; font-weight: 500; margin-bottom: 16px; }
    .rd-flash p { margin: 0; }
    .rd-flash--ok { background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; }
    .rd-flash--err { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
    .rd-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px 24px; margin: 0; }
    .rd-facts dt { font-size: 13px; color: var(--compass-muted, #6B7280); }
    .rd-facts dd { margin: 2px 0 0; font-size: 15px; font-weight: 600; color: var(--compass-text, #1F2937); }
    .rd-block { margin-bottom: 16px; }
    .rd-block h3 { margin: 0 0 8px; font-size: 15px; font-weight: 700; color: var(--compass-text, #1F2937); }
    .rd-block .rd-box { margin: 0; padding: 12px 16px; background: #F9FAFB; border-radius: 12px; color: #4B5563; }
    .rd-note { margin: 0 0 12px; font-size: 14px; color: var(--compass-muted, #6B7280); }
    .rd-note--tight { margin: 8px 0 0; }
    .rd-actions { display: flex; flex-wrap: wrap; gap: 8px; padding-top: 16px; border-top: 1px solid var(--compass-border, #E5E7EB); }
    .rd-actions > * { min-width: 0; }
    .coordination-grid > div { background:#f5f9f7; border:1px solid #e5eee8; padding:10px 12px; border-radius:10px; }
    .coordination-grid p { margin: 0; font-size: 13px; color: var(--compass-muted, #6B7280); }
    .coordination-grid strong { display:block; margin-top:5px; color:#173e2c; font-size:14px; }
    .referral-release summary { cursor:pointer; font-weight:600; color:#173e2c; padding:2px 0; }
    .referral-release .release-intro { font-size:14px; color:#64748b; margin:12px 0 16px; }
    .identity-field-grid label { border:1px solid #e2e8e5; border-radius:9px; padding:10px; font-size:14px; }
    .identity-field-grid input { accent-color:#049b50; }
    .identity-field-grid label:has(input:checked) { background:#edf9f1; border-color:#90d9b0; }
    .referral-detail-page :is(input,select,textarea,button,summary):focus-visible { outline:2px solid #049b50; outline-offset:3px; }
    .coordination-grid, .identity-field-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }
    .identity-field-grid > p { grid-column:1/-1; }
    .referral-release textarea, .coordination-form textarea, .coordination-form select { width:100%; border:1px solid #d1d5db; border-radius:10px; padding:10px 12px; font:inherit; margin:6px 0 12px; }
    .referral-release textarea, .coordination-form textarea { min-height:76px; }
    .referral-next-step{border-top:3px solid #049b50}.referral-next-step .btn-primary{display:inline-flex;margin-top:12px}.referral-next-step,#professionalAssignment,#professionalIdentityRelease{scroll-margin-top:20px}
    .referral-release button:disabled { opacity:.5; cursor:not-allowed; }
    @media (max-width: 768px) {
        .coordination-grid, .identity-field-grid { grid-template-columns:1fr; }
    }
</style>
@endpush

@section('content')
@php
    $identityReady = app(\App\Services\IdentityVaultService::class)->hasCurrentSubmission($referral);
    $priorityTone = ['low' => 'neutral', 'moderate' => 'amber', 'high' => 'red', 'emergency' => 'red'];
    $statusLabel = ucwords(str_replace('_', ' ', $referral->status));
    $statusTone = in_array($referral->status, ['completed'], true) ? 'green' : (in_array($referral->status, ['declined', 'closed'], true) ? 'neutral' : 'amber');
@endphp
<div class="referral-detail-page">
    <x-ui.page-header title="Referral Detail" description="Referral information and actions"
        :back-href="route('adviser.referrals')" back-label="Back to Referral Queue" />

    @if(session('success'))
        <div class="rd-flash rd-flash--ok">{{ session('success') }}</div>
    @endif
    @if($errors->any())<div class="rd-flash rd-flash--err" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if(session('error'))
        <div class="rd-flash rd-flash--err">{{ session('error') }}</div>
    @endif

    <x-ui.card title="Summary" :subtitle="'Case reference '.$referral->case_reference">
        <x-slot:actions>
            <x-ui.badge :tone="$priorityTone[$referral->priority_level] ?? 'neutral'">{{ ucfirst($referral->priority_level) }} priority</x-ui.badge>
            <x-ui.badge :tone="$statusTone">{{ $statusLabel }}</x-ui.badge>
        </x-slot:actions>
        <dl class="rd-facts">
            <div><dt>Seeker</dt><dd>{{ $referral->session->seeker->generated_alias ?? 'Anonymous' }}</dd></div>
            <div><dt>Helper</dt><dd>{{ $referral->helper->first_name ?? 'Unknown' }} {{ $referral->helper->last_name ?? '' }}</dd></div>
            <div><dt>Professional</dt><dd>{{ $referral->professional?->full_name ?? 'Not yet assigned' }}</dd></div>
            <div><dt>Referred</dt><dd>{{ $referral->created_at->format('M d, Y') }}</dd></div>
            <div><dt>Referral</dt><dd>#{{ $referral->id }}</dd></div>
            <div><dt>Case reference</dt><dd>{{ $referral->case_reference }}</dd></div>
        </dl>
    </x-ui.card>

    @if ($referral->session?->risk_level === 'emergency')
        <form class="form-maximized ui-card" method="POST" action="{{ route('identity.emergency-review', $referral->session) }}">
            @csrf
            <label>Emergency identity access review
                <textarea name="notes" required minlength="20" maxlength="1000" class="block w-full" placeholder="Record your review of the emergency responder's access."></textarea>
            </label>
            <button class="btn-primary" type="submit">Record access review</button>
        </form>
    @endif

    @include('adviser.partials.completion-review')
    @include('adviser.partials.referral-next-step')

    <x-ui.card title="Professional coordination" aria-label="Referral coordination status">
        <div class="coordination-grid">
            <div><p>Assigned professional</p><strong>{{ $referral->professional?->full_name ?? 'Not yet assigned' }}</strong></div>
            <div><p>Referral status</p><strong>{{ $statusLabel }}</strong></div>
            <div><p>Identity details</p><strong>{{ $identityReady ? 'Current submission available' : 'Awaiting current Seeker submission' }}</strong></div>
        </div>
        @if(! $identityReady && $referral->approved_at && $referral->help_seeker_consent)
            <p role="status" class="rd-note rd-note--tight">The Seeker must open Referral decisions and submit identity disclosure. Release stays unavailable until current details are saved.</p>
        @endif
        @if($referral->professional)
            <p class="rd-note rd-note--tight">This referral is assigned to the professional shown above. Active cases retain their assignment; identity release authorizes access to selected contact details.</p>
        @endif
        @if($referral->isOpen() && (!$referral->approved_at || !$referral->help_seeker_consent))
            <div class="coordination-form" style="margin-top:12px"><label for="professionalPreview">Professional</label><select id="professionalPreview" disabled aria-describedby="professionalPreviewReason"><option>Choose a Professional after approval and Seeker consent</option></select><p id="professionalPreviewReason" role="status" class="rd-note">{{ !$referral->approved_at ? 'Review and approve the recommendation first.' : 'Waiting for Seeker referral consent in live chat.' }}</p></div>
        @endif
    </x-ui.card>

    @if($referral->approved_at && $referral->help_seeker_consent && in_array($referral->status,['pending_professional','no_professional_available']))
        @php($assignmentCandidates = $professionals->where('id', '!=', $referral->professional_id))
        <x-ui.card id="professionalAssignment" :title="$referral->professional_id ? 'Change Professional' : 'Assign a professional'">
            @if($referral->professional_id)<p class="rd-note">Currently assigned: {{ $referral->professional?->full_name }}. Record a reason to transfer the pending assignment. Identity release must be authorized for the new recipient.</p>@endif
            <p class="rd-note">After the Seeker saves their identity details, assign an available professional here. The referral appears in their review queue only after assignment. Identity release is a separate authorized action.</p>
            <form method="POST" action="{{ route('adviser.referral.assign', $referral->case_reference) }}" class="coordination-form" data-confirm="Assign and notify Professional?" data-confirm-message="The selected Professional will receive this referral for review. Personal details remain subject to separate identity release." data-confirm-text="Assign and notify">@csrf
                <label for="professional">Professional</label><select name="professional_id" id="professional" required class="w-full rounded-lg border-gray-300"><option value="">Choose a professional</option>@foreach($assignmentCandidates as $professional)<option value="{{ $professional->id }}" @selected((string)old('professional_id', $assignmentCandidates->count() === 1 ? $professional->id : '') === (string)$professional->id)>{{ $professional->full_name }}</option>@endforeach</select>
                <label for="assignmentReason">Assignment reason</label><textarea name="reason" id="assignmentReason" required maxlength="1000" class="w-full rounded-lg border-gray-300">{{ old('reason') }}</textarea>
                <button class="btn btn-primary" @disabled($assignmentCandidates->isEmpty() || ! $identityReady)>{{ $referral->professional_id ? 'Change and notify Professional' : 'Assign and notify Professional' }}</button>
                @if($assignmentCandidates->isEmpty())<p class="rd-note rd-note--tight">No other active, available Professional can receive this assignment. The current assignment remains unchanged.</p>@endif
            </form>
        </x-ui.card>
    @endif

    @if(in_array($referral->status, \App\Models\Referral::ACTIVE_STATUSES, true))
        <x-ui.card id="professionalTransfer" title="Transfer Professional responsibility">
            @php($pendingTransfer = \Illuminate\Support\Facades\DB::table('referral_professional_transfers')->where('referral_id', $referral->id)->where('status', 'pending')->first())
            @if($pendingTransfer)<p class="rd-note">Transfer #{{ $pendingTransfer->id }} is awaiting receiving Professional acceptance. The current Professional remains responsible.</p><form method="POST" action="{{ route('adviser.referral.transfer.cancel', $referral) }}">@csrf<button class="btn" type="submit">Cancel pending transfer</button></form>
            @elseif($referral->hasPendingCompletion())<p class="rd-note">Review the Professional completion report before requesting a transfer.</p>
            @else
            @php($transferCandidates = $professionals->where('id', '!=', $referral->professional_id))
            @if($transferCandidates->isEmpty())<p class="rd-note">No other active, available Professional can receive a transfer.</p>@endif
            <form class="coordination-form" method="POST" action="{{ route('adviser.referral.transfer', $referral) }}">@csrf
                <label for="transferProfessional">Receiving Professional</label><select id="transferProfessional" name="professional_id" required><option value="">Choose a different Professional</option>@foreach($transferCandidates as $professional)<option value="{{ $professional->id }}">{{ $professional->full_name }}</option>@endforeach</select>
                <label for="transferReason">Transfer reason</label><textarea id="transferReason" name="reason" required minlength="10" maxlength="1000"></textarea>
                <button class="btn-primary" type="submit" @disabled($transferCandidates->isEmpty())>Request Professional transfer</button>
            </form>@endif
        </x-ui.card>
    @endif

    @if ($referral->approved_at && $referral->help_seeker_consent && $referral->professional_id && !in_array($referral->status, ['completed', 'closed', 'declined']) && (int) $referral->adviser_id === (int) auth()->user()->adviser->id)
        <form id="professionalIdentityRelease" class="ui-card referral-release" method="POST" action="{{ route('identity.release', $referral) }}">
            @csrf
            <details open><summary>Identity release permissions</summary><p class="release-intro">Choose the contact details {{ $referral->professional->full_name }} needs for coordination. Identity values remain private.</p>
            <fieldset class="my-3" @disabled(! $identityReady)><legend class="font-semibold">Select only the necessary information</legend><div class="identity-field-grid">
                @foreach(\App\Services\IdentityVaultService::FIELDS as $field)
                    <label class="flex items-center gap-2"><input type="checkbox" name="fields[]" value="{{ $field }}" @checked(in_array($field,['phone_number','email']))> {{ ucwords(str_replace('_',' ',$field)) }}</label>
                @endforeach
            </div></fieldset>
            <label class="block my-3">Purpose of this disclosure<textarea name="reason" required minlength="20" maxlength="1000" class="block w-full rounded border-gray-300" placeholder="Explain why these fields are needed for this referral.">{{ old('reason') }}</textarea></label>
            <button class="btn-primary" type="submit" @disabled(! $identityReady)>Authorize identity release</button></details>
        </form>
    @endif

    <x-ui.card title="Referral review" subtitle="Review referral recommendation">
        <div class="rd-block">
            <h3>Reason for Referral</h3>
            <p class="rd-box">{{ $referral->referral_reason }}</p>
        </div>

        <div class="rd-block">
            <h3>Session Notes</h3>
            <div class="rd-box">
                <p style="margin:0">{{ $sessionReport->session_summary ?? 'No session summary available.' }}</p>
                @if($sessionReport && $sessionReport->personal_reflection)
                    <p class="rd-note rd-note--tight"><strong>Reflection:</strong> {{ $sessionReport->personal_reflection }}</p>
                @endif
            </div>
        </div>

        <dl class="rd-facts rd-block">
            <div><dt>Help Seeker Consent</dt><dd>{{ $referral->help_seeker_consent ? 'Granted' : 'Not Granted' }}</dd></div>
            <div><dt>Identity Disclosed</dt><dd>{{ $referral->identity_disclosed ? 'Yes' : 'No' }}</dd></div>
        </dl>

        @if($referral->clarification_requested_at && !$referral->clarification_received_at && in_array($referral->status,[\App\Models\Referral::STATUS_PENDING_ADVISER,\App\Models\Referral::STATUS_CONSENT_REQUESTED]))
            <div class="rd-flash rd-flash--err"> This referral was returned to the Helper for revision. Approval is blocked until the Helper responds with the requested clarification.</div>
        @endif

        @if($referral->approved_at && $referral->session?->screeningResponses()->exists())
            @php($intake=$referral->session->screeningResponses()->oldest('id')->first())
            @php($safetyAnswer=$intake->responses['suicidal_thoughts']??'Not recorded')
            <div class="rd-block">
                <h3>Pre-session screening summary</h3>
                <p class="rd-note" style="margin-bottom:4px">Concern: {{ $referral->session->concern?->concern_name??'Not recorded' }}</p>
                <p class="rd-note" style="margin-bottom:4px">Safety answer: {{ is_bool($safetyAnswer) ? ($safetyAnswer ? 'Yes' : 'No') : ucwords(str_replace('_',' ',(string)$safetyAnswer)) }}</p>
                <p class="rd-note" style="margin-bottom:4px">Description: {{ $intake->responses['description']??'Not provided' }}</p>
                <p class="rd-note">Recorded {{ $intake->created_at?->timezone('Asia/Manila')->format('M d, Y g:i A') }}. Original responses are retained.</p>
            </div>
        @endif

        <div class="rd-actions">
            <a href="{{ route('adviser.session.show', $referral->session_id) }}" class="btn-outline">
                <x-ui-icon name="eye" class="mr-2" /> View Session
            </a>
            @if(in_array($referral->status,[\App\Models\Referral::STATUS_PENDING_ADVISER,\App\Models\Referral::STATUS_CONSENT_REQUESTED]) && ! $referral->reviewed_at && ! $referral->approved_at && ! $referral->professional_id && !($referral->clarification_requested_at && !$referral->clarification_received_at))
            <button type="button" class="btn-outline" onclick="openReviseModal()">
                <x-ui-icon name="undo" class="mr-2" /> Request Revision
            </button>
            <button type="button" class="btn-primary" onclick="openApproveModal()">
                <x-ui-icon name="check" class="mr-2" /> Approve Referral
            </button>
            <button class="btn-danger" onclick="openRejectModal({{ $referral->id }})">
                <x-ui-icon name="close" class="mr-2" /> Reject Referral
            </button>
            @endif
        </div>
    </x-ui.card>

    <!-- Approve Modal -->
    <dialog class="workflow-dialog adviser-review-dialog" id="approveModal" aria-label="Approve referral">
        <div class="modal-box av-referral-dialog">
            <div class="flex items-center gap-3 mb-4">

                <h3 class="text-xl font-bold text-gray-800">Approve Referral</h3>
            </div>
            <p class="text-gray-500 text-sm mb-4">Record a short review note. This is stored alongside the approval for accountability.</p>

            <form class="form-maximized" id="approveForm" method="POST" action="{{ route('adviser.referral.approve', $referral->case_reference) }}"><div class="workflow-dialog__body">
                @csrf<input type="hidden" name="_workflow_action" value="approveModal">
                @error('review_notes')<p role="alert">{{ $message }}</p>@enderror
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Review Notes <span class="text-red-500">*</span></label>
                    <textarea name="review_notes" class="w-full p-3 border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none" rows="4" placeholder="Summarize your assessment of this referral..." required maxlength="1000">{{ old('review_notes') }}</textarea>
                </div>
                </div><div class="workflow-dialog__footer av-referral-actions">
                    <button type="button" class="btn-outline flex-1" onclick="closeApproveModal()">Cancel</button>
                    <button type="submit" class="btn-primary flex-1">Approve Referral</button>
                </div>
            </form>
        </div>
    </dialog>

    <!-- Request Revision Modal -->
    <dialog class="workflow-dialog adviser-review-dialog" id="reviseModal" aria-label="Request referral revision">
        <div class="modal-box av-referral-dialog">
            <div class="flex items-center gap-3 mb-4">

                <h3 class="text-xl font-bold text-gray-800">Request Revision</h3>
            </div>
            <p class="text-gray-500 text-sm mb-4">Return this referral to the Helper with comments. The Helper can revise the recommendation, and approval stays blocked until they respond.</p>

            <form class="form-maximized" id="reviseForm" method="POST" action="{{ route('adviser.referral.request-info', $referral->case_reference) }}"><div class="workflow-dialog__body">
                @csrf<input type="hidden" name="_workflow_action" value="reviseModal">
                @error('info_request')<p role="alert">{{ $message }}</p>@enderror
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Comments for the Helper <span class="text-red-500">*</span></label>
                    <textarea name="info_request" class="w-full p-3 border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none" rows="4" placeholder="Explain what to revise or clarify in the recommendation..." required minlength="10" maxlength="2000">{{ old('info_request') }}</textarea>
                </div>
                </div><div class="workflow-dialog__footer av-referral-actions">
                    <button type="button" class="btn-outline flex-1" onclick="closeReviseModal()">Cancel</button>
                    <button type="submit" class="btn-primary flex-1" style="background:#f59e0b;">Request Revision</button>
                </div>
            </form>
        </div>
    </dialog>

    <!-- Reject Modal -->
    <dialog class="workflow-dialog adviser-review-dialog" id="rejectModal" aria-label="Reject referral">
        <div class="modal-box av-referral-dialog">
            <div class="flex items-center gap-3 mb-4">

                <h3 class="text-xl font-bold text-gray-800">Reject Referral</h3>
            </div>
            <p class="text-gray-500 text-sm mb-4">Record why this recommendation will not proceed. This does not resolve a linked emergency.</p>

            <form class="form-maximized" id="rejectForm" method="POST" action="{{ route('adviser.referral.decline', $referral->case_reference) }}"><div class="workflow-dialog__body">
                @csrf<input type="hidden" name="_workflow_action" value="rejectModal">
                @error('rejection_reason')<p role="alert">{{ $message }}</p>@enderror
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Rejection reason <span class="text-red-500">*</span></label>
                    <textarea name="rejection_reason" class="w-full p-3 border border-gray-300 rounded-xl focus:border-green-500 focus:ring-2 focus:ring-green-200 outline-none" rows="4" placeholder="Explain why this referral is being rejected..." required>{{ old('rejection_reason') }}</textarea>
                </div>
                </div><div class="workflow-dialog__footer av-referral-actions">
                    <button type="button" class="btn-outline flex-1" onclick="closeRejectModal()">Cancel</button>
                    <button type="submit" class="btn-danger flex-1">Reject Referral</button>
                </div>
            </form>
        </div>
    </dialog>


    <script>
        function openApproveModal() {
            window.CompassDialogs.open(document.getElementById('approveModal'), document.activeElement);
        }

        function closeApproveModal() {
            window.CompassDialogs.close(document.getElementById('approveModal'));
        }

        function openRejectModal(id) {
            window.CompassDialogs.open(document.getElementById('rejectModal'), document.activeElement);
        }

        function closeRejectModal() {
            window.CompassDialogs.close(document.getElementById('rejectModal'));
        }

        function openReviseModal() {
            window.CompassDialogs.open(document.getElementById('reviseModal'), document.activeElement);
        }

        function closeReviseModal() {
            window.CompassDialogs.close(document.getElementById('reviseModal'));
        }

        document.getElementById('rejectModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                window.CompassDialogs.close(this);
            }
        });
        @if($errors->any() && in_array(old('_workflow_action'), ['approveModal','reviseModal','rejectModal']))
        document.addEventListener('DOMContentLoaded', () => window.CompassDialogs.open(document.getElementById(@json(old('_workflow_action')))));
        @endif
    </script>

    @if($referral->clarification_requested_at)
    <section class="card p-5 my-4" aria-label="Referral clarification">
        <h3 class="font-semibold">{{ $referral->clarification_received_at ? 'Clarification received' : 'Awaiting Helper clarification' }}</h3>
        <p class="text-sm mt-2">{{ $referral->clarification_question }}</p>
        <p class="text-sm mt-2">{{ $referral->clarification_response }}</p>
    </section>
    @endif
    @include('partials.referral-recommendation')
    <x-supervision-history :record="$referral" />
@include('partials.referral-appointments')
</div>
@endsection
