@extends('layouts.app')

@section('title', 'COMPASS – Evaluate Helper')

@push('styles')
<style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F8FBF9; }

        .form-card {
            background: white;
            border-radius: 24px;
            padding: 32px 36px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 4px 20px rgba(0,0,0,0.01);
        }

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

        .btn-primary {
            background: linear-gradient(135deg, #04A052, #038A45);
            color: white;
            font-weight: 600;
            padding: 14px 36px;
            border-radius: 50px;
            border: none;
            box-shadow: 0 4px 20px rgba(4,160,82,0.2);
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(4,160,82,0.3); }

        .btn-outline {
            background: transparent;
            color: #6b7280;
            font-weight: 600;
            padding: 14px 28px;
            border-radius: 50px;
            border: 1.5px solid #e5e7eb;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-outline:hover { background: #f3f4f6; }

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

        .risk-badge {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .risk-badge.low { background: #dcfce7; color: #166534; }
        .risk-badge.moderate { background: #fef3c7; color: #92400e; }
        .risk-badge.high { background: #fee2e2; color: #991b1b; }
        .risk-badge.emergency { background: #fee2e2; color: #991b1b; }

        @media (max-width: 768px) {
            .form-card { padding: 20px 16px; border-radius: 16px; }
            .btn-primary, .btn-outline { padding: 12px 24px; font-size: 14px; width: 100%; justify-content: center; }
            .rating-btn { width: 38px; height: 38px; font-size: 14px; }
        }
        .flash-success { background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }
        .flash-error { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }
        @media (max-width: 480px) {
            .form-card { padding: 16px 12px; }
            .rating-btn { width: 34px; height: 34px; font-size: 13px; }
        }
    </style>
@endpush

@section('content')
<div class="adviser-page-content">
        <!-- Top Bar -->
        <div class="flex items-center gap-4 mb-6">
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-800">Evaluate Helper</h1>
                <p class="text-sm text-gray-500 hidden sm:block">
                    Competency evaluation form
                </p>
            </div>
        </div>

        @if(session('success'))
            <div class="flash-success"><i class="fas fa-check-circle mr-1"></i> {{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="flash-error"><i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}</div>
        @endif

        <!-- Back Link -->
        <a href="{{ route('adviser.evaluations') }}" class="inline-flex items-center gap-2 text-gray-500 hover:text-gray-700 mb-6 text-sm font-medium">
            <i class="fas fa-arrow-left"></i> Back to Evaluations
        </a>

        <div class="form-card">

            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-6 pb-4 border-b border-gray-200">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Competency Evaluation</h1>
                    <p class="text-sm text-gray-500">Evaluate helper performance using the competency rubric</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" onclick="openConversation()" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-comments text-green-600" aria-hidden="true"></i> View conversation
                    </button>
                    <span class="risk-badge {{ $report->session->risk_level ?? 'low' }}">
                        {{ ucfirst($report->session->risk_level ?? 'Low') }} Risk
                    </span>
                </div>
            </div>

            <!-- Session Info -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-gray-50 rounded-xl mb-6 text-sm">
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
            </div>

            <!-- Session Summary -->
            <div class="mb-6 p-4 bg-gray-50 rounded-xl">
                <h4 class="font-semibold text-gray-700 text-sm mb-2">Session Summary</h4>
                <p class="text-gray-600 text-sm">{{ $report->session_summary ?? 'No summary available' }}</p>
            </div>

            <div class="mb-6 p-4 bg-gray-50 rounded-xl space-y-3 text-sm">
                <p><strong>Session result:</strong> {{ ucwords(str_replace('_',' ',$report->session_result ?? 'Not recorded')) }}</p>
                <p><strong>Follow-up plan:</strong> {{ $report->follow_up_plan ?: 'Not recorded' }}</p>
                <p><strong>Personal reflection:</strong> {{ $report->personal_reflection ?: 'Not submitted' }}</p>
                <p><strong>Skills applied:</strong> {{ collect($report->skills)->map(fn($skill)=>(['active_listening'=>'Active Listening','empathy'=>'Empathy','crisis_intervention'=>'Clarification','problem_solving'=>'Problem Solving','validation'=>'Validation','referral'=>'Referral'][$skill] ?? ucwords(str_replace('_',' ',$skill))))->join(', ') ?: 'Not recorded' }}</p>
                @if($report->reassessment_requested_at)<p><strong>Risk reassessment requested:</strong> {{ ucfirst($report->risk_level_assessed) }}. Review through the screening review workflow; this is a helper observation, not an official classification.</p>@endif
                <p><strong>Documentation:</strong> {{ $report->documentation_late ? 'Submitted after 24 hours' : 'No overdue submission recorded' }}</p>
            </div>

            <!-- Referral & Risk Review -->
            <div class="mb-6 p-4 bg-gray-50 rounded-xl">
                <h4 class="font-semibold text-gray-700 text-sm mb-3">Referral &amp; Risk Review</h4>
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
            </div>
            <!-- Evaluation Form -->
            <form class="form-maximized" method="POST" action="{{ route('adviser.evaluate.store', $report->id) }}">
                @csrf
                <p class="text-sm text-gray-500 my-3">Rubric: {{ \App\Services\CompetencyRubric::VERSION }}. Evidence: submitted session report #{{ $report->id }} and its correction history. Each criterion is rated independently; weighted total is on a 1?5 scale.</p>

                <!-- Evidence considered -->
                <div class="mb-5 p-4 bg-gray-50 rounded-xl text-sm">
                    <h4 class="font-semibold text-gray-700 mb-2">Evidence considered</h4>
                    <ul class="list-disc pl-5 space-y-1 text-gray-600">
                        <li>Submitted documentation (report #{{ $report->id }}): summary, observations, actions taken, session result, and follow-up plan.</li>
                        @if($report->personal_reflection)
                            <li>Personal reflection submitted {{ $report->reflection_submitted_at?->format('M d, Y g:i A') ?: '' }}.</li>
                        @endif
                        @if(count($report->skills))
                            <li>Skills reported as applied: {{ collect($report->skills)->map(fn($skill)=>(['active_listening'=>'Active Listening','empathy'=>'Empathy','crisis_intervention'=>'Clarification','problem_solving'=>'Problem Solving','validation'=>'Validation','referral'=>'Referral'][$skill] ?? ucwords(str_replace('_',' ',$skill))))->join(', ') }}.</li>
                        @endif
                        <li>{{ $messages->count() ? 'Reviewed ' . $messages->count() . ' conversation messages from the authorized (consent-gated, aliased) record.' : 'Conversation transcript is not disclosed without an authorized access grant; evaluation relies on submitted documentation.' }}</li>
                        @if($existingFeedback)
                            <li>Prior feedback exists for this report — see the correction history above; ignore prior ratings unless this is a correction run.</li>
                        @endif
                    </ul>
                </div>

                <!-- Rating descriptor reference -->
                <details class="mb-6 rounded-xl border border-gray-200 p-4 group">
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
                @if($existingFeedback)
                    <label class="block text-sm font-semibold my-3">Correction reason<input name="correction_reason" required minlength="10" maxlength="1000" class="form-input mt-1" placeholder="Explain why this evaluation or feedback needs correction."></label>
                    <x-supervision-history :record="$existingFeedback" />
                @endif
                <label class="block text-sm my-3">Feedback follow-up date<input type="date" name="follow_up_date" class="form-input mt-1" value="{{ old('follow_up_date', $existingFeedback?->follow_up_date?->format('Y-m-d')) }}"></label>


                <div class="space-y-6">

                    <!-- Active Listening (25%) -->
                    <div>
                        <label class="form-label">Active Listening <span class="text-gray-400 font-normal">(25%)</span></label>
                        <p class="text-sm text-gray-400 mb-2">Uses minimal encouragers; avoids interrupting; accurately reflects the help seeker's statements</p>
                        <div class="rating-group" data-target="active_listening">
                            @for($i = 1; $i <= 5; $i++)
                                <button type="button" class="rating-btn" data-value="{{ $i }}" onclick="selectRating(this, 'active_listening')">{{ $i }}</button>
                            @endfor
                        </div>
                        <input type="hidden" name="active_listening" id="active_listening" required>
                    </div>

                    <!-- Empathy (25%) -->
                    <div>
                        <label class="form-label">Empathy <span class="text-gray-400 font-normal">(25%)</span></label>
                        <p class="text-sm text-gray-400 mb-2">Uses emotional validation phrases; matches the help seeker's tone appropriately; avoids dismissive language</p>
                        <div class="rating-group" data-target="empathy">
                            @for($i = 1; $i <= 5; $i++)
                                <button type="button" class="rating-btn" data-value="{{ $i }}" onclick="selectRating(this, 'empathy')">{{ $i }}</button>
                            @endfor
                        </div>
                        <input type="hidden" name="empathy" id="empathy" required>
                    </div>

                    <!-- Respect & Professionalism (20%) -->
                    <div>
                        <label class="form-label">Respect & Professionalism <span class="text-gray-400 font-normal">(20%)</span></label>
                        <p class="text-sm text-gray-400 mb-2">Maintains professional boundaries; uses polite and culturally appropriate language</p>
                        <div class="rating-group" data-target="respect_professionalism">
                            @for($i = 1; $i <= 5; $i++)
                                <button type="button" class="rating-btn" data-value="{{ $i }}" onclick="selectRating(this, 'respect_professionalism')">{{ $i }}</button>
                            @endfor
                        </div>
                        <input type="hidden" name="respect_professionalism" id="respect_professionalism" required>
                    </div>

                    <!-- Ethical Practices (20%) -->
                    <div>
                        <label class="form-label">Ethical Practices <span class="text-gray-400 font-normal">(20%)</span></label>
                        <p class="text-sm text-gray-400 mb-2">Protects confidentiality, respects autonomy, remains within scope, and applies ethical judgment</p>
                        <div class="rating-group" data-target="ethical_practices">
                            @for($i = 1; $i <= 5; $i++)
                                <button type="button" class="rating-btn" data-value="{{ $i }}" onclick="selectRating(this, 'ethical_practices')">{{ $i }}</button>
                            @endfor
                        </div>
                        <input type="hidden" name="ethical_practices" id="ethical_practices" required>
                    </div>

                    <!-- Referral Accuracy (10%) -->
                    <div>
                        <label class="form-label">Referral Accuracy <span class="text-gray-400 font-normal">(10%)</span></label>
                        <p class="text-sm text-gray-400 mb-2">Correctly tags cases matching adviser-approved referral guidelines</p>
                        <div class="rating-group" data-target="referral_accuracy">
                            @for($i = 1; $i <= 5; $i++)
                                <button type="button" class="rating-btn" data-value="{{ $i }}" onclick="selectRating(this, 'referral_accuracy')">{{ $i }}</button>
                            @endfor
                        </div>
                        <input type="hidden" name="referral_accuracy" id="referral_accuracy" required>
                    </div>

                    <!-- Score Display -->
                    <div class="score-display">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <span class="text-sm text-gray-500">Overall Competency Score</span>
                                <div class="text-3xl font-bold text-gray-800" id="overallScore">—</div>
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

                    <!-- Feedback Section -->
                    <div class="border-t border-gray-200 pt-6 mt-6">
                        <h4 class="font-semibold text-gray-700 mb-4">Adviser Feedback</h4>

                        <div class="mb-4">
                            <label class="form-label">Strengths Demonstrated</label>
                            <textarea name="strengths" class="form-input" rows="3" placeholder="What did the helper do well?"></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Areas for Improvement</label>
                            <textarea name="improvement_areas" class="form-input" rows="3" placeholder="What could be improved?"></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Developmental Recommendations</label>
                            <textarea name="recommendations" class="form-input" rows="3" placeholder="Training or development suggestions"></textarea>
                        </div>

                        <div>
                            <label class="form-label">Recommended Action <span class="text-red-500">*</span></label>
                            <select name="recommended_action" class="form-input" required>
                                <option value="">Select action...</option>
                                <option value="continue">Continue regular peer-support duties</option>
                                <option value="mentoring">Provide support or mentoring</option>
                                <option value="supervised">Require additional supervised sessions</option>
                                <option value="training_listening">Recommend active-listening training</option>
                                <option value="training_empathy">Recommend empathy and communication training</option>
                                <option value="training_ethics">Recommend ethics and confidentiality training</option>
                                <option value="training_referral">Recommend referral and emergency-response training</option>
                                <option value="restrict_high_risk">Temporarily restrict assignment to high-risk cases</option>
                                <option value="suspend">Temporarily suspend new session assignments pending improvement</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-gray-200">
                        <div class="flex items-start gap-3 text-xs text-gray-500">
                            <input type="checkbox" id="evaluationDeclaration" required class="mt-0.5 accent-[#04A052]" aria-required="true">
                            <label for="evaluationDeclaration" class="leading-relaxed">
                                I declare that I rated each criterion independently against the 5-point descriptors and the evidence listed above, without duplicating any prior score. This declaration is attestation only and is not stored as a separate record.
                            </label>
                        </div>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('adviser.evaluations') }}" class="btn-outline">
                                <i class="fas fa-times mr-2"></i> Cancel
                            </a>
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-check mr-2"></i> Submit Evaluation
                            </button>
                        </div>
                    </div>

                </div>
            </form>

        </div>

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
                        <i class="fas fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="overflow-y-auto p-4 space-y-3 divide-y divide-gray-100" style="max-height: 70vh;">
                    @forelse($messages as $message)
                        @php($isSeeker = $message->sender === 'seeker')
                        <div class="flex gap-3 pt-3 {{ $isSeeker ? '' : 'bg-green-50/30 -mx-3 px-3 rounded-xl' }}">
                            <span class="shrink-0 mt-0.5 h-8 w-8 rounded-full {{ $isSeeker ? 'bg-gray-100 text-gray-600' : 'bg-green-100 text-green-700' }} flex items-center justify-center text-xs font-semibold">
                                {{ $isSeeker ? mb_substr($report->session->seeker->generated_alias ?? 'S', 0, 1) : mb_substr($report->session->helper->public_alias ?? 'H', 0, 1) }}
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-baseline gap-2">
                                    <span class="text-xs font-semibold {{ $isSeeker ? 'text-gray-700' : 'text-green-700' }}">
                                        {{ $isSeeker ? ($report->session->seeker->generated_alias ?? 'Seeker') : ($report->session->helper->public_alias ?? 'Peer Helper') }}
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
                document.getElementById('overallScore').textContent = total.toFixed(2) + ' / 5.00';
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
