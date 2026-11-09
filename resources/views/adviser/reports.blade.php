@extends('layouts.app')
@section('title', 'Reports - COMPASS')
@section('content')
<div class="adviser-page-content av-page">
<header><div><h1>Reports</h1><p class="av-muted">Authorized supervision records. {{ $filters['start']->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }} to {{ $filters['end']->copy()->subMicrosecond()->timezone('Asia/Manila')->format('M d, Y g:i A') }} (Philippine Time).</p></div><div class="av-actions"><a class="av-button" href="{{ route('queue.performance', request()->only(['from','to','period'])) }}">Queue performance</a><a class="av-button" href="{{ route('adviser.reports.export', array_merge(request()->except(['page','cases_page']),['format'=>'csv'])) }}">Export case CSV</a><a class="av-button" href="{{ route('adviser.reports.export', array_merge(request()->except(['page','cases_page']),['format'=>'pdf'])) }}">Export case PDF</a></div></header>
@if(session('success'))<p class="av-note" role="status">{{ session('success') }}</p>@endif
@if($errors->any())<p role="alert" class="av-note">{{ $errors->first() }}</p>@endif
<nav class="av-tabs" aria-label="Report groups">
@foreach(['cases'=>'Sessions / Cases','performance'=>'Helper Performance','safety'=>'Emergency / Safety','activity'=>'Activity History'] as $key=>$label)<a href="{{ route('adviser.reports', array_merge(request()->except(['page','cases_page','actions_page','emergency_page']),['tab'=>$key])) }}" @if($tab===$key) aria-current="page" @endif>{{ $label }}</a>@endforeach
</nav>
<form method="GET" class="av-panel av-filter">
<input type="hidden" name="tab" value="{{ $tab }}">
<label>Period<select name="period">@foreach(['today'=>'Today','weekly'=>'Last 7 days','monthly'=>'Last 30 days','semester'=>'Semester','all'=>'All time','custom'=>'Custom','quarterly'=>'Last 90 days','yearly'=>'Last year'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['period'] ?? 'monthly')===$key) @disabled($key==='semester' && (!config('analytics.semester_start') || !config('analytics.semester_end')))>{{ $label }}</option>@endforeach</select></label>
<label>From (Philippine Time)<input name="from" type="date" value="{{ request('from') ? \Illuminate\Support\Carbon::parse(request('from'),'Asia/Manila')->format('Y-m-d') : '' }}"></label>
<label>To (Philippine Time)<input name="to" type="date" value="{{ request('to') ? \Illuminate\Support\Carbon::parse(request('to'),'Asia/Manila')->format('Y-m-d') : '' }}"></label>
@if($tab!=='activity')
<label>Helper<select name="helper_id"><option value="">All supervised Helpers</option>@foreach($filterHelpers as $helper)<option value="{{ $helper->id }}" @selected(request('helper_id')==$helper->id)>{{ $helper->full_name ?: 'Name not recorded' }}</option>@endforeach</select></label>
<label>Concern<select name="concern_id"><option value="">All concerns</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('concern_id')==$category->id)>{{ $category->concern_name }}</option>@endforeach</select></label>
@endif
@if(in_array($tab,['cases','performance']))
<label>Case status<select name="case_status"><option value="">All statuses</option>@foreach(['active','completed','evaluated','waiting','helper_assigned','pending_review','emergency','cancelled','no_show'] as $state)<option value="{{ $state }}" @selected(request('case_status')===$state)>{{ ucwords(str_replace('_',' ',$state)) }}</option>@endforeach</select></label>
<label>Referral status<select name="referral_status"><option value="">All referrals</option>@foreach(\App\Models\Referral::STATUSES as $status)<option value="{{ $status }}" @selected(request('referral_status')===$status)>{{ ucwords(str_replace('_',' ',$status)) }}</option>@endforeach</select></label>
<label>Case history<select name="history">@foreach(['current'=>'Unarchived records','archived'=>'Archived cases','all'=>'All records'] as $key=>$label)<option value="{{ $key }}" @selected(request('history','current')===$key)>{{ $label }}</option>@endforeach</select></label>
@endif
@if($tab==='performance')<label>Competency metric<select name="competency_metric">@foreach(['overall_score'=>'Overall competency'] + collect(\App\Services\CompetencyRubric::CRITERIA)->mapWithKeys(fn($c)=>[$c[2]=>$c[0]])->all() as $key=>$label)<option value="{{ $key }}" @selected(($filters['competency_metric'] ?? 'overall_score')===$key)>{{ $label }}</option>@endforeach</select></label>@endif
@if($tab==='safety')
<label>Emergency status<select name="emergency_status"><option value="">All statuses</option>@foreach(['open','triggered','pending','under_review','responding','acknowledged','escalated','resolved','closed'] as $state)<option value="{{ $state }}" @selected(request('emergency_status')===$state)>{{ ucwords(str_replace('_',' ',$state)) }}</option>@endforeach</select></label>
<label>Priority<select name="priority"><option value="">All priorities</option>@foreach(['emergency','high','moderate','low'] as $priority)<option value="{{ $priority }}" @selected(request('priority')===$priority)>{{ ucfirst($priority) }}</option>@endforeach</select></label>
@endif
<div class="av-actions"><button class="av-button av-button-primary" type="submit">Apply filters</button><a class="av-button" href="{{ route('adviser.reports',['tab'=>$tab]) }}">Reset</a></div>
</form>
@if($tab==='cases')
<dl class="av-stats">@foreach(['Cases in period','Completed cases','Active sessions','Pending requests'] as $label)<div><dt>{{ $label }}</dt><dd>{{ $roleReport['summary'][$label] }}</dd></div>@endforeach</dl>
<x-report-charts :groups="collect($roleReport['groups'])->only(['Cases by status','Case categories','Case outcome trend'])->all()" heading="Case outcomes and categories" caption="Selected period · Philippine Time" />
<section class="av-panel"><header class="av-heading"><div><h2>Session records</h2><p class="av-muted">{{ $sessions->total() }} records. Archiving preserves history and never reopens or closes a case.</p></div></header>
<div class="av-table-wrap" role="region" aria-label="Session records" tabindex="0"><table class="av-table"><thead><tr><th>Reference</th><th>Helper</th><th>Concern</th><th>Status</th><th>Activity date</th><th>Actions</th></tr></thead><tbody>
@forelse($sessions as $session)<tr><td>{{ $session->reference_number }}</td><td>{{ $session->helper?->full_name ?? 'Unassigned' }}</td><td>{{ $session->concern?->concern_name ?? 'Not recorded' }}</td><td><span class="av-badge">{{ ucwords(str_replace('_',' ',$session->session_status)) }}</span>@if($session->archived_at)<p class="av-muted">Archived</p>@endif</td><td>{{ ($session->end_time ?? $session->start_time ?? $session->submitted_at ?? $session->created_date ?? $session->created_at)?->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }}</td><td><div class="av-actions"><a class="av-link" href="{{ route('adviser.session.show',$session->id) }}">View documentation</a>@include('adviser.partials.archive-action',['record'=>$session])</div></td></tr>
@empty<tr><td colspan="6" class="av-empty">No records match these filters.</td></tr>@endforelse
</tbody></table></div><div class="av-pagination">{{ $sessions->links() }}</div></section>
@elseif($tab==='performance')
<section class="av-panel"><header class="av-heading"><h2>Service performance</h2></header><p class="av-muted">Actual supervised service records in the selected period. Scheduled duty is planned coverage, not attendance.</p><div class="av-table-wrap mt-4"><table class="av-table"><thead><tr><th>Measure</th><th>Recorded result</th></tr></thead><tbody>
@foreach(['total'=>['Session records',''],'completed'=>['Completed sessions',''],'completion_rate'=>['Completion rate','%'],'referral_rate'=>['Referral rate','%'],'response_minutes'=>['Helper first response',' min'],'waiting_minutes'=>['Queue waiting time',' min'],'duration_minutes'=>['Completed session duration',' min'],'helpfulness'=>['Helpfulness rating (1–5)',''],'referral_approval_rate'=>['Referral approval rate','%'],'duty_hours'=>['Scheduled duty coverage',' h'],'competency'=>['Average competency (1–5)',''],'workload'=>['Completed sessions per serving Helper',''],'documentation_rate'=>['Documentation completion rate','%'],'documentation_pending'=>['Pending documentation',''],'emergency_rate'=>['Emergency escalation rate','%']] as $key=>$label)<tr><td>{{ $label[0] }}</td><td>{{ \App\Services\SessionPerformance::number($metrics[$key], $label[1]) }}@if(isset($samples[$key]))<small class="av-muted"> | {{ $samples[$key] }} records measured</small>@endif</td></tr>@endforeach
</tbody></table></div></section>
@php
    $monthLabel = fn ($m) => \Carbon\Carbon::createFromFormat('Y-m', $m)->format('M Y');
    $activityChart = ['type' => 'column', 'labels' => collect($trends)->pluck('month')->map($monthLabel)->values()->all(),
        'datasets' => [['label' => 'Session records', 'data' => collect($trends)->pluck('sessions')->values()->all()], ['label' => 'Completed', 'data' => collect($trends)->pluck('completed')->values()->all()]]];
    $competencyChart = ['type' => 'line', 'scale' => [1, 5], 'unit' => '', 'labels' => collect($competency)->pluck('month')->map($monthLabel)->values()->all(),
        'datasets' => [['label' => 'Average score (1–5)', 'data' => collect($competency)->pluck('score')->map(fn ($v) => $v === null ? null : (float) $v)->values()->all()]]];
@endphp
<div class="analytics-chart-grid report-chart-grid">
<x-analytics-chart title="Monthly session activity" description="Supervised session records and completions per month · Philippine Time" :chart="$activityChart" />
<x-analytics-chart title="Competency in the selected period" :description="ucwords(str_replace('_',' ', $filters['competency_metric'])).' from recorded evaluations, monthly average on a 1–5 scale.'" :chart="$competencyChart" />
</div>
<details class="av-panel"><summary class="font-semibold cursor-pointer">Calculation definitions</summary>@foreach($definitions as $label=>$definition)<p class="av-muted mt-3"><strong>{{ ucwords(str_replace('_',' ',$label)) }}:</strong> {{ $definition }}</p>@endforeach</details>
@else
@php
    $reportTable = collect($roleReport['tables'])->firstWhere('title', $tab==='safety' ? 'Emergency case activity' : 'Your recorded actions');
    $summaryLabels = $tab==='safety' ? ['Emergencies','Resolved emergencies','Unresolved emergencies','Emergency resolution time'] : [];
@endphp
@if($summaryLabels)<dl class="av-stats">@foreach($summaryLabels as $label)<div><dt>{{ $label }}</dt><dd>{{ $roleReport['summary'][$label] }}</dd></div>@endforeach</dl>@endif
@if($tab==='safety')<x-report-charts :groups="collect($roleReport['groups'])->only(['Emergency status','Emergency priority'])->all()" heading="Emergency overview" />@endif
<section class="av-panel"><header class="av-heading"><h2>{{ $tab==='safety' ? 'Emergency case activity' : 'Your recorded actions' }}</h2></header>
@if($tab==='activity')<p class="av-muted mb-4">Your auditable Adviser actions, including archive and restore events. Case and emergency records are available in their report groups.</p>@endif
<div class="av-table-wrap" role="region" aria-label="{{ $reportTable['title'] }}" tabindex="0"><table class="av-table"><thead><tr>@foreach($reportTable['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead><tbody>
@forelse($reportTable['records'] as $record)
@php
    $cells = call_user_func($reportTable['format'], $record);
@endphp
<tr>@foreach($cells as $cell)<td>{{ $cell ?? 'Not recorded' }}</td>@endforeach</tr>
@empty<tr><td colspan="{{ count($reportTable['columns']) }}" class="av-empty">No records match these filters.</td></tr>@endforelse
</tbody></table></div><div class="av-pagination">{{ $reportTable['records']->links() }}</div></section>
@endif
</div>
@endsection
