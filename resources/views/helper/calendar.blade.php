@extends('layouts.helper')
@section('title','Calendar')
@section('heading','Calendar')
@section('subheading','Your assigned duty dates and sessions in Philippine Time.')
@section('content')
<div class="hf-page">
<section class="card">
<header class="card-header">
<h3>{{ $monthName }}</h3>
<div class="hf-actions">
<form method="GET" action="{{ route('helper.calendar') }}" class="hf-actions">
    <input type="hidden" name="year" value="{{ $year }}">
    <select name="month" class="form-control" style="width:auto" aria-label="Calendar month">
        @for($m=1;$m<=12;$m++)
        <option value="{{ $m }}" @selected($m===$month)>{{ \Illuminate\Support\Carbon::create($year,$m,1)->format('F') }}</option>
        @endfor
    </select>
    <button class="btn btn-secondary btn-sm" type="submit">Go</button>
</form>
<a class="btn btn-secondary btn-sm" href="{{ route('helper.calendar',['month'=>$prevMonth,'year'=>$prevYear]) }}">Previous month</a>
<a class="btn btn-secondary btn-sm" href="{{ route('helper.calendar',['month'=>now('Asia/Manila')->month,'year'=>now('Asia/Manila')->year]) }}">Today</a>
<a class="btn btn-secondary btn-sm" href="{{ route('helper.calendar',['month'=>$nextMonth,'year'=>$nextYear]) }}">Next month</a>
</div>
</header>
<p class="hf-muted" style="margin-bottom:14px">Duty is assigned by your Moderator or Adviser. Open a session to see its details.</p>
<div class="hf-calendar-desktop">
<div class="hf-calendar-grid">
@foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)
<div class="hf-calendar-day">{{ $day }}</div>
@endforeach
@foreach($grid as $week)@foreach($week as $cell)
<div class="hf-calendar-cell {{ !$cell['in_month'] ? 'outside' : '' }} {{ $cell['today'] ? 'today' : '' }}">
<strong>{{ $cell['day'] }}</strong>
@foreach($cell['events'] as $event)@if($event['kind']==='session')
<a class="hf-calendar-event" href="{{ route('helper.cases.show',$event['id']) }}" title="{{ $event['reference'] }} / {{ $event['status_label'] }}">{{ $event['reference'] }} / {{ $event['time'] ?? 'Time not recorded' }}<br>{{ $event['status_label'] }}</a>
@else<span class="hf-calendar-event {{ $event['status'] }}">Duty / {{ $event['time'] }}<br>{{ ucfirst($event['status']) }}</span>
@endif @endforeach</div>
@endforeach @endforeach
</div>
</div>
<div class="hf-calendar-agenda">
@php($eventDays=collect($grid)->flatten(1)->filter(fn($cell)=>$cell['in_month'] && $cell['events']->isNotEmpty()))@forelse($eventDays as $cell)
<details @if($cell['today']) open @endif>
<summary>{{ \Illuminate\Support\Carbon::parse($cell['date'])->format('D, M d') }} / {{ $cell['events']->count() }} {{ $cell['events']->count()===1 ? 'event' : 'events' }}</summary>
@foreach($cell['events'] as $event)@if($event['kind']==='session')
<a class="hf-calendar-event" href="{{ route('helper.cases.show',$event['id']) }}">{{ $event['reference'] }} / {{ $event['time'] ?? 'Time not recorded' }} / {{ $event['status_label'] }}</a>
@else<p class="hf-calendar-event {{ $event['status'] }}">Duty / {{ $event['time'] }} / {{ ucfirst($event['status']) }}</p>
@endif @endforeach</details>
@empty<div class="empty-state">No duties or sessions are recorded for this month.</div>
@endforelse</div>
</section>
<section class="card">
<header class="card-header">
<h3>Official duty schedule</h3>
<span class="hf-muted">Assigned by your Moderator or Adviser</span>
</header>
<div class="table-container">
<table>
<thead>
<tr>
<th>Date</th>
<th>Duty hours</th>
<th>Status</th>
</tr>
</thead>
<tbody>
@forelse($schedules as $shift)
<tr>
<td>{{ $shift->date->format('M d, Y') }}</td>
<td>{{ $shift->shift_label }}</td>
<td>
<span class="hf-status {{ !$shift->is_active ? 'hf-status-muted' : '' }}">{{ !$shift->is_active ? 'Inactive' : ($shift->isOnDuty() ? 'On duty now' : ($shift->window()[1]->isPast() ? 'Past' : 'Upcoming')) }}</span>
</td>
</tr>
@empty<tr>
<td colspan="3" class="empty-state">No duty dates have been assigned this month. Contact your Adviser or Moderator for scheduling.
@if($adviser)<a class="link" href="mailto:{{ $adviser->email ?? $adviser->user?->email }}">Contact {{ $adviser->full_name }}</a>@endif</td>
</tr>
@endforelse</tbody>
</table>
</div>
</section>
</div>
@endsection
