@php
    $isHelper = auth()->user()->role === 'helper';
    $peer = $isHelper ? ($seekerName ?? 'Seeker') : ($helperName ?? 'Peer Helper');
    $report = $report ?? $session->report;
@endphp
<!DOCTYPE html>
<html class="compass-ui" lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Voice support - COMPASS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/chat.js'])
    @include('partials.ui-assets')
    <link rel="stylesheet" href="{{ asset('css/chat-workflow.css') }}?v={{ filemtime(public_path('css/chat-workflow.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/voice-room.css') }}?v={{ filemtime(public_path('css/voice-room.css')) }}">
</head>
<body class="voice-session" data-voice-room data-user-role="{{ auth()->user()->role }}">
    <input type="hidden" id="sessionId" value="{{ $session->id }}">
    <input type="hidden" id="currentUserId" value="{{ auth()->id() }}">
    <input type="hidden" id="currentUserRole" value="{{ auth()->user()->role }}">
    <input type="hidden" id="peerName" value="{{ $peer }}">
    <header class="voice-room-header chat-header">
        <a class="voice-back" href="{{ $isHelper ? route('helper.cases') : route('seeker.dashboard') }}" aria-label="Back to dashboard"><x-ui-icon name="arrow-left" /></a>
        <div class="voice-peer"><strong>{{ $peer }}</strong><small>Voice support · <span id="sessionTimer">00:00</span></small></div>
        <div data-notification-slot></div>
        <form method="POST" action="{{ $isHelper ? route('helper.session.end', ['id' => $session->id]) : route('session.end', ['session_id'=>$session->id]) }}"
              data-confirm="End support session?" data-confirm-message="This ends both the voice call and text chat for you and your session partner." data-confirm-text="End session">
            @csrf
            <button class="voice-end" type="submit">End session</button>
        </form>
    </header>
    <main class="voice-room room-card">
        <section class="voice-stage" aria-label="Voice session">
            <div class="voice-person" aria-hidden="true">{{ \Illuminate\Support\Str::substr($peer, 0, 1) }}</div>
            <h1>{{ $peer }}</h1>
            <x-voice-call :session="$session" :dedicated="true" />
            <div class="voice-tools">
                <button type="button" data-messages-toggle aria-expanded="false" aria-controls="voiceMessages"><x-ui-icon name="message" /> Messages <span data-message-count hidden></span></button>
                @if($isHelper)
                    @if($session->isReferralEligible())<button type="button" id="referralBtn" @disabled(!$session->canRecommendReferral()) title="Exchange messages or connect a voice call first">Referral</button>@endif
                    <button type="button" id="emergencyBtn" @disabled(!$session->canReportEmergency()) title="A participant message or connected voice call is required" class="voice-emergency">Emergency</button>
                @endif
            </div>
            <p class="voice-hint">Messages stay available during the call. Leaving the call does not end your support session.</p>
            @if(!$isHelper && !$report?->help_seeker_condition)
                <details class="voice-checkin"><summary>Optional: how are you feeling?</summary>
                    <form data-voice-checkin action="{{ route('session.checkin', ['session_id'=>$session->id]) }}">
                        <label for="voiceCondition">Your Helper sees only your answer.</label>
                        <select id="voiceCondition" name="condition" required><option value="">Choose an answer</option>
                            @foreach(['coping_well'=>'Coping well','mild_distress'=>'Mild distress','moderate_distress'=>'Moderate distress','severe_distress'=>'Severe distress','prefer_not_to_say'=>'Prefer not to say'] as $value=>$label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="submit">Save my answer</button><p role="status" data-checkin-status></p>
                    </form>
                </details>
            @endif
            <div data-professional-support-slot></div>
        </section>
        <aside class="voice-messages" id="voiceMessages" aria-label="Session messages" hidden>
            <div class="voice-messages-heading"><h2>Messages</h2><button type="button" data-messages-close aria-label="Close messages">Close</button></div>
            <div class="chat-messages" id="chatMessages" aria-live="polite">
                <div id="emptyChat"><p>Your messages appear here.</p></div>
                <div id="typingIndicator" style="display:none"><span id="typingLabel"></span></div>
            </div>
            <div class="voice-composer"><input type="text" id="messageInput" aria-label="Message" placeholder="Type a message…" autocomplete="off"><button type="button" id="sendButton">Send</button></div>
        </aside>
    </main>
    @include('session.connection-status')
    @include('session.referral-prompt')
    @if($isHelper) @include('partials.helper-session-actions') @endif
    @include('components.confirmation-modal')
    @include('partials.workflow-notice')
    <script src="{{ asset('js/voice-room.js') }}?v={{ filemtime(public_path('js/voice-room.js')) }}" defer></script>
</body>
</html>
