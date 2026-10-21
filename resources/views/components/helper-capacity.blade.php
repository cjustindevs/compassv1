@props(['helper'])
@php($used = $helper->currentAssignedSessionsCount())
<div class="helper-capacity"><strong>{{ $used }}/{{ \App\Models\Helper::MAX_SESSIONS_PER_SHIFT }}</strong> duty sessions
<small>{{ $helper->assignmentBlockers()->count() }}/1 live assignments</small>
@if($used >= \App\Models\Helper::MAX_SESSIONS_PER_SHIFT)<span>At Capacity</span>@endif</div>
@once<style>.helper-capacity{font-size:12px;line-height:1.6;white-space:nowrap}.helper-capacity strong{font-size:15px;font-variant-numeric:tabular-nums}.helper-capacity small{display:block;color:#64748b}.helper-capacity>span{display:inline-block;padding:2px 7px;border-radius:5px;background:#fff2dd;color:#824b0d;font-size:11px;font-weight:600}</style>@endonce
