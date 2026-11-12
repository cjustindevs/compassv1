@extends('layouts.app')

@section('title', 'COMPASS – Pending Evaluations')

@section('content')
<div class="adviser-page-content">
    <x-ui.page-header title="Pending Evaluations" description="Review session documentation and evaluate helper competency." />

    <x-ui.kpi-grid>
        <x-ui.kpi label="Pending Reviews" :value="$totalPending" tone="green" icon="clipboard" note="Sessions awaiting review" />
        <x-ui.kpi label="High Risk" :value="$highRiskPending" tone="red" icon="warning" note="Require immediate attention" />
        <x-ui.kpi label="Total Feedback Given" :value="$feedbackCount" tone="green" icon="check-circle" note="Evaluations completed" />
    </x-ui.kpi-grid>

    <x-ui.card class="ui-eval-process">
        <div class="ui-eval-process-body">
            <div class="ui-eval-process-intro">
                <h2 class="ui-card-title">Evaluation Process</h2>
                <p class="ui-card-sub">A 4-step workflow for reviewing and evaluating helper sessions.</p>
            </div>
            <x-evaluation-steps :current="3" variant="stepper" />
        </div>
    </x-ui.card>

    <x-ui.card title="Awaiting review" id="awaitingReview">
        <x-slot:actions><x-ui.badge tone="green">{{ $totalPending }} sessions</x-ui.badge></x-slot:actions>

        @if($pendingReports->isNotEmpty())
            <div class="ui-filter-bar ui-eval-controls">
                <div class="ui-field ui-eval-search">
                    <label for="evalSearch">Search</label>
                    <input type="search" id="evalSearch" data-control="search" placeholder="Search by help seeker, helper, or concern…" autocomplete="off">
                </div>
                <div class="ui-field">
                    <label for="evalRisk">Risk</label>
                    <select id="evalRisk" data-control="select">
                        <option value="all">All Risk Levels</option>
                        <option value="high">High (includes emergency)</option>
                        <option value="moderate">Moderate</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                <div class="ui-field">
                    <label for="evalSort">Sort</label>
                    <select id="evalSort" data-control="select">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="risk">Highest Risk First</option>
                        <option value="alias">Alias A–Z</option>
                    </select>
                </div>
            </div>
            <p class="ui-list-sub ui-eval-showing"><span id="evalShowing">Showing {{ $pendingReports->count() }} of {{ $pendingReports->count() }} on this page</span> · Search, filter and sort apply to this page only.</p>
            <div class="ui-eval-list">
            @foreach($pendingReports as $report)
                @php
                    $risk = $report->session->risk_level ?? 'low';
                    $riskTone = ['low' => 'green', 'moderate' => 'amber'][$risk] ?? 'red';
                    $alias = $report->session->seeker->generated_alias ?? 'Anonymous';
                    $searchText = mb_strtolower(trim($alias.' '.($report->session->helper->first_name ?? '').' '.($report->session->helper->last_name ?? '').' '.($report->session->concern->concern_name ?? 'General')));
                @endphp
                <div class="ui-eval-row ui-eval-row--{{ $risk }}" data-eval-row data-risk="{{ $risk }}" data-time="{{ $report->created_at->timestamp }}" data-alias="{{ $alias }}" data-search="{{ $searchText }}">
                    <span class="ui-avatar" aria-hidden="true">{{ strtoupper(substr($alias, 0, 1)) }}</span>
                    <div class="ui-eval-who">
                        <p class="ui-list-title">{{ $alias }}</p>
                        <p class="ui-list-sub">
                            {{ $report->session->helper->first_name ?? 'Unknown' }} {{ $report->session->helper->last_name ?? '' }}
                            · {{ $report->created_at->format('M d, Y') }}
                            · {{ $report->session->session_type ?? 'Chat' }}
                        </p>
                    </div>
                    <div class="ui-eval-risk">
                        <x-ui.badge :tone="$riskTone">{{ ucfirst($risk) }} Risk</x-ui.badge>
                        <p class="ui-list-sub">
                            Concern: {{ $report->session->concern->concern_name ?? 'General' }}
                            @if($report->referral_recommended)
                                <span class="ui-eval-referral"> Referral Recommended</span>
                            @endif
                        </p>
                    </div>
                    <div class="ui-eval-progress">
                        <x-evaluation-steps :current="3" variant="dots" />
                        <p class="ui-list-sub">Awaiting your evaluation</p>
                    </div>
                    <div class="ui-eval-actions">
                        <a href="{{ route('adviser.session.show', $report->session_id) }}" class="btn-outline">
                            <x-ui-icon name="eye" /> View Session
                        </a>
                        <a href="{{ route('adviser.evaluate', $report->id) }}" class="btn-primary">
                            <x-ui-icon name="check" /> Evaluate
                        </a>
                    </div>
                </div>
            @endforeach
            </div>
            <p class="ui-list-sub ui-eval-nomatch" id="evalNoMatch" hidden>No sessions on this page match.</p>
            {{ $pendingReports->links('vendor.pagination.compass', ['noun' => 'sessions']) }}
        @else
            <x-ui.empty title="All caught up!" text="No pending evaluations to review">
                @if(!empty($heldReports))
                    <p class="text-sm mt-3 text-gray-500">
                        {{ $heldReports }} {{ $heldReports === 1 ? 'report is' : 'reports are' }} withheld
                        until the session is completed or evaluated.
                    </p>
                @endif
                @if(!empty($undocumentedReports))
                    <p class="text-sm mt-3 text-gray-500">
                        {{ $undocumentedReports }} {{ $undocumentedReports === 1 ? 'report is' : 'reports are' }} awaiting
                        session documentation from the Helper.
                    </p>
                @endif
            </x-ui.empty>
        @endif
    </x-ui.card>

        <div class="av-actions mb-4"><a class="av-button" href="{{ route('adviser.evaluations') }}">Recent reviews</a><a class="av-button" href="{{ route('adviser.evaluations',['history'=>'archived']) }}">Archived reviews</a></div>
        <!-- Recently Completed -->
        @if($completedReports->isNotEmpty())
            <x-ui.card :title="request('history')==='archived' ? 'Archived reviews' : 'Recently Completed'">
                <x-slot:actions><span class="text-sm text-gray-400">{{ $completedReports->total() }} reviews</span></x-slot:actions>
                @foreach($completedReports as $report)
                    <div class="ui-list-row">
                        <div class="ui-list-main">
                            <p class="ui-list-title">
                                {{ $report->session->seeker->generated_alias ?? 'Anonymous' }}
                                <x-ui.badge tone="green">Reviewed</x-ui.badge>
                            </p>
                            <p class="ui-list-sub">
                                {{ $report->session->helper?->full_name ?: 'Unassigned' }}
                                · {{ $report->updated_at->format('M d, Y') }}
                            </p>
                            @if($report->adviser_review_note)
                                <p class="ui-list-sub">Completion reason: {{ $report->adviser_review_note }}</p>
                            @endif
                        </div>
                        <div class="av-actions"><span class="text-xs text-gray-400">{{ $report->updated_at->diffForHumans() }}</span>@include('adviser.partials.archive-action',['record'=>$report->session])</div>
                    </div>
                @endforeach
                {{ $completedReports->links() }}
            </x-ui.card>
        @else
            <section class="av-panel av-empty">{{ request('history')==='archived' ? 'No completed reviews have been archived.' : 'No completed reviews to show.' }}</section>
        @endif

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">

            Quality supervision leads to quality support.
        </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/eval-list-filter.js') }}?v={{ filemtime(public_path('js/eval-list-filter.js')) }}" defer></script>
@endpush
