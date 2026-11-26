@extends('layouts.app')
@section('title','Adviser Dashboard - COMPASS')
@section('content')
<div class="adviser-page-content">
    <x-ui.page-header title="Adviser dashboard" description="Your supervised Helpers and authorized case updates.">
        <x-slot:actions>
            <a class="btn-primary" href="{{ route('adviser.reports', \App\Services\AnalyticsPeriod::fromRequest(request())->dates()) }}">View reports</a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.attention :items="[
        ['count' => $evalBadge, 'label' => 'Evaluations due', 'href' => route('adviser.evaluations'), 'tone' => 'amber'],
        ['count' => $screeningBadge, 'label' => 'Screening reviews', 'href' => route('adviser.screenings'), 'tone' => 'blue'],
        ['count' => $referralBadge, 'label' => 'Referrals to review', 'href' => route('adviser.referrals'), 'tone' => 'blue'],
        ['count' => $emergencyCount, 'label' => 'Emergencies open', 'href' => route('adviser.emergencies'), 'tone' => 'red'],
    ]" />

    <x-dashboard-overview part="summary" :overview="$overview" :show-recent="false" :show-definitions="false" :smooth-lines="true" />
    {{-- Charts sit right under the KPI cards (QA): same period, same look. --}}
    <x-dashboard-overview part="analytics" :overview="$overview" :show-recent="false" :show-definitions="false" :smooth-lines="true" />
    <div class="av-grid">
        <x-ui.card title="Session documentation">
            <x-slot:actions><a class="av-link" href="{{ route('adviser.evaluations') }}">View all reviews</a></x-slot:actions>
            @if($pendingEvaluations->isEmpty())
                <x-ui.empty title="No documented, concluded sessions are waiting for review." />
            @else
            <x-ui.table :stack="true" role="region" aria-label="Session documentation awaiting review">
                <thead><tr><th>Case</th><th>Helper</th><th>Submitted</th><th></th></tr></thead>
                <tbody>
                @foreach($pendingEvaluations as $report)
                    <tr>
                        <td data-label="Case">{{ $report->session->reference_number }}<p class="av-muted">{{ $report->session->seeker?->generated_alias ?? 'Seeker' }}</p></td>
                        <td data-label="Helper">{{ $report->session->helper?->full_name ?? 'Unassigned' }}</td>
                        <td data-label="Submitted">{{ ($report->summary_submitted_at ?? $report->created_at)->copy()->timezone('Asia/Manila')->format('M d, Y') }}</td>
                        <td data-label="Action" class="ui-actions"><a class="av-link" href="{{ route('adviser.evaluate', $report->id) }}">Evaluate</a></td>
                    </tr>
                @endforeach
                </tbody>
            </x-ui.table>
            @endif
        </x-ui.card>
        <x-ui.card title="Recent activity">
            <x-slot:actions><a class="av-link" href="{{ route('adviser.reports', ['tab'=>'activity']) }}">View all activity</a></x-slot:actions>
            @if(count($recentActivity) === 0)
                <x-ui.empty title="No recent supervision activity." />
            @else
            @php $activityIcons = ['emergency' => 'warning', 'evaluation' => 'clipboard', 'referral' => 'share', 'helper' => 'user']; @endphp
            <ol class="av-timeline">
            @foreach($recentActivity as $activity)
                <li class="av-timeline-item av-timeline-item--{{ $activity['type'] ?? 'other' }}">
                    <span class="av-timeline-icon" aria-hidden="true"><x-ui-icon :name="$activityIcons[$activity['type'] ?? ''] ?? 'activity'" /></span>
                    <div class="av-timeline-body"><p class="av-timeline-title">{{ $activity['message'] }}</p><p class="av-muted">{{ \Illuminate\Support\Str::limit($activity['detail'], 70) }}</p></div>
                    <time class="av-timeline-time">{{ $activity['time'] }}</time>
                </li>
            @endforeach
            </ol>
            @endif
        </x-ui.card>
    </div>
    <x-ui.card title="Supervised Helpers" :subtitle="$totalHelpers.' assigned to you. Availability uses the current matching eligibility rules.'">
        <x-slot:actions><a class="av-link" href="{{ route('adviser.helpers') }}">View all Helpers</a></x-slot:actions>
        @if($helpers->isEmpty())
            <x-ui.empty title="No Helpers are currently assigned to you." />
        @else
        <x-ui.table :stack="true" role="region" aria-label="Supervised Helpers">
            <thead><tr><th>Helper</th><th>Availability</th><th>Readiness</th><th>Workload</th><th>Competency</th></tr></thead>
            <tbody>
            @foreach($helpers->take(5) as $helper)
                @php
                    $eligibility = app(\App\Services\HelperEligibilityService::class)->status($helper);
                @endphp
                <tr>
                    <td data-label="Helper"><a class="av-link" href="{{ route('adviser.helper.show', $helper->id) }}">{{ $helper->full_name ?: 'Name not recorded' }}</a></td>
                    <td data-label="Availability"><x-ui.badge :tone="$eligibility['assignable'] ? 'green' : 'neutral'">{{ $eligibility['label'] }}</x-ui.badge></td>
                    <td data-label="Readiness">{{ ucwords(str_replace('_', ' ', $helper->getReadinessStatus())) }}</td>
                    <td data-label="Workload"><x-helper-capacity :helper="$helper" /></td>
                    <td data-label="Competency">{{ ([1=>'Trainee',2=>'Beginner',3=>'Intermediate',4=>'Advanced',5=>'Expert'][$helper->competency_level] ?? 'Not assessed') }}</td>
                </tr>
            @endforeach
            </tbody>
        </x-ui.table>
        @endif
        @if($totalHelpers > min(5, $helpers->count()))<p class="av-muted mt-3">Showing {{ min(5, $helpers->count()) }} of {{ $totalHelpers }} Helpers. <a class="av-link" href="{{ route('adviser.helpers') }}">View all</a></p>@endif
    </x-ui.card>
</div>
<style>
.av-timeline{list-style:none;margin:0;padding:0;display:grid;gap:2px}
.av-timeline-item{display:grid;grid-template-columns:32px minmax(0,1fr) auto;gap:10px;align-items:start;padding:10px 0;border-top:1px solid #edf0ee}
.av-timeline-item:first-child{border-top:0}
.av-timeline-icon{display:inline-grid;place-items:center;width:32px;height:32px;border-radius:999px;background:#ecfdf3;color:#087341}
.av-timeline-icon .compass-icon{width:16px;height:16px}
.av-timeline-item--emergency .av-timeline-icon{background:#fef2f2;color:#b91c1c}
.av-timeline-item--referral .av-timeline-icon{background:#eff6ff;color:#1d4ed8}
.av-timeline-title{font-size:14px;font-weight:600;margin:0;color:#163b2d}
.av-timeline-body .av-muted{margin:2px 0 0;font-size:12px}
.av-timeline-time{font-size:12px;color:#64748b;white-space:nowrap}
</style>
@endsection
