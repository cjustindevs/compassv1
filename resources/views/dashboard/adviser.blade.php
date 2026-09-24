@extends('layouts.app')
@section('title','Adviser Dashboard - COMPASS')
@push('styles')<style>
.adviser-page-content .card,.adviser-page-content .stat-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:20px;margin-bottom:16px}
.adviser-page-content .card-header{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.adviser-page-content .card-header h3{font-weight:700}.adviser-page-content .stat-label{font-size:13px;color:#6b7280}.adviser-page-content .stat-number{font-size:26px;font-weight:700;color:#163b2d}
.adviser-page-content table{width:100%;font-size:13px;text-align:left}.adviser-page-content th,.adviser-page-content td{padding:12px;border-bottom:1px solid #e5e7eb}.adviser-page-content .btn-small{color:var(--green-600,#038a45);font-weight:600}
</style>@endpush
@section('content')
<div class="adviser-page-content">
<header class="mb-5"><h1 class="text-2xl font-bold text-gray-800">Adviser dashboard</h1><p class="text-sm text-gray-500 mt-1">Your supervised Helpers and authorized review tasks.</p></header>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
@foreach(['Assigned Helpers'=>$totalHelpers,'Documentation awaiting review'=>$pendingEvaluationCount,'Referrals awaiting review'=>$pendingReferralCount,'Emergency reviews'=>$emergencyReviewCount] as $label=>$value)<div class="stat-card"><p class="stat-label">{{ $label }}</p><p class="stat-number">{{ $value }}</p></div>@endforeach
</div>
<details class="card mb-5" open><summary class="font-semibold cursor-pointer mb-4">Performance in the last 30 days</summary><x-adviser-metrics :metrics="$reportData['metrics']" :definitions="$reportData['definitions']" /><p class="text-sm text-gray-500 mt-4">Asia/Manila activity cohort. <a class="text-green-700 underline" href="{{ route('adviser.reports') }}">Metric definitions and filtered reports</a></p></details>
<p class="text-sm mb-5"><a class="text-green-700 underline" href="{{ route('adviser.training') }}">{{ $trainingFollowUpCount }} training completions awaiting review</a></p>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="card lg:col-span-2">
                <div class="card-header">
                    <h3>Matching Oversight</h3>
                    <a href="{{ route('adviser.schedule') }}">Manage schedules</a>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                    <div class="p-3 bg-gray-50 rounded-xl"><div class="text-xs text-gray-400">Helpers</div><div class="font-bold text-gray-800">{{ $matchingStats['total_helpers'] }}</div></div>
                    <div class="p-3 bg-gray-50 rounded-xl"><div class="text-xs text-gray-400">Available</div><div class="font-bold text-gray-800">{{ $matchingStats['available_helpers'] }}</div></div>
                    <div class="p-3 bg-gray-50 rounded-xl"><div class="text-xs text-gray-400">Avg Competency</div><div class="font-bold text-gray-800">{{ $matchingStats['average_competency'] }}</div></div>
                    <div class="p-3 bg-gray-50 rounded-xl"><div class="text-xs text-gray-400">Avg Match</div><div class="font-bold text-gray-800">{{ $matchingStats['avg_matching_score'] }}%</div></div>
                </div>
                <div class="space-y-2">
                    @foreach($helpers->take(6) as $helper)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                            <div>
                                <p class="font-medium text-gray-800">{{ $helper->user?->name ?? $helper->full_name }}</p>
                                <p class="text-xs text-gray-500">{{ ucfirst($helper->availability ?? $helper->status) }} · {{ str_replace('_', ' ', $helper->getReadinessStatus()) }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-gray-500">{{ $helper->current_shift_sessions }}/{{ \App\Models\Helper::MAX_SESSIONS_PER_SHIFT }}</span>
                                <a class="text-sm text-green-600 hover:underline" href="{{ route('adviser.helper.matching', $helper->id) }}">Details</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h3>Matching Alerts</h3>
                    <a href="{{ route('adviser.transcripts') }}">Transcripts</a>
                </div>
                <div class="space-y-3 text-sm">
                    <div class="p-3 rounded-xl {{ $helpersAtCapacity->isEmpty() ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700' }}">
                        {{ $helpersAtCapacity->count() }} helper(s) at shift capacity
                    </div>
                    <div class="p-3 rounded-xl {{ $helpersExpiredReadiness->isEmpty() ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                        {{ $helpersExpiredReadiness->count() }} helper(s) need readiness refresh
                    </div>
                    <div class="p-3 rounded-xl bg-blue-50 text-blue-700">
                        {{ $assignedQueueCount }} assigned queue request(s)
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── HIGH RISK ALERT ─── -->
        @if($highRiskCases->isNotEmpty())
            <div class="mb-6 p-4 bg-red-50 rounded-xl border border-red-200">
                <div class="flex items-center gap-3">
                    <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                    <div>
                        <p class="text-sm font-medium text-red-700">High-Risk Cases Require Attention</p>
                        <p class="text-xs text-red-600">Review these cases immediately</p>
                    </div>
                </div>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach($highRiskCases as $case)
                        <span class="px-3 py-1 bg-white rounded-full text-xs font-medium text-red-600 border border-red-200">
                            {{ $case->seeker->generated_alias ?? 'Anonymous' }} - {{ ucfirst($case->risk_level) }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- ─── LEFT COLUMN (2/3) ─── -->
            <div class="lg:col-span-2">

                <!-- Pending Evaluations -->
                <div class="card mb-6">
                    <div class="card-header">
                        <h3>Pending Evaluations</h3>
                        <a href="{{ route('adviser.evaluations') }}">View all</a>
                    </div>
                    @if($pendingEvaluations->isNotEmpty())
                        <div class="space-y-3">
                            @foreach($pendingEvaluations->take(5) as $report)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                    <div>
                                        <p class="font-medium text-gray-800">
                                            {{ $report->session->seeker->generated_alias ?? 'Anonymous' }}
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            {{ $report->session->helper->first_name ?? 'Unknown' }} · {{ $report->created_at->format('M d, Y') }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="status-badge pending">Pending</span>
                                        <a href="#" class="text-sm text-green-600 hover:underline">Review</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 text-gray-400">
                            <p>No pending evaluations</p>
                        </div>
                    @endif
                </div>

                <!-- Evaluation Queue -->
                <div class="card mb-6">
                    <div class="card-header">
                        <h3>Evaluation Queue</h3>
                        <span class="text-xs text-gray-400">Session waiting time</span>
                    </div>
                    <div class="space-y-2">
                        @foreach($waitingTimeStats as $sessionId => $data)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                <div>
                                    <p class="font-medium text-gray-800">{{ $sessionId }}</p>
                                    <p class="text-sm text-gray-500">{{ $data['helper'] }}</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="status-badge {{ strtolower(str_replace(' ', '-', $data['status'])) }}">
                                        {{ $data['status'] }}
                                    </span>
                                    <span class="text-sm text-gray-400">{{ $data['waiting'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Pre-work Feedback -->
                <div class="card">
                    <div class="card-header">
                        <h3>Pre-work Feedback Given</h3>
                    </div>
                    <div class="space-y-2">
                        @foreach($helperProgress as $item)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                <div>
                                    <p class="font-medium text-gray-800">{{ $item['session'] }}</p>
                                    <p class="text-sm text-gray-500">{{ $item['feedback'] }}</p>
                                </div>
                                <span class="text-xs text-green-600 font-medium"><i class="fas fa-check" aria-hidden="true"></i> Feedback given</span>
                            </div>
                        @endforeach
                    </div>
                </div>

            </div>

            <!-- ─── RIGHT COLUMN (1/3) ─── -->
            <div class="lg:col-span-1">

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Activity</h3>
                    </div>
                    <div>
                        @foreach($recentActivity as $activity)
                            <div class="activity-item">
                                <div class="icon {{ $activity['type'] }}">
                                    @if($activity['type'] == 'emergency')
                                        <i class="fas fa-exclamation"></i>
                                    @elseif($activity['type'] == 'evaluation')
                                        <i class="fas fa-star"></i>
                                    @elseif($activity['type'] == 'referral')
                                        <i class="fas fa-arrow-right"></i>
                                    @else
                                        <i class="fas fa-user"></i>
                                    @endif
                                </div>
                                <div class="content">
                                    <div class="message">{{ $activity['message'] }}</div>
                                    <div class="detail">{{ $activity['detail'] }}</div>
                                    <div class="time">{{ $activity['time'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

            </div>

        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Quality supervision leads to quality support.
        </div>

    </div>
@endsection
