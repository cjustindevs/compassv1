@props(['current' => 3, 'compact' => false])
{{-- QA: "paano ang sequence ng documentation completion / evaluate ng adviser". One shared sequence. --}}
@php
    $steps = [
        ['Session ends', 'The Helper ends the chat or voice session.'],
        ['Helper documents', 'Summary and reflection are submitted, ideally within 24 hours.'],
        ['You evaluate', 'Review the documentation and score the five skills (1–5).'],
        ['Feedback shared', 'The Helper sees your feedback; their competency score updates.'],
    ];
@endphp
<ol {{ $attributes->merge(['class' => 'eval-steps'.($compact ? ' is-compact' : '')]) }} aria-label="Evaluation sequence">
    @foreach($steps as $i => [$title, $detail])
        @php($n = $i + 1)
        <li class="{{ $n < $current ? 'is-done' : ($n === $current ? 'is-current' : '') }}" @if($n === $current) aria-current="step" @endif>
            <span class="es-num">{{ $n }}</span>
            <span class="es-text"><strong>{{ $title }}</strong>@unless($compact)<small>{{ $detail }}</small>@endunless</span>
        </li>
    @endforeach
</ol>
@once
<style>
.eval-steps { list-style: none; margin: 0 0 16px; padding: 0; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
.eval-steps li { display: flex; gap: 10px; align-items: flex-start; background: #fff; border: 1px solid #e2e8e4; border-radius: 12px; padding: 10px 12px; }
.eval-steps .es-num { flex: none; width: 24px; height: 24px; border-radius: 50%; display: grid; place-items: center; font-size: 12px; font-weight: 800; background: #f1f4f2; color: #64746b; }
.eval-steps .es-text { display: grid; gap: 2px; font-size: 13px; }
.eval-steps strong { color: #163b2d; font-weight: 700; }
.eval-steps small { color: #64746b; font-size: 12px; line-height: 1.4; }
.eval-steps li.is-done .es-num { background: #0ca30c; color: #fff; }
.eval-steps li.is-current { border-color: #04a052; background: #eaf8f0; }
.eval-steps li.is-current .es-num { background: #04a052; color: #fff; }
.eval-steps.is-compact { margin: 8px 0 0; gap: 4px; }
.eval-steps.is-compact li { padding: 4px 8px; border-radius: 99px; align-items: center; }
.eval-steps.is-compact .es-num { width: 18px; height: 18px; font-size: 10px; }
.eval-steps.is-compact strong { font-size: 11.5px; font-weight: 600; }
@media (max-width: 860px) { .eval-steps { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
@endonce
