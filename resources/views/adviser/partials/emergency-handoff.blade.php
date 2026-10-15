@php
    $handoffSession = $alert->session;
    $handoffReady = $handoffSession?->isReferralEligible() && $handoffSession?->helper?->user?->is_active;
    $handoffRequested = \Illuminate\Support\Facades\DB::table('emergency_review_actions')->where('emergency_alert_id', $alert->id)->where('action', 'referral_requested')->exists();
    $canReviewLinkedReferral = $alert->referral && \App\Models\Referral::whereKey($alert->referral->id)->forAdviser((int) auth()->user()->adviser->id)->exists();
    $linkedReferral = $alert->referral;
@endphp
<section class="cm-card er-professional-handoff" id="emergencyProfessionalHandoff" aria-labelledby="emergencyHandoffTitle">
    <h2 id="emergencyHandoffTitle">Professional handoff</h2>
    <p class="cm-help">Raise professional support through an urgent referral. Referral approval, Seeker consent, contact submission, and authorized identity release remain separate steps.</p>
    @if($alert->referral)
        <p><strong>Referral #{{ $alert->referral->id }}</strong> &middot; {{ ucwords(str_replace('_',' ',$alert->referral->status)) }}</p>
        @if($linkedReferral->professional_id)
            <p><strong>Selected Professional:</strong> {{ $linkedReferral->professional?->full_name ?? 'Assigned Professional' }}</p>
            <p>{{ $linkedReferral->professional_notified_at ? 'An in-system notification was sent. Waiting for the Professional response unless already accepted.' : 'Professional notification is pending.' }} The emergency stays open until its outcome is recorded.</p>
        @endif
        @if($canReviewLinkedReferral)
            <a class="cm-button cm-primary" href="{{ route('adviser.referral.show',$alert->referral->id) }}">Continue Professional referral</a>
            @if(!$terminal && !$linkedReferral->professional_id)
                @if(!$linkedReferral->isOpen())
                    <p role="status">This referral has concluded. No Professional assignment is available.</p>
                @elseif(!$linkedReferral->approved_at)
                    <p role="status"><strong>Waiting for Adviser referral approval.</strong> Open the recommendation above and review it first.</p>
                @elseif(!$linkedReferral->help_seeker_consent)
                    <p role="status"><strong>Waiting for Seeker referral consent.</strong> The Seeker can decide in the live-chat popup.</p>
                @elseif(!app(\App\Services\IdentityVaultService::class)->hasCurrentSubmission($linkedReferral))
                    <p role="status"><strong>Waiting for Seeker contact details.</strong> The Seeker can complete Identity Disclosure Consent in the live chat.</p>
                @elseif(in_array($linkedReferral->status, ['pending_professional','no_professional_available'], true))
                    <h3>Raise to Professional</h3>
                    <p class="cm-help">Select the Professional who will review this session's emergency referral. This does not add them to the live chat or automatically release personal details.</p>
                    <form method="POST" action="{{ route('adviser.emergencies.professional', $alert) }}" data-confirm="Raise emergency to this Professional?" data-confirm-message="The selected Professional will receive an urgent referral notification and a link to review this case. Identity release is separate." data-confirm-text="Raise and notify">
                        @csrf
                        <div class="cm-field"><label for="emergencyProfessional">Professional</label><select id="emergencyProfessional" name="professional_id" required><option value="">Choose a Professional</option>@foreach($professionals as $professional)<option value="{{ $professional->id }}" @selected((string)old('professional_id') === (string)$professional->id)>{{ $professional->full_name }}</option>@endforeach</select></div>
                        <div class="cm-field"><label for="professionalRaiseReason">Reason for handoff</label><textarea id="professionalRaiseReason" name="reason" required minlength="10" maxlength="1000" rows="3">{{ old('reason') }}</textarea></div>
                        <button type="submit" class="cm-button cm-primary" @disabled($professionals->isEmpty())>Raise and notify Professional</button>
                        @if($professionals->isEmpty())<p role="status">No active, available Professional. Keep coordinating the emergency; no handoff has been sent.</p>@endif
                    </form>
                @endif
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
        <button class="cm-button" type="button" disabled>Request Helper recommendation</button>
    @else
        <p><strong>Next action: request an urgent recommendation from the Helper</strong></p>
        <form method="POST" action="{{ route('adviser.emergencies.referral-request',$alert->id) }}">@csrf
            <div class="cm-field"><label for="handoffNotes">Instructions for the Helper</label><textarea id="handoffNotes" name="notes" required minlength="10" maxlength="2000" rows="3" placeholder="Explain the urgent recommendation and next steps needed.">{{ old('notes') }}</textarea></div>
            <button class="cm-button cm-primary" type="submit">Request Helper recommendation</button>
        </form>
        <p class="cm-help">This notifies the Helper to complete the recommendation; it does not directly assign a Professional or disclose identity.</p>
    @endif
</section>
@foreach($alert->chatIncidents as $report)
<section class="cm-card"><h2>During-chat report</h2><p class="cm-message">{{ $report->description }}</p>@if($report->immediate_action)<h3>Action recorded by Helper</h3><p class="cm-message">{{ $report->immediate_action }}</p>@endif</section>
@endforeach
