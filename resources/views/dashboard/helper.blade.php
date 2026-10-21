@extends('layouts.helper')
@section('title','Dashboard')
@section('heading','Good '.(now('Asia/Manila')->hour < 12 ? 'morning' : (now('Asia/Manila')->hour < 18 ? 'afternoon' : 'evening')).', '.(($helper ?? null)?->first_name ?? 'Helper'))
@section('subheading','Here is what is happening with your sessions today.')
@section('content')
<div class="hf-page">
@if(isset($helperProfileMissing) && $helperProfileMissing)
<section class="card empty-state">
<h3>Your Helper profile is incomplete</h3>
<p>Contact your Adviser or administrator to finish onboarding.</p>
</section>
@else
<x-analytics-period :action="route('helper.dashboard')" :dates="$analyticsDates" />
@include('helper.partials.duty-summary')
@php
    $m = $performance['metrics']; $n = $performance['samples'];
    $metricValue = fn ($key, $unit = '') => \App\Services\SessionPerformance::number($m[$key], $unit);
    $reportLink = route('helper.reports', $analyticsDates + ['period'=>request('period','monthly')]);
    $performanceCards = [
        ['label'=>'Completed sessions','value'=>$m['completed'],'detail'=>'Actual session end in selected period','link'=>$reportLink],
        ['label'=>'Competency score','value'=>$metricValue('competency','/5'),'detail'=>'Finalized current-rubric evaluations','samples'=>$n['competency'],'link'=>route('helper.competency')],
        ['label'=>'Positive helpfulness feedback','value'=>$metricValue('helpfulness_rate','%'),'detail'=>'Helpful / Very Helpful answers','samples'=>$n['helpfulness_rate'],'link'=>$reportLink],
        ['label'=>'Documentation completion','value'=>$metricValue('documentation_rate','%'),'detail'=>'Summary and reflection submitted','samples'=>$n['documentation_rate'],'link'=>route('helper.cases')],
    ];
@endphp
<x-performance-cards :cards="$performanceCards" />
<div style="display:flex;align-items:center;flex-wrap:wrap;gap:16px;margin:12px 0"><x-helper-capacity :helper="$helper" /><p class="hf-muted">Pending documentation: <strong>{{ $helper->sessions()->whereNotNull('start_time')->whereNotNull('end_time')->where('end_time','<=',now())->where('documentation_status', '!=', 'submitted')->whereIn('session_status',['completed','evaluated','cancelled','no_show'])->count() }}</strong></p></div>
<p class="hf-muted"><strong>Upcoming Session:</strong> @if($upcomingSession){{ $upcomingSession->scheduled_start->copy()->timezone('Asia/Manila')->format('M d, g:i A') }} PHT | {{ $upcomingSession->reference_number }}@else No upcoming sessions. @endif</p>
@if(count($emergencyCases))
<section class="card">
<header class="card-header">
<h3>Emergency support</h3>
<span class="hf-status hf-status-danger">Attention required</span>
</header>
<p class="hf-muted">{{ $emergencyCases[0]['alias'] }} / {{ $emergencyCases[0]['incident'] }}</p>
<a href="{{ route('helper.cases.show',$emergencyCases[0]['id']) }}" class="btn btn-secondary btn-sm">Open assigned case</a>
</section>
@endif
<details class="card hf-secondary" style="margin:14px 0"><summary>Service metrics and calculation notes</summary>
<dl class="helper-performance-details">
@foreach(['response_minutes'=>['Average Helper Response Time',' min'],'waiting_minutes'=>['Average waiting time',' min'],'duration_minutes'=>['Average session duration',' min'],'completion_rate'=>['Session completion rate','%'],'documentation_rate'=>['Documentation completion rate','%'],'duty_hours'=>['Scheduled duty hours',' hr']] as $key=>[$label,$unit])
<div><dt>{{ $label }}</dt><dd>{{ $metricValue($key,$unit) }}@if(isset($n[$key])) | {{ $n[$key] }} records measured @endif</dd></div>
@endforeach
<div><dt>Competency growth</dt><dd>{{ $performance['growth'] === null ? 'Insufficient history' : ($performance['growth'] >= 0 ? '+' : '').$performance['growth'].' points / 5' }}</dd></div>
</dl>
<p class="hf-muted">Response time measures session start to your first message or connected voice call. Waiting time measures queue entry to session start. Helpfulness uses original categorical feedback; legacy composite ratings stay in history. Scheduled hours are planned coverage, not verified attendance. Documentation completion requires both summary and reflection; it is not an on-time rate. Historical metrics include archived sessions.</p>
<a href="{{ $reportLink }}" class="link">View service report</a>
</details>
@php
    $growthRows = $performance['evaluations']->filter(fn ($e) => $e->rubric_version === \App\Services\CompetencyRubric::VERSION);
    $growthChart = ['title'=>'Competency growth trend','unit'=>'Rating / 5','values'=>$growthRows->groupBy(fn ($e) => $e->evaluation_date->timezone('Asia/Manila')->format('M Y'))->map(fn ($group) => round($group->avg('normalized_score'),2))->all(),'definition'=>'Average finalized evaluations per month on the current rubric, rated 1-5. Missing months have no evaluation; no performance is inferred. At least two evaluations are needed for growth.'];
@endphp
@if($growthRows->count() >= 2)<x-dashboard-chart :chart="$growthChart" :smooth="true" />@else<section class="card" style="margin:14px 0"><h3>Competency growth</h3><p class="hf-muted">Insufficient history. At least two evaluations on the current rubric are required.</p></section>@endif
<style>.helper-performance-details{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:14px 0}.helper-performance-details dt{font-size:12px;color:#64748b}.helper-performance-details dd{font-size:14px;margin:4px 0}@media(max-width:560px){.helper-performance-details{grid-template-columns:1fr}}</style>
<link rel="stylesheet" href="{{ asset('css/dashboard-overview.css') }}">

<div class="hf-grid" style="margin-bottom:20px">
    <section class="card hf-availability">
<header class="card-header">
<h3>Availability</h3>
<a href="{{ route('helper.calendar') }}" class="link">Duty schedule</a>
</header>
<strong data-dashboard-availability style="color:{{ $sidebarStats['availabilityStatus']==='available' ? 'var(--green-500)' : 'var(--yellow-500)' }}">{{ $sidebarStats['availabilityLabel'] }}</strong>
<p class="hf-muted" data-dashboard-availability-reason>{{ $sidebarStats['availabilityReason'] }}</p>
<div class="hf-actions" style="margin-top:12px">
<a class="btn btn-secondary btn-sm" href="{{ route('helper.availability') }}">View availability</a>
</div>
</section>
    <section class="card">
<header class="card-header">
<h3>Readiness</h3>
<strong data-dashboard-readiness class="hf-status {{ $helper->isReady() ? '' : 'hf-status-warning' }}">{{ $helper->getReadinessStatus()==='ready' ? 'Ready' : ($helper->getReadinessStatus()==='not_ready' ? 'Not ready' : 'Readiness required') }}</strong>
</header>
<p class="hf-muted" data-dashboard-readiness-until>
@if($currentReadiness?->assessment_result==='ready')Valid until {{ $currentReadiness->valid_until?->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT.@else Complete a current readiness check before accepting cases.@endif</p>
<div class="hf-actions" style="margin-top:12px">
<a class="btn btn-secondary btn-sm" href="{{ route('helper.readiness') }}">Review readiness</a>
<a class="btn btn-secondary btn-sm" href="{{ route('helper.readiness.history') }}">Readiness history</a>
</div>
</section>
</div>
@if($activeSessionData)
<section class="card">
<header class="card-header">
<h3>Active Session</h3>
<span class="hf-status">Live</span>
</header>
<p>{{ $activeSessionData['alias'] }} / {{ $activeSessionData['mode'] }} / {{ $activeSessionData['elapsed'] }} elapsed</p>
@if($activeSessionData['elapsed_minutes']>=85)
<p class="hf-muted">{{ $activeSessionData['elapsed_minutes']>=90 ? '90-minute limit exceeded. End and document this session.' : 'Approaching the 90-minute session limit.' }}</p>
@endif<a class="btn btn-primary btn-sm" href="{{ route('helper.cases.show',$activeSessionData['id']) }}">Resume session</a>
</section>
@endif
<div class="hf-grid" style="margin-bottom:20px">
    <div>
<section class="card">
<header class="card-header">
<h3>Documentation to complete</h3>
</header>
<ul class="hf-list">
@forelse($documentationTasks as $task)
<li>
<div class="hf-actions" style="justify-content:space-between">
<span>{{ $task->reference_number }} / {{ $task->end_time?->lt(now()->subHours(24)) ? 'Overdue' : 'Pending' }}</span>
<a class="btn btn-secondary btn-sm" href="{{ route('helper.session.notes',$task->id) }}">Open documentation</a>
</div>
</li>
@empty<li class="hf-muted">No session documentation is waiting for you.</li>
@endforelse</ul>
</section>
<section class="card">
<header class="card-header">
<h3>Quick Actions</h3>
</header>
<div class="hf-actions">
@foreach($quickActions as $action)
<a class="btn btn-secondary btn-sm" href="{{ route($action['route'],$action['params']) }}">{{ $action['label']==='Session Notes' ? 'Session documentation' : $action['label'] }}</a>
@endforeach</div>
</section>
</div>
    <section class="card">
<header class="card-header">
<h3>Recent Activity</h3>
<a class="link" href="{{ route('helper.notifications') }}">View all</a>
</header>
<ul class="hf-list">
@forelse($recentActivity as $activity)
<li>
<header>
<strong>{{ $activity['message'] }}</strong>
<time>{{ $activity['time'] }}</time>
</header>
<p class="hf-muted">{{ \Illuminate\Support\Str::limit($activity['detail'],160) }}</p>
@if(!empty($activity['link']))
<a class="link" href="{{ $activity['link'] }}">Open update</a>
@endif</li>
@empty<li class="hf-muted">No important updates yet.</li>
@endforelse</ul>
</section>
</div>
<section class="card">
<header class="card-header">
<h3>Assigned Cases</h3>
<a class="link" href="{{ route('helper.cases') }}">View all</a>
</header>
<div class="table-container">
<table>
<thead>
<tr>
<th>Case</th>
<th>Seeker alias</th>
<th>Concern</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>
<tbody>
@forelse($activeCases as $case)
<tr>
<td>{{ $case['reference'] }}</td>
<td>{{ $case['alias'] }}</td>
<td>{{ $case['notes'] }}</td>
<td>
<span class="hf-status">{{ ucwords(str_replace('_',' ',$case['status'])) }}</span>
</td>
<td>
<a class="btn btn-secondary btn-sm" href="{{ route('helper.cases.show',$case['id']) }}">Open case</a>
</td>
</tr>
@empty<tr>
<td colspan="5">
<div class="empty-state">
<h3>No assigned cases</h3>
<p>You do not need to take action. New assignments will appear here when a case becomes available.</p>
</div>
</td>
</tr>
@endforelse</tbody>
</table>
</div>
</section>
<section class="card">
<header class="card-header">
<h3>Recent Sessions</h3>
<a class="link" href="{{ route('helper.reports') }}">View all</a>
</header>
<div class="table-container">
<table>
<thead>
<tr>
<th>Session</th>
<th>Category</th>
<th>Status</th>
<th>Date (PHT)
</th>
<th>Action</th>
</tr>
</thead>
<tbody>
@forelse($recentSessions as $session)
<tr>
<td>{{ $session->reference_number }}</td>
<td>{{ $session->concern?->concern_name ?? 'Unrecorded' }}</td>
<td>
<span class="hf-status hf-status-muted">{{ $session->status_label }}</span>
</td>
<td>{{ ($session->end_time ?? $session->created_date ?? $session->created_at)?->copy()->timezone('Asia/Manila')->format('M d, Y') }}</td>
<td>
<a class="link" href="{{ route('helper.cases.show',$session->id) }}">View session</a>
</td>
</tr>
@empty<tr>
<td colspan="5" class="empty-state">Your recent session history will appear here.</td>
</tr>
@endforelse</tbody>
</table>
</div>
</section>
@endif
</div>
@endsection
