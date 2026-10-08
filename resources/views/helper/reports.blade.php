@extends('layouts.helper')
@section('title','My Service Reports')
@section('heading','My Service Reports')
@section('subheading','Your own case history, service outcomes and duty records.')
@section('content')
<div class="hf-page">
<section class="card">
<form class="hf-filter" method="GET" action="{{ route('helper.reports') }}" style="margin-bottom:0">
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
<div class="hf-actions" style="justify-content:flex-end;margin-bottom:16px">
<a class="btn btn-secondary btn-sm" href="{{ route('helper.reports.export',array_merge(request()->only(['date_range','from','to','case_status','concern_id']),['format'=>'pdf'])) }}">Download PDF</a>
<a class="btn btn-secondary btn-sm" href="{{ route('helper.reports.export',array_merge(request()->only(['date_range','from','to','case_status','concern_id']),['format'=>'csv'])) }}">Download CSV</a>
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
<div class="hf-record-count">Showing {{ $table['records']->firstItem() ?? 0 }}-{{ $table['records']->lastItem() ?? 0 }} of {{ $table['records']->total() }} records</div>{{ $table['records']->links() }}
@if($table['title']!=='Case activity')
</details>
@else</section>
@endif
@endforeach
</div>
@endsection
