<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\Helper;
use App\Models\HelperCompetencyHistory;
use App\Models\IncidentReport;
use App\Models\Referral;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModeratorReportController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->get('from') ?: now()->subMonths(6)->startOfMonth()->toDateString();
        $to = $request->get('to') ?: now()->toDateString();

        $monthly = $this->getMonthlyReport($from, $to);
        $referralOutcomes = $this->getReferralOutcomes($from, $to);
        $competencyGrowth = $this->getCompetencyGrowth($from, $to);
        $incidentLog = IncidentReport::with(['session', 'session.seeker'])
            ->whereBetween(DB::raw('COALESCE(resolved_at, created_at)'), [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->latest()
            ->limit(50)
            ->get();
        $benchmarks = $this->getBenchmarks($from, $to);

        return view('moderator.reports', compact(
            'monthly',
            'referralOutcomes',
            'competencyGrowth',
            'incidentLog',
            'benchmarks',
            'from',
            'to'
        ));
    }

    public function export(Request $request)
    {
        $from = $request->get('from') ?: now()->subMonths(6)->startOfMonth()->toDateString();
        $to = $request->get('to') ?: now()->toDateString();

        $monthly = $this->getMonthlyReport($from, $to);
        $referralOutcomes = $this->getReferralOutcomes($from, $to);
        $benchmarks = $this->getBenchmarks($from, $to);

        $rows = [
            ['COMPASS Operations Report', $from . ' to ' . $to],
            [],
            ['Month', 'Sessions', 'Chat', 'Voice', 'Completed', 'Cancelled'],
        ];

        foreach ($monthly as $m) {
            $rows[] = [$m['month'], $m['total'], $m['chat'], $m['voice'], $m['completed'], $m['cancelled']];
        }

        $rows[] = [];
        $rows[] = ['Referral Status', 'Count'];

        foreach ($referralOutcomes as $status => $count) {
            $rows[] = [ucwords(str_replace('_', ' ', $status)), $count];
        }

        $rows[] = [];
        $rows[] = ['Benchmark', 'Value'];
        $rows[] = ['Avg response time (min)', $benchmarks['avg_response_min']];
        $rows[] = ['Avg session length (min)', $benchmarks['avg_length_min']];
        $rows[] = ['Avg satisfaction rating', $benchmarks['avg_rating']];
        $rows[] = ['Utilization rate (%)', $benchmarks['utilization_pct']];

        $output = fopen('php://temp', 'w');

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="operations-report-' . $from . '_' . $to . '.csv"');
    }

    private function getMonthlyReport(string $from, string $to): array
    {
        $monthKey = \App\Support\DatabaseHelper::monthKey('COALESCE(end_time, created_date)');
        $countFilter = fn (string $cond) => \App\Support\DatabaseHelper::countFilter($cond);

        $rows = Session::selectRaw($monthKey . ' as month')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw($countFilter("session_type = 'chat'") . ' as chat')
            ->selectRaw($countFilter("session_type = 'voice'") . ' as voice')
            ->selectRaw($countFilter("session_status IN ('completed','evaluated')") . ' as completed')
            ->selectRaw($countFilter("session_status IN ('cancelled','no_show')") . ' as cancelled')
            ->whereBetween(DB::raw('COALESCE(end_time, created_date)'), [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->groupBy(DB::raw($monthKey))
            ->orderBy(DB::raw($monthKey))
            ->get();

        return $rows->map(function ($row) {
            $date = \Carbon\Carbon::createFromFormat('Y-m', $row->month);

            return [
                'month' => $date->format('M Y'),
                'total' => $row->total,
                'chat' => $row->chat,
                'voice' => $row->voice,
                'completed' => $row->completed,
                'cancelled' => $row->cancelled,
            ];
        })->toArray();
    }

    private function getReferralOutcomes(string $from, string $to): array
    {
        return Referral::whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }

    private function getCompetencyGrowth(string $from, string $to): array
    {
        return Helper::with('latestCompetency')
            ->get()
            ->map(function (Helper $helper) {
                $history = $helper->competencyHistory()
                    ->orderBy('evaluation_date')
                    ->get();

                $first = $history->first();
                $latest = $history->last();

                return [
                    'helper' => $helper->full_name,
                    'first_score' => $first ? (float) $first->overall_score : null,
                    'latest_score' => $latest ? (float) $latest->overall_score : null,
                    'growth' => $first && $latest
                        ? round((float) $latest->overall_score - (float) $first->overall_score, 1)
                        : 0,
                ];
            })
            ->sortByDesc('growth')
            ->values()
            ->toArray();
    }

    private function getBenchmarks(string $from, string $to): array
    {
        $completed = Session::whereIn('session_status', ['completed', 'evaluated'])
            ->whereBetween(DB::raw('COALESCE(end_time, created_date)'), [$from . ' 00:00:00', $to . ' 23:59:59']);

        $avgLength = (clone $completed)->whereNotNull('duration')->avg('duration');
        $avgRating = \App\Models\HelpSeekerEvaluation::avg('overall_score');

        $avgResponse = \App\Models\QueueRequest::where('request_status', 'assigned')
            ->whereNotNull('matched_date')
            ->whereBetween('matched_date', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('AVG(' . \App\Support\DatabaseHelper::secondsBetween('matched_date', 'request_date') . ') as avg_wait')
            ->first();

        $helpers = Helper::where('status', 'available')->count();
        $totalSlots = max(1, $helpers * 2);
        $inUse = Session::whereIn('session_status', ['active', 'helper_assigned'])->count();

        return [
            'avg_response_min' => $avgResponse && $avgResponse->avg_wait ? round($avgResponse->avg_wait / 60) : 0,
            'avg_length_min' => round((float) ($avgLength ?? 0)),
            'avg_rating' => round((float) ($avgRating ?? 0), 2),
            'utilization_pct' => round(min(100, ($inUse / $totalSlots) * 100)),
            'sessions_total' => $completed->count(),
        ];
    }
}