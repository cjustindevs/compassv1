@extends('layouts.helper')

@section('title', 'Live Chat')

@section('heading', 'Live Chat')
@section('subheading', 'Choose a session to start chatting.')

@section('content')

    @if(isset($sessions) && $sessions->isNotEmpty())

        <div class="card">
            <div class="card-header">
                <h3>Active Sessions</h3>
                <span class="text-xs text-gray-400">{{ $sessions->count() }} session(s)</span>
            </div>
            <div style="display:flex;flex-direction:column;">
                @foreach($sessions as $session)
                    <a href="{{ route('helper.chat.show', ['id' => $session->id]) }}" style="display:flex;align-items:center;gap:14px;padding:14px;border-radius:14px;text-decoration:none;color:var(--gray-700);transition:all .2s ease;" onmouseover="this.style.background='var(--green-50)'" onmouseout="this.style.background='transparent'">
                        <div class="avatar-sm">{{ \Illuminate\Support\Str::substr($session->seeker->generated_alias ?? 'S', 0, 2) }}</div>
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span class="font-semibold text-gray-800">{{ $session->seeker->generated_alias ?? 'Seeker' }}</span>
                                <span class="status-badge {{ str_replace('_', '-', $session->session_status) }}">{{ $session->status_label }}</span>
                            </div>
                            <div class="text-xs text-gray-400">{{ $session->concern->concern_name ?? 'Session' }} · {{ $session->created_at?->diffForHumans() }}</div>
                            @if($session->messages->isNotEmpty())
                                <div class="text-xs text-gray-500 mt-1" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;">
                                    {{ $session->messages->sortByDesc('sent_datetime')->first()->sender_role === 'helper' ? 'You: ' : '' }}{{ $session->messages->sortByDesc('sent_datetime')->first()->message_text }}
                                </div>
                            @endif
                        </div>
                        <i class="fas fa-chevron-right text-gray-300"></i>
                    </a>
                @endforeach
            </div>
        </div>

    @else

        <div class="card">
            <div class="empty-state">
                <i class="fas fa-comment-slash"></i>
                <h3 style="font-size:16px;font-weight:700;color:var(--gray-800);margin-bottom:6px;">No active sessions</h3>
                <p>No active sessions. Please check your assigned cases.</p>
                <a href="{{ route('helper.cases') }}" class="btn btn-primary btn-sm" style="margin-top:14px;">
                    <i class="fas fa-folder-open mr-1"></i> View Assigned Cases
                </a>
            </div>
        </div>

    @endif

@endsection