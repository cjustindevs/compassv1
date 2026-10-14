@extends('layouts.app')

@section('title', 'Screening reviews – COMPASS')

@section('content')
@php
    $counts = [
        'all' => $sessions->count(),
        'pre-session' => $sessions->filter(fn ($s) => ! ($s->report?->reassessment_requested_at))->count(),
        'reassessment' => $sessions->filter(fn ($s) => (bool) $s->report?->reassessment_requested_at)->count(),
    ];
    $riskMap = [
        'low' => ['label' => 'Low', 'classes' => 'bg-green-50 text-green-700 ring-green-200', 'dot' => 'bg-green-500', 'guidance' => 'Peer support can proceed under routine supervision and standard documentation.'],
        'moderate' => ['label' => 'Moderate', 'classes' => 'bg-amber-50 text-amber-700 ring-amber-200', 'dot' => 'bg-amber-500', 'guidance' => 'Peer support can proceed with closer monitoring and scheduled review points.'],
        'high' => ['label' => 'High', 'classes' => 'bg-orange-50 text-orange-700 ring-orange-200', 'dot' => 'bg-orange-500', 'guidance' => 'Adviser-led support with professional referral review recommended.'],
        'emergency' => ['label' => 'Emergency', 'classes' => 'bg-red-50 text-red-700 ring-red-200', 'dot' => 'bg-red-500', 'guidance' => 'Immediate escalation to emergency services is required now.'],
        'unclassified' => ['label' => 'Unclassified', 'classes' => 'bg-gray-100 text-gray-600 ring-gray-200', 'dot' => 'bg-gray-400', 'guidance' => 'Review the screening answers and assign a risk level before the request moves forward.'],
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

<div class="adviser-page-content adviser-compact-page screening-reviews space-y-5">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Screening reviews</h1>
            <p class="text-sm text-gray-500 mt-1">
                Review assigned screenings and Helper reassessments. Original responses are retained.
            </p>
        </div>
    </header>

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

    <nav class="flex flex-wrap gap-2" aria-label="Filter screening reviews">
        <button type="button" data-filter="all" class="px-3 py-1.5 rounded-full text-sm font-medium border bg-gray-900 text-white border-gray-900">All ({{ $counts['all'] }})</button>
        <button type="button" data-filter="pre-session" class="px-3 py-1.5 rounded-full text-sm font-medium border bg-white text-gray-600 border-gray-300">Awaiting decision ({{ $counts['pre-session'] }})</button>
        <button type="button" data-filter="reassessment" class="px-3 py-1.5 rounded-full text-sm font-medium border bg-white text-gray-600 border-gray-300">Helper reassessment ({{ $counts['reassessment'] }})</button>
    </nav>

    @forelse($sessions as $session)
        @php
            $screening = $session->screeningResponses->sortBy('id')->first();
            $alias = $session->seeker?->generated_alias ?? 'Seeker';
            $helperAlias = $session->helper?->full_name ?? 'Unassigned';
            $submitted = ($session->created_date ?? $session->created_at)?->timezone('Asia/Manila')->format('M d, Y \a\t g:i A');
            $reassessment = (bool) ($session->report?->reassessment_requested_at);
            $risk = $session->risk_level ?? 'unclassified';
            $riskMeta = $riskMap[$risk] ?? $riskMap['unclassified'];
            $answers = collect($screening?->responses ?? []);
            $flags = $answers->filter(fn ($v, $k) => in_array($k, $flagKeys, true) && in_array($v, ['yes',true,1,'1'],true));
            $single = $screening?->instrument_version === \App\Services\CompactScreening::SINGLE_VERSION;
            $followUpFlow = $single && !$reassessment && $answers->get('suicidal_thoughts') === 'prefer_not_to_say';
            $retry = (string) old('_review_session') === (string) $session->id;
        @endphp

        <section id="screening-{{ $session->id }}"
            data-review-type="{{ $reassessment ? 'reassessment' : 'pre-session' }}"
            data-filter-target
            class="screening-review-card bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
            <div class="screening-review-body p-5 space-y-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <div>
                            <h2 class="font-semibold text-gray-800 leading-tight">{{ $session->reference_number }}</h2>
                            <p class="text-sm text-gray-500 mt-0.5">Seeker {{ $alias }} &middot; Screened {{ $submitted }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-2.5 py-1 rounded-full text-xs font-medium {{ $reassessment ? 'bg-purple-50 text-purple-700 ring-1 ring-purple-200' : 'bg-blue-50 text-blue-700 ring-1 ring-blue-200' }}">
                            {{ $reassessment ? 'Helper reassessment' : ($followUpFlow && $screening->follow_up_requested_at ? ($screening->follow_up_answered_at ? 'Follow-up received' : 'Awaiting Seeker response') : 'Awaiting decision') }}
                        </span>
                        @if(app(\App\Services\AdviserTranscriptAccess::class)->allowed($session))
                            <a href="{{ route('adviser.screenings.conversation', $session) }}"
                               class="inline-flex items-center gap-1.5 rounded-lg bg-white border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                <x-ui-icon name="message"  /> View chat session
                            </a>
                        @endif
                    </div>
                </div>

                <dl class="screening-review-meta grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm bg-gray-50 rounded-xl p-4">
                    <div>
                        <dt class="text-gray-400 text-xs uppercase tracking-wide">Concern</dt>
                        <dd class="text-gray-700 mt-0.5 font-medium">{{ $session->concern?->concern_name ?? 'Not specified' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400 text-xs uppercase tracking-wide">Assigned helper</dt>
                        <dd class="text-gray-700 mt-0.5 font-medium">{{ $helperAlias }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400 text-xs uppercase tracking-wide">Current classification</dt>
                        <dd class="mt-0.5">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ring-1 {{ $riskMeta['classes'] }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $riskMeta['dot'] }}" aria-hidden="true"></span>
                                {{ $riskMeta['label'] }}
                            </span>
                        </dd>
                    </div>
                </dl>

                @if($single)
                    <p class="screening-intake-answer" data-safety-answer="{{ $answers->get('suicidal_thoughts') }}"><span>Recorded safety answer</span><strong>{{ match($answers->get('suicidal_thoughts')) { 'yes'=>'Yes', 'no'=>'No', 'prefer_not_to_say'=>'Prefer not to say', default=>'Not recorded' } }}</strong></p>
                @endif

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

                <details class="screening-review-expand" @if($retry) open @endif>
                    <summary><span>Review screening and record decision</span><span class="screening-expand-hint" aria-hidden="true">Open review</span></summary>
                    <div class="screening-review-content">
                @if($single)
                    <p class="screening-safety-note text-sm text-amber-800">{{ $followUpFlow ? 'This intake asked one safety question. Use the follow-up below to obtain a response on the Seeker waiting page. Other questions were not assessed.' : 'This intake asked one safety question. Clarify that answer if needed; other questions were not assessed. Record the source of any follow-up separately from the original response.' }}</p>
                @endif
                <p class="screening-guidance text-sm text-blue-800"><span class="font-semibold">Guidance:</span> {{ $riskMeta['guidance'] }}</p>

                <div class="screening-controls">
                <div class="screening-answer-control rounded-xl border border-gray-200 p-3">
                    <details class="text-sm">
                        <summary class="cursor-pointer font-medium text-gray-700">
                            <x-ui-icon name="clipboard" class="mr-1 text-gray-400" />
                            View original screening answers
                        </summary>
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
                    </details>
                </div>

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
                            <div class="grid grid-cols-1 gap-4">
                                <label class="block text-sm font-medium text-gray-700">Reassessed risk
                                    <select name="risk_level" required class="mt-1 block w-full rounded-lg border-gray-300">
                                        <option value="" disabled @selected($retry ? !old('risk_level') : !$session->risk_level)>Select reassessed risk</option>
                                        @foreach(['low', 'moderate', 'high', 'emergency'] as $riskOption)
                                            <option value="{{ $riskOption }}" @selected(($retry ? old('risk_level') : $session->risk_level) === $riskOption)>{{ $riskMap[$riskOption]['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <span class="mt-1 block text-xs text-gray-400">{{ $single && !$reassessment ? 'The clarified answer uses the same routing as the original single-question intake.' : 'Changing the classification requires complete clarification using the original instrument.' }}</span>
                                </label>
                            </div>
                            @if($single && !$reassessment)
                            <p class="text-sm text-gray-500">Review the original saved safety answer above. No replacement answer is required to confirm its classification.</p>
                            @elseif(!$screening || in_array($screening->instrument_version, [\App\Services\CompactScreening::VERSION, \App\Services\CompactScreening::FORM_VERSION, \App\Services\CompactScreening::SINGLE_VERSION], true))
                            <fieldset class="rounded-xl bg-gray-50 p-4 space-y-3"><legend class="font-semibold text-sm">Documented clarification</legend>
                            <label class="text-sm"><input type="checkbox" name="use_clarified_answers" value="1" @checked($retry && old('use_clarified_answers'))> Reassess using clarified answers</label>
                            <p class="text-xs text-gray-500">Complete every answer only when clarification has been obtained. The classification must match the deterministic routing rules. Without clarification, only the current classification can be confirmed.</p>
                            <div class="grid sm:grid-cols-2 gap-3">
                            @foreach(\App\Services\CompactScreening::FIELDS as $field)
                            <label class="text-sm">{{ ucfirst(str_replace('_',' ',$field)) }}<select name="answers[{{ $field }}]" class="block w-full rounded-lg border-gray-300"><option value="">Not clarified</option><option value="0" @selected($retry && old('answers.'.$field) === '0')>No</option><option value="1" @selected($retry && old('answers.'.$field) === '1')>Yes</option></select></label>
                            @endforeach
                            </div></fieldset>
                            @else<p class="text-sm text-gray-500">This legacy screening may be confirmed. Changing its classification requires clarification using its original approved instrument.</p>@endif
                            <label class="block text-sm font-medium text-gray-700">Review / follow-up source
                                <input name="evidence_source" required maxlength="255" class="mt-1 block w-full rounded-lg border-gray-300" value="{{ $retry ? old('evidence_source') : '' }}" placeholder="Which answers or session notes support this decision?">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">Decision reason
                                <textarea name="reason" required minlength="10" maxlength="1000" rows="3" class="mt-1 block w-full rounded-lg border-gray-300" placeholder="Explain the reassessment, referencing the recorded answers and any conversation content.">{{ $retry ? old('reason') : '' }}</textarea>
                            </label>
                            <p class="text-xs text-gray-500">Approve peer support only when appropriate under the existing protocol. Approval returns the Seeker to the request steps and matching; it does not start a chat automatically.</p>
                            <div class="screening-decision-actions flex flex-wrap justify-end gap-2">
                                <button type="reset" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</button>
                                <button type="submit" name="review_action" value="record" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Record without approval</button>
                                <button type="submit" name="review_action" value="approve" class="inline-flex items-center gap-2 rounded-xl bg-green-600 px-5 py-2 text-sm font-semibold text-white hover:bg-green-700">
                                    <x-ui-icon name="check" /> Approve peer support
                                </button>
                            </div>
                        </form>
                </div>
                @endif
                </div>

                <p class="text-xs text-gray-400">Original screening responses are retained with the reassessment in the request record.</p>
                    </div>
                </details>
            </div>
        </section>
    @empty
        <div data-filter-empty class="bg-white rounded-2xl border border-gray-200 p-10 text-center">
            <p class="text-sm text-gray-500"><span>No screening reviews are assigned to you right now.</span></p>
        </div>
    @endforelse

    @if($sessions->isNotEmpty())
    <div data-filter-empty hidden class="bg-white rounded-2xl border border-gray-200 p-10 text-center">
        <p class="text-sm text-gray-500"><span>Nothing matches this filter.</span></p>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-screening-review-form]').forEach(form => {
        const check = form.querySelector('[name="use_clarified_safety_answer"]');
        const answer = form.querySelector('[name="clarified_safety_answer"]');
        const risk = form.querySelector('[name="risk_level"]');
        const syncAnswer = () => {
            if (!check || !answer) return;
            answer.disabled = !check.checked;
            answer.required = check.checked;
            if (check.checked && answer.value) risk.value = answer.selectedOptions[0].dataset.risk;
        };
        check?.addEventListener('change', syncAnswer);
        answer?.addEventListener('change', syncAnswer);
        syncAnswer();
        form.addEventListener('reset', () => setTimeout(syncAnswer, 0));
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
    const activeClass = 'bg-gray-900 text-white border-gray-900';
    const inactiveClass = 'bg-white text-gray-600 border-gray-300';
    document.querySelectorAll('[data-filter]').forEach(button => button.setAttribute('aria-pressed', button.dataset.filter === 'all' ? 'true' : 'false'));

    document.querySelectorAll('[data-filter]').forEach(function (button) {
        button.addEventListener('click', function () {
            const filter = button.dataset.filter;

            document.querySelectorAll('[data-filter]').forEach(function (btn) {
                const isActive = btn.dataset.filter === filter;
                btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                btn.classList.remove.apply(btn.classList, (isActive ? inactiveClass : activeClass).split(' '));
                btn.classList.add.apply(btn.classList, (isActive ? activeClass : inactiveClass).split(' '));
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
    const target = document.getElementById(location.hash.slice(1));
    if (target?.matches('[data-filter-target]')) {
        const review = target.querySelector('.screening-review-expand');
        if (review) review.open = true;
    }
});
</script>
@endpush
