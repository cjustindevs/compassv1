<section class="mo-page" id="moderatorEmergencyContent">
<p class="mo-filter-note mo-monitoring">Checks every 2 seconds &middot; Updated {{ now('Asia/Manila')->format('M d, Y g:i A') }} PHT</p>
<x-ui.kpi-grid>
    <x-ui.kpi label="Open Emergencies" :value="$stats['open']" tone="red" />
    <x-ui.kpi label="Escalated Today" :value="$stats['escalated_today']" tone="blue" title="Recorded escalations or professional referrals dated today in Philippine Time." />
    <x-ui.kpi label="Avg Response Time" :value="$stats['average_response']" title="Detection to first recorded acknowledgment, for acknowledgments in the last 30 days. Missing or invalid timestamps are excluded." />
    <x-ui.kpi label="Resolved (30d)" :value="$stats['resolved_30d']" tone="green" />
</x-ui.kpi-grid>
</div>
<x-ui.card title="Emergency Workflow" subtitle="Current open stages | Closed in the last 30 days" class="mo-workflow">
    <ol class="mo-workflow-stages" aria-label="Recorded emergency workflow stages">
    @foreach($workflow as $stage=>$count)
        <li class="mo-stage-{{ strtolower($stage) }}"><strong>{{ $count }}</strong><span>{{ $stage }}</span></li>
    @endforeach
    </ol>
    <p class="mo-filter-note">Each open case appears in one recorded stage. Closed includes resolved and closed cases; cancelled and archived-only states are excluded.</p>
</x-ui.card>
<div class="mo-grid mo-emergency-grid">
<x-ui.card :title="$tab === 'completed' ? 'Completed Emergency Cases' : 'Active Emergency Cases'">
<x-slot:actions><a class="btn {{ $tab === 'active' ? 'btn-primary' : 'btn-outline' }}" href="{{ route('moderator.emergency', ['tab'=>'active']) }}">Active ({{ $stats['open'] }})</a><a class="btn {{ $tab === 'completed' ? 'btn-primary' : 'btn-outline' }}" href="{{ route('moderator.emergency', ['tab'=>'completed']) }}">Completed</a></x-slot:actions>
<x-ui.table :stack="true" class="mo-emergency-cases"><thead><tr><th>Case / Alias</th><th>Detected by</th><th>Risk</th><th>Status</th><th>Onset (Philippine Time)</th><th>Actions</th></tr></thead><tbody>
@forelse($openIncidents as $case)
<tr>
    <td data-label="Case / Alias"><strong>{{ $case->source==='incident_reports' ? 'Incident':'Emergency' }} #{{ $case->id }}</strong><br>{{ $case->seeker_alias ?? 'Anonymous' }}</td>
    <td data-label="Detected by">{{ $case->detected_by==='seeker' ? 'Seeker':ucwords(str_replace('_',' ',$case->detected_by)) }}</td>
    <td data-label="Risk"><x-ui.badge :tone="in_array($case->risk_level,['emergency','high']) ? 'red' : 'amber'">{{ ucfirst($case->risk_level) }}</x-ui.badge></td>
    <td data-label="Status"><x-ui.badge :tone="$case->acknowledged_at ? 'blue' : 'amber'">{{ app(\App\Services\ModeratorEmergencyCases::class)->statusLabel($case) }}</x-ui.badge></td>
    <td data-label="Onset (Philippine Time)">{{ \Illuminate\Support\Carbon::parse($case->triggered_at)->timezone('Asia/Manila')->format('M d, Y') }}<br>{{ \Illuminate\Support\Carbon::parse($case->triggered_at)->timezone('Asia/Manila')->format('g:i A') }}<small class="mo-event-outcome">{{ \Illuminate\Support\Carbon::parse($case->triggered_at)->diffForHumans() }}</small></td>
    <td data-label="Actions" class="ui-actions">
<a class="btn btn-outline" href="{{ route('moderator.emergency.coordinate', [$case->source, $case->id]) }}">{{ $tab === 'completed' ? 'View record' : ($case->helper_id ? 'Open' : 'Open / Assign Helper') }}</a>@if($tab === 'completed' && $case->resolved_at)<small class="mo-event-outcome">Resolved {{ \Illuminate\Support\Carbon::parse($case->resolved_at)->timezone('Asia/Manila')->format('M d, Y g:i A') }}</small>@endif</td>
</tr>
@empty
    <tr><td colspan="6" class="mo-empty" data-label="">{{ $tab === 'completed' ? 'No completed emergency cases.' : 'No active emergency cases. Resolved records remain in emergency history.' }}</td></tr>
@endforelse
</tbody></x-ui.table>
<div class="mo-pagination">{{ $openIncidents->links() }}</div>
<p class="mo-filter-note">Review and resolution remain with the responsible Adviser. Identity Vault details are not available to Moderators.</p>
</x-ui.card>
<x-ui.card title="Priority Distribution" class="mo-priority-card">
    @include('moderator.partials.distribution',['values'=>$priorityDistribution,'chartTitle'=>'Active emergency priorities','donutOnly'=>true,'showPercent'=>false])
    <p class="mo-filter-note">The same {{ $stats['open'] }} open cases shown in Active Emergency Cases.</p>
</x-ui.card>
</div>
<x-ui.card title="Emergency Contacts"><div class="mo-contacts">
@forelse($contacts as $contact)
    <article class="mo-contact"><h3>{{ $contact->agency_name }}</h3><a href="tel:{{ preg_replace('/[^0-9+]/','',$contact->hotline) }}">{{ $contact->hotline }}</a><p class="mo-muted">{{ $contact->description }}</p></article>
@empty
    <p class="mo-empty">No published emergency contacts are available.</p>
@endforelse
</div></x-ui.card>
</section>
