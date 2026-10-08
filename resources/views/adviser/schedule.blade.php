@extends('layouts.app')
@section('title','Helper Schedules - COMPASS')
@section('content')
<div class="adviser-page-content av-page">
<header><div><h1>Helper schedules</h1><p class="av-muted">Plan duty days and check current availability. Dates use Philippine Time.</p></div></header>
@if(session('success'))<p class="av-note" role="status">{{ session('success') }}</p>@endif
@if($errors->any())<p class="av-note" role="alert">{{ $errors->first() }}</p>@endif
<form method="GET" class="av-panel av-filter"><label>Duty date<input type="date" name="date" value="{{ $date->toDateString() }}" required></label><label>Records<select name="history">@foreach(['current'=>'Unarchived duty days','archived'=>'Archived duty days','all'=>'All duty days'] as $key=>$label)<option value="{{ $key }}" @selected(request('history','current')===$key)>{{ $label }}</option>@endforeach</select></label><button class="av-button av-button-primary">View schedules</button></form>
<div class="av-schedule">
<section class="av-panel"><header class="av-heading"><h2>Schedule duty</h2></header><p class="av-muted mb-4">Duty covers the selected day. Availability still requires readiness and the existing matching rules.</p>
<form method="POST" action="{{ route('adviser.schedule.update') }}" class="av-filter" id="adviserDutyForm">
@csrf
<input type="hidden" name="shift_id" value="{{ old('shift_id') }}">
<label>Helper<select name="helper_id" required><option value="">Select Helper</option>@foreach($helpers as $helper)<option value="{{ $helper->id }}" @selected((string)old('helper_id')===(string)$helper->id)>{{ $helper->full_name ?: 'Name not recorded' }}</option>@endforeach</select></label>
<label>Duty date<input type="date" name="date" value="{{ old('date',$date->toDateString()) }}" required></label>
<div class="av-actions"><button class="av-button av-button-primary" type="submit" id="dutySubmit">{{ old('shift_id') ? 'Save duty day' : 'Add duty day' }}</button><button class="av-button" type="button" id="dutyReset" @if(!old('shift_id')) hidden @endif>Cancel edit</button></div>
</form></section>
<section class="av-panel"><header class="av-heading"><div><h2>Duty schedules for {{ $date->format('M d, Y') }}</h2><p class="av-muted">{{ $dutySchedules->total() }} duty records</p></div></header>
<div class="av-table-wrap" role="region" aria-label="Duty schedules" tabindex="0"><table class="av-table"><thead><tr><th>Helper / Date</th><th>Duty period</th><th>Schedule status</th><th>Current availability</th><th>Actions</th></tr></thead><tbody>
@forelse($dutySchedules as $shift)
@php
    $status = !$shift->is_active ? 'Cancelled' : ($shift->isWithinShift() ? 'On duty now' : ($shift->window()[1]->lte(now()) ? 'Past duty' : 'Scheduled'));
    $eligibility = app(\App\Services\HelperEligibilityService::class)->status($shift->helper);
@endphp
<tr><td class="font-semibold">{{ $shift->helper->full_name ?: 'Name not recorded' }}<p class="av-muted">{{ $shift->date->format('M d, Y') }}</p></td><td>{{ $shift->shift_label }}</td><td><span class="av-badge {{ !$shift->is_active ? 'av-badge-muted' : '' }}">{{ $status }}</span>@if($shift->archived_at)<p class="av-muted">Archived</p>@endif</td><td>{{ $eligibility['label'] }}<p class="av-muted">Readiness: {{ ucwords(str_replace('_',' ', $shift->helper->getReadinessStatus())) }}</p></td><td><div class="av-actions">
@if(!$shift->archived_at && $shift->is_active)<button type="button" class="av-button" data-shift-edit data-helper-id="{{ $shift->helper_id }}" data-date="{{ $shift->date->toDateString() }}" data-shift-id="{{ $shift->id }}">Edit duty</button><form method="POST" action="{{ route('adviser.schedule.destroy') }}">@csrf<input type="hidden" name="helper_id" value="{{ $shift->helper_id }}"><input type="hidden" name="date" value="{{ $shift->date->toDateString() }}"><input type="hidden" name="shift_id" value="{{ $shift->id }}"><button class="av-button" type="submit">Remove duty</button></form>@endif
@include('adviser.partials.archive-action',['record'=>$shift])
</div></td></tr>
@empty<tr><td colspan="5" class="av-empty">No duty schedules match this date and record filter.</td></tr>@endforelse
</tbody></table></div><div class="av-pagination">{{ $dutySchedules->links() }}</div></section>
</div></div>
@endsection
@push('scripts')
<script>
(() => {
 const form=document.getElementById('adviserDutyForm'), submit=document.getElementById('dutySubmit'), reset=document.getElementById('dutyReset');
 document.querySelectorAll('[data-shift-edit]').forEach(button=>button.addEventListener('click',()=>{
  form.elements.shift_id.value=button.dataset.shiftId;form.elements.helper_id.value=button.dataset.helperId;form.elements.date.value=button.dataset.date;
  submit.textContent='Save duty day';reset.hidden=false;form.elements.helper_id.focus();
 }));
 reset.addEventListener('click',()=>{form.elements.shift_id.value='';submit.textContent='Add duty day';reset.hidden=true;});
})();
</script>
@endpush
