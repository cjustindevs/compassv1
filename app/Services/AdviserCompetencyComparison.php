<?php

namespace App\Services;

use App\Models\{Helper, HelperCompetencyHistory, TrainingRecommendation};
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class AdviserCompetencyComparison
{
    /** Current, documented results only; correction snapshots are never separate samples. */
    public function evaluations(int $adviserId, ?AnalyticsPeriod $period = null): Collection
    {
        return app(CompetencyEvidence::class)->evaluations(Helper::where('adviser_id', $adviserId)->pluck('id'), $period);
    }

    public function forAdviser(int $adviserId, AnalyticsPeriod $period, Request $request): array
    {
        $options = ['overall' => 'Overall competency'] + collect(CompetencyRubric::CRITERIA)->map(fn ($c) => $c[0])->all();
        $input = $request->validate([
            'competency_skill' => ['nullable', Rule::in(array_keys($options))],
            'competency_sort' => 'nullable|in:lowest,highest,name',
            'competency_page' => 'nullable|integer|min:1',
        ]);
        $skill = $input['competency_skill'] ?? 'overall';
        $sort = $input['competency_sort'] ?? 'lowest';
        $helpers = Helper::where('adviser_id', $adviserId)->get(['id', 'first_name', 'last_name']);
        $evaluations = $this->evaluations($adviserId, $period)->groupBy('helper_id');
        $training = TrainingRecommendation::whereIn('helper_id', $helpers->pluck('id'))
            ->whereIn('status', ['assigned', 'in_progress', 'completed'])->get()->groupBy('helper_id');
        $rows = $helpers->map(function ($helper) use ($evaluations, $training, $skill) {
            $records = $evaluations->get($helper->id, collect());
            $scores = collect(CompetencyRubric::CRITERIA)->map(fn ($c) => $records->isEmpty() ? null : round($records->avg($c[2]), 2));
            $overall = $records->isEmpty() ? null : round($records->avg('overall_score'), 2);
            $weakest = $records->isEmpty() ? null : $scores->sort()->keys()->first();
            $criterion = $skill === 'overall' ? $weakest : $skill;
            // Link the proposed activity to an actual low-scoring evaluation, not just an average.
            $evidence = $criterion ? $records->sortBy(CompetencyRubric::CRITERIA[$criterion][2], SORT_NUMERIC)->first() : null;
            $ongoing = $training->get($helper->id, collect());
            return [
                'helper' => $helper, 'score' => $skill === 'overall' ? $overall : $scores[$skill],
                'overall' => $overall, 'scores' => $scores, 'weakest' => $weakest,
                'count' => $records->count(), 'latest' => $records->first()?->evaluation_date,
                'needs_attention' => $overall !== null && ($overall < 2.5 || $scores->min() < 2.5),
                'evidence' => $records, 'criterion' => $criterion, 'training_evaluation' => $evidence,
                'training' => $ongoing, 'has_training' => $ongoing->contains('criterion', $criterion),
            ];
        });
        $ordered = $rows->sort(function ($a, $b) use ($sort) {
            if ($sort !== 'name') {
                if (($a['score'] === null) !== ($b['score'] === null)) return $a['score'] === null ? 1 : -1;
                $difference = ($a['score'] ?? 0) <=> ($b['score'] ?? 0);
                if ($difference) return $sort === 'highest' ? -$difference : $difference;
            }
            return strnatcasecmp($a['helper']->full_name, $b['helper']->full_name) ?: $a['helper']->id <=> $b['helper']->id;
        })->values();
        $page = min((int) ($input['competency_page'] ?? 1), max(1, (int) ceil($ordered->count() / 8)));
        $paginator = new LengthAwarePaginator($ordered->forPage($page, 8)->values(), $ordered->count(), 8, $page,
            ['path' => route('adviser.dashboard'), 'pageName' => 'competency_page', 'fragment' => 'competency-comparison']);
        $paginator->appends($request->except('competency_page'));
        $legacyCount = HelperCompetencyHistory::whereIn('helper_id', $helpers->pluck('id'))
            ->where(fn ($q) => $q->whereNull('rubric_version')->orWhere('rubric_version', '!=', CompetencyRubric::VERSION))
            ->where('evaluation_date', '>=', $period->start->utc())->where('evaluation_date', '<', $period->end->utc())
            ->where('evaluation_date', '<=', now())->count();

        return ['rows' => $paginator, 'options' => $options, 'skill' => $skill, 'sort' => $sort,
            'filters' => ['competency_skill' => $skill, 'competency_sort' => $sort], 'dates' => $period->dates(),
            'evaluated' => $rows->whereNotNull('score')->count(), 'not_evaluated' => $rows->whereNull('score')->count(),
            'needs_attention' => $rows->where('needs_attention', true)->count(), 'legacy_count' => $legacyCount];
    }
}
