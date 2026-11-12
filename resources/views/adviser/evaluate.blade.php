@extends('layouts.app')

@section('title', 'COMPASS – Evaluate Helper')

@push('styles')
<style>
        .adviser-page-content { font-family: 'Inter', sans-serif; box-sizing: border-box; }
        body { background: #F8FBF9; }


        .form-label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            color: var(--gray-700);
            margin-bottom: 6px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border-radius: 14px;
            border: 1.5px solid #e5e7eb;
            outline: none;
            transition: all 0.2s ease;
            background: white;
            font-size: 14px;
        }
        .form-input:focus { border-color: #04A052; box-shadow: 0 0 0 3px rgba(4,160,82,0.08); }

        .rating-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .rating-btn {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: 1.5px solid #e5e7eb;
            background: white;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 600;
            font-size: 16px;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .rating-btn:hover { border-color: #04A052; background: #EAF8F0; }
        .rating-btn.active { border-color: #04A052; background: #EAF8F0; color: #04A052; }


        .score-display {
            background: #f8fbf9;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #e5e7eb;
        }

        .competency-level {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
        }
        .competency-level.outstanding { background: #dcfce7; color: #166534; }
        .competency-level.very-good { background: #dbeafe; color: #1e40af; }
        .competency-level.satisfactory { background: #fef3c7; color: #92400e; }
        .competency-level.needs-improvement { background: #fee2e2; color: #991b1b; }
        .competency-level.unsatisfactory { background: #fee2e2; color: #991b1b; }


        @media (max-width: 768px) {
            .rating-btn { width: 38px; height: 38px; font-size: 14px; }
        }
        .flash-success { background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }
        .flash-error { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }
        @media (max-width: 480px) {
            .rating-btn { width: 34px; height: 34px; font-size: 13px; }
        }
    </style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.documentElement.classList.add('js-save-bar');
        var form = document.querySelector('form.form-maximized');
        var bar = form && form.querySelector('[data-save-bar]');
        if (!bar) return;
        var reveal = function () { bar.classList.add('is-visible'); };
        form.addEventListener('input', reveal);
        form.addEventListener('change', reveal);
        // Rating buttons set hidden inputs, which fire no input event.
        form.querySelectorAll('.rating-btn').forEach(function (b) { b.addEventListener('click', reveal); });
    });
</script>
@endpush

@section('content')
<div class="adviser-page-content">
    <x-ui.page-header title="Evaluate Helper" description="Competency evaluation form" back-href="{{ route('adviser.evaluations') }}" back-label="Back to Pending Evaluations">
        <x-slot:actions>
            <button type="button" onclick="openConversation()" class="btn-outline"><x-ui-icon name="message" class="text-green-600" /> View conversation</button>
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('success'))
        <div class="flash-success"> {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="flash-error"> {{ session('error') }}</div>
    @endif

    @php($risk = $report->session->risk_level ?? 'low')
    @php($riskTone = ['low' => 'green', 'moderate' => 'amber'][$risk] ?? 'red')
    <x-ui.card class="mb-4">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
            <div>
                <span class="text-gray-400 text-xs uppercase tracking-wider">Seeker</span>
                <p class="font-medium text-gray-800">{{ $report->session->seeker->generated_alias ?? 'Anonymous' }}</p>
            </div>
            <div>
                <span class="text-gray-400 text-xs uppercase tracking-wider">Helper</span>
                <p class="font-medium text-gray-800">{{ $report->session->helper->first_name ?? 'Unknown' }} {{ $report->session->helper->last_name ?? '' }}</p>
            </div>
            <div>
                <span class="text-gray-400 text-xs uppercase tracking-wider">Date</span>
                <p class="font-medium text-gray-800">{{ $report->created_at->format('M d, Y') }}</p>
            </div>
            <div>
                <span class="text-gray-400 text-xs uppercase tracking-wider">Concern</span>
                <p class="font-medium text-gray-800">{{ $report->session->concern->concern_name ?? 'General' }}</p>
            </div>
            <div>
                <span class="text-gray-400 text-xs uppercase tracking-wider">Risk</span>
                <p><x-ui.badge :tone="$riskTone">{{ ucfirst($risk) }} Risk</x-ui.badge></p>
            </div>
        </div>
        <div class="mt-4">
            <x-evaluation-steps variant="stepper" :current="$existingFeedback ? 4 : 3" />
        </div>
    </x-ui.card>

    <x-ui.card title="Session record" class="mb-4">
        <div class="mb-4 p-4 bg-gray-50 rounded-xl">
            <h4 class="font-semibold text-gray-700 text-sm mb-2">Session Summary</h4>
            <p class="text-gray-600 text-sm">{{ $report->session_summary ?? 'No summary available' }}</p>
        </div>
        <div class="p-4 bg-gray-50 rounded-xl space-y-3 text-sm">
            <p><strong>Session result:</strong> {{ ucwords(str_replace('_',' ',$report->session_result ?? 'Not recorded')) }}</p>
            <p><strong>Follow-up plan:</strong> {{ $report->follow_up_plan ?: 'Not recorded' }}</p>
            <p><strong>Personal reflection:</strong> {{ $report->personal_reflection ?: 'Not submitted' }}</p>
            <p><strong>Skills applied:</strong> {{ collect($report->skills)->map(fn($skill)=>ucwords(str_replace('_',' ',$skill)))->join(', ') ?: 'Not recorded' }}</p>
            @if($report->reassessment_requested_at)<p><strong>Risk reassessment requested:</strong> {{ ucfirst($report->risk_level_assessed) }}. Review through the screening review workflow; this is a helper observation, not an official classification.</p>@endif
            <p><strong>Documentation:</strong> {{ $report->documentation_late ? 'Submitted after 24 hours' : 'No overdue submission recorded' }}</p>
        </div>
    </x-ui.card>

    <x-ui.card title="Referral and risk review" class="mb-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
            <div>
                <span class="text-gray-400 text-xs uppercase tracking-wider">Referral recommended</span>
                <p class="font-medium">{{ $report->referral_recommended ? 'Yes' : 'No' }}</p>
            </div>
            <div>
                <span class="text-gray-400 text-xs uppercase tracking-wider">Recorded risk</span>
                <p class="font-medium capitalize">{{ $report->session->risk_level ?? 'Not recorded' }}</p>
            </div>
            <div>
                <span class="text-gray-400 text-xs uppercase tracking-wider">Reassessment on file</span>
                <p class="font-medium capitalize">{{ $report->reassessment_requested_at ? ($report->risk_level_assessed ?? 'Requested') : 'None' }}</p>
            </div>
            <div>
                <span class="text-gray-400 text-xs uppercase tracking-wider">Current referral</span>
                <p class="font-medium">{{ $report->session->referrals()->latest('id')->value('status') ?? 'None' }}</p>
            </div>
        </div>
        @php($sessionReferrals = $report->session->referrals()->latest('id')->take(3)->get())
        @if($sessionReferrals->count())
            <div class="mt-3 space-y-2">
                @foreach($sessionReferrals as $r)
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white border border-gray-200 px-3 py-2">
                        <span class="capitalize">{{ str_replace('_', ' ', $r->status) }}</span>
                        <span class="text-xs text-gray-400">{{ $r->created_at->format('M d, Y g:i A') }} · {{ $r->priority ? ucfirst($r->priority) : 'Standard' }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-400 mt-3">No referrals were recorded for this session. Note whether a referral should have been initiated when asking the helper about scope and judgment.</p>
        @endif
    </x-ui.card>

    <form class="form-maximized" method="POST" action="{{ route('adviser.evaluate.store', $report->id) }}">
        @csrf
        <x-ui.card title="Evidence and rating guide" class="mb-4">
            <p class="text-sm text-gray-500 mb-3">Rubric: {{ \App\Services\CompetencyRubric::VERSION }}. Evidence: submitted session report #{{ $report->id }} and its correction history. Each criterion is rated independently; weighted total is on a 1 to 5 scale.</p>

            <div class="mb-5 p-4 bg-gray-50 rounded-xl text-sm">
                <h4 class="font-semibold text-gray-700 mb-2">Evidence considered</h4>
                <ul class="list-disc pl-5 space-y-1 text-gray-600">
                    <li>Submitted documentation (report #{{ $report->id }}): summary, observations, actions taken, session result, and follow-up plan.</li>
                    @if($report->personal_reflection)
                        <li>Personal reflection submitted {{ $report->reflection_submitted_at?->format('M d, Y g:i A') ?: '' }}.</li>
                    @endif
                    @if(count($report->skills))
                        <li>Skills reported as applied: {{ collect($report->skills)->map(fn($skill)=>ucwords(str_replace('_',' ',$skill)))->join(', ') }}.</li>
                    @endif
                    <li>{{ $messages->count() ? 'Reviewed ' . $messages->count() . ' conversation messages from the authorized (consent-gated, aliased) record.' : 'Conversation transcript is not disclosed without an authorized access grant; evaluation relies on submitted documentation.' }}</li>
                    @if($existingFeedback)
                        <li>Prior feedback exists for this report — see the correction history above; ignore prior ratings unless this is a correction run.</li>
                    @endif
                </ul>
            </div>

            <details class="rounded-xl border border-gray-200 p-4 group">
                <summary class="cursor-pointer text-sm font-semibold text-gray-700">View rating descriptors (5 = highest)</summary>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-sm text-left border-collapse">
                        <thead>
                            <tr class="text-xs uppercase tracking-wider text-gray-400 border-b border-gray-200">
                                <th class="py-2 pr-3 font-semibold">Rating</th>
                                <th class="py-2 pr-3 font-semibold">Meaning</th>
                                <th class="py-2 font-semibold">What the helper does at this level</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach([
                                '5' => ['Excellent', 'Consistently and skillfully applies the behavior, even under difficult circumstances; serves as a model.'],
                                '4' => ['Good', 'Applies the behavior consistently; rare, minor lapses that do not affect the session.'],
                                '3' => ['Satisfactory', 'Applies the behavior adequately; present but with clear room to deepen skills.'],
                                '2' => ['Needs attention', 'Applies the behavior inconsistently or superficially; noticeable gaps in quality.'],
                                '1' => ['Unsatisfactory', 'Does not apply the behavior, or applies it in ways that undermine the session.'],
                            ] as $score => [$tag, $meaning])
                                <tr class="align-top">
                                    <td class="py-2 pr-3 font-bold text-gray-800">{{ $score }}</td>
                                    <td class="py-2 pr-3 font-medium text-gray-700">{{ $tag }}</td>
                                    <td class="py-2 text-gray-500">{{ $meaning }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </x-ui.card>

        <x-ui.card title="Your evaluation" class="mb-4">
            @if($existingFeedback)
                <label class="block text-sm font-semibold my-3">Correction reason<input name="correction_reason" required minlength="10" maxlength="1000" class="form-input mt-1" placeholder="Explain why this evaluation or feedback needs correction." value="{{ old('correction_reason') }}"></label>
                @error('correction_reason')<p class="ui-field-error" role="alert">{{ $message }}</p>@enderror
                <x-supervision-history :record="$existingFeedback" />
            @endif
            <label class="block text-sm my-3">Feedback follow-up date<input type="date" name="follow_up_date" class="form-input mt-1" value="{{ old('follow_up_date', $existingFeedback?->follow_up_date?->format('Y-m-d')) }}"></label>
            @error('follow_up_date')<p class="ui-field-error" role="alert">{{ $message }}</p>@enderror

            <div class="space-y-6">
                @foreach([
                    ['active_listening', 'Active Listening', '25%', "Uses minimal encouragers; avoids interrupting; accurately reflects the help seeker's statements"],
                    ['empathy', 'Empathy', '25%', "Uses emotional validation phrases; matches the help seeker's tone appropriately; avoids dismissive language"],
                    ['respect_professionalism', 'Respect & Professionalism', '20%', 'Maintains professional boundaries; uses polite and culturally appropriate language'],
                    ['ethical_practices', 'Ethical Practices', '20%', 'Protects confidentiality, respects autonomy, remains within scope, and applies ethical judgment'],
                    ['referral_accuracy', 'Referral Judgment', '10%', 'Identifies concerns requiring adviser review, referral, or emergency escalation and follows the proper procedure'],
                ] as [$field, $label, $weight, $hint])
                    <div>
                        <label class="form-label">{{ $label }} <span class="text-gray-400 font-normal">({{ $weight }})</span></label>
                        <p class="text-sm text-gray-400 mb-2">{{ $hint }}</p>
                        <div class="rating-group" data-target="{{ $field }}">
                            @for($i = 1; $i <= 5; $i++)
                                <button type="button" class="rating-btn" data-value="{{ $i }}" onclick="selectRating(this, '{{ $field }}')">{{ $i }}</button>
                            @endfor
                        </div>
                        <input type="hidden" name="{{ $field }}" id="{{ $field }}" required>
                        @error($field)<p class="ui-field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                @endforeach

                <div class="score-display">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <span class="text-sm text-gray-500">Overall Competency Score</span>
                            <div class="text-[28px] font-bold text-gray-800" id="overallScore">—</div>
                        </div>
                        <div>
                            <span class="text-sm text-gray-500">Competency Level</span>
                            <div id="competencyLevelDisplay">—</div>
                        </div>
                    </div>
                    <div class="mt-2 text-xs text-gray-400">
                        <span class="font-medium">Formula:</span> (AL × 0.25) + (Emp × 0.25) + (RP × 0.20) + (EP × 0.20) + (RA × 0.10)
                    </div>
                </div>

                <div class="border-t border-gray-200 pt-6 mt-6">
                    <h4 class="font-semibold text-gray-700 mb-4">Adviser Feedback</h4>

                    <div class="mb-4">
                        <label class="form-label">Strengths Demonstrated</label>
                        <textarea name="strengths" class="form-input" rows="3" placeholder="What did the helper do well?">{{ old('strengths') }}</textarea>
                        @error('strengths')<p class="ui-field-error" role="alert">{{ $message }}</p>@enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Areas for Improvement</label>
                        <textarea name="improvement_areas" class="form-input" rows="3" placeholder="What could be improved?">{{ old('improvement_areas') }}</textarea>
                        @error('improvement_areas')<p class="ui-field-error" role="alert">{{ $message }}</p>@enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Developmental Recommendations</label>
                        <textarea name="recommendations" class="form-input" rows="3" placeholder="Training or development suggestions">{{ old('recommendations') }}</textarea>
                        @error('recommendations')<p class="ui-field-error" role="alert">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="form-label">Recommended Action <span class="text-red-500">*</span></label>
                        <select name="recommended_action" class="form-input" required>
                            <option value="">Select action...</option>
                            @foreach([
                                'continue' => 'Continue regular peer-support duties',
                                'mentoring' => 'Provide support or mentoring',
                                'supervised' => 'Require additional supervised sessions',
                                'training_listening' => 'Recommend active-listening training',
                                'training_empathy' => 'Recommend empathy and communication training',
                                'training_ethics' => 'Recommend ethics and confidentiality training',
                                'training_referral' => 'Recommend referral and emergency-response training',
                                'restrict_high_risk' => 'Temporarily restrict assignment to high-risk cases',
                                'suspend' => 'Temporarily suspend new session assignments pending improvement',
                                'other' => 'Other',
                            ] as $value => $optionLabel)
                                <option value="{{ $value }}" @selected(old('recommended_action') === $value)>{{ $optionLabel }}</option>
                            @endforeach
                        </select>
                        @error('recommended_action')<p class="ui-field-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="flex items-start gap-3 text-xs text-gray-500 pt-4 border-t border-gray-200">
                    <input type="checkbox" id="evaluationDeclaration" required class="mt-0.5 accent-[#04A052]" aria-required="true">
                    <label for="evaluationDeclaration" class="leading-relaxed">
                        I declare that I rated each criterion independently against the 5-point descriptors and the evidence listed above, without duplicating any prior score. This declaration is attestation only and is not stored as a separate record.
                    </label>
                </div>
            </div>
        </x-ui.card>

        <div class="ui-save-bar{{ $errors->any() ? ' is-visible' : '' }}" data-save-bar>
            <a href="{{ route('adviser.evaluations') }}" class="btn-outline"><x-ui-icon name="close" class="mr-2" /> Cancel</a>
            <button type="submit" class="btn-primary">
                <x-ui-icon name="check" class="mr-2" /> Submit Evaluation
            </button>
        </div>
    </form>

    <!-- Session conversation window -->
    <div id="conversationModal" role="dialog" aria-modal="true" aria-labelledby="conversationTitle"
         class="fixed inset-0 z-50 hidden items-center justify-center p-4"
         style="background: rgba(15, 23, 42, 0.55);">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl flex flex-col" style="max-height: 85vh;">
            <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-gray-200">
                <div>
                    <h3 id="conversationTitle" class="font-bold text-gray-800">Session conversation · {{ $report->session->reference_number }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Read-only record from the session for this evaluation. Access is audited and uses aliases only — it does not use voice transcription.</p>
                </div>
                <button type="button" onclick="closeConversation()" class="rounded-lg p-2 hover:bg-gray-100 text-gray-500" aria-label="Close conversation window">
                    <x-ui-icon name="close"  />
                </button>
            </div>
            <div class="overflow-y-auto p-4 space-y-3 divide-y divide-gray-100" style="max-height: 70vh;">
                @forelse($messages as $message)
                    @php($isSeeker = $message->sender === 'seeker')
                    <div class="flex gap-3 pt-3 {{ $isSeeker ? '' : 'bg-green-50/30 -mx-3 px-3 rounded-xl' }}">
                        <span class="shrink-0 mt-0.5 h-8 w-8 rounded-full {{ $isSeeker ? 'bg-gray-100 text-gray-600' : 'bg-green-100 text-green-700' }} flex items-center justify-center text-xs font-semibold">
                            {{ $isSeeker ? mb_substr($report->session->seeker->generated_alias ?? 'S', 0, 1) : mb_substr($report->session->helper->full_name ?? 'H', 0, 1) }}
                        </span>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-baseline gap-2">
                                <span class="text-xs font-semibold {{ $isSeeker ? 'text-gray-700' : 'text-green-700' }}">
                                    {{ $isSeeker ? ($report->session->seeker->generated_alias ?? 'Seeker') : ($report->session->helper->full_name ?? 'Peer Helper') }}
                                </span>
                                <span class="text-xs text-gray-400">{{ ($message->sent_datetime ?? $message->created_at)?->timezone('Asia/Manila')->format('M d, Y \a\t g:i A') }}</span>
                            </div>
                            <p class="text-sm text-gray-700 whitespace-pre-wrap break-words mt-1">{{ $message->message_text }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No conversation is disclosed without an authorized access grant. Review the submitted documentation or request access from Transcript Review.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
    <script>
        let ratings = {};

        function openConversation() {
            const modal = document.getElementById('conversationModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function closeConversation() {
            const modal = document.getElementById('conversationModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';
        }

        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('conversationModal');
            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) closeConversation();
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') closeConversation();
                });
            }
        });

        function selectRating(btn, field) {
            const group = btn.closest('.rating-group');
            const value = btn.dataset.value;

            // Update UI
            group.querySelectorAll('.rating-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // Store value
            ratings[field] = parseInt(value);
            document.getElementById(field).value = value;

            // Update score
            updateScore();
        }

        function updateScore() {
            const al = ratings.active_listening || 0;
            const emp = ratings.empathy || 0;
            const rp = ratings.respect_professionalism || 0;
            const ep = ratings.ethical_practices || 0;
            const ra = ratings.referral_accuracy || 0;

            const total = (al * 0.25) + (emp * 0.25) + (rp * 0.20) + (ep * 0.20) + (ra * 0.10);

            if (al > 0 && emp > 0 && rp > 0 && ep > 0 && ra > 0) {
                document.getElementById('overallScore').textContent = total.toFixed(2);
                document.getElementById('competencyLevelDisplay').innerHTML = getLevelDisplay(total);
            } else {
                document.getElementById('overallScore').textContent = '—';
                document.getElementById('competencyLevelDisplay').textContent = '—';
            }
        }

        function getLevelDisplay(score) {
            let level, className;
            if (score >= 4.5) { level = 'Outstanding'; className = 'competency-level outstanding'; }
            else if (score >= 3.5) { level = 'Very Good'; className = 'competency-level very-good'; }
            else if (score >= 2.5) { level = 'Satisfactory'; className = 'competency-level satisfactory'; }
            else if (score >= 1.5) { level = 'Needs Improvement'; className = 'competency-level needs-improvement'; }
            else { level = 'Unsatisfactory'; className = 'competency-level unsatisfactory'; }
            return `<span class="${className}">${level}</span>`;
        }
</script>

@endsection
