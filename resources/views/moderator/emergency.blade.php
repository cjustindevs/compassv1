@extends('layouts.app')
@section('title','Emergency Alerts - COMPASS')
@section('content')
<link rel="stylesheet" href="{{ asset('css/moderator-operations.css') }}?v={{ filemtime(public_path('css/moderator-operations.css')) }}">
<section class="mo-page">
<header class="mo-heading"><div><h1>Emergency Alerts</h1><p>Monitor emergency reports and coordinate with the responsible Adviser.</p></div><div class="mo-monitoring"><a href="{{ route('moderator.emergency') }}" class="mo-button secondary">Refresh</a><p>Checks every 30 seconds</p><small>Updated {{ now('Asia/Manila')->format('M d, Y g:i A') }} PHT</small></div></header>
<div class="mo-kpis mo-emergency-kpis">
    <div class="mo-kpi danger"><span>Open Emergencies</span><strong>{{ $stats['open'] }}</strong></div>
    <div class="mo-kpi information" title="Recorded escalations or professional referrals dated today in Philippine Time."><span>Escalated Today</span><strong>{{ $stats['escalated_today'] }}</strong></div>
    <div class="mo-kpi neutral" title="Detection to first recorded acknowledgment, for acknowledgments in the last 30 days. Missing or invalid timestamps are excluded."><span>Avg Response Time</span><strong>{{ $stats['average_response'] }}</strong></div>
    <div class="mo-kpi"><span>Resolved (30d)</span><strong>{{ $stats['resolved_30d'] }}</strong></div>
</div>
<section class="mo-card mo-workflow">
    <header class="mo-heading"><h2>Emergency Workflow</h2><p>Current open stages | Closed in the last 30 days</p></header>
    <ol class="mo-workflow-stages" aria-label="Recorded emergency workflow stages">
    @foreach($workflow as $stage=>$count)
        <li class="mo-stage-{{ strtolower($stage) }}"><strong>{{ $count }}</strong><span>{{ $stage }}</span></li>
    @endforeach
    </ol>
    <p class="mo-filter-note">Each open case appears in one recorded stage. Closed includes resolved and closed cases; cancelled and archived-only states are excluded.</p>
</section>
<div class="mo-grid mo-emergency-grid">
<section class="mo-card"><header class="mo-heading"><h2>Active Emergency Cases</h2><div class="mo-actions"><span class="mo-muted">{{ $openIncidents->total() }} active</span><a class="mo-text-link" href="{{ route('moderator.reports',['tab'=>'safety','overview'=>'emergencies','emergency_status'=>'active']) }}">View history</a></div></header>
<div class="mo-table-wrap"><table class="mo-table"><thead><tr><th>Case / Alias</th><th>Detected by</th><th>Risk</th><th>Status</th><th>Onset (Philippine Time)</th><th>Actions</th></tr></thead><tbody>
@forelse($openIncidents as $case)
<tr>
    <td><strong>{{ $case->source==='incident_reports'?'Incident':'Emergency' }} #{{ $case->id }}</strong><br>{{ $case->seeker_alias ?? 'Anonymous' }}<small class="mo-event-outcome">Helper: {{ trim($case->helper_first_name.' '.$case->helper_last_name) ?: 'Unassigned' }}<br>Adviser: {{ trim($case->adviser_first_name.' '.$case->adviser_last_name) ?: 'Unassigned' }}</small></td>
    <td>{{ $case->detected_by==='seeker'?'Seeker':ucwords(str_replace('_',' ',$case->detected_by)) }}</td>
    <td><span class="mo-badge {{ in_array($case->risk_level,['emergency','high'])?'danger':'warning' }}">{{ ucfirst($case->risk_level) }}</span></td>
    <td><span class="mo-badge {{ $case->acknowledged_at?'information':'warning' }}">{{ app(\App\Services\ModeratorEmergencyCases::class)->statusLabel($case) }}</span></td>
    <td>{{ \Illuminate\Support\Carbon::parse($case->triggered_at)->timezone('Asia/Manila')->format('M d, Y') }}<br>{{ \Illuminate\Support\Carbon::parse($case->triggered_at)->timezone('Asia/Manila')->format('g:i A') }}<small class="mo-event-outcome">{{ \Illuminate\Support\Carbon::parse($case->triggered_at)->diffForHumans() }}</small></td>
    <td>
@if($case->session_id)<a class="mo-text-link" href="{{ route('moderator.sessions.show',$case->session_id) }}">View session</a>
@else<span class="mo-muted">Awaiting linked session</span>
@endif</td>
</tr>
@empty
    <tr><td colspan="6" class="mo-empty">No active emergency cases. Resolved records remain in emergency history.</td></tr>
@endforelse
</tbody></table></div>
<div class="mo-pagination">{{ $openIncidents->links() }}</div>
<p class="mo-filter-note">Review and resolution remain with the responsible Adviser. Identity Vault details are not available to Moderators.</p>
</section>
<section class="mo-card mo-priority-card"><h2>Priority Distribution</h2>
    @include('moderator.partials.distribution',['values'=>$priorityDistribution,'chartTitle'=>'Active emergency priorities','donutOnly'=>true,'showPercent'=>false])
    <p class="mo-filter-note">The same {{ $stats['open'] }} open cases shown in Active Emergency Cases.</p>
</section>
</div>
<section class="mo-card"><h2>Emergency Contacts</h2><div class="mo-contacts">
@forelse($contacts as $contact)
    <article class="mo-contact"><h3>{{ $contact->agency_name }}</h3><a href="tel:{{ preg_replace('/[^0-9+]/','',$contact->hotline) }}">{{ $contact->hotline }}</a><p class="mo-muted">{{ $contact->description }}</p></article>
@empty
    <p class="mo-empty">No published emergency contacts are available.</p>
@endforelse
</div></section>
</section>
<script>
const emergencySignature = @json($emergencySignature);
setInterval(async () => {
    if (document.hidden) return;
    try {
        const response = await fetch(@json(route('moderator.emergency.stats')), {headers: {'Accept':'application/json'}});
        if (!response.ok) return;
        const data = await response.json();
        if (data.signature !== emergencySignature) window.location.reload();
    } catch (_) { /* The current records and manual Refresh remain available. */ }
}, 30000);
</script>
@endsection
