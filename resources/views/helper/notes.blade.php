@extends('layouts.helper')

@section('title', 'Session Notes')

@section('heading', 'Session Notes')
@section('subheading', 'Case ' . $session->reference_number . ' · ' . ($session->seeker->generated_alias ?? 'Seeker'))

@section('content')

        <a href="{{ route('helper.cases.show', ['id' => $session->id]) }}" class="btn btn-secondary btn-sm mb-4"><i class="fas fa-arrow-left"></i> Back to case</a>

        @if(session('success'))
            <div class="alert alert-success mb-4" role="status" aria-live="polite">
                <i class="fas fa-check-circle" aria-hidden="true"></i>
                <div>{{ session('success') }}</div>
            </div>
        @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-2">
            <div class="card">
                <div class="card-header">
                    <h3>Session Documentation</h3>
                    @if($report)
                        <span class="pill">{{ $report->adviser_reviewed ? 'Reviewed by adviser' : 'Pending adviser review' }}</span>
                    @endif
                </div>

                <form class="form-container form-maximized" method="POST" action="{{ route('helper.session.notes.store', ['id' => $session->id]) }}">
                    @csrf

                    <fieldset class="form-section-compact">
                    <legend>Session documentation</legend>
                    <div class="form-row">

                    <div class="form-group form-span-all">
                        <label class="form-label">Seeker condition</label>
                        <input type="text" id="help_seeker_condition" name="help_seeker_condition" class="form-control" placeholder="Briefly describe the seeker's condition" value="{{ old('help_seeker_condition', $report->help_seeker_condition ?? '') }}" maxlength="500">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="session_summary">Session summary <span style="color:var(--red-600);">*</span></label>
                        <textarea id="session_summary" name="session_summary" class="form-control" placeholder="Summarize what was discussed during the session" required maxlength="2000">{{ old('session_summary', $report->session_summary ?? '') }}</textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="observations">Observations</label>
                        <textarea id="observations" name="observations" required class="form-control" placeholder="Record objective observations, emotional cues, and relevant context" maxlength="2000">{{ old('observations', $report->observations ?? '') }}</textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="actions_taken">Actions taken</label>
                        <textarea id="actions_taken" name="actions_taken" required class="form-control" placeholder="Document support actions, grounding exercises, resources shared, or escalation steps" maxlength="2000">{{ old('actions_taken', $report->actions_taken ?? '') }}</textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="risk_level_assessed">Request adviser risk reassessment (optional)</label>
                        <select id="risk_level_assessed" name="risk_level_assessed" class="form-control"><option value="">No reassessment requested</option>
                            @foreach(['low' => 'Low', 'moderate' => 'Moderate', 'high' => 'High', 'emergency' => 'Emergency'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('risk_level_assessed', $report?->risk_level_assessed) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    </div>
                    </fieldset>
                    <div class="form-group">
                        <label class="form-label" for="session_result">Session result</label>
                        <select id="session_result" name="session_result" class="form-control" required>
                            <option value="">Choose a result</option>
                            @foreach(['stable'=>'Stable','needs_follow_up'=>'Needs follow-up','needs_referral'=>'Needs referral'] as $value=>$label)
                                <option value="{{ $value }}" @selected(old('session_result',$report?->session_result)===$value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label" for="follow_up_plan">Follow-up or referral plan</label><textarea id="follow_up_plan" name="follow_up_plan" class="form-control" maxlength="2000">{{ old('follow_up_plan',$report?->follow_up_plan) }}</textarea><small>Required when follow-up or a referral is needed. Submit a referral from the case actions for adviser approval.</small></div>
                    @if($report?->session_summary)
                        <div class="form-group"><label class="form-label">Reason for correction</label><input name="correction_reason" class="form-control" maxlength="1000" required></div>
                    @endif
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save" aria-hidden="true"></i> Submit summary</button>
                </form>
                <hr class="divider">
                <form class="form-container form-maximized" method="POST" action="{{ route('helper.reflection.submit',$session->id) }}">
                    @csrf
                    <fieldset class="form-section-compact"><legend>Personal reflection</legend><div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="personal_reflection">Personal reflection</label>
                        <textarea id="personal_reflection" name="personal_reflection" required class="form-control" placeholder="Your personal reflection on the session (not shared with the seeker)" maxlength="2000">{{ old('personal_reflection', $report->personal_reflection ?? '') }}</textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Skills applied</label>
                        <div class="skills-grid-max">
                            @foreach($skills as $key => $label)
                                <label class="checkbox-group border border-gray-200 rounded-xl p-3">
                                    <input type="checkbox" name="skills_applied[]" value="{{ $key }}"
                                        @if(in_array($key, old('skills_applied', $report?->skills ?? []))) checked @endif>
                                    <span class="text-sm text-gray-700">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    @if($report?->personal_reflection)
                    <div class="form-group form-span-all"><label class="form-label">Reason for correction</label><input name="correction_reason" class="form-control" maxlength="1000" required></div>
                    @endif
                    </div>
                    </fieldset>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Submit reflection</button>
                </form>
            </div>
        </div>

        <div class="lg:col-span-1">
            <div class="card mb-6">
                <div class="card-header">
                    <h3>Session Info</h3>
                </div>
                <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                    <div>
                        <div class="text-xs text-gray-400 mb-1">Status</div>
                        <span class="status-badge {{ str_replace('_', '-', $session->session_status) }}">{{ $session->status_label }}</span>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 mb-1">Mode</div>
                        <div class="font-semibold text-gray-800">{{ $session->mode_label }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 mb-1">Risk</div>
                        <span class="pill">Assigned peer support</span>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 mb-1">Duration</div>
                        <div class="font-semibold text-gray-800">{{ $session->duration ? $session->duration . 'm' : '—' }}</div>
                    </div>
                </div>
                <hr class="divider">
                <div class="text-sm text-gray-500 mb-3">
                    <div class="mb-2"><i class="fas fa-calendar mr-2"></i>{{ $session->created_at?->format('M d, Y') }}</div>
                    <div class="mb-2"><i class="fas fa-clock mr-2"></i>Started {{ $session->start_time?->format('h:i A') ?? '—' }}</div>
                    <div><i class="fas fa-hourglass-end mr-2"></i>Ended {{ $session->end_time?->format('h:i A') ?? '—' }}</div>
                </div>
                <hr class="divider">
                @if($session->session_status === 'active')
                    <form method="POST" action="{{ route('helper.session.end', ['id' => $session->id]) }}"
                          data-confirm="End session?"
                          data-confirm-message="This will end the session and notify the seeker for evaluation."
                          data-confirm-text="End session"
                          data-confirm-class="bg-red-600 hover:bg-red-700 focus:ring-red-500">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-block"><i class="fas fa-stop-circle"></i> End Session</button>
                    </form>
                @else
                    <div class="alert alert-success" style="margin-bottom:0;"><i class="fas fa-check-circle"></i> This session is not active.</div>
                @endif
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Guidelines</h3>
                </div>
                <ul style="font-size:13px;color:var(--gray-500);line-height:1.7;padding-left:18px;">
                    <li>Write objectively. Avoid guessing the seeker's identity.</li>
                    <li>Reflections stay private between you and your adviser.</li>
                    <li>Report safety concerns promptly using the case actions. Your adviser determines referrals and risk updates.</li>
                    <li>Complete documentation within 24 hours after the session ends.</li>
                </ul>
            </div>
        </div>

    </div>
    @if($revisions->isNotEmpty())
    <div class="card mt-6"><div class="card-header"><h3>Correction history</h3></div>
        @foreach($revisions as $revision)
        @php($previous=json_decode($revision->snapshot,true))
        <details class="border border-gray-200 rounded-xl p-4 mb-3"><summary class="cursor-pointer text-sm font-semibold">{{ \Illuminate\Support\Carbon::parse($revision->created_at)->timezone('Asia/Manila')->format('M d, Y h:i A') }} &middot; {{ $revision->reason }}</summary>
            <p class="text-sm text-gray-600 mt-3"><strong>Previous summary:</strong> {{ $previous['session_summary'] ?? 'Not submitted' }}</p>
            <p class="text-sm text-gray-600 mt-3"><strong>Previous reflection:</strong> {{ $previous['personal_reflection'] ?? 'Not submitted' }}</p>
        </details>
        @endforeach
    </div>
    @endif
@endsection
