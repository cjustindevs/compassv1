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

<div class="adviser-page-content space-y-5">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Screening reviews</h1>
            <p class="text-sm text-gray-500 mt-1">
                Decisions flagged for you. Review the recorded answers and the helper's note, then set the risk and
                peer-support outcome. Original responses are always retained.
            </p>
        </div>
        <div class="flex items-center gap-3 text-sm">
            <div class="bg-white rounded-2xl border border-gray-200 px-4 py-2.5 text-center">
                <div class="text-2xl font-bold text-gray-800 leading-none">{{ $counts['all'] }}</div>
                <div class="text-xs text-gray-400 mt-1">to review</div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 px-4 py-2.5 text-center">
                <div class="text-2xl font-bold text-amber-600 leading-none">{{ $counts['reassessment'] }}</div>
                <div class="text-xs text-gray-400 mt-1">reassessments</div>
            </div>
        </div>
    </header>

    @if(session('success'))
        <div role="status" class="flex items-start gap-3 rounded-xl bg-green-50 border border-green-100 p-3 text-green-800">
            <i class="fas fa-circle-check mt-0.5" aria-hidden="true"></i>
            <p class="text-sm">{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div role="alert" class="flex items-start gap-3 rounded-xl bg-red-50 border border-red-100 p-3 text-red-800">
            <i class="fas fa-circle-exclamation mt-0.5" aria-hidden="true"></i>
            <p class="text-sm">{{ session('error') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div role="alert" class="flex items-start gap-3 rounded-xl bg-red-50 border border-red-100 p-3 text-red-800">
            <i class="fas fa-circle-exclamation mt-0.5" aria-hidden="true"></i>
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
            $screening = $session->screeningResponses->sortByDesc('id')->first();
            $alias = $session->seeker?->generated_alias ?? 'Seeker';
            $helperAlias = $session->helper?->public_alias ?? 'Unassigned';
            $submitted = ($session->created_date ?? $session->created_at)?->timezone('Asia/Manila')->format('M d, Y \a\t g:i A');
            $approved = (bool) $session->peer_support_approved_at;
            $reassessment = (bool) ($session->report?->reassessment_requested_at);
            $risk = $session->risk_level ?? 'unclassified';
            $riskMeta = $riskMap[$risk] ?? $riskMap['unclassified'];
            $answers = collect($screening?->responses ?? []);
            $flags = $answers->filter(fn ($v, $k) => in_array($k, $flagKeys, true) && $v === 'yes');
        @endphp

        <section id="screening-{{ $session->id }}"
            data-review-type="{{ $reassessment ? 'reassessment' : 'pre-session' }}"
            data-filter-target
            class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
            <div class="p-5 space-y-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 h-10 w-10 rounded-xl bg-green-50 text-green-700 flex items-center justify-center font-semibold">
                            {{ mb_substr($alias, 0, 1) }}
                        </span>
                        <div>
                            <h2 class="font-semibold text-gray-800 leading-tight">{{ $session->reference_number }}</h2>
                            <p class="text-sm text-gray-500 mt-0.5">Seeker {{ $alias }} &middot; Screened {{ $submitted }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-2.5 py-1 rounded-full text-xs font-medium {{ $reassessment ? 'bg-purple-50 text-purple-700 ring-1 ring-purple-200' : 'bg-blue-50 text-blue-700 ring-1 ring-blue-200' }}">
                            {{ $reassessment ? 'Helper reassessment' : 'Awaiting decision' }}
                        </span>
                        @if(app(\App\Services\AdviserTranscriptAccess::class)->allowed($session))
                            <a href="{{ route('adviser.screenings.conversation', $session) }}"
                               class="inline-flex items-center gap-1.5 rounded-lg bg-white border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-comments" aria-hidden="true"></i> View chat session
                            </a>
                        @endif
                    </div>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm bg-gray-50 rounded-xl p-4">
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

                @if($reassessment)
                    <div class="flex items-start gap-3 rounded-xl bg-amber-50 border border-amber-100 p-3 text-sm text-amber-800">
                        <i class="fas fa-rotate mt-0.5" aria-hidden="true"></i>
                        <div>
                            <p class="font-semibold">The helper requested a reassessment</p>
                            <p class="mt-0.5">Assessed {{ $session->report?->risk_level_assessed ?? 'unspecified' }}. {{ $session->report?->observations }}</p>
                        </div>
                    </div>
                @endif

                @if($flags->isNotEmpty())
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
                @else
                    <div class="flex items-center gap-2 rounded-xl bg-green-50/60 border border-green-100 p-3 text-sm text-green-700">
                        <i class="fas fa-check-circle" aria-hidden="true"></i>
                        <span>No immediate danger cues were recorded on the screening.</span>
                    </div>
                @endif

                <div class="rounded-xl border border-gray-200 p-3">
                    <details class="text-sm">
                        <summary class="cursor-pointer font-medium text-gray-700">
                            <i class="fas fa-list-check mr-1 text-gray-400" aria-hidden="true"></i>
                            View original screening answers
                        </summary>
                        <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                            @forelse($answers as $key => $answer)
                                @php($question = \App\Services\ScreeningInstrument::QUESTIONS[$key] ?? ucfirst(str_replace('_', ' ', $key)))
                                <div>
                                    <dt class="text-xs text-gray-400">{{ $question }}</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium ring-1 {{ $answerBadge[is_scalar($answer) ? $answer : 'prefer_not_to_say'] ?? $answerBadge['prefer_not_to_say'] }}">
                                            {{ is_scalar($answer) ? ucfirst(str_replace('_', ' ', (string) $answer)) : 'Prefer not to say' }}
                                        </span>
                                    </dd>
                                </div>
                            @empty
                                <p class="text-gray-500 col-span-2">No response details recorded for this reassessment.</p>
                            @endforelse
                        </dl>
                    </details>
                </div>

                <div class="flex items-start gap-3 rounded-xl border border-blue-100 bg-blue-50/60 p-3 text-sm text-blue-800">
                    <i class="fas fa-lightbulb mt-0.5" aria-hidden="true"></i>
                    <p><span class="font-semibold">Guidance:</span> {{ $riskMeta['guidance'] }}</p>
                </div>

                <div class="rounded-xl border border-gray-200">
                    <details class="group">
                        <summary class="cursor-pointer flex items-center justify-between px-4 py-3 text-sm font-semibold text-gray-800">
                            <span><i class="fas fa-clipboard-check mr-2 text-green-600" aria-hidden="true"></i>Record your decision</span>
                            <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform" aria-hidden="true"></i>
                        </summary>
                        <form method="POST" action="{{ route('adviser.screenings.review', $session) }}"
                              data-confirm="Record this review?"
                              data-confirm-message="Your reassessment, evidence and reason will be stored on the request."
                              data-confirm-text="Record review"
                              class="border-t border-gray-100 p-4 space-y-4">
                            @csrf
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <label class="block text-sm font-medium text-gray-700">Reassessed risk
                                    <select name="risk_level" required class="mt-1 block w-full rounded-lg border-gray-300">
                                        @foreach(['low', 'moderate', 'high', 'emergency'] as $riskOption)
                                            <option value="{{ $riskOption }}" @selected($session->risk_level === $riskOption)>{{ $riskMap[$riskOption]['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <span class="mt-1 block text-xs text-gray-400">Set this as high or emergency when the helper's note or the conversation requires referral review.</span>
                                </label>
                                <label class="block text-sm font-medium text-gray-700">Peer support outcome
                                    <select name="allow_peer_support" required class="mt-1 block w-full rounded-lg border-gray-300">
                                        <option value="0" @selected(! $approved)>No — continue adviser review / referral coordination</option>
                                        <option value="1" @selected($approved)>Yes — peer support is appropriate under protocol</option>
                                    </select>
                                    <span class="mt-1 block text-xs text-gray-400">Select "No" when professional support must be coordinated instead.</span>
                                </label>
                            </div>
                            @if(!$screening || $screening->instrument_version === \App\Services\CompactScreening::VERSION)
                            <fieldset class="rounded-xl bg-gray-50 p-4 space-y-3"><legend class="font-semibold text-sm">Documented clarification</legend>
                            <label class="text-sm"><input type="checkbox" name="use_clarified_answers" value="1"> Reassess using clarified answers</label>
                            <p class="text-xs text-gray-500">Complete every answer only when clarification has been obtained. The classification must match the deterministic routing rules. Without clarification, only the current classification can be confirmed.</p>
                            <div class="grid sm:grid-cols-2 gap-3">
                            @foreach(\App\Services\CompactScreening::FIELDS as $field)
                            <label class="text-sm">{{ ucfirst(str_replace('_',' ',$field)) }}<select name="answers[{{ $field }}]" class="block w-full rounded-lg border-gray-300"><option value="">Not clarified</option><option value="0">No</option><option value="1">Yes</option></select></label>
                            @endforeach
                            </div></fieldset>
                            @else<p class="text-sm text-gray-500">This legacy screening may be confirmed. Changing its classification requires clarification using its original approved instrument.</p>@endif
                            <label class="block text-sm font-medium text-gray-700">Evidence source / clarification reference
                                <input name="evidence_source" required maxlength="255" class="mt-1 block w-full rounded-lg border-gray-300" value="{{ old('evidence_source') }}" placeholder="Which answers or session notes support this decision?">
                            </label>
                            <label class="block text-sm font-medium text-gray-700">Reason and documented clarification
                                <textarea name="reason" required maxlength="1000" rows="3" class="mt-1 block w-full rounded-lg border-gray-300" placeholder="Explain the reassessment, referencing the recorded answers and any conversation content.">{{ old('reason') }}</textarea>
                            </label>
                            <div class="flex justify-end gap-2">
                                <button type="reset" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Reset</button>
                                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-green-600 px-5 py-2 text-sm font-semibold text-white hover:bg-green-700">
                                    <i class="fas fa-clipboard-check" aria-hidden="true"></i> Record review
                                </button>
                            </div>
                        </form>
                    </details>
                </div>

                <p class="text-xs text-gray-400">Original screening responses are retained with the reassessment in the request record.</p>
            </div>
        </section>
    @empty
        <div data-filter-empty class="bg-white rounded-2xl border border-gray-200 p-10 text-center">
            <p class="text-sm text-gray-500"><span>No screening reviews are assigned to you right now.</span></p>
        </div>
    @endforelse

    <div data-filter-empty hidden class="bg-white rounded-2xl border border-gray-200 p-10 text-center">
        <p class="text-sm text-gray-500"><span>Nothing matches this filter.</span></p>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const activeClass = 'bg-gray-900 text-white border-gray-900';
    const inactiveClass = 'bg-white text-gray-600 border-gray-300';

    document.querySelectorAll('[data-filter]').forEach(function (button) {
        button.addEventListener('click', function () {
            const filter = button.dataset.filter;

            document.querySelectorAll('[data-filter]').forEach(function (btn) {
                const isActive = btn.dataset.filter === filter;
                btn.classList.remove.apply(btn.classList, (isActive ? inactiveClass : activeClass).split(' '));
                btn.classList.add.apply(btn.classList, (isActive ? activeClass : inactiveClass).split(' '));
            });

            let shown = 0;
            document.querySelectorAll('[data-filter-target]').forEach(function (card) {
                const show = filter === 'all' || card.dataset.reviewType === filter;
                card.classList.toggle('hidden', !show);
                if (show) shown++;
            });

            document.querySelectorAll('[data-filter-empty]').forEach(function (empty) {
                empty.classList.toggle('hidden', shown > 0);
            });
        });
    });
});
</script>
@endpush