@props(['session', 'dedicated' => false])
@if($session->session_type === 'voice' && $session->isActive())
@php
    $configuration = app(\App\Services\VoiceConfiguration::class);
    $voiceConfig = ['sessionId'=>$session->id, 'userId'=>auth()->id(), 'socket'=>$configuration->publicSocket(),
        'state'=>route('voice.state',$session), 'credentials'=>route('voice.credentials',$session),
        'start'=>route('voice.start',$session), 'action'=>route('voice.action',$session), 'signal'=>route('voice.signal',$session)];
@endphp
<section class="voice-call-panel {{ $dedicated ? 'voice-call-dedicated' : '' }}" data-voice-call data-voice-state="checking" aria-label="Live voice call">
    <div class="voice-call-visual" aria-hidden="true"><x-ui-icon name="phone" /><span></span><span></span><span></span></div>
    <div class="voice-call-copy">
        <div class="voice-call-heading"><h2>Voice support</h2><span class="voice-call-badge" data-voice-badge>Checking</span><time data-voice-duration hidden aria-label="Call duration">00:00</time></div>
        <h3 data-voice-title>Getting your call ready</h3>
        <p data-voice-status role="status" aria-live="polite">Checking call availability…</p>
        <small>Audio is not recorded. Leaving the call keeps this support session and text chat open.</small>
    </div>
    <div class="voice-call-actions">
        <button type="button" data-voice-start disabled>Call</button>
        <button type="button" data-voice-accept hidden>Accept call</button>
        <button type="button" data-voice-decline hidden>Decline</button>
        <button type="button" data-voice-mute hidden aria-pressed="false">Mute</button>
        <button type="button" data-voice-leave hidden>Leave call</button>
        <button type="button" data-voice-play hidden>Play audio</button>
        <button type="button" data-voice-retry hidden>Retry connection</button>
    </div>
    <audio data-voice-audio autoplay playsinline></audio>
    <script type="application/json" data-voice-config>@json($voiceConfig)</script>
</section>
@once @vite(['resources/js/voice-call.js']) @endonce
@endif
