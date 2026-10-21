<?php
namespace App\Services;

use App\Models\HelperCompetencyHistory;
use Illuminate\Support\Collection;

/** Same finalized, evidenced rubric population for KPI, comparison, and reports. */
class CompetencyEvidence
{
    public function evaluations(Collection $helperIds, ?AnalyticsPeriod $period = null, ?Collection $reportIds = null): Collection
    {
        return HelperCompetencyHistory::with('helper:id,first_name,last_name')
            ->whereIn('helper_id', $helperIds)
            ->where('rubric_version', CompetencyRubric::VERSION)
            ->where('evaluation_date', '<=', now())
            ->when($period, fn ($q) => $q->where('evaluation_date', '>=', $period->start->utc())
                ->where('evaluation_date', '<', $period->end->utc()))
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('session_reports')
                ->join('counseling_sessions', 'counseling_sessions.id', '=', 'session_reports.session_id')
                ->whereColumn('session_reports.id', 'helper_competency_history.report_id')
                ->whereColumn('counseling_sessions.helper_id', 'helper_competency_history.helper_id')
                ->where('session_reports.adviser_reviewed', true)
                ->where('counseling_sessions.is_test_record', false)->whereNull('counseling_sessions.duplicate_of_id')
                ->whereIn('counseling_sessions.session_status', ['completed', 'evaluated']))
            ->when($reportIds !== null, fn ($q) => $q->whereIn('report_id', $reportIds))
            ->orderByDesc('evaluation_date')->orderByDesc('id')->get()
            ->filter(function ($evaluation) {
                if ((int) ($evaluation->evidence['session_report_id'] ?? 0) !== (int) $evaluation->report_id) return false;
                $scores = [];
                foreach (CompetencyRubric::CRITERIA as $key => [, , $field]) {
                    $value = $evaluation->$field;
                    if ($value === null || $value < 1 || $value > 5 || floor($value) !== $value) return false;
                    $scores[$key] = (int) $value;
                }
                return abs(CompetencyRubric::score($scores) - $evaluation->overall_score) < 0.011;
            })->unique('report_id')->values();
    }

}
