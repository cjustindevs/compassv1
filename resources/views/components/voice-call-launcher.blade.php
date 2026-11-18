@props(['session'])
{{-- Round call button for the chat top bar, next to the bell. It shows or hides the <x-voice-call> panel. --}}
@if(in_array($session->session_type, ['voice','chat'], true) && $session->isActive())
<button type="button" class="voice-launcher" data-voice-toggle aria-expanded="false" aria-label="Voice call" data-tooltip="Voice call"><x-ui-icon name="phone" /></button>
@endif
