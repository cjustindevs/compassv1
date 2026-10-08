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
<div class="hf-kpis">
    <article class="hf-kpi">
<span>Upcoming Session</span>
@if($upcomingSession)
<strong class="hf-upcoming">{{ $upcomingSession->scheduled_start->copy()->timezone('Asia/Manila')->format('M d, g:i A') }}</strong>
<small>{{ $upcomingSession->reference_number }} / Philippine Time</small>
@else<strong class="hf-upcoming">No upcoming sessions</strong>
<small>Your next scheduled session will appear here.</small>
@endif</article>
    <article class="hf-kpi">
<span>Active Sessions</span>
<strong>{{ $stats['active_sessions'] }}</strong>
<small>Currently ongoing</small>
</article>
    <article class="hf-kpi">
<span>Pending Requests</span>
<strong>{{ $stats['pending_requests'] }}</strong>
<small>Awaiting your action</small>
</article>
    <article class="hf-kpi">
<span>Competency Score</span>
<strong>{{ $stats['competency_score']!==null ? $stats['competency_score'].'%' : 'Not evaluated' }}</strong>
<small>{{ $competency?->level_label ?? 'No evaluation recorded' }}</small>
</article>
</div>
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
