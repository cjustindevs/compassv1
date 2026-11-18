@extends('layouts.helper')
@section('title','My Service Reports')
@section('heading','My Service Reports')
@section('subheading','Your own case history, service outcomes and duty records.')
@section('content')
<div class="hf-page">
<section class="card">
@if(request('period') === 'all')<p class="hf-muted">All-time history selected.</p>@endif
<form class="hf-filter" method="GET" action="{{ route('helper.reports') }}" style="margin-bottom:0">
@if(request('period') === 'all')<input type="hidden" name="period" value="all">@endif
<x-helper-date-range :from="$roleReport['from']" :to="$roleReport['to']" />
<label>Case Status<select class="form-control" name="case_status">
<option value="">All</option>
@foreach(['screening_completed','preferences_set','active','completed','evaluated','waiting','helper_assigned','scheduled','pending_review','emergency','cancelled','no_show'] as $status)
<option value="{{ $status }}" @selected(request('case_status')===$status)>{{ ucwords(str_replace('_',' ',$status)) }}</option>
@endforeach</select>
</label>
<label>Case Category<select class="form-control" name="concern_id">
<option value="">All</option>
@foreach($categories as $category)
<option value="{{ $category->id }}" @selected((string)request('concern_id')===(string)$category->id)>{{ $category->concern_name }}</option>
@endforeach</select>
</label>
<button class="btn btn-primary" type="submit">Apply</button>
<a class="btn btn-secondary" href="{{ route('helper.reports') }}">Reset</a>
</form>
</section>
<div class="hf-kpis">
@foreach(['Completed cases'=>'Completed Cases','Active sessions'=>'Active Sessions','Cancelled / no-show'=>'Cancelled / No-show','Completed session duration'=>'Average Session Duration'] as $key=>$label)
<article class="hf-kpi">
<span>{{ $label }}</span>
<strong>{{ $roleReport['summary'][$key] }}</strong>
<small>Selected date range</small>
</article>
@endforeach</div>
{{-- QA: Reports are tables; the case charts are on the Dashboard. --}}
<p class="hf-muted" style="margin:6px 0 14px">Case charts for this period are on your <a class="link hf-inline-link" href="{{ route('helper.dashboard') }}">Dashboard</a>.</p>
@php
    // Label => [icon, what it means in one line]
    $metricTiles = [
        'Average Helper Response Time' => ['bolt', 'Request to your first message'],
        'Average waiting time' => ['hourglass', 'Queue entry to session start'],
        'Helpfulness rating (1–5)' => ['heart', 'From Seeker feedback'],
        'Competency score (1–5)' => ['star', 'From Adviser evaluations'],
        'Session completion rate' => ['check-circle', 'Started sessions that finished'],
        'Scheduled duty hours' => ['calendar', 'Planned coverage, not attendance'],
        'Documentation completion rate' => ['file-text', 'Summary and reflection submitted'],
        'Pending documentation' => ['clipboard', 'Notes still to complete'],
    ];
@endphp
<section class="card hf-metrics" aria-labelledby="hfMetricsTitle">
    <header class="hf-metrics-head"><h3 id="hfMetricsTitle">Performance metrics</h3><span class="hf-muted">Selected date range</span></header>
    <dl class="hf-metric-grid">
        @foreach($metricTiles as $label => [$icon, $hint])
        <div class="hf-metric">
            <dt><span class="hf-metric-icon" aria-hidden="true"><x-ui-icon :name="$icon" /></span>{{ $label }}</dt>
            <dd>{{ $roleReport['summary'][$label] ?? '—' }}</dd>
            <small>{{ $hint }}</small>
        </div>
        @endforeach
    </dl>
    <details class="hf-metric-notes"><summary>How these are calculated</summary>
        <p>Same definitions as your dashboard. Response is submission to your first actual message. Scheduled hours are planned coverage, not attendance. Documentation requires both summary and reflection; this is not an on-time rate. Scores use a 1–5 scale. The selected date range includes archived history.</p>
    </details>
</section>
<style>
.hf-metrics{margin:16px 0}
.hf-metrics-head{display:flex;align-items:baseline;justify-content:space-between;gap:12px;margin-bottom:14px}
.hf-metrics-head h3{margin:0;font-size:16px;font-weight:700;color:#163b2d}
.hf-metric-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:0}
.hf-metric{display:grid;gap:4px;align-content:start;min-width:0;padding:14px 16px;border:1px solid #e3ebe6;border-radius:12px;background:#fbfdfc}
.hf-metric dt{display:flex;align-items:center;gap:8px;min-height:36px;font-size:13px;font-weight:600;color:#3f5247;line-height:1.35}
.hf-metric-icon{display:inline-grid;place-items:center;flex:none;width:28px;height:28px;border-radius:8px;background:#ecfdf3;color:#087341}
.hf-metric-icon .compass-icon{width:16px;height:16px}
.hf-metric dd{margin:4px 0 0;font-size:24px;font-weight:750;line-height:1.2;color:#163b2d;font-variant-numeric:tabular-nums}
.hf-metric small{font-size:12px;line-height:1.4;color:#566170}
.hf-metric-notes{margin-top:14px;font-size:13px;color:#566170}
.hf-metric-notes summary{cursor:pointer;font-weight:600;color:#087341}
.hf-metric-notes p{margin:8px 0 0;line-height:1.6;max-width:880px}
@media(max-width:1100px){.hf-metric-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:480px){.hf-metric-grid{grid-template-columns:1fr}}
</style>
<div class="hf-actions" style="justify-content:flex-end;margin-bottom:16px">
<a class="btn btn-secondary btn-sm" href="{{ route('helper.reports.export',array_merge(request()->only(['date_range','from','to','case_status','concern_id','period']),['format'=>'pdf'])) }}">Download PDF</a>
<a class="btn btn-secondary btn-sm" href="{{ route('helper.reports.export',array_merge(request()->only(['date_range','from','to','case_status','concern_id','period']),['format'=>'csv'])) }}">Download CSV</a>
</div>
@foreach($roleReport['tables'] as $table)
@if($table['title']!=='Case activity')
<details class="card hf-secondary">
<summary>{{ $table['title'] }}</summary>
@else<section class="card">
<header class="card-header">
<h3>Case Activity</h3>
<span class="hf-muted">{{ $table['records']->total() }} records / Philippine Time</span>
</header>
@endif
<div class="table-container">
<table>
<thead>
<tr>
@foreach($table['columns'] as $column)
<th>{{ $column }}</th>
@endforeach</tr>
</thead>
<tbody>
@forelse($table['records'] as $record)
<tr>
@foreach(($table['format'])($record) as $value)
<td>{{ $value }}</td>
@endforeach</tr>
@empty<tr>
<td colspan="{{ count($table['columns']) }}" class="empty-state">No records match the selected filters.</td>
</tr>
@endforelse</tbody>
</table>
</div>
{{ $table['records']->links('vendor.pagination.compass', ['noun' => 'records']) }}
@if($table['title']!=='Case activity')
</details>
@else</section>
@endif
@endforeach
</div>
@endsection
