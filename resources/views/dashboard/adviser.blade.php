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
                        <td data-label="Action"><a class="av-link" href="{{ route('adviser.evaluate', $report->id) }}">Evaluate</a></td>
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
            <ul class="av-activity">@foreach($recentActivity as $activity)<li><p class="font-semibold">{{ $activity['message'] }}</p><p class="av-muted">{{ $activity['detail'] }}</p><time>{{ $activity['time'] }}</time></li>@endforeach</ul>
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
            @foreach($helpers as $helper)
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
        @if($totalHelpers > $helpers->count())<p class="av-muted mt-3">Showing {{ $helpers->count() }} Helpers. Open View all for the full list.</p>@endif
    </x-ui.card>
    <x-dashboard-overview part="analytics" :overview="$overview" :show-recent="false" :show-definitions="false" :smooth-lines="true" />
</div>
@endsection
