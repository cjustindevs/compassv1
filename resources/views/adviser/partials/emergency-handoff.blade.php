@php
    $handoffSession = $alert->session;
    $handoffReady = $handoffSession?->isReferralEligible() && $handoffSession?->helper?->user?->is_active;
    $handoffRequested = \Illuminate\Support\Facades\DB::table('emergency_review_actions')->where('emergency_alert_id', $alert->id)->where('action', 'referral_requested')->exists();
    $canReviewLinkedReferral = $alert->referral && \App\Models\Referral::whereKey($alert->referral->id)->forAdviser((int) auth()->user()->adviser->id)->exists();
    $linkedReferral = $alert->referral;
    $handoffCandidates = $professionals->where('id', '!=', $linkedReferral?->professional_id);
    $handoffBlockReason = match (true) {
        $terminal => 'This emergency has concluded.',
        !$linkedReferral => 'Request the Helper recommendation below before selecting a Professional.',
        !$canReviewLinkedReferral => 'Only the assigned referral reviewer can choose or change its Professional.',
        !$linkedReferral->isOpen() => 'This referral has concluded.',
        !$linkedReferral->approved_at => 'Waiting for Adviser referral approval. Open the linked recommendation to review it.',
        !$linkedReferral->help_seeker_consent => 'Waiting for Seeker referral consent in live chat.',
        !app(\App\Services\IdentityVaultService::class)->hasCurrentSubmission($linkedReferral) => 'Waiting for Seeker contact details. The Seeker can complete Identity Disclosure Consent in live chat.',
        !in_array($linkedReferral->status, ['pending_professional','no_professional_available'], true) => 'Open the linked referral to request a Professional transfer.',
        default => null,
    };
@endphp
<section class="ui-card er-professional-handoff" id="emergencyProfessionalHandoff" aria-labelledby="emergencyHandoffTitle">
    <div class="er-card-head"><h2 id="emergencyHandoffTitle" class="er-card-title">Professional handoff</h2>@if($alert->referral)<x-ui.badge :tone="$linkedReferral->isOpen() ? 'amber' : 'green'">Referral #{{ $alert->referral->id }} · {{ ucwords(str_replace('_',' ',$alert->referral->status)) }}</x-ui.badge>@endif</div>
    @if($alert->referral && $linkedReferral->professional_id)
        <dl class="er-facts er-facts-2">
            <div><dt>Selected Professional</dt><dd>{{ $linkedReferral->professional?->full_name ?? 'Assigned Professional' }}</dd></div>
            <div><dt>Professional response</dt><dd>{{ $linkedReferral->accepted_at ? 'Accepted the referral' : ($linkedReferral->professional_notified_at ? 'Notified, awaiting response' : 'Notification pending') }}</dd></div>
        </dl>
    @endif
    @if($handoffBlockReason)
        <p class="er-status" id="professionalUnavailableReason" role="status">{{ $handoffBlockReason }}</p>
    @endif
    @if($alert->referral)
        @if($linkedReferral->completionReviews()->exists())<p role="status"><strong>Professional completion report:</strong> {{ ucwords(str_replace('_',' ',$linkedReferral->completionReviews()->latest('id')->value('review_status'))) }}. Referral completion and emergency resolution are separate.</p>@endif
        @if($canReviewLinkedReferral)
            @if($linkedReferral->isOpen())<a class="btn btn-primary" href="{{ route('adviser.referral.show',$alert->referral->id) }}">Continue Professional referral</a>@else<a class="btn btn-outline" href="{{ route('adviser.referral.show',$alert->referral->id) }}">View referral</a>@endif
            @if(!$terminal && !$handoffBlockReason)
                    <h3>{{ $linkedReferral->professional_id ? 'Change Professional' : 'Raise to Professional' }}</h3>
                    <p class="er-help">Select the Professional who will review this session's emergency referral. This does not add them to the live chat or automatically release personal details.</p>
                    <form method="POST" action="{{ route('adviser.emergencies.professional', $alert) }}" data-confirm="Raise emergency to this Professional?" data-confirm-message="The selected Professional will receive an urgent referral notification and a link to review this case. Identity release is separate." data-confirm-text="Raise and notify">
                        @csrf
                        <div class="er-field"><label for="emergencyProfessional">Professional</label><select id="emergencyProfessional" name="professional_id" required><option value="">Choose a Professional</option>@foreach($handoffCandidates as $professional)<option value="{{ $professional->id }}" @selected((string)old('professional_id') === (string)$professional->id)>{{ $professional->full_name }}</option>@endforeach</select></div>
                        <div class="er-field"><label for="professionalRaiseReason">Reason for handoff</label><textarea id="professionalRaiseReason" name="reason" required minlength="10" maxlength="1000" rows="3">{{ old('reason') }}</textarea></div>
                        <button type="submit" class="btn btn-primary" @disabled($handoffCandidates->isEmpty())>Raise and notify Professional</button>
                        @if($handoffCandidates->isEmpty())<p role="status">No active, available Professional. Keep coordinating the emergency; no handoff has been sent.</p>@endif
                    </form>
            @endif
        @else
            <p role="status">Referral reviewer: {{ $alert->referral->adviser?->full_name ?? 'Awaiting assignment' }}. Coordinate with this reviewer to continue the referral.</p>
        @endif
    @elseif($terminal)
        <p>No referral was linked before this emergency concluded.</p>
    @elseif($handoffRequested)
        <p role="status"><strong>Waiting for Helper recommendation</strong></p>
        <p>The Helper has been notified to submit the urgent recommendation. Once submitted, its review link will appear here. This request has not yet been sent to a Professional.</p>
    @elseif(!$handoffReady)
        <p role="status"><strong>Waiting for Helper connection</strong></p>
        <p>The Seeker continues priority queuing and matching while you review the emergency. The referral request becomes available when an active Helper is connected and the session has started.</p>
        <button class="btn btn-outline" type="button" disabled>Request Helper recommendation</button>
    @else
        <p><strong>Next action: request an urgent recommendation from the Helper</strong></p>
        <form method="POST" action="{{ route('adviser.emergencies.referral-request',$alert->id) }}">@csrf
            <div class="er-field"><label for="handoffNotes">Instructions for the Helper</label><textarea id="handoffNotes" name="notes" required minlength="10" maxlength="2000" rows="3" placeholder="Explain the urgent recommendation and next steps needed.">{{ old('notes') }}</textarea></div>
            <button class="btn btn-primary" type="submit">Request Helper recommendation</button>
        </form>
        <p class="er-help">This notifies the Helper to complete the recommendation; it does not directly assign a Professional or disclose identity.</p>
    @endif
</section>
@foreach($alert->chatIncidents as $report)
<section class="ui-card"><h2 class="er-card-title">During-chat report</h2><dl class="er-report"><div><dt>What the Helper reported</dt><dd class="er-message">{{ $report->description }}</dd></div>@if($report->immediate_action)<div><dt>Action recorded by Helper</dt><dd class="er-message">{{ $report->immediate_action }}</dd></div>@endif</dl></section>
@endforeach
