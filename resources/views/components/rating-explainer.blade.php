@props(['helper', 'audience' => 'helper', 'from' => null, 'to' => null])
{{--
 Rating summary: three compact cards (Seeker feedback, Adviser evaluation, Combined rating) with the
 explanation in a collapsible block. QA Nov 24: the numbers follow the page's date range and the same
 evaluations as the dashboard (App\Services\HelperRatingSummary); display only, no calculation change.
--}}
@php
    $summary = app(\App\Services\HelperRatingSummary::class)->for($helper, $from, $to);
    $fmt = fn ($v) => $v === null ? 'N/A' : \App\Support\ScoreFormat::number($v, 2);
    $period = $summary['from'] && $summary['to']
        ? \Illuminate\Support\Carbon::parse($summary['from'])->format('M j').' – '.\Illuminate\Support\Carbon::parse($summary['to'])->format('M j, Y')
        : 'All time';
    $you = $audience === 'helper' ? 'your' : 'this Helper\'s';
@endphp
<section {{ $attributes->merge(['class' => 'rating-explainer']) }} aria-label="Rating summary">
    <div class="re-cards">
        <div class="re-item">
            <span class="re-label">Seeker feedback</span>
            <strong>{{ $fmt($summary['seeker_avg']) }}</strong>
            <small>{{ $summary['seeker_count'] }} {{ \Illuminate\Support\Str::plural('rating', $summary['seeker_count']) }} · {{ $period }}</small>
        </div>
        <div class="re-item">
            <span class="re-label">Adviser evaluation</span>
            <strong>{{ $fmt($summary['adviser_avg']) }}</strong>
            <small>{{ $summary['adviser_count'] }} {{ \Illuminate\Support\Str::plural('evaluation', $summary['adviser_count']) }} · {{ $period }}</small>
        </div>
        <div class="re-item re-total">
            <span class="re-label">Combined rating</span>
            <strong>{{ $fmt($summary['combined']) }}</strong>
            <small>Average of the two sources</small>
        </div>
    </div>
    <x-ui.explainer title="How {{ $audience === 'helper' ? 'your' : 'the' }} ratings work">
        <p>All ratings use the same 1–5 scale (5 is best). Seekers rate each session after it ends; older 1–10 ratings are converted to 1–5. {{ $audience === 'helper' ? 'Your' : 'The' }} Adviser scores five skills from session documentation. Only finalized evaluations are counted.</p>
        <p><strong>Combined rating:</strong> the average of the Seeker and Adviser averages above, or the one that exists.</p>
        <p><strong>Used for matching:</strong> only the latest Adviser evaluation ({{ \App\Support\ScoreFormat::number($summary['latest']) }}) counts, as 30% of {{ $you }} matching score. Workload, specialty, language, availability and experience make up the rest. Seeker feedback is not used for matching.</p>
        <p><strong>Used for growth:</strong> Adviser evaluations set {{ $you }} competency level and training recommendations. Seeker feedback is reviewed by the Adviser during supervision.</p>
    </x-ui.explainer>
</section>
@once
<style>
.rating-explainer { display: grid; gap: 10px; margin: 0 0 16px; }
.re-cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.re-item { display: grid; gap: 2px; align-content: start; background: #fff; border: 1px solid #dfe9e3; border-radius: 12px; padding: 12px 14px; }
.re-item strong { font-size: 20px; color: #31711d; font-variant-numeric: tabular-nums; }
.re-item small { font-size: 12px; color: #64746b; line-height: 1.45; }
.re-total { background: #f3fbf6; }
.re-label { font-size: 13px; font-weight: 600; color: #334155; }
.rating-explainer .ui-explainer { margin: 0; }
.rating-explainer .ui-explainer-body p { margin: 0 0 8px; }
@media (max-width: 760px) { .re-cards { grid-template-columns: 1fr; } }
</style>
@endonce
