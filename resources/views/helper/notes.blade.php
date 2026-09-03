@extends('layouts.helper')

@section('title', 'Session Notes')

@section('heading', 'Session Notes')
@section('subheading', 'Case ' . $session->reference_number . ' · ' . ($session->seeker->generated_alias ?? 'Seeker'))

@section('content')

    <a href="{{ route('helper.cases.show', ['id' => $session->id]) }}" class="btn btn-secondary btn-sm mb-4" style="padding:6px 14px;"><i class="fas fa-arrow-left"></i> Back to case</a>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-2">
            <div class="card">
                <div class="card-header">
                    <h3>Session Documentation</h3>
                    @if($report)
                        <span class="pill">{{ $report->adviser_reviewed ? 'Reviewed by adviser' : 'Pending adviser review' }}</span>
                    @endif
                </div>

                <form method="POST" action="{{ route('helper.session.notes.store', ['id' => $session->id]) }}">
                    @csrf

                    <div class="form-group">
                        <label class="form-label">Seeker condition</label>
                        <input type="text" name="help_seeker_condition" class="form-control" placeholder="Briefly describe the seeker's condition" value="{{ old('help_seeker_condition', $report->help_seeker_condition ?? '') }}" maxlength="500">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Session summary <span style="color:var(--red-600);">*</span></label>
                        <textarea name="session_summary" class="form-control" placeholder="Summarize what was discussed during the session" required maxlength="2000">{{ old('session_summary', $report->session_summary ?? '') }}</textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Observations</label>
                        <textarea name="observations" class="form-control" placeholder="Record objective observations, emotional cues, and relevant context" maxlength="2000">{{ old('observations', $report->observations ?? '') }}</textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Actions taken</label>
                        <textarea name="actions_taken" class="form-control" placeholder="Document support actions, grounding exercises, resources shared, or escalation steps" maxlength="2000">{{ old('actions_taken', $report->actions_taken ?? '') }}</textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Risk level assessed</label>
                        <select name="risk_level_assessed" class="form-control">
                            @foreach(['low' => 'Low', 'moderate' => 'Moderate', 'high' => 'High', 'emergency' => 'Emergency'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('risk_level_assessed', $report->risk_level_assessed ?? $session->risk_level) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Personal reflection</label>
                        <textarea name="personal_reflection" class="form-control" placeholder="Your personal reflection on the session (not shared with the seeker)" maxlength="2000">{{ old('personal_reflection', $report->personal_reflection ?? '') }}</textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Skills applied</label>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            @foreach($skills as $key => $label)
                                <label class="checkbox-group border border-gray-200 rounded-xl p-3">
                                    <input type="checkbox" name="skills_applied[]" value="{{ $key }}"
                                        @if(in_array($key, old('skills_applied', $report?->skills ?? []))) checked @endif>
                                    <span class="text-sm text-gray-700">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-group border border-gray-200 rounded-xl p-4">
                            <input type="checkbox" name="referral_recommended" value="1"
                                @if(old('referral_recommended', $report->referral_recommended ?? false)) checked @endif>
                            <span>
                                <span class="font-semibold text-gray-800" style="font-size:14px;">Referral recommended</span>
                                <span class="block text-xs text-gray-500">Mark this if the seeker should be referred to a professional. Your adviser will be notified.</span>
                            </span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Notes</button>
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
                        <span class="risk-badge {{ $session->risk_level ?? 'low' }}">{{ ucfirst($session->risk_level ?? 'Low') }}</span>
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
                @if(! in_array($session->session_status, ['completed', 'evaluated'], true))
                    <form method="POST" action="{{ route('helper.session.end', ['id' => $session->id]) }}"
                          data-confirm="End session?"
                          data-confirm-message="This will end the session and notify the seeker for evaluation."
                          data-confirm-text="End session"
                          data-confirm-class="bg-red-600 hover:bg-red-700 focus:ring-red-500">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-block"><i class="fas fa-stop-circle"></i> End Session</button>
                    </form>
                @else
                    <div class="alert alert-success" style="margin-bottom:0;"><i class="fas fa-check-circle"></i> This session is completed.</div>
                @endif
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Guidelines</h3>
                </div>
                <ul style="font-size:13px;color:var(--gray-500);line-height:1.7;padding-left:18px;">
                    <li>Write objectively. Avoid guessing the seeker's identity.</li>
                    <li>Reflections stay private between you and your adviser.</li>
                    <li>Always recommend a referral for high or emergency risk cases.</li>
                    <li>Complete documentation within 24 hours after the session ends.</li>
                </ul>
            </div>
        </div>

    </div>

@endsection
