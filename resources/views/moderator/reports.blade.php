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
<section class="reports-page">
<x-ui.page-header title="Reports" description="View and analyze operational reports.">
    <x-slot:actions>
        <a class="btn-secondary" href="{{ route('queue.performance', request()->only(['from','to','period'])) }}">Queue Performance</a>
        <a class="btn-secondary" href="{{ route('moderator.reports.export',request()->except(['cases_page','emergency_page','queue_page','activity_page'])) }}"><x-ui-icon name="download" /> Export Filtered CSV</a>
    </x-slot:actions>
</x-ui.page-header>
@if(session('success'))<p role="status" class="ui-card">{{ session('success') }}</p>
@endif
@if($errors->any())<div class="ui-card" role="alert">
@foreach($errors->all() as $error)<p>{{ $error }}</p>
@endforeach</div>
@endif
@php
    $resetHref = route('moderator.reports',['tab'=>$tab]);
    $chips = \App\Support\FilterChips::from(request(), ['case_status'=>'Case status','emergency_status'=>'Emergency status','priority'=>'Priority']);
@endphp
<x-ui.card class="reports-filter-card">
<x-ui.filter-bar aria-label="Report filters" class="reports-filter-bar">
@foreach(['archive','activity','search','period'] as $key)
    @if(request()->filled($key))<input type="hidden" name="{{ $key }}" value="{{ request($key) }}">
@endif
@endforeach
<input type="hidden" name="tab" value="{{ $tab }}"><input type="hidden" name="overview" value="{{ $overview }}">
<x-ui.field label="Date Range (Philippine Time)" for="report-range" class="mo-range"><input id="report-range" data-control="date" type="text" readonly value="{{ \Illuminate\Support\Carbon::parse($report['from'])->format('m/d/Y') }} &ndash; {{ \Illuminate\Support\Carbon::parse($report['to'])->format('m/d/Y') }}" aria-haspopup="dialog" style="cursor:pointer"><input type="hidden" id="date-range-value" name="date_range" value="{{ $report['from'] }} to {{ $report['to'] }}"></x-ui.field>
<x-ui.field label="Case Status" for="case-status"><select name="case_status" id="case-status"><option value="">All</option>
@foreach(['screening_completed','preferences_set','waiting','helper_assigned','scheduled','active','pending_review','emergency','completed','evaluated','cancelled','no_show'] as $s)<option value="{{ $s }}" @selected(request('case_status')===$s)>{{ ucwords(str_replace('_',' ',$s)) }}</option>
@endforeach</select></x-ui.field>
<x-ui.field label="Emergency Status" for="emergency-status"><select name="emergency_status" id="emergency-status"><option value="">All</option>
@foreach(['active','pending','notified','acknowledged','responding','under_review','open','escalated','referred','resolved','closed','cancelled','archived'] as $s)<option value="{{ $s }}" @selected(request('emergency_status')===$s)>{{ ucfirst($s) }}</option>
@endforeach</select></x-ui.field>
<x-ui.field label="Priority" for="priority"><select name="priority" id="priority"><option value="">All</option>
@foreach(['emergency','high','moderate','low'] as $s)<option value="{{ $s }}" @selected(request('priority')===$s)>{{ ucfirst($s) }}</option>
@endforeach</select></x-ui.field>
<x-slot:buttons><button type="submit" class="btn-primary">Apply</button><a class="btn-secondary" href="{{ $resetHref }}">Reset</a></x-slot:buttons>
</x-ui.filter-bar>
<x-ui.filter-chips :chips="$chips" :reset-href="$resetHref" />
<p class="mo-filter-note">Status filters apply to their own records; priority and dates apply to all charts and the Activity Log (maximum range 366 days; All time includes retained history).</p>
</x-ui.card>
<x-ui.kpi-grid>
@foreach(['Cases in period'=>['Total Cases','green'],'Completed cases'=>['Completed','green'],'Pending requests'=>['Pending','amber'],'Emergencies'=>['Emergencies','red']] as $key=>[$label,$tone])<x-ui.kpi :label="$label" :value="$report['summary'][$key]" :tone="$tone" />
@endforeach
</x-ui.kpi-grid>
<details class="ui-card reports-details"><summary>Waiting time and Helper workload</summary>
<p>Average waiting time: <strong>{{ $report['summary']['Average waiting time'] }}</strong> | {{ $report['summary']['Waiting time samples'] }} records measured.</p>
<p>Average Helper workload: <strong>{{ $report['summary']['Average Helper workload'] }}</strong> completed sessions per serving Helper | {{ $report['summary']['Serving Helpers'] }} Helpers measured.</p>
<p class="mo-filter-note">Same calculation as the dashboard: queue entry to actual start, excluding ongoing waits. These two measures use the session activity cohort (end, start, submission or legacy creation date), with the selected case status, priority and history filters. The case table retains its submission-date basis.</p>
</details>
<x-ui.card title="Case Overview" subtitle="Actual records in the selected Philippine Time date range. Each record is counted once.">
    <div class="analytics-chart-grid report-chart-grid">
    @foreach(['status','categories','emergencies','referrals'] as $key)
        @if(isset($report['charts'][$key]))
            @include('moderator.partials.distribution',['values'=>$report['charts'][$key]['values'],'chartTitle'=>$report['charts'][$key]['title']])
        @endif
    @endforeach
    </div>
</x-ui.card>
<x-ui.card title="Helpers and competency">
    <div class="analytics-chart-grid report-chart-grid">
        <x-analytics-chart title="Current Helper workload" description="Active and assigned sessions per Helper right now; independent of the date filter." :chart="['type'=>'bar','labels'=>collect($workload)->pluck('helper')->all(),'datasets'=>[['label'=>'Open sessions','data'=>collect($workload)->pluck('count')->all()]]]" />
        <x-analytics-chart title="Competency by skill" description="Average of current-rubric evaluations in the selected period, on a 1–5 scale." :chart="['type'=>'radar','scale'=>[1,5],'unit'=>'','labels'=>$radar['labels'],'datasets'=>[['label'=>'All Helpers (1–5)','data'=>$radar['values']]]]" />
    </div>
</x-ui.card>
<x-ui.card title="Activity Log" class="mo-activity">
    <x-slot:actions>
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
            <button class="btn-secondary">Search</button>
        </form>
    </x-slot:actions>
    @php $activityLabels=['Reference','Activity','Type','Current status','Priority','Date (Philippine Time)','Actor / helper']; @endphp
    <x-ui.table :stack="true"><thead><tr><th>Reference</th><th>Activity</th><th>Type</th><th>Current status</th><th>Priority</th><th>Date (Philippine Time)</th><th>Actor / helper</th><th>Actions</th></tr></thead><tbody>
    @forelse($activityTable['records'] as $record)
        @php
            $cells=($activityTable['format'])($record);
        @endphp
        <tr>
        @foreach(array_slice($cells,0,7) as $value)
            <td data-label="{{ $activityLabels[$loop->index] }}">
            @if(in_array($loop->index,[3,4]))<x-ui.badge :tone="in_array(strtolower($value),['emergency','high','cancelled'])?'red':(in_array(strtolower($value),['waiting','pending'])?'amber':'neutral')">{{ $value }}</x-ui.badge>
            @else{{ $value }}@endif
            @if($loop->index===1)<small class="mo-event-outcome">Event: {{ $cells[7] }}</small>
@endif
            </td>
        @endforeach
        <td data-label="Actions" class="ui-actions">
@if($record->record_type==='queue_requests')<a class="mo-text-link" href="{{ route('moderator.queue') }}">Open queue</a>
@else<span class="mo-muted">Recorded</span>
@endif</td>
        </tr>
    @empty
        <tr><td colspan="8" class="mo-empty">No activity matches these filters.</td></tr>
    @endforelse
    </tbody></x-ui.table>
    {{ $activityTable['records']->links('vendor.pagination.compass', ['noun' => 'records']) }}
</x-ui.card>
<section class="ui-card">
    <details class="mo-record-details" @if(request('archive')==='archived'||request('cases_page')||request('queue_page')||request('emergency_page')) open @endif>
    <summary>Detailed records and archive</summary>
    <x-ui.filter-bar class="mo-history-filter">
        @foreach(request()->except(['archive','cases_page','queue_page','emergency_page','activity_page']) as $key=>$value)
            @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">
@endif
        @endforeach
        <x-ui.field label="History" for="archive-filter"><select name="archive" id="archive-filter">
@foreach(['all'=>'All records','current'=>'Not archived','archived'=>'Archived only'] as $value=>$label)<option value="{{ $value }}" @selected(request('archive','all')===$value)>{{ $label }}</option>
@endforeach</select></x-ui.field>
        <x-slot:buttons><button type="submit" class="btn-secondary">Apply history filter</button></x-slot:buttons>
    </x-ui.filter-bar>
@foreach($report['tables'] as $table)@if($table['group']===$tab && $table['type'])
<div class="reports-record-table"><h3 class="ui-card-title">{{ $table['title'] }}</h3><x-ui.table :stack="true"><thead><tr>
@foreach($table['columns'] as $column)<th>{{ $column }}</th>
@endforeach @if($table['type'])<th>History action</th>
@endif</tr></thead><tbody>
@forelse($table['records'] as $record)<tr>
@foreach(($table['format'])($record) as $value)<td data-label="{{ $table['columns'][$loop->index] ?? '' }}">{{ $value }}</td>
@endforeach
@if($table['type'])<td data-label="History action" class="ui-actions">@php $status=$record->session_status ?? $record->request_status ?? $record->status; $kind=$table['type']==='emergency'?$record->source:$table['type']; @endphp
@if(in_array($status,['completed','evaluated','cancelled','expired','no_show','resolved','closed']))<form method="POST" action="{{ route('moderator.archive.store') }}">@csrf<input type="hidden" name="record_type" value="{{ $kind }}"><input type="hidden" name="record_id" value="{{ $record->id }}"><input type="hidden" name="restore" value="{{ $record->archived_at?1:0 }}"><button class="text-green-700 underline">{{ $record->archived_at?'Restore history':'Archive' }}</button></form>
@else<span class="mo-muted">Active record</span>
@endif</td>
@endif</tr>
@empty<tr><td colspan="{{ count($table['columns'])+1 }}" class="mo-empty">No records match these filters.</td></tr>
@endforelse</tbody></x-ui.table>{{ $table['records']->links('vendor.pagination.compass', ['noun' => 'records']) }}</div>
@endif
@endforeach
    </details>
</section>
<style>
.reports-page .reports-filter-card{padding-bottom:16px}.reports-page .reports-filter-bar{margin-bottom:12px}.reports-page .reports-filter-bar .mo-range{flex:1 1 340px;min-width:min(100%,340px)}.reports-page .ui-badge{white-space:nowrap}.reports-page .mo-activity-tabs{flex-wrap:nowrap;white-space:nowrap}.reports-page .mo-search input{min-width:200px}.reports-page .ui-table td{overflow-wrap:normal}@media (max-width:768px){.reports-page .mo-activity-tabs{flex-wrap:wrap;white-space:normal}.reports-page .mo-search{flex-wrap:wrap;width:100%}.reports-page .mo-search input{min-width:0;flex:1 1 160px}}.reports-page .reports-filter-bar .ui-field{flex:1 1 180px}.reports-page .reports-filter-bar .ui-filter-buttons{flex:0 0 auto}
.reports-page .btn-secondary,.reports-page .btn-primary{gap:8px}.reports-page .mo-filter-note{margin:0;font-size:13px;color:var(--compass-muted,#6b7280)}
.reports-page .reports-details>summary{cursor:pointer;font-size:18px;font-weight:700}.reports-page .reports-details[open]>summary{margin-bottom:12px}
.reports-page .mo-activity .ui-card-actions{display:flex;flex-wrap:wrap;gap:12px;align-items:center}
.reports-page .ui-table td small{display:block;font-size:13px;color:var(--compass-muted,#6b7280)}.reports-page .reports-record-table{margin-top:24px}
</style>
</section>
<dialog class="mo-dialog" id="range-dialog"><form method="dialog"><h2 class="font-semibold mb-4">Date Range (Philippine Time)</h2><p class="mo-muted">Choose up to 366 days.</p><div class="mo-filters"><div><label for="range-start">Start date</label><input id="range-start" type="date" value="{{ $report['from'] }}" required></div><div><label for="range-end">End date</label><input id="range-end" type="date" value="{{ $report['to'] }}" required></div></div><p id="range-error" role="alert" class="text-red-700 text-sm mt-2"></p><div class="mo-actions"><button class="mo-button" id="range-save" type="button">Use date range</button><button class="mo-button secondary" formnovalidate>Cancel</button></div></form></dialog>
<script>
const rangeDialog=document.getElementById('range-dialog');
document.getElementById('report-range').addEventListener('click',()=>rangeDialog.showModal());
document.getElementById('report-range').addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();rangeDialog.showModal();}});
document.getElementById('range-save').addEventListener('click',()=>{const a=document.getElementById('range-start').value,b=document.getElementById('range-end').value;if(!a||!b||a>b||(Date.parse(b)-Date.parse(a))/86400000>365){document.getElementById('range-error').textContent='Choose valid dates with the end on or after the start, up to 366 days.';return;}document.getElementById('date-range-value').value=a+' to '+b;const pretty=d=>d.slice(5,7)+'/'+d.slice(8,10)+'/'+d.slice(0,4);document.getElementById('report-range').value=pretty(a)+' - '+pretty(b);rangeDialog.close();});
</script>
@endsection
