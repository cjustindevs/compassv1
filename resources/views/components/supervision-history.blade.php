@props(['record'])
@php
    $versions = app(\App\Services\SupervisionVersions::class)->history($record);
    $actors = \App\Models\User::whereIn('id',$versions->pluck('actor_id')->filter()->unique())->pluck('name','id');
    $fields = ['overall_score','active_listening_score','empathy_score','respect_score','ethical_practices_score','referral_accuracy_score','strengths','improvement_areas','feedback_text','follow_up_action','review_notes','completion_evidence','activity','status','reason','title','description','content','visibility','review_date','archived_at'];
@endphp
<section class="review-history">
<h3>Update history</h3>
<div class="compact-table-wrap"><table class="compact-data-table"><thead><tr><th>Date</th><th>Recorded by</th><th>Change</th><th><span class="sr-only">Details</span></th></tr></thead><tbody>
@forelse($versions as $version)
@php
    $snapshot = json_decode($version->snapshot,true) ?? [];
    $previousVersion = $versions->first(fn ($entry) => $entry->version < $version->version);
    $previous = $previousVersion ? (json_decode($previousVersion->snapshot,true) ?? []) : [];
@endphp
<tr><td>{{ \Illuminate\Support\Carbon::parse($version->created_at)->timezone('Asia/Manila')->format('M d, Y g:i A') }}</td><td>{{ $actors[$version->actor_id] ?? 'System' }}</td><td>{{ $version->reason }}</td><td>
<section data-focus-panel data-focus-label="View changes" data-focus-title="Recorded changes">
<dl>@foreach($snapshot as $field=>$value)@if(in_array($field,$fields,true))<div><dt>{{ ucwords(str_replace('_',' ',$field)) }}</dt><dd>@if(array_key_exists($field,$previous) && $previous[$field] !== $value)<span>Before: {{ is_scalar($previous[$field]) ? $previous[$field] : '—' }}</span><br>@endif<span>{{ array_key_exists($field,$previous) ? 'After: ' : '' }}{{ is_scalar($value) ? $value : '—' }}</span></dd></div>@endif
@endforeach</dl>
</section></td></tr>
@empty<tr><td colspan="4">No updates recorded.</td></tr>@endforelse
</tbody></table></div>
</section>
