<section class="card mb-4 referral-next-step" aria-labelledby="referralNextStepTitle">
    <h2 id="referralNextStepTitle" class="font-semibold mb-2">Next step for Professional referral</h2>
    @if(in_array($referral->status, ['completed','closed','declined'], true))
        <p>This referral is {{ str_replace('_',' ', $referral->status) }}. No further Professional assignment or identity release is available.</p>
    @elseif(!$referral->approved_at)
        @if($referral->clarification_requested_at && !$referral->clarification_received_at)
            <p role="status"><strong>Waiting for Helper revision.</strong> Review can continue after the Helper submits the requested information.</p>
        @elseif($referral->adviser_id && in_array($referral->status, ['pending_adviser','consent_requested'], true) && !$referral->reviewed_at && !$referral->professional_id)
            <p><strong>Your action: review the recommendation.</strong> Approval sends the referral to the Seeker for consent; it does not yet notify a Professional.</p>
            <button type="button" class="btn-primary" onclick="openApproveModal()">Review and approve referral</button>
        @else
            <p role="status">The referral must be assigned to its responsible Adviser and reviewed before Professional coordination.</p>
        @endif
    @elseif(!$referral->help_seeker_consent)
        <p role="status"><strong>Waiting for Seeker referral consent.</strong> The Seeker can open Referral decisions to agree or decline. A Professional cannot be assigned yet.</p>
    @elseif(!$identityReady)
        <p role="status"><strong>Waiting for Seeker contact details.</strong> Ask the Seeker to open Referral decisions, choose Provide contact details for coordination, and submit with identity-disclosure consent.</p>
    @elseif(!$referral->professional_id && in_array($referral->status, ['pending_professional','no_professional_available'], true))
        @if($professionals->isEmpty())
            <p role="status"><strong>No available Professional.</strong> Keep the referral pending and coordinate availability. The emergency, if linked, remains open.</p>
        @else
            <p><strong>Your action: assign and notify a Professional.</strong> Contact details have been submitted. Choose the recipient and record the assignment reason.</p>
            <a href="#professionalAssignment" class="btn btn-primary">Choose Professional</a>
        @endif
    @elseif($referral->professional_id && !$referral->identity_disclosed)
        <p><strong>Your action: authorize necessary contact details.</strong> The assigned Professional can review the referral, but personal details remain unavailable until you authorize release.</p>
        @if((int) $referral->adviser_id === (int) auth()->user()->adviser->id)
            <a href="#professionalIdentityRelease" class="btn btn-primary">Review identity release</a>
        @else
            <p class="text-sm">The assigned referral Adviser, {{ $referral->adviser?->full_name ?? 'Awaiting assignment' }}, must authorize identity release.</p>
        @endif
    @else
        <p role="status"><strong>Professional handoff recorded.</strong> Track the Professional response and appointment below. A linked emergency is resolved separately by its responsible reviewer.</p>
    @endif
</section>
