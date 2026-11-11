@props(['helper', 'audience' => 'helper'])
{{--
    "How your ratings work" (QA: Helpers and Advisers could not tell where ratings come from,
    how they are averaged, or where they are used). Display only: no calculation changes.
--}}
@php
    $seekerScores = \App\Models\HelpSeekerEvaluation::whereHas('session', fn ($q) => $q->where('helper_id', $helper->id))
        ->pluck('overall_score')
        ->map(fn ($s) => \App\Services\AdviserAnalytics::rating($s === null ? null : (float) $s))
        ->filter(fn ($s) => $s !== null);
    $adviserScores = \App\Models\HelperCompetencyHistory::where('helper_id', $helper->id)->whereNotNull('overall_score')->get()
        ->map(fn ($e) => $e->normalized_score)->filter(fn ($s) => $s !== null && $s > 0);
    $seekerAvg = $seekerScores->isNotEmpty() ? round($seekerScores->avg(), 2) : null;
    $adviserAvg = $adviserScores->isNotEmpty() ? round($adviserScores->avg(), 2) : null;
    $parts = array_values(array_filter([$seekerAvg, $adviserAvg], fn ($v) => $v !== null));
    $combined = $parts ? round(array_sum($parts) / count($parts), 2) : null;
    $latest = $helper->competency_score !== null && (float) $helper->competency_score > 0 ? round((float) $helper->competency_score, 1) : null;
    $you = $audience === 'helper' ? 'your' : 'this Helper\'s';
    $fmt = fn ($v) => \App\Support\ScoreFormat::number($v, 2);
@endphp
<section {{ $attributes->merge(['class' => 'rating-explainer']) }} aria-labelledby="ratingExplainerTitle">
    <header>
        <h3 id="ratingExplainerTitle">How {{ $audience === 'helper' ? 'your' : 'the' }} ratings work</h3>
        <p>All ratings use the same 1–5 scale (5 is best).</p>
    </header>
    <div class="re-grid">
        <div class="re-item">
            <span class="re-label">Seeker feedback</span>
            <strong>{{ $fmt($seekerAvg) }}</strong>
            <small>{{ $seekerScores->count() }} {{ \Illuminate\Support\Str::plural('rating', $seekerScores->count()) }} · Seekers rate each session after it ends. Older 1–10 ratings are converted to 1–5.</small>
        </div>
        <div class="re-op" aria-hidden="true">+</div>
        <div class="re-item">
            <span class="re-label">Adviser evaluation</span>
            <strong>{{ $fmt($adviserAvg) }}</strong>
            <small>{{ $adviserScores->count() }} {{ \Illuminate\Support\Str::plural('evaluation', $adviserScores->count()) }} · Your Adviser scores five skills from session documentation.</small>
        </div>
        <div class="re-op" aria-hidden="true">=</div>
        <div class="re-item re-total">
            <span class="re-label">Combined rating</span>
            <strong>{{ $fmt($combined) }}</strong>
            <small>Average of the two sources above (or the one that exists).</small>
        </div>
    </div>
    <dl class="re-uses">
        <div><dt>Used for matching</dt><dd>Only the <strong>latest Adviser evaluation</strong> ({{ \App\Support\ScoreFormat::number($latest) }}) counts, as 30% of {{ $you }} matching score. Workload, specialty, language, availability and experience make up the rest. Seeker feedback is not used for matching.</dd></div>
        <div><dt>Used for growth</dt><dd>Adviser evaluations set {{ $you }} competency level and training recommendations. Seeker feedback is reviewed by the Adviser during supervision.</dd></div>
    </dl>
</section>
@once
<style>
.rating-explainer { background: #fff; border: 1px solid #dfe9e3; border-radius: 14px; padding: 16px 18px; display: grid; gap: 12px; margin: 14px 0; }
.rating-explainer header h3 { margin: 0; font-size: 14px; font-weight: 700; color: #163b2d; }
.rating-explainer header p { margin: 2px 0 0; font-size: 13px; color: #64746b; }
.re-grid { display: grid; grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr) auto minmax(0, 1fr); gap: 10px; align-items: stretch; }
.re-item { display: grid; gap: 2px; align-content: start; background: #f6faf7; border-radius: 10px; padding: 10px 12px; }
.re-item strong { font-size: 20px; color: #163b2d; font-variant-numeric: tabular-nums; }
.re-item small { font-size: 12px; color: #64746b; line-height: 1.45; }
.re-total { background: #eaf8f0; }
.re-label { font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #087341; }
.re-op { align-self: center; font-size: 20px; font-weight: 700; color: #94a39a; }
.re-uses { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 20px; margin: 0; }
.re-uses dt { font-size: 12px; font-weight: 700; color: #163b2d; }
.re-uses dd { margin: 2px 0 0; font-size: 13px; color: #4b5b52; line-height: 1.5; }
@media (max-width: 860px) { .re-grid { grid-template-columns: 1fr; } .re-op { display: none; } .re-uses { grid-template-columns: 1fr; } }
</style>
@endonce
