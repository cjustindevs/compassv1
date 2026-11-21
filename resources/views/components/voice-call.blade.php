@props(['session', 'dedicated' => false])
@if(in_array($session->session_type, ['voice','chat'], true) && $session->isActive())
@php
    $configuration = app(\App\Services\VoiceConfiguration::class);
    $voiceConfig = ['sessionId'=>$session->id, 'userId'=>auth()->id(), 'socket'=>$configuration->publicSocket(),
        'state'=>route('voice.state',$session), 'credentials'=>route('voice.credentials',$session),
        'start'=>route('voice.start',$session), 'action'=>route('voice.action',$session), 'signal'=>route('voice.signal',$session),
        'consent'=>route('voice.consent',$session), 'requestConsent'=>route('voice.consent-request',$session), 'role'=>auth()->user()->role];
    // Round icon controls (QA: icons instead of text). The label stays for screen readers and as the tooltip.
    $controls = [
        'start' => ['phone', 'Call', 'voice-btn voice-btn--go', 'disabled'],
        'accept' => ['phone', 'Accept call', 'voice-btn voice-btn--go', 'hidden'],
        'decline' => ['phone', 'Decline', 'voice-btn voice-btn--end', 'hidden'],
        'mute' => ['microphone', 'Mute', 'voice-btn', 'hidden aria-pressed="false"'],
        'play' => ['play', 'Play audio', 'voice-btn', 'hidden'],
        'microphone' => ['microphone', 'Check microphone', 'voice-btn', 'disabled'],
        'leave' => ['phone', 'Leave call', 'voice-btn voice-btn--end', 'hidden'],
    ];
@endphp
{{-- In text chat the panel opens from <x-voice-call-launcher> in the top bar. --}}
<section class="voice-call-panel {{ $dedicated ? 'voice-call-dedicated' : 'voice-chat-panel' }}" data-voice-call data-chat="{{ $dedicated ? 'false' : 'true' }}" data-voice-state="checking" aria-label="Live voice call" @if(!$dedicated) hidden @endif>
    <div class="voice-call-visual" aria-hidden="true"><x-ui-icon name="phone" /><span></span><span></span><span></span></div>
    <div class="voice-call-copy">
        <div class="voice-call-heading"><h2>Voice support</h2><span class="voice-call-badge" data-voice-badge>Checking</span><time data-voice-duration hidden aria-label="Call duration">00:00</time></div>
        <h3 data-voice-title>Getting your call ready</h3>
        <p data-voice-status role="status" aria-live="polite">Checking call availability…</p>
        <small>Calls are not recorded. Your supervising COMPASS Adviser may listen live to keep support safe. Leaving the call keeps this support session and text chat open.</small>
    </div>
    {{-- Live supervision is never hidden: both participants see this while an Adviser is listening. --}}
    <p class="voice-call-supervised" data-voice-supervised role="status" hidden><x-ui-icon name="eye" /> A COMPASS Adviser is listening for supervision. Calls are not recorded.</p>
    <div class="voice-call-actions">
        <button type="button" class="voice-consent-btn" data-voice-consent hidden>{{ auth()->user()->role === 'seeker' ? 'Enable voice calls' : 'Request voice call' }}</button>
        @foreach($controls as $name => [$icon, $label, $class, $state])
        <button type="button" class="{{ $class }}" data-voice-{{ $name }} {!! $state !!} aria-label="{{ $label }}" data-tooltip="{{ $label }}"><x-ui-icon :name="$icon" /><span class="sr-only" data-voice-label>{{ $label }}</span></button>
        @endforeach
    </div>
    <span class="sr-only" data-voice-microphone-status role="status">Microphone not checked</span>
    {{-- Shown only when the connection has a problem (reconnecting, failed, unavailable). --}}
    <div class="voice-call-alert" data-voice-alert role="alert" hidden>
        <x-ui-icon name="warning" />
        <p data-voice-alert-text>Connection problem.</p>
        <button type="button" class="voice-btn" data-voice-retry hidden aria-label="Retry connection" data-tooltip="Retry connection"><x-ui-icon name="refresh" /><span class="sr-only" data-voice-label>Retry connection</span></button>
    </div>
    <audio data-voice-audio autoplay playsinline></audio>
    @if(auth()->user()->role === 'seeker')
    <dialog data-voice-consent-dialog class="cm-dialog" data-tone="primary" aria-label="Voice participation consent">
        <div class="cm-scroll">
            <div class="cm-head"><span class="cm-icon" aria-hidden="true"><x-ui-icon name="phone" /></span><div><h2 class="cm-title">Enable voice calls?</h2><p class="cm-text">I agree to live audio with my assigned Helper. My supervising COMPASS Adviser may listen live to keep support safe, and I will see a notice whenever they do. Calls are not recorded or transcribed. I can leave the call and continue with messages.</p></div></div>
            <div class="cm-body"><label class="cm-check"><input type="checkbox" data-voice-consent-check> I agree to voice calls and live supervision.</label></div>
        </div>
        <p class="cm-error" data-voice-consent-error role="alert"></p>
        <div class="cm-actions"><button type="button" class="cm-btn cm-btn--secondary" data-voice-consent-cancel>Keep using messages</button><button type="button" class="cm-btn cm-btn--primary" data-voice-consent-save>Enable voice</button></div>
    </dialog>
    @endif
    <script type="application/json" data-voice-config>@json($voiceConfig)</script>
</section>
@once @vite(['resources/js/voice-call.js']) @endonce
@endif
