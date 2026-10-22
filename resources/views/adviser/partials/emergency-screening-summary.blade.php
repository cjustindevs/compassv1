<details data-focus-panel data-focus-label="Original screening" class="cm-card" aria-labelledby="emergencyScreeningTitle">
    <summary id="emergencyScreeningTitle">Original pre-session screening</summary>
    <p class="cm-help">Read-only Seeker responses. Operational emergency priority does not replace the original classification.</p>
    @php($originalScreening = $alert->session?->screeningResponses->sortBy('id')->first())
    <dl class="er-facts">
        <div><dt>Concern</dt><dd>{{ $alert->session?->concern?->concern_name ?? 'Not specified' }}</dd></div>
        <div><dt>Saved classification</dt><dd>{{ ucfirst($originalScreening?->risk_level ?? 'Unclassified') }}</dd></div>
        <div><dt>Screened</dt><dd>{{ $originalScreening?->created_at?->timezone('Asia/Manila')->format('M d, Y g:i A') ?? 'Not recorded' }}</dd></div>
    </dl>
    @forelse(($originalScreening?->responses ?? []) as $key => $answer)
        <p><strong>{{ $key === 'suicidal_thoughts' && $originalScreening->instrument_version === \App\Services\CompactScreening::SINGLE_VERSION ? \App\Services\CompactScreening::SINGLE_QUESTION : (\App\Services\ScreeningInstrument::QUESTIONS[$key] ?? ucfirst(str_replace('_', ' ', $key))) }}</strong><br>
        {{ is_bool($answer) ? ($answer ? 'Yes' : 'No') : (is_scalar($answer) ? ucfirst(str_replace('_', ' ', (string) $answer)) : 'Not provided') }}</p>
    @empty
        <p class="cm-help">No pre-session screening was recorded for this case.</p>
    @endforelse
    @if($originalScreening?->follow_up_answered_at)
        <p><strong>Seeker follow-up response</strong><br>{{ ucfirst(str_replace('_', ' ', $originalScreening->follow_up_answer)) }}</p>
    @endif
</details>
