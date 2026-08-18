<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\Helper;
use App\Models\HelperCompetencyHistory;
use App\Models\HelpSeekerEvaluation;
use App\Models\IncidentReport;
use App\Models\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModeratorAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->get('from') ?: now()->subMonths(6)->startOfMonth()->toDateString();
        $to = $request->get('to') ?: now()->toDateString();

        $metrics = $this->getMetrics($from, $to);
        $riskDistribution = $this->getRiskDistribution($from, $to);
        $competencyTrajectory = $this->getCompetencyTrajectory($from, $to);
        $radar = $this->getSkillRadar();
        $workload = $this->getWorkloadDistribution();

        return view('moderator.analytics', compact(
            'metrics',
            'riskDistribution',
            'competencyTrajectory',
            'radar',
            'workload',
            'from',
            'to'
        ));
    }

    public function data(Request $request): JsonResponse
    {
        $from = $request->get('from') ?: now()->subMonths(6)->startOfMonth()->toDateString();
        $to = $request->get('to') ?: now()->toDateString();

        return response()->json([
            'metrics' => $this->getMetrics($from, $to),
            'risk_distribution' => $this->getRiskDistribution($from, $to),
            'competency_trajectory' => $this->getCompetencyTrajectory($from, $to),
            'radar' => $this->getSkillRadar(),
            'workload' => $this->getWorkloadDistribution(),
        ]);
    }

    public function export(Request $request)
    {
        $from = $request->get('from') ?: now()->subMonths(6)->startOfMonth()->toDateString();
        $to = $request->get('to') ?: now()->toDateString();

        $metrics = $this->getMetrics($from, $to);
        $risk = $this->getRiskDistribution($from, $to);
        $workload = $this->getWorkloadDistribution();

        $rows = [
            ['Analytics Export', $from . ' to ' . $to],
            [],
            ['Metric', 'Value'],
            ['Sessions completed', $metrics['sessions_completed']],
            ['Avg session length (min)', $metrics['avg_length']],
            ['Avg rating (/5)', $metrics['avg_rating']],
            ['Avg competency growth (pts)', $metrics['competency_growth']],
            ['Active helpers', $metrics['active_helpers']],
            [],
            ['Risk Level', 'Sessions'],
            ...collect($risk)->map(fn ($v, $k) => [ucfirst($k), $v])->values()->toArray(),
            [],
            ['Helper', 'Active Sessions'],
            ...$workload,
        ];

        return $this->csvResponse('analytics-' . $from . '_' . $to . '.csv', $rows);
    }

    private function getMetrics(string $from, string $to): array
    {
        $completed = Session::whereIn('session_status', ['completed', 'evaluated'])
            ->whereBetween(DB::raw('COALESCE(end_time, created_date)'), [$from . ' 00:00:00', $to . ' 23:59:59']);

        $avgLength = Session::whereIn('session_status', ['completed', 'evaluated'])
            ->whereNotNull('duration')
            ->whereBetween(DB::raw('COALESCE(end_time, created_date)'), [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->avg('duration');

        $avgRating = HelpSeekerEvaluation::whereHas('session', fn ($q) => $q->whereBetween(DB::raw('COALESCE(counseling_sessions.end_time, counseling_sessions.created_date)'), [$from . ' 00:00:00', $to . ' 23:59:59']))
            ->avg('overall_score');

        $firstPeriod = HelperCompetencyHistory::whereBetween('evaluation_date', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('helper_id, MIN(evaluation_date) as d')
            ->groupBy('helper_id')
            ->get();

        $avgGrowth = 0;
        $growthCount = 0;

        foreach ($firstPeriod as $entry) {
            $first = HelperCompetencyHistory::where('helper_id', $entry->helper_id)->orderBy('evaluation_date')->first();
            $latest = HelperCompetencyHistory::where('helper_id', $entry->helper_id)->orderByDesc('evaluation_date')->first();

            if ($first && $latest && $first->id !== $latest->id) {
                $avgGrowth += (float) $latest->overall_score - (float) $first->overall_score;
                $growthCount++;
            }
        }

        return [
            'sessions_completed' => $completed->count(),
            'avg_length' => round((float) ($avgLength ?? 0)),
            'avg_rating' => round((float) ($avgRating ?? 0), 2),
            'competency_growth' => $growthCount ? round($avgGrowth / $growthCount, 1) : 0,
            'active_helpers' => Helper::where('status', 'available')->count(),
            'queue_served' => \App\Models\QueueRequest::where('request_status', 'assigned')
                ->whereBetween('matched_date', [$from . ' 00:00:00', $to . ' 23:59:59'])
                ->count(),
        ];
    }

    private function getRiskDistribution(string $from, string $to): array
    {
        $rows = Session::selectRaw('risk_level, COUNT(*) as total')
            ->whereBetween(DB::raw('COALESCE(end_time, created_date)'), [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->groupBy('risk_level')
            ->pluck('total', 'risk_level')
            ->toArray();

        return [
            'low' => $rows['low'] ?? 0,
            'moderate' => $rows['moderate'] ?? 0,
            'high' => $rows['high'] ?? 0,
            'emergency' => $rows['emergency'] ?? 0,
        ];
    }

    private function getCompetencyTrajectory(string $from, string $to): array
    {
        $rows = HelperCompetencyHistory::selectRaw("TO_CHAR(evaluation_date, 'YYYY-MM') as month, AVG(overall_score) as avg_score")
            ->whereNotNull('overall_score')
            ->whereBetween('evaluation_date', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->groupBy(DB::raw("TO_CHAR(evaluation_date, 'YYYY-MM')"))
            ->orderBy(DB::raw("TO_CHAR(evaluation_date, 'YYYY-MM')"))
            ->get();

        $labels = [];
        $values = [];

        foreach ($rows as $row) {
            $labels[] = \Carbon\Carbon::createFromFormat('Y-m', $row->month)->format('M Y');
            $values[] = round((float) $row->avg_score, 1);
        }

        return compact('labels', 'values');
    }

    private function getSkillRadar(): array
    {
        $row = HelperCompetencyHistory::selectRaw('AVG(active_listening_score) as a, AVG(empathy_score) as b, AVG(respect_score) as c, AVG(ethical_practices_score) as d, AVG(referral_accuracy_score) as e')
            ->whereNotNull('overall_score')
            ->first();

        return [
            'labels' => ['Active Listening', 'Empathy', 'Respect & Professionalism', 'Ethical Practices', 'Referral Accuracy'],
            'values' => $row ? [
                round((float) $row->a, 1),
                round((float) $row->b, 1),
                round((float) $row->c, 1),
                round((float) $row->d, 1),
                round((float) $row->e, 1),
            ] : [0, 0, 0, 0, 0],
        ];
    }

    private function getWorkloadDistribution(): array
    {
        return Session::with('helper')
            ->whereIn('session_status', ['active', 'helper_assigned'])
            ->get()
            ->groupBy('helper_id')
            ->map(function ($sessions) {
                $helper = $sessions->first()->helper;

                return [
                    'helper' => $helper?->full_name ?? 'Unassigned',
                    'count' => $sessions->count(),
                ];
            })
            ->values()
            ->toArray();
    }

    private function csvResponse(string $filename, array $rows)
    {
        $output = fopen('php://temp', 'w');

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}