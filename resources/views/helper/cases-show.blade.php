@extends('layouts.helper')

@section('title', 'Case ' . $session->reference_number)

@section('heading', 'Case ' . $session->reference_number)
@section('subheading', $session->seeker->generated_alias ?? 'Seeker')

@section('content')

        <a href="{{ route('helper.cases') }}" class="btn btn-secondary btn-sm mb-4"><i class="fas fa-arrow-left"></i> Back to cases</a>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-2">
            <div class="card mb-6">
                <div class="card-header">
                    <h3>Case Overview</h3>
                    <span class="status-badge {{ str_replace('_', '-', $session->session_status) }}">{{ $session->status_label }}</span>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <div>
                        <div class="text-xs text-gray-400 mb-1">Risk level</div>
                        <span class="pill">Assigned peer support</span>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 mb-1">Mode</div>
                        <div class="font-semibold text-gray-800">{{ $session->mode_label }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 mb-1">Language</div>
                        <div class="font-semibold text-gray-800">{{ $session->seeker?->user?->preferred_language ?: 'English' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 mb-1">Duration</div>
                        <div class="font-semibold text-gray-800">{{ $session->duration ? $session->duration . 'm' : '—' }}</div>
                    </div>
                </div>

                <hr class="divider">

                <div class="form-group">
                    <label class="form-label">Area of concern</label>
                    <div class="text-sm text-gray-700">{{ $session->concern->concern_name ?? 'No concern specified' }}</div>
                    @if($session->concern?->description)
                        <div class="text-sm text-gray-500 mt-1">{{ $session->concern->description }}</div>
                    @endif
                </div>

                <div class="form-group">
                    <label class="form-label">Requested on</label>
                    <div class="text-sm text-gray-700">{{ $session->created_at?->format('M d, Y h:i A') }} ({{ $session->created_at?->diffForHumans() }})</div>
                </div>

                @if($session->start_time)
                    <div class="form-group">
                        <label class="form-label">Started</label>
                        <div class="text-sm text-gray-700">{{ $session->start_time->format('M d, Y h:i A') }}</div>
                    </div>
                @endif
                @if($session->end_time)
                    <div class="form-group">
                        <label class="form-label">Ended</label>
                        <div class="text-sm text-gray-700">{{ $session->end_time->format('M d, Y h:i A') }}</div>
                    </div>
                @endif
            </div>

            <!-- Messages preview -->
            <div class="card mb-6">
                <div class="card-header">
                    <h3>Conversation</h3>
                    <a href="{{ route('helper.session.chat', ['id' => $session->id]) }}" class="link"><i class="fas fa-comment-dots"></i> Open chat</a>
                </div>
                @if(optional($session->messages)->isNotEmpty())
                    <div style="max-height:260px;overflow-y:auto;display:flex;flex-direction:column;gap:8px;" id="msgPreview">
                        @foreach($session->messages->sortBy('sent_datetime')->take(10) as $message)
                            <div class="chat-bubble {{ $message->is_helper ? 'helper' : 'seeker' }}" style="max-width:85%;">
                                {{ $message->message_text }}
                                <span class="meta">{{ $message->is_helper ? 'You' : ($session->seeker->generated_alias ?? 'Seeker') }} · {{ $message->time_formatted }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="empty-state"><i class="fas fa-comments"></i><p>{{ $session->isActive() ? 'No messages yet.' : 'Conversation is available during an accepted active session. Archived transcripts require adviser authorization.' }}</p></div>
                @endif
            </div>

            <!-- Session report -->
            <div class="card">
                <div class="card-header">
                    <h3>Documentation</h3>
                    <a href="{{ route('helper.session.notes', ['id' => $session->id]) }}" class="link">Open notes</a>
                </div>
                @if($session->report)
                    <div class="form-group">
                        <label class="form-label">Seeker condition</label>
                        <div class="text-sm text-gray-700">{{ $session->report->help_seeker_condition ?: '—' }}</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Session summary</label>
                        <div class="text-sm text-gray-700">{{ $session->report->session_summary }}</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Referral recommended</label>
                        <div class="text-sm text-gray-700">{{ $session->report->referral_recommended ? 'Yes' : 'No' }}</div>
                    </div>
                    <span class="pill">{{ $session->report->adviser_reviewed ? 'Reviewed by adviser' : 'Pending adviser review' }}</span>
                @else
                    <div class="empty-state"><i class="fas fa-file-alt"></i><p>No documentation yet for this case.</p></div>
                @endif
            </div>
        </div>

        <div class="lg:col-span-1">
            <div class="card" style="position:sticky;top:24px;">
                <div class="card-header">
                    <h3>Seeker</h3>
                </div>
                <div style="text-align:center;padding:8px 0 16px;">
                    <div class="avatar-lg" style="margin:0 auto 12px;">{{ \Illuminate\Support\Str::substr($session->seeker->generated_alias ?? 'S', 0, 2) }}</div>
                    <div class="font-bold text-gray-800">{{ $session->seeker->generated_alias ?? 'Anonymous Seeker' }}</div>
                    <div class="text-xs text-gray-400 mt-1">
                        {{ $session->seeker?->gender ? ucfirst($session->seeker->gender) : 'Prefer not to say' }}
                        {{ $session->seeker?->age ? ' · ' . $session->seeker->age . ' yrs' : '' }}
                    </div>
                </div>
                <hr class="divider">
                @if($session->session_status === 'helper_assigned' && $session->scheduled_start)
                    <div style="text-align:center;padding:0 0 10px;">
                        <div class="text-xs text-gray-500"><i class="fas fa-calendar-check mr-1"></i>Session scheduled</div>
                        <div class="font-bold text-emerald-600">{{ $session->scheduled_start->setTimezone(config('app.schedule_timezone'))->format('M d, h:i A') }}</div>
                    </div>
                    <hr class="divider">
                @endif
                <div style="display:flex;flex-direction:column;gap:8px;">
                    @if($session->session_status === 'helper_assigned' && $session->helper_accepted_at)
                        <a class="btn btn-primary btn-block" href="{{ route('helper.session.pre-assessment',$session->id) }}">Prepare and start session</a>
                    @elseif($session->session_status === 'helper_assigned')
                        <form method="POST" action="{{ route('helper.cases.accept', ['id' => $session->id]) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-check"></i> Accept Case</button>
                        </form>
                        <form method="POST" action="{{ route('helper.cases.decline', ['id' => $session->id]) }}"
                              data-confirm="Decline case?"
                              data-confirm-message="This case will be returned to the queue and reassigned."
                              data-confirm-text="Decline"
                              data-confirm-class="bg-red-600 hover:bg-red-700 focus:ring-red-500">
                            @csrf<label class="sr-only" for="decline-{{ $session->id ?? $case['id'] }}">Reason for declining</label><select id="decline-{{ $session->id ?? $case['id'] }}" name="reason" class="form-control" required><option value="">Choose a reason</option>@foreach(['fatigue'=>'Fatigue','illness'=>'Illness','personal_emergency'=>'Personal emergency','academic_conflict'=>'Academic conflict','conflict_of_interest'=>'Conflict of interest','unavailable'=>'Unavailable'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                            <button type="submit" class="btn btn-outline-danger btn-block"><i class="fas fa-times"></i> Decline Case</button>
                        </form>
                    @else
                        <a href="{{ route('helper.session.chat', ['id' => $session->id]) }}" class="btn btn-primary btn-block"><i class="fas fa-comment-dots"></i> Open Chat</a>
                        <a href="{{ route('helper.session.voice', ['id' => $session->id]) }}" class="btn btn-secondary btn-block"><i class="fas fa-phone"></i> Voice unavailable</a>
                        <a href="{{ route('helper.session.notes', ['id' => $session->id]) }}" class="btn btn-secondary btn-block"><i class="fas fa-edit"></i> Session Notes</a>
                        @if($session->session_status === 'active')
                            <form method="POST" action="{{ route('helper.session.end', ['id' => $session->id]) }}"
                                  data-confirm="End session?"
                                  data-confirm-message="The seeker will be asked to evaluate."
                                  data-confirm-text="End session"
                                  data-confirm-class="bg-red-600 hover:bg-red-700 focus:ring-red-500">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-block"><i class="fas fa-stop-circle"></i> End Session</button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
        </div>

    </div>

@endsection
