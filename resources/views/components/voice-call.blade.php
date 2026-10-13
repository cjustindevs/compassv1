@props(['session'])
@if($session->session_type === 'voice' && $session->isActive())
@php
    $configuration = app(\App\Services\VoiceConfiguration::class);
    $voiceConfig = ['sessionId'=>$session->id, 'userId'=>auth()->id(), 'socket'=>$configuration->publicSocket(),
        'state'=>route('voice.state',$session), 'credentials'=>route('voice.credentials',$session),
        'start'=>route('voice.start',$session), 'action'=>route('voice.action',$session), 'signal'=>route('voice.signal',$session)];
@endphp
<section class="voice-call-panel" data-voice-call aria-label="Live voice call">
    <div class="voice-call-copy"><h2><x-ui-icon name="phone" /> Voice support</h2><p data-voice-status role="status" aria-live="polite">Checking call availability…</p><small>Live audio is not recorded. Leaving a call keeps your support session and text chat open.</small></div>
    <div class="voice-call-actions">
        <button type="button" data-voice-start disabled>Call</button>
        <button type="button" data-voice-accept hidden>Accept call</button>
        <button type="button" data-voice-decline hidden>Decline</button>
        <button type="button" data-voice-mute hidden aria-pressed="false">Mute</button>
        <button type="button" data-voice-leave hidden>Leave call</button>
        <button type="button" data-voice-play hidden>Play audio</button>
    </div>
    <audio data-voice-audio autoplay playsinline></audio>
    <script type="application/json" data-voice-config>@json($voiceConfig)</script>
</section>
@once @vite(['resources/js/voice-call.js']) @endonce
@endif
