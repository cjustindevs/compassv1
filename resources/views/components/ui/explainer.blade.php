@props(['title' => 'How this works', 'open' => false])
{{-- One collapsible explanation (QA: move lengthy descriptions into a collapsible section). --}}
<details class="ui-explainer" {{ $attributes }} @if($open) open @endif>
    <summary><x-ui-icon name="info" /> {{ $title }}</summary>
    <div class="ui-explainer-body">{{ $slot }}</div>
</details>
