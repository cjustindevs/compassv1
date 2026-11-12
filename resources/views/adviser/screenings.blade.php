@extends('layouts.app')

@section('title', 'Screening Reviews – COMPASS')

@section('content')
@php
    $counts = [
        'all' => $sessions->count(),
        'pre-session' => $sessions->filter(fn ($s) => ! ($s->report?->reassessment_requested_at))->count(),
        'reassessment' => $sessions->filter(fn ($s) => (bool) $s->report?->reassessment_requested_at)->count(),
    ];
    $riskMap = [
        'low' => ['label' => 'Low', 'tone' => 'green', 'guidance' => 'Peer support can proceed under routine supervision and standard documentation.'],
        'moderate' => ['label' => 'Moderate', 'tone' => 'amber', 'guidance' => 'Peer support can proceed with closer monitoring and scheduled review points.'],
        'high' => ['label' => 'High', 'tone' => 'orange', 'guidance' => 'Adviser-led support with professional referral review recommended.'],
        'emergency' => ['label' => 'Emergency', 'tone' => 'red', 'guidance' => 'Immediate escalation to emergency services is required now.'],
        'unclassified' => ['label' => 'Unclassified', 'tone' => 'neutral', 'guidance' => 'No classification was assumed. Review the saved answer alongside peer matching.'],
    ];
    $flagKeys = [
        'suicidal_thoughts', 'immediate_intent', 'current_suicide_plan', 'access_to_means',
        'ongoing_self_harm', 'recent_attempt_needs_assistance', 'immediate_threat_to_life',
        'immediate_threat_to_others', 'recent_self_harm', 'severe_distress', 'suspected_abuse',
        'referral_indicator',
    ];
    $answerBadge = [
        'yes' => 'bg-red-50 text-red-700 ring-red-100',
        'no' => 'bg-green-50 text-green-700 ring-green-100',
        'prefer_not_to_say' => 'bg-gray-100 text-gray-500 ring-gray-200',
    ];
@endphp

<div class="adviser-page-content adviser-compact-page screening-reviews">
    <x-ui.page-header title="Screening Reviews" description="Review pre-session answers and Helper requests for reassessment." />

    @if(session('success'))
        <div role="status" class="flex items-start gap-3 rounded-xl bg-green-50 border border-green-100 p-3 text-green-800">

            <p class="text-sm">{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div role="alert" class="flex items-start gap-3 rounded-xl bg-red-50 border border-red-100 p-3 text-red-800">

            <p class="text-sm">{{ session('error') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div role="alert" class="flex items-start gap-3 rounded-xl bg-red-50 border border-red-100 p-3 text-red-800">

            <p class="text-sm">{{ $errors->first() }}</p>
        </div>
    @endif

    <nav class="ui-chips" aria-label="Filter screening reviews">
        <button type="button" data-filter="all" class="ui-chip is-active">All ({{ $counts['all'] }})</button>
        <button type="button" data-filter="pre-session" class="ui-chip">Pre-session ({{ $counts['pre-session'] }})</button>
        <button type="button" data-filter="reassessment" class="ui-chip">Helper reassessment ({{ $counts['reassessment'] }})</button>
    </nav>

    @forelse($sessions as $session)
        @php
            $screening = $session->screeningResponses->sortBy('id')->first();
            $alias = $session->seeker?->generated_alias ?? 'Seeker';
            $helperAlias = $session->helper?->full_name ?? 'Unassigned';
            $submitted = ($session->created_date ?? $session->created_at)?->timezone('Asia/Manila')->format('M d, Y \a\t g:i A');
            $reassessment = (bool) ($session->report?->reassessment_requested_at);
            $parallelIntake = $session->permitsParallelScreeningSupport();
            $risk = $session->risk_level ?? 'unclassified';
            $riskMeta = $riskMap[$risk] ?? $riskMap['unclassified'];
            $answers = collect($screening?->responses ?? []);
            $flags = $answers->filter(fn ($v, $k) => in_array($k, $flagKeys, true) && in_array($v, ['yes',true,1,'1'],true));
            $single = $screening?->instrument_version === \App\Services\CompactScreening::SINGLE_VERSION;
            $followUpFlow = $single && !$reassessment && $answers->get('suicidal_thoughts') === 'prefer_not_to_say';
            $retry = (string) old('_review_session') === (string) $session->id;
        @endphp

        <x-ui.card :id="'screening-'.$session->id" :title="$session->reference_number"
            :subtitle="'Seeker '.$alias.' · Screened '.$submitted"
            data-review-type="{{ $reassessment ? 'reassessment' : 'pre-session' }}"
            data-filter-target
            class="screening-review-card">
            <x-slot:actions>
                <x-ui.badge :tone="$reassessment ? 'blue' : 'neutral'">{{ $reassessment ? 'Helper reassessment' : ($followUpFlow && $screening->follow_up_requested_at ? ($screening->follow_up_answered_at ? 'Follow-up received' : 'Awaiting Seeker response') : 'Review needed') }}</x-ui.badge>
                <x-ui.badge :tone="$riskMeta['tone']">{{ $riskMeta['label'] }}</x-ui.badge>
                @if(app(\App\Services\AdviserTranscriptAccess::class)->allowed($session))
                    <a href="{{ route('adviser.screenings.conversation', $session) }}" class="btn-outline">
                        <x-ui-icon name="message" /> View chat session
                    </a>
                @endif
            </x-slot:actions>
            <div class="screening-review-body space-y-5">

                <dl class="screening-review-meta">
                    <div>
                        <dt>Concern</dt>
                        <dd>{{ $session->concern?->concern_name ?? 'Not specified' }}</dd>
                    </div>
                    <div>
                        <dt>Assigned Helper</dt>
                        <dd>{{ $helperAlias }}</dd>
                    </div>
                    <div>
                        <dt>Current classification</dt>
                        <dd>
                            <x-ui.badge :tone="$riskMeta['tone']">{{ $riskMeta['label'] }}</x-ui.badge>
                        </dd>
                    </div>
                    @if($single)
                    <div class="screening-intake-answer" data-safety-answer="{{ $answers->get('suicidal_thoughts') }}">
                        <dt>Recorded safety answer</dt>
                        <dd><strong>{{ match($answers->get('suicidal_thoughts')) { 'yes'=>'Yes', 'no'=>'No', 'prefer_not_to_say'=>'Prefer not to say', default=>'Not recorded' } }}</strong></dd>
                    </div>
                    @endif
                    @if($parallelIntake)
                    <div>
                        <dt>Peer support</dt>
                        <dd>{{ \App\Services\SeekerRequestPresentation::status($session) }}<small>This review does not gate matching.</small></dd>
                    </div>
                    @endif
                </dl>

                @if($reassessment)
                    <div class="screening-review-note flex items-start gap-3 rounded-xl bg-amber-50 border border-amber-100 p-3 text-sm text-amber-800">

                        <div>
                            <p class="font-semibold">The helper requested a reassessment</p>
                            <p class="mt-0.5">Assessed {{ $session->report?->risk_level_assessed ?? 'unspecified' }}. {{ $session->report?->observations }}</p>
                        </div>
                    </div>
                @endif

                @if($flags->isNotEmpty() && !$single)
                    <div class="rounded-xl border border-orange-100 bg-orange-50/50 p-3">
                        <p class="text-xs font-semibold text-orange-700 uppercase tracking-wide mb-2">Safety cues flagged ({{ $flags->count() }})</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($flags as $key => $answer)
                                <span class="px-2.5 py-1 rounded-full text-xs font-medium ring-1 ring-red-200 bg-red-50 text-red-700">
                                    {{ \App\Services\ScreeningInstrument::QUESTIONS[$key] ?? ucfirst(str_replace('_', ' ', $key)) }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @elseif(!$single)
                    <div class="screening-safety-note text-sm text-green-700">

                        <span>No immediate danger cues were recorded on the screening.</span>
                    </div>
                @endif

                <div class="screening-review-launcher">
                    <p>View the saved answers, then choose a review action.</p>
                    <button type="button" class="av-button av-button-primary" data-screening-toggle
                        aria-controls="screening-workspace-{{ $session->id }}" aria-expanded="true" hidden>
                        <span data-review-toggle-label>Close review</span><x-ui-icon name="chevron-down" />
                    </button>
                </div>
                <div id="screening-workspace-{{ $session->id }}" class="screening-review-content"
                    data-screening-workspace data-initial-open="{{ $retry ? 'true' : 'false' }}" tabindex="-1"
                    role="region" aria-labelledby="screening-workspace-heading-{{ $session->id }}">
                    <header class="screening-workspace-heading">
                        <h3 id="screening-workspace-heading-{{ $session->id }}">Review {{ $session->reference_number }}</h3>
                        <span>Review open</span>
                    </header>
                @if($single)
                    <p class="screening-safety-note text-sm text-amber-800">{{ $followUpFlow ? 'One-question screening. Follow up alongside peer matching; original answers stay unchanged.' : 'This screening asked one safety question. The saved answer is read-only; other questions were not assessed.' }}</p>
                @endif
                <p class="screening-guidance text-sm text-blue-800"><span class="font-semibold">Guidance:</span> {{ $riskMeta['guidance'] }}</p>

                <div class="screening-controls">
                <div class="screening-answer-control rounded-xl border border-gray-200 p-3">
                        <h3 class="screening-column-heading"><span>1</span> Saved answers</h3>
                        <p class="screening-section-caption">Original Seeker answers are read-only.</p>
                        <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                            @forelse($answers as $key => $answer)
                                @php($question = $key === 'suicidal_thoughts' && $screening?->instrument_version === \App\Services\CompactScreening::SINGLE_VERSION ? \App\Services\CompactScreening::SINGLE_QUESTION : (\App\Services\ScreeningInstrument::QUESTIONS[$key] ?? ucfirst(str_replace('_', ' ', $key))))
                                <div>
                                    <dt class="text-xs text-gray-400">{{ $question }}</dt>
                                    <dd class="mt-1">
                                        @if(in_array($key, ['description','custom_concern'], true))
                                            <p>{{ is_scalar($answer) && trim((string)$answer) !== '' ? $answer : 'Not provided' }}</p>
                                        @else
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium ring-1 {{ $answerBadge[is_scalar($answer) ? $answer : 'prefer_not_to_say'] ?? $answerBadge['prefer_not_to_say'] }}">
                                            {{ is_bool($answer) ? ($answer ? 'Yes' : 'No') : (is_scalar($answer) ? ucfirst(str_replace('_', ' ', (string) $answer)) : 'Not recorded') }}
                                        </span>
                                        @endif
                                    </dd>
                                </div>
                            @empty
                                <p class="text-gray-500 col-span-2">No response details recorded for this reassessment.</p>
                            @endforelse
                        </dl>
                </div>

                <div class="screening-action-column">
                <h3 class="screening-column-heading"><span>2</span> Review action</h3>
                @if($followUpFlow)
                    @include('adviser.partials.screening-follow-up')
                @else
                <div class="screening-decision-control rounded-xl border border-gray-200">
                    <h3 class="screening-decision-heading">Record your decision</h3>
                        <form method="POST" action="{{ route('adviser.screenings.review', $session) }}"
                              data-screening-review-form
                              class="border-t border-gray-100 p-4 space-y-4">
                            @csrf
                            <input type="hidden" name="_review_session" value="{{ $session->id }}">
                            <input type="hidden" name="risk_level" value="{{ $session->risk_level }}">
                            <p class="text-sm text-gray-600">Saved classification: <strong>{{ $riskMeta['label'] }}</strong>. Original Seeker answers are read-only. Record review notes separately; emergency coordination is managed in Emergencies.</p>
                            <label class="block text-sm font-medium text-gray-700">Review / follow-up source
                                <input name="evidence_source" required maxlength="255" class="mt-1 block w-full rounded-lg border-gray-300" value="{{ $retry ? old('evidence_source') : '' }}" placeholder="Which answers or session notes support this decision?">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">Decision reason
                                <textarea name="reason" required minlength="10" maxlength="1000" rows="3" class="mt-1 block w-full rounded-lg border-gray-300" placeholder="Explain the reassessment, referencing the recorded answers and any conversation content.">{{ $retry ? old('reason') : '' }}</textarea>
                            </label>
                            <p class="text-xs text-gray-500">Approve peer support only when appropriate under the existing protocol. Approval returns the Seeker to the request steps and matching; it does not start a chat automatically.</p>
                            <div class="screening-decision-actions flex flex-wrap justify-end gap-2">
                                <button type="reset" class="btn-outline">Reset</button>
                                <button type="submit" name="review_action" value="record" class="btn-outline">Record without approval</button>
                                <button type="submit" name="review_action" value="approve" class="btn-primary">
                                    <x-ui-icon name="check" /> Approve peer support
                                </button>
                            </div>
                        </form>
                </div>
                @endif
                </div>
                </div>

                <footer class="screening-workspace-footer">
                    <p>Changes are saved when you submit a review action.</p>
                    <button type="button" class="av-button" data-screening-close hidden>Close review</button>
                </footer>
                    </div>
            </div>
        </x-ui.card>
    @empty
        <x-ui.empty data-filter-empty title="No screening reviews are assigned to you right now." />
    @endforelse

    @if($sessions->isNotEmpty())
        <x-ui.empty data-filter-empty hidden title="Nothing matches this filter." />
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/screening-review-workspace.js') }}?v={{ filemtime(public_path('js/screening-review-workspace.js')) }}" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-screening-review-form]').forEach(form => {
        form.addEventListener('submit', async event => {
            if (form.dataset.reviewConfirmed === '1' || typeof window.confirmAction !== 'function') return;
            event.preventDefault();
            const submitter = event.submitter;
            const approving = submitter?.value === 'approve';
            const confirmed = await window.confirmAction({
                title: approving ? 'Approve peer support?' : 'Record without approval?',
                message: approving ? 'The Seeker will continue through the existing request steps and matching. Your review and original response will be retained.' : 'This records your review without allowing the Seeker to continue to peer support.',
                confirmText: approving ? 'Approve peer support' : 'Record review',
                confirmClass: 'bg-green-600 hover:bg-green-700 focus:ring-green-500',
            });
            if (!confirmed) return;
            form.dataset.reviewConfirmed = '1';
            // Preserve the clicked outcome button through confirmation.
            form.requestSubmit(submitter || undefined);
        });
    });
    document.querySelectorAll('[data-filter]').forEach(button => button.setAttribute('aria-pressed', button.dataset.filter === 'all' ? 'true' : 'false'));

    document.querySelectorAll('[data-filter]').forEach(function (button) {
        button.addEventListener('click', function () {
            const filter = button.dataset.filter;

            document.querySelectorAll('[data-filter]').forEach(function (btn) {
                const isActive = btn.dataset.filter === filter;
                btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                btn.classList.toggle('is-active', isActive);
            });

            let shown = 0;
            document.querySelectorAll('[data-filter-target]').forEach(function (card) {
                const show = filter === 'all' || card.dataset.reviewType === filter;
                card.hidden = !show;
                if (show) shown++;
            });

            document.querySelectorAll('[data-filter-empty]').forEach(function (empty) {
                empty.hidden = shown > 0;
            });
        });
    });
});
</script>
@endpush
