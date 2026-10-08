@extends('layouts.app')
@section('title','Adviser Dashboard - COMPASS')
@section('content')
<div class="adviser-page-content av-page">
    <header><div><h1>Adviser dashboard</h1><p class="av-muted">Your supervised Helpers and authorized case updates.</p></div><a class="av-button" href="{{ route('adviser.reports') }}">View reports</a></header>
    <x-dashboard-overview :overview="$overview" :show-recent="false" :show-definitions="false" />
    <section class="av-panel">
        <header class="av-heading"><div><h2>Supervised Helpers</h2><p class="av-muted">{{ $totalHelpers }} assigned to you. Availability uses the current matching eligibility rules.</p></div><a class="av-link" href="{{ route('adviser.helpers') }}">View all Helpers</a></header>
        <div class="av-table-wrap" role="region" aria-label="Supervised Helpers" tabindex="0"><table class="av-table"><thead><tr><th>Helper</th><th>Availability</th><th>Readiness</th><th>Workload</th><th>Competency</th></tr></thead><tbody>
        @forelse($helpers as $helper)
            @php
                $eligibility = app(\App\Services\HelperEligibilityService::class)->status($helper);
            @endphp
            <tr><td><a class="av-link" href="{{ route('adviser.helper.show', $helper->id) }}">{{ $helper->full_name ?: 'Name not recorded' }}</a></td><td><span class="av-badge {{ $eligibility['assignable'] ? '' : 'av-badge-muted' }}">{{ $eligibility['label'] }}</span></td><td>{{ ucwords(str_replace('_', ' ', $helper->getReadinessStatus())) }}</td><td>{{ $helper->currentAssignedSessionsCount() }} / {{ \App\Models\Helper::MAX_SESSIONS_PER_SHIFT }}</td><td>{{ ([1=>'Trainee',2=>'Beginner',3=>'Intermediate',4=>'Advanced',5=>'Expert'][$helper->competency_level] ?? 'Not assessed') }}</td></tr>
        @empty<tr><td colspan="5" class="av-empty">No Helpers are currently assigned to you.</td></tr>
        @endforelse
        </tbody></table></div>
        @if($totalHelpers > $helpers->count())<p class="av-muted mt-3">Showing {{ $helpers->count() }} Helpers. Open View all for the full list.</p>@endif
    </section>
    <div class="av-grid">
        <section class="av-panel">
            <header class="av-heading"><h2>Session documentation</h2><a class="av-link" href="{{ route('adviser.evaluations') }}">View all reviews</a></header>
            <div class="av-table-wrap" role="region" aria-label="Session documentation awaiting review" tabindex="0"><table class="av-table av-compact-table"><thead><tr><th>Case</th><th>Helper</th><th>Submitted</th><th></th></tr></thead><tbody>
            @forelse($pendingEvaluations as $report)
                <tr><td>{{ $report->session->reference_number }}<p class="av-muted">{{ $report->session->seeker?->generated_alias ?? 'Seeker' }}</p></td><td>{{ $report->session->helper?->full_name ?? 'Unassigned' }}</td><td>{{ ($report->summary_submitted_at ?? $report->created_at)->copy()->timezone('Asia/Manila')->format('M d, Y') }}</td><td><a class="av-link" href="{{ route('adviser.evaluate', $report->id) }}">Evaluate</a></td></tr>
            @empty<tr><td colspan="4" class="av-empty">No documented, concluded sessions are waiting for review.</td></tr>
            @endforelse
            </tbody></table></div>
        </section>
        <section class="av-panel">
            <header class="av-heading"><h2>Recent activity</h2><a class="av-link" href="{{ route('adviser.reports', ['tab'=>'activity']) }}">View all activity</a></header>
            <ul class="av-activity">@forelse($recentActivity as $activity)<li><p class="font-semibold">{{ $activity['message'] }}</p><p class="av-muted">{{ $activity['detail'] }}</p><time>{{ $activity['time'] }}</time></li>@empty<li class="av-empty">No recent supervision activity.</li>@endforelse</ul>
        </section>
    </div>
</div>
@endsection
