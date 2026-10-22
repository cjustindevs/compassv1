@props(['session', 'dedicated' => false])
@if(in_array($session->session_type, ['voice','chat'], true) && $session->isActive())
@php
    $configuration = app(\App\Services\VoiceConfiguration::class);
    $voiceConfig = ['sessionId'=>$session->id, 'userId'=>auth()->id(), 'socket'=>$configuration->publicSocket(),
        'state'=>route('voice.state',$session), 'credentials'=>route('voice.credentials',$session),
        'start'=>route('voice.start',$session), 'action'=>route('voice.action',$session), 'signal'=>route('voice.signal',$session),
        'consent'=>route('voice.consent',$session), 'requestConsent'=>route('voice.consent-request',$session), 'role'=>auth()->user()->role];
@endphp
@if(!$dedicated)<button type="button" class="voice-chat-launcher" data-voice-toggle aria-expanded="false"><x-ui-icon name="phone" /> Call</button>@endif
<section class="voice-call-panel {{ $dedicated ? 'voice-call-dedicated' : 'voice-chat-panel' }}" data-voice-call data-chat="{{ $dedicated ? 'false' : 'true' }}" data-voice-state="checking" aria-label="Live voice call" @if(!$dedicated) hidden @endif>
    <div class="voice-call-visual" aria-hidden="true"><x-ui-icon name="phone" /><span></span><span></span><span></span></div>
    <div class="voice-call-copy">
        <div class="voice-call-heading"><h2>Voice support</h2><span class="voice-call-badge" data-voice-badge>Checking</span><time data-voice-duration hidden aria-label="Call duration">00:00</time></div>
        <h3 data-voice-title>Getting your call ready</h3>
        <p data-voice-status role="status" aria-live="polite">Checking call availability…</p>
        <small>Audio is not recorded. Leaving the call keeps this support session and text chat open.</small>
    </div>
    <div class="voice-call-actions">
        <button type="button" data-voice-consent hidden>{{ auth()->user()->role === 'seeker' ? 'Enable voice calls' : 'Request voice call' }}</button>
        <button type="button" data-voice-start disabled>Call</button>
        <button type="button" data-voice-accept hidden>Accept call</button>
        <button type="button" data-voice-decline hidden>Decline</button>
        <button type="button" data-voice-mute hidden aria-pressed="false">Mute</button>
        <button type="button" data-voice-leave hidden>Leave call</button>
        <button type="button" data-voice-play hidden>Play audio</button>
        <button type="button" data-voice-retry hidden>Retry connection</button>
        <button type="button" data-voice-microphone disabled>Check microphone</button>
    </div>
    <div class="voice-call-check"><span data-voice-microphone-status role="status">Microphone not checked</span></div>
    <details class="voice-call-details">
        <summary>Connection details</summary>
        <dl>
            <div><dt>Microphone</dt><dd data-voice-detail-microphone>Not checked</dd></div>
            <div><dt>Session service</dt><dd data-voice-detail-server>Checking</dd></div>
            <div><dt>Call messages</dt><dd data-voice-detail-signaling>Checking</dd></div>
            <div><dt>Audio connection</dt><dd data-voice-detail-audio>Not started</dd></div>
            <div><dt>Last issue</dt><dd data-voice-detail-error>None</dd></div>
        </dl>
    </details>
    <audio data-voice-audio autoplay playsinline></audio>
    @if(auth()->user()->role === 'seeker')
    <dialog data-voice-consent-dialog class="focus-panel-dialog" aria-label="Voice participation consent"><h2>Enable voice calls?</h2><p>I agree to live audio with my assigned Helper. Audio is not recorded or transcribed. I can leave the call and continue with messages.</p><label><input type="checkbox" data-voice-consent-check> I agree to voice participation.</label><p data-voice-consent-error role="alert"></p><div class="voice-call-actions"><button type="button" data-voice-consent-save>Enable voice</button><button type="button" data-voice-consent-cancel>Keep using messages</button></div></dialog>
    @endif
    <script type="application/json" data-voice-config>@json($voiceConfig)</script>
</section>
@once @vite(['resources/js/voice-call.js']) @endonce
@endif
