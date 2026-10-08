@extends('layouts.app')
@section('title','Operations Reports - COMPASS')
@section('content')
<link rel="stylesheet" href="{{ asset('css/moderator-operations.css') }}?v={{ filemtime(public_path('css/moderator-operations.css')) }}">
@php
    $tab=request('tab') ?: 'operations'; $report=$roleReport;
    $overview=request('overview') ?: ($tab==='safety'?'emergencies':'status');
    $chart=$report['charts'][$overview];
    $activityTable=collect($report['tables'])->firstWhere('group','activity');
@endphp
<section class="mo-page">
<header class="mo-heading"><div><h1>Reports</h1><p>View and analyze operational reports.</p></div><a class="mo-button secondary" href="{{ route('moderator.reports.export',request()->except(['cases_page','emergency_page','queue_page','activity_page'])) }}">Export filtered CSV</a></header>
@if(session('success'))<p role="status" class="mo-card">{{ session('success') }}</p>
@endif
@if($errors->any())<div class="mo-card" role="alert">
@foreach($errors->all() as $error)<p>{{ $error }}</p>
@endforeach</div>
@endif
<form method="GET" class="mo-card mo-filters" aria-label="Report filters">
@foreach(['archive','activity','search'] as $key)
    @if(request()->filled($key))<input type="hidden" name="{{ $key }}" value="{{ request($key) }}">
@endif
@endforeach
<input type="hidden" name="tab" value="{{ $tab }}"><input type="hidden" name="overview" value="{{ $overview }}">
<div class="mo-range"><label for="report-range">Date Range (Philippine Time)</label><input id="report-range" type="text" readonly value="{{ \Illuminate\Support\Carbon::parse($report['from'])->format('m/d/Y') }} &ndash; {{ \Illuminate\Support\Carbon::parse($report['to'])->format('m/d/Y') }}" aria-haspopup="dialog" style="cursor:pointer"><input type="hidden" id="date-range-value" name="date_range" value="{{ $report['from'] }} to {{ $report['to'] }}"></div>
<div><label for="case-status">Case Status</label><select name="case_status" id="case-status"><option value="">All</option>
@foreach(['screening_completed','preferences_set','waiting','helper_assigned','scheduled','active','pending_review','emergency','completed','evaluated','cancelled','no_show'] as $s)<option value="{{ $s }}" @selected(request('case_status')===$s)>{{ ucwords(str_replace('_',' ',$s)) }}</option>
@endforeach</select></div>
<div><label for="emergency-status">Emergency Status</label><select name="emergency_status" id="emergency-status"><option value="">All</option>
@foreach(['active','pending','notified','acknowledged','responding','under_review','open','escalated','referred','resolved','closed','cancelled','archived'] as $s)<option value="{{ $s }}" @selected(request('emergency_status')===$s)>{{ ucfirst($s) }}</option>
@endforeach</select></div>
<div><label for="priority">Priority</label><select name="priority" id="priority"><option value="">All</option>
@foreach(['emergency','high','moderate','low'] as $s)<option value="{{ $s }}" @selected(request('priority')===$s)>{{ ucfirst($s) }}</option>
@endforeach</select></div>
<button class="mo-button">Apply</button><a class="mo-button secondary" href="{{ route('moderator.reports',['tab'=>$tab]) }}">Reset</a>
</form>
<p class="mo-filter-note">Status filters apply to their corresponding records. Priority and dates apply to all overview charts and the Activity Log. Maximum date range: 366 days.</p>
<section class="mo-card"><h2>Report Summary</h2><div class="mo-kpis">
@foreach(['Cases in period'=>'Total cases','Completed cases'=>'Completed','Pending requests'=>'Pending','Emergencies'=>'Emergencies'] as $key=>$label)<div class="mo-kpi {{ $key==='Emergencies'?'danger':($key==='Pending requests'?'warning':'') }}"><strong>{{ $report['summary'][$key] }}</strong><span>{{ $label }}</span></div>
@endforeach</div></section>
<section class="mo-card">
    <h2>Case Overview</h2>
    <nav class="mo-tabs" aria-label="Case overview">
    @foreach(['status'=>'Status','categories'=>'Categories','emergencies'=>'Emergencies','referrals'=>'Referrals'] as $value=>$label)
        <a href="{{ route('moderator.reports',array_merge(request()->except(['cases_page','queue_page','emergency_page','activity_page']),['overview'=>$value,'tab'=>$value==='emergencies'?'safety':'operations'])) }}" @if($overview===$value) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
    </nav>
    @include('moderator.partials.distribution',['values'=>$chart['values'],'chartTitle'=>$chart['title']])
    <p class="mo-filter-note">{{ $chart['title'] }} | Actual records in the selected Philippine Time date range. Each record is counted once.</p>
</section>
<section class="mo-card mo-activity">
    <header class="mo-activity-heading">
        <h2>Activity Log</h2>
        <nav class="mo-tabs mo-activity-tabs" aria-label="Activity type">
        @foreach([''=>'All','cases'=>'Cases','emergencies'=>'Emergencies','queue'=>'Queue','actions'=>'Actions'] as $value=>$label)
            <a href="{{ route('moderator.reports',array_merge(request()->except('activity_page'),['activity'=>$value])) }}" @if((request('activity') ?: '')===$value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
        </nav>
        <form method="GET" class="mo-search">
            @foreach(request()->except(['search','activity_page']) as $key=>$value)
                @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">
@endif
            @endforeach
            <label class="sr-only" for="activity-search">Search reference, activity or actor</label>
            <input id="activity-search" name="search" maxlength="100" value="{{ request('search') }}" placeholder="Search reference, activity or actor...">
            <button class="mo-button secondary">Search</button>
        </form>
    </header>
    <div class="mo-table-wrap"><table class="mo-table"><thead><tr><th>Reference</th><th>Activity</th><th>Type</th><th>Current status</th><th>Priority</th><th>Date (Philippine Time)</th><th>Actor / helper</th><th>Actions</th></tr></thead><tbody>
    @forelse($activityTable['records'] as $record)
        @php
            $cells=($activityTable['format'])($record);
        @endphp
        <tr>
        @foreach(array_slice($cells,0,7) as $value)
            <td>
            @if(in_array($loop->index,[3,4]))<span class="mo-badge {{ in_array(strtolower($value),['emergency','high','cancelled'])?'danger':(in_array(strtolower($value),['waiting','pending'])?'warning':'') }}">{{ $value }}</span>
            @else{{ $value }}@endif
            @if($loop->index===1)<small class="mo-event-outcome">Event: {{ $cells[7] }}</small>
@endif
            </td>
        @endforeach
        <td>
@if($record->linked_session_id)<a class="mo-text-link" href="{{ route('moderator.sessions.show',$record->linked_session_id) }}">View session</a>
@elseif($record->record_type==='queue_requests')<a class="mo-text-link" href="{{ route('moderator.queue') }}">Open queue</a>
@else<span class="mo-muted">Recorded</span>
@endif</td>
        </tr>
    @empty
        <tr><td colspan="8" class="mo-empty">No activity matches these filters.</td></tr>
    @endforelse
    </tbody></table></div>
    <div class="mo-pagination">{{ $activityTable['records']->links() }}</div>
</section>
<section class="mo-card">
    <details class="mo-record-details" @if(request('archive')==='archived'||request('cases_page')||request('queue_page')||request('emergency_page')) open @endif>
    <summary>Detailed records and archive</summary>
    <form method="GET" class="mo-filters mo-history-filter">
        @foreach(request()->except(['archive','cases_page','queue_page','emergency_page','activity_page']) as $key=>$value)
            @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">
@endif
        @endforeach
        <div><label for="archive-filter">History</label><select name="archive" id="archive-filter">
@foreach(['all'=>'All records','current'=>'Not archived','archived'=>'Archived only'] as $value=>$label)<option value="{{ $value }}" @selected(request('archive','all')===$value)>{{ $label }}</option>
@endforeach</select></div>
        <button class="mo-button secondary">Apply history filter</button>
    </form>
@foreach($report['tables'] as $table)@if($table['group']===$tab && $table['type'])
<section class="mo-card"><h2>{{ $table['title'] }}</h2><div class="mo-table-wrap"><table class="mo-table"><thead><tr>
@foreach($table['columns'] as $column)<th>{{ $column }}</th>
@endforeach @if($table['type'])<th>History action</th>
@endif</tr></thead><tbody>
@forelse($table['records'] as $record)<tr>
@foreach(($table['format'])($record) as $value)<td>{{ $value }}</td>
@endforeach
@if($table['type'])<td>@php $status=$record->session_status ?? $record->request_status ?? $record->status; $kind=$table['type']==='emergency'?$record->source:$table['type']; @endphp
@if(in_array($status,['completed','evaluated','cancelled','expired','no_show','resolved','closed']))<form method="POST" action="{{ route('moderator.archive.store') }}">@csrf<input type="hidden" name="record_type" value="{{ $kind }}"><input type="hidden" name="record_id" value="{{ $record->id }}"><input type="hidden" name="restore" value="{{ $record->archived_at?1:0 }}"><button class="text-green-700 underline">{{ $record->archived_at?'Restore history':'Archive' }}</button></form>
@else<span class="mo-muted">Active record</span>
@endif</td>
@endif</tr>
@empty<tr><td colspan="{{ count($table['columns'])+1 }}" class="mo-empty">No records match these filters.</td></tr>
@endforelse</tbody></table></div><div class="mt-4">{{ $table['records']->links() }}</div></section>
@endif
@endforeach
    </details>
</section>
</section>
<dialog class="mo-dialog" id="range-dialog"><form method="dialog"><h2 class="font-semibold mb-4">Date Range (Philippine Time)</h2><p class="mo-muted">Choose up to 366 days.</p><div class="mo-filters"><div><label for="range-start">Start date</label><input id="range-start" type="date" value="{{ $report['from'] }}" required></div><div><label for="range-end">End date</label><input id="range-end" type="date" value="{{ $report['to'] }}" required></div></div><p id="range-error" role="alert" class="text-red-700 text-sm mt-2"></p><div class="mo-actions"><button class="mo-button" id="range-save" type="button">Use date range</button><button class="mo-button secondary" formnovalidate>Cancel</button></div></form></dialog>
<script>
const rangeDialog=document.getElementById('range-dialog');
document.getElementById('report-range').addEventListener('click',()=>rangeDialog.showModal());
document.getElementById('report-range').addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();rangeDialog.showModal();}});
document.getElementById('range-save').addEventListener('click',()=>{const a=document.getElementById('range-start').value,b=document.getElementById('range-end').value;if(!a||!b||a>b||(Date.parse(b)-Date.parse(a))/86400000>365){document.getElementById('range-error').textContent='Choose valid dates with the end on or after the start, up to 366 days.';return;}document.getElementById('date-range-value').value=a+' to '+b;const pretty=d=>d.slice(5,7)+'/'+d.slice(8,10)+'/'+d.slice(0,4);document.getElementById('report-range').value=pretty(a)+' - '+pretty(b);rangeDialog.close();});
</script>
@endsection
