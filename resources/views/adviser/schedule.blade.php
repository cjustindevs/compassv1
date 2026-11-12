@extends('layouts.app')
@section('title','Helper Schedules - COMPASS')
@section('content')
<div class="adviser-page-content schedule-page">
<x-ui.page-header title="Helper schedules" description="Plan duty days and check current availability. Dates use Philippine Time." />
@if(session('success'))<p class="ui-card" role="status">{{ session('success') }}</p>@endif
@if($errors->any())<p class="ui-notice" role="alert">{{ $errors->first() }}</p>@endif
<x-ui.filter-bar aria-label="Schedule filters">
<x-ui.field label="Duty date" for="scheduleDate"><input id="scheduleDate" type="date" name="date" data-control="date" value="{{ $date->toDateString() }}" required></x-ui.field>
<x-ui.field label="Records" for="scheduleHistory"><select id="scheduleHistory" name="history" data-control="select">@foreach(['current'=>'Unarchived duty days','archived'=>'Archived duty days','all'=>'All duty days'] as $key=>$label)<option value="{{ $key }}" @selected(request('history','current')===$key)>{{ $label }}</option>@endforeach</select></x-ui.field>
<x-slot:buttons><button class="btn-primary" type="submit">View schedules</button></x-slot:buttons>
</x-ui.filter-bar>
<x-ui.card title="Schedule duty" subtitle="Duty covers the selected day. Availability still requires readiness and the existing matching rules.">
<form method="POST" action="{{ route('adviser.schedule.update') }}" class="ui-filter-bar" id="adviserDutyForm">
@csrf
<input type="hidden" name="shift_id" value="{{ old('shift_id') }}">
<x-ui.field label="Helper" for="dutyHelper"><select id="dutyHelper" name="helper_id" data-control="select" required><option value="">Select Helper</option>@foreach($helpers as $helper)<option value="{{ $helper->id }}" @selected((string)old('helper_id')===(string)$helper->id)>{{ $helper->full_name ?: 'Name not recorded' }}</option>@endforeach</select></x-ui.field>
<x-ui.field label="Duty date" for="dutyDate"><input id="dutyDate" type="date" name="date" data-control="date" value="{{ old('date',$date->toDateString()) }}" required></x-ui.field>
<div class="ui-filter-buttons"><button class="btn-primary" type="submit" id="dutySubmit">{{ old('shift_id') ? 'Save duty day' : 'Add duty day' }}</button><button class="btn-secondary" type="button" id="dutyReset" @if(!old('shift_id')) hidden @endif>Cancel edit</button></div>
</form>
</x-ui.card>
<x-ui.card :title="'Duty schedules for '.$date->format('M d, Y')" :subtitle="$dutySchedules->total().' duty records'" style="margin-top:24px">
<x-ui.table :stack="true" class="ui-table--wide"><thead><tr><th>Helper / Date</th><th>Duty period</th><th>Schedule status</th><th>Current availability</th><th>Actions</th></tr></thead><tbody>
@forelse($dutySchedules as $shift)
@php
    $status = !$shift->is_active ? 'Cancelled' : ($shift->isWithinShift() ? 'On duty now' : ($shift->window()[1]->lte(now()) ? 'Past duty' : 'Scheduled'));
    $eligibility = app(\App\Services\HelperEligibilityService::class)->status($shift->helper);
@endphp
<tr><td data-label="Helper / Date"><strong>{{ $shift->helper->full_name ?: 'Name not recorded' }}</strong><small>{{ $shift->date->format('M d, Y') }}</small></td><td data-label="Duty period">{{ $shift->shift_label }}</td><td data-label="Schedule status"><x-ui.badge :tone="!$shift->is_active ? 'neutral' : 'green'">{{ $status }}</x-ui.badge>@if($shift->archived_at)<small>Archived</small>@endif</td><td data-label="Current availability">{{ $eligibility['label'] }}<small>Readiness: {{ ucwords(str_replace('_',' ', $shift->helper->getReadinessStatus())) }}</small></td><td data-label="Actions" class="ui-actions">
@if(!$shift->archived_at && $shift->is_active)<button type="button" class="btn-secondary" data-shift-edit data-helper-id="{{ $shift->helper_id }}" data-date="{{ $shift->date->toDateString() }}" data-shift-id="{{ $shift->id }}">Edit duty</button><form method="POST" action="{{ route('adviser.schedule.destroy') }}" style="display:inline">@csrf<input type="hidden" name="helper_id" value="{{ $shift->helper_id }}"><input type="hidden" name="date" value="{{ $shift->date->toDateString() }}"><input type="hidden" name="shift_id" value="{{ $shift->id }}"><button class="btn-secondary" type="submit">Remove duty</button></form>@endif
@include('adviser.partials.archive-action',['record'=>$shift])
</td></tr>
@empty<tr><td colspan="5">No duty schedules match this date and record filter.</td></tr>@endforelse
</tbody></x-ui.table>
{{ $dutySchedules->links('vendor.pagination.compass', ['noun' => 'duty records']) }}
</x-ui.card>
</div>
<style>.schedule-page .ui-table td small{display:block;font-size:13px;color:var(--compass-muted,#6b7280)}.schedule-page #dutyReset[hidden]{display:none}</style>
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
