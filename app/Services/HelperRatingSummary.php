<?php

namespace App\Services;

use App\Models\Helper;
use App\Models\HelpSeekerEvaluation;
use Carbon\CarbonImmutable;

/**
 * The three numbers in "How your ratings work" (Seeker feedback, Adviser evaluation, Combined).
 *
 * Same population as the dashboard: Adviser evaluations come from CompetencyEvidence (finalized,
 * current rubric, reviewed report, real completed session); Seeker feedback excludes test and
 * duplicate sessions. With a date range, both sources follow it (Philippine Time days, inclusive).
 */
class HelperRatingSummary
{
    /** @return array{seeker_avg: ?float, seeker_count: int, adviser_avg: ?float, adviser_count: int, combined: ?float, latest: ?float, from: ?string, to: ?string} */
    public function for(Helper $helper, ?string $from = null, ?string $to = null): array
    {
        $period = $from && $to
            ? new AnalyticsPeriod(CarbonImmutable::parse($from, 'Asia/Manila')->startOfDay(), CarbonImmutable::parse($to, 'Asia/Manila')->addDay()->startOfDay())
            : null;

        $seeker = HelpSeekerEvaluation::query()
            ->whereHas('session', fn ($q) => $q->where('helper_id', $helper->id)->where('is_test_record', false)->whereNull('duplicate_of_id'))
            ->when($period, fn ($q) => $q->whereRaw('COALESCE(submitted_at, created_at) >= ?', [$period->start->utc()])
                ->whereRaw('COALESCE(submitted_at, created_at) < ?', [$period->end->utc()]))
            ->pluck('overall_score')
            ->map(fn ($score) => AdviserAnalytics::rating($score === null ? null : (float) $score))
            ->filter(fn ($score) => $score !== null);

        $adviser = app(CompetencyEvidence::class)->evaluations(collect([$helper->id]), $period)
            ->map(fn ($evaluation) => (float) $evaluation->normalized_score)
            ->filter(fn ($score) => $score > 0);

        $seekerAvg = $seeker->isNotEmpty() ? round($seeker->avg(), 2) : null;
        $adviserAvg = $adviser->isNotEmpty() ? round($adviser->avg(), 2) : null;
        $parts = array_values(array_filter([$seekerAvg, $adviserAvg], fn ($value) => $value !== null));
        $latest = $helper->competency_score !== null && (float) $helper->competency_score > 0 ? round((float) $helper->competency_score, 1) : null;

        return [
            'seeker_avg' => $seekerAvg,
            'seeker_count' => $seeker->count(),
            'adviser_avg' => $adviserAvg,
            'adviser_count' => $adviser->count(),
            'combined' => $parts ? round(array_sum($parts) / count($parts), 2) : null,
            'latest' => $latest,
            'from' => $period ? $from : null,
            'to' => $period ? $to : null,
        ];
    }
}
