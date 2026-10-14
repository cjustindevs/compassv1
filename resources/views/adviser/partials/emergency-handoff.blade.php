<section class="cm-card" aria-labelledby="emergencyHandoffTitle">
    <h2 id="emergencyHandoffTitle">Professional handoff</h2>
    @if($alert->referral)
        <p class="cm-help">Referral #{{ $alert->referral->id }} · {{ ucwords(str_replace('_',' ',$alert->referral->status)) }}</p>
        <p>{{ $alert->professional_referred ? 'The referral has been sent to an assigned Professional.' : 'The recommendation must be reviewed, consented to, and securely coordinated before Professional assignment.' }}</p>
        <a class="cm-button cm-primary" href="{{ route('adviser.referral.show',$alert->referral->id) }}">Review linked referral</a>
    @elseif(!$terminal)
        <p class="cm-help">Ask the Helper to document an urgent recommendation. This does not grant consent or resolve the emergency.</p>
        @if(\Illuminate\Support\Facades\DB::table('emergency_review_actions')->where('emergency_alert_id',$alert->id)->where('action','referral_requested')->exists())
            <p>Recommendation requested. Awaiting the Helper’s submission.</p>
        @else
            <form method="POST" action="{{ route('adviser.emergencies.referral-request',$alert->id) }}">@csrf
                <div class="cm-field"><label for="handoffNotes">Coordination instructions</label><textarea id="handoffNotes" name="notes" required minlength="10" maxlength="2000" rows="3">{{ old('notes') }}</textarea></div>
                <button class="cm-button cm-primary" type="submit">Request urgent recommendation</button>
            </form>
        @endif
    @else<p>No referral was linked to this emergency.</p>@endif
</section>
@foreach($alert->chatIncidents as $report)
<section class="cm-card"><h2>During-chat report</h2><p class="cm-message">{{ $report->description }}</p>@if($report->immediate_action)<h3>Action recorded by Helper</h3><p class="cm-message">{{ $report->immediate_action }}</p>@endif</section>
@endforeach
