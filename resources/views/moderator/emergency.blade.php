@extends('layouts.app')
@section('title','Emergency Alerts - COMPASS')
@section('content')
<link rel="stylesheet" href="{{ asset('css/moderator-operations.css') }}?v={{ filemtime(public_path('css/moderator-operations.css')) }}">
<link rel="stylesheet" href="{{ asset('css/dashboard-overview.css') }}?v={{ filemtime(public_path('css/dashboard-overview.css')) }}">
<section class="mo-page">
<header class="mo-heading"><div><h1>Emergency Alerts</h1><p>Monitor emergency cases and coordinate with the responsible Adviser.</p></div><div><a href="{{ route('moderator.emergency') }}" class="mo-button secondary">Refresh</a><p class="mo-muted">Updated {{ now('Asia/Manila')->format('M d, Y g:i A') }} PHT | Checks every 30 seconds</p></div></header>
<div class="mo-card mo-kpis">
<div class="mo-kpi danger"><strong>{{ $stats['open'] }}</strong><span>Active emergencies</span></div>
<div class="mo-kpi"><strong>{{ $stats['escalated_today'] }}</strong><span>Active escalations detected today</span></div>
<div class="mo-kpi"><strong>{{ $stats['resolved_30d'] }}</strong><span>Resolved in the last 30 days</span></div>
<div class="mo-kpi"><a href="{{ route('moderator.reports',['tab'=>'safety']) }}" class="mo-button secondary">Emergency history</a><p class="mo-muted">Resolved cases and archived records</p></div>
</div>
<div class="mo-grid mo-emergency-grid">
<section class="mo-card"><header class="mo-heading"><h2>Active Emergency Cases</h2><span class="mo-muted">{{ $openIncidents->total() }} active</span></header>
<div class="mo-table-wrap"><table class="mo-table"><thead><tr><th>Case / alias</th><th>Risk</th><th>Status</th><th>Helper / Adviser</th><th>Detected (Philippine Time)</th><th>Coordination</th></tr></thead><tbody>
@forelse($openIncidents as $case)
<tr><td><strong>{{ $case->source==='incident_reports'?'Incident':'Emergency' }} #{{ $case->id }}</strong><br>{{ $case->seeker_alias ?? 'Anonymous' }}</td><td><span class="mo-badge danger">{{ ucfirst($case->risk_level) }}</span></td><td><span class="mo-badge">{{ app(\App\Services\ModeratorEmergencyCases::class)->statusLabel($case) }}</span></td><td>{{ trim($case->helper_first_name.' '.$case->helper_last_name) ?: 'Helper unassigned' }}<br><span class="mo-muted">{{ trim($case->adviser_first_name.' '.$case->adviser_last_name) ?: 'Adviser unassigned' }}</span></td><td>{{ \Illuminate\Support\Carbon::parse($case->triggered_at)->timezone('Asia/Manila')->format('M d, Y g:i A') }}<br><span class="mo-muted">{{ \Illuminate\Support\Carbon::parse($case->triggered_at)->diffForHumans() }}</span></td><td>@if($case->session_id)<a class="text-green-700" href="{{ route('moderator.sessions.show',$case->session_id) }}">View session status</a>@else<span class="mo-muted">Awaiting linked session</span>@endif</td></tr>
@empty<tr><td colspan="6" class="mo-empty">No active emergency cases. Resolved records remain in emergency history.</td></tr>@endforelse
</tbody></table></div><div class="mt-4">{{ $openIncidents->links() }}</div>
<p class="mo-muted mt-3">Emergency review and resolution remain with the responsible Adviser. Moderator access does not include Identity Vault details.</p>
</section>
<section class="mo-card"><h2>Active priority distribution</h2><x-dashboard-chart :chart="['title'=>'Severity / priority','values'=>$priorityDistribution,'definition'=>'The same active cases shown in the queue.']" /></section>
</div>
<section class="mo-card"><h2>Emergency Contacts</h2><div class="mo-contacts">@forelse($contacts as $contact)<article class="mo-contact"><h3>{{ $contact->agency_name }}</h3><a href="tel:{{ preg_replace('/[^0-9+]/','',$contact->hotline) }}">{{ $contact->hotline }}</a><p class="mo-muted">{{ $contact->description }}</p></article>@empty<p class="mo-empty">No published emergency contacts are available.</p>@endforelse</div></section>
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
    } catch (_) { /* Keep the current records visible; Refresh remains available. */ }
}, 30000);
</script>
@endsection
