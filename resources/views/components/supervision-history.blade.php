@props(['record'])
@php($versions = app(\App\Services\SupervisionVersions::class)->history($record))
<section class="bg-white border border-gray-200 rounded-2xl p-5 my-4">
<h3 class="font-semibold text-gray-800">Version history</h3>
@forelse($versions as $version)
<details class="border-t border-gray-100 mt-3 pt-3 text-sm"><summary class="cursor-pointer">Version {{ $version->version }} ? {{ $version->created_at }} ? {{ $version->reason }}</summary>
<p class="text-xs text-gray-500 mt-2">Recorded by account #{{ $version->actor_id ?? 'System' }}</p>
@foreach(json_decode($version->snapshot, true) ?? [] as $field=>$value)
@if(in_array($field,['overall_score','active_listening_score','empathy_score','respect_score','ethical_practices_score','referral_accuracy_score','rubric_version','strengths','improvement_areas','feedback_text','follow_up_action','review_notes','completion_evidence','activity','status','reason','title','description','content','hotline','agency_name','visibility','review_date','archived_at']))
<p class="mt-2 whitespace-pre-wrap break-words"><strong>{{ ucfirst(str_replace('_',' ',$field)) }}:</strong> {{ is_scalar($value) ? $value : 'Not recorded' }}</p>
@endif
@endforeach
</details>
@empty<p class="text-sm text-gray-500 mt-2">No versioned corrections recorded.</p>@endforelse
</section>
