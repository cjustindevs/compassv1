<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Models\Helper;
use App\Models\HelperCompetencyHistory;
use App\Models\Referral;
use App\Models\HelpSeekerEvaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdviserReportController extends Controller
{
    /**
     * Show reports and analytics page
     */
    public function index(Request $request)
    {
        $this->validateFilters($request);
        // Get filter parameters (custom date range takes precedence over presets)
        $period = $request->get('period', 'monthly');
        $helperId = $request->get('helper_id');
        $fromDate = $request->get('from');
        $toDate = $request->get('to');

        if ($fromDate && $toDate) {
            $startDate = \Illuminate\Support\Carbon::parse($fromDate)->startOfDay();
            $endDate = \Illuminate\Support\Carbon::parse($toDate)->endOfDay();
        } else {
            [$startDate, $endDate] = $this->getDateRange($period);
        }

        $helperIds = Helper::where('adviser_id', Auth::user()->adviser?->id)->pluck('id');
        $selectedHelperId = $helperId && $helperIds->contains((int) $helperId) ? (int) $helperId : null;

        // Base queries
        $sessionsQuery = Session::whereIn('helper_id', $helperIds)->whereBetween('created_at', [$startDate, $endDate]);
        $completedQuery = (clone $sessionsQuery)->whereIn('session_status', ['completed', 'evaluated']);
        $activeQuery = (clone $sessionsQuery)->where('session_status', 'active');

        // Filter by helper if specified
        if ($selectedHelperId) {
            $sessionsQuery->where('helper_id', $selectedHelperId);
            $completedQuery->where('helper_id', $selectedHelperId);
            $activeQuery->where('helper_id', $selectedHelperId);
        }

        // Statistics
        $totalSessions = $sessionsQuery->count();
        $completedSessions = $completedQuery->count();
        $activeSessions = $activeQuery->count();
        $completionRate = $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100) : 0;
        $riskDistribution = (clone $sessionsQuery)->select('risk_level', DB::raw('COUNT(*) as total'))->groupBy('risk_level')->pluck('total', 'risk_level');

        // Average response time (time from request to first message)
        $avgResponseTime = $this->calculateAverageResponseTime($startDate, $endDate, $helperIds, $selectedHelperId);

        // Average waiting time (time from queue to session start)
        $avgWaitingTime = $this->calculateAverageWaitingTime($startDate, $endDate, $helperIds, $selectedHelperId);

        // Referral statistics
        $referralStats = $this->getReferralStats($startDate, $endDate, $helperIds, $selectedHelperId);

        // Competency trends
        $competencyTrends = $this->getCompetencyTrends($helperIds, $selectedHelperId);

        // Monthly session trends (real data, last 6 months within the range)
        $monthlyTrends = $this->getMonthlyTrends($startDate, $endDate, $helperIds, $selectedHelperId);

        // Satisfaction scores
        $satisfactionScores = $this->getSatisfactionScores($startDate, $endDate, $helperIds, $selectedHelperId);

        // Helper performance ranking
        $helperRanking = $this->getHelperRanking($startDate, $endDate, $helperIds);

        // Get helpers list for filter
        $helpers = Helper::with('user')->where('adviser_id', Auth::user()->adviser?->id)->get();
        $helperId = $selectedHelperId;

        return view('adviser.reports', compact(
            'totalSessions',
            'completedSessions',
            'activeSessions',
            'completionRate',
            'riskDistribution',
            'avgResponseTime',
            'avgWaitingTime',
            'referralStats',
            'competencyTrends',
            'monthlyTrends',
            'satisfactionScores',
            'helperRanking',
            'helpers',
            'period',
            'helperId',
            'selectedHelperId',
            'fromDate',
            'toDate'
        ));
    }

    /**
     * Export report as CSV
     */
    public function export(Request $request)
    {
        $this->validateFilters($request);
        $period = $request->get('period', 'monthly');
        $helperId = $request->get('helper_id');
        $fromDate = $request->get('from');
        $toDate = $request->get('to');

        if ($fromDate && $toDate) {
            $startDate = \Illuminate\Support\Carbon::parse($fromDate)->startOfDay();
            $endDate = \Illuminate\Support\Carbon::parse($toDate)->endOfDay();
        } else {
            [$startDate, $endDate] = $this->getDateRange($period);
        }
        $helperIds = Helper::where('adviser_id', Auth::user()->adviser?->id)->pluck('id');
        $selectedHelperId = $helperId && $helperIds->contains((int) $helperId) ? (int) $helperId : null;

        if ($request->input('format', 'pdf') === 'pdf') {
            $sessions = Session::with(['helper', 'concern', 'evaluation'])
                ->whereIn('helper_id', $helperIds)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->when($selectedHelperId, fn ($query) => $query->where('helper_id', $selectedHelperId))
                ->orderBy('created_at')->get();
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('adviser.report-export', compact('sessions', 'startDate', 'endDate'));
            $pdf->render();
            $pdf->getDomPDF()->getCanvas()->page_text(490, 810, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 8);
            return $pdf->download('compass-report-'.now()->format('Y-m-d').'.pdf');
        }

        $filename = 'compass-report-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($helperIds, $startDate, $endDate, $selectedHelperId) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Session ID', 'Seeker', 'Helper', 'Concern', 'Type',
                'Risk Level', 'Status', 'Created', 'Duration (min)', 'Rating',
            ]);

            $query = Session::with(['seeker:id,generated_alias', 'helper:id,first_name,last_name', 'concern:id,concern_name', 'evaluation:session_id,overall_score'])
                ->whereIn('helper_id', $helperIds)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'desc');

            if ($selectedHelperId) {
                $query->where('helper_id', $selectedHelperId);
            }

            $query->chunk(200, function ($sessions) use ($file) {
                foreach ($sessions as $session) {
                    fputcsv($file, [
                        $session->id,
                        $session->seeker->generated_alias ?? 'Anonymous',
                        $session->helper ? $session->helper->first_name . ' ' . $session->helper->last_name : 'N/A',
                        $session->concern->concern_name ?? 'General',
                        $session->session_type ?? 'Chat',
                        $session->risk_level ?? 'Low',
                        $session->session_status ?? 'Unknown',
                        $session->created_at->format('Y-m-d H:i'),
                        $session->duration ?? 'N/A',
                        $session->evaluation?->overall_score ?? 'N/A',
                    ]);
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get date range based on period
     */
    private function validateFilters(Request $request): void
    {
        $request->validate([
            'period' => 'nullable|in:weekly,monthly,quarterly,yearly',
            'from' => 'nullable|required_with:to|date',
            'to' => 'nullable|required_with:from|date|after_or_equal:from',
            'helper_id' => 'nullable|integer',
            'format' => 'nullable|in:pdf,csv',
        ]);
    }

    private function getDateRange($period)
    {
        $endDate = now();

        switch ($period) {
            case 'weekly':
                $startDate = now()->subDays(7);
                break;
            case 'monthly':
                $startDate = now()->subDays(30);
                break;
            case 'quarterly':
                $startDate = now()->subDays(90);
                break;
            case 'yearly':
                $startDate = now()->subDays(365);
                break;
            default:
                $startDate = now()->subDays(30);
        }

        return [$startDate, $endDate];
    }

    /**
     * Calculate average response time (time from request to first helper message).
     */
    private function calculateAverageResponseTime($startDate, $endDate, $helperIds, $helperId)
    {
        $query = \App\Models\Message::join('counseling_sessions', 'messages.session_id', '=', 'counseling_sessions.id')
            ->whereIn('counseling_sessions.helper_id', $helperIds)
            ->whereBetween('counseling_sessions.created_at', [$startDate, $endDate])
            ->where('messages.created_at', '>=', DB::raw('counseling_sessions.created_date'));

        if ($helperId) {
            $query->where('counseling_sessions.helper_id', $helperId);
        }

        $avg = $query->selectRaw(
            'AVG(' . \App\Support\DatabaseHelper::secondsBetween('messages.created_at', 'counseling_sessions.created_date') . ' / 60) as avg_min'
        )->value('avg_min');

        return $avg !== null ? round((float) $avg, 1) . 'm' : '—';
    }

    /**
     * Calculate average waiting time (time from request to session start).
     */
    private function calculateAverageWaitingTime($startDate, $endDate, $helperIds, $helperId)
    {
        $query = Session::whereIn('helper_id', $helperIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('start_time')
            ->where('start_time', '>=', DB::raw('created_date'));

        if ($helperId) {
            $query->where('helper_id', $helperId);
        }

        $avg = $query->selectRaw(
            'AVG(' . \App\Support\DatabaseHelper::secondsBetween('start_time', 'created_date') . ' / 60) as avg_min'
        )->value('avg_min');

        return $avg !== null ? round((float) $avg, 1) . 'm' : '—';
    }

    /**
     * Get referral statistics
     */
    private function getReferralStats($startDate, $endDate, $helperIds, $helperId)
    {
        $base = Referral::whereIn('helper_id', $helperIds)
            ->when($helperId, fn ($query) => $query->where('helper_id', $helperId))
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalReferrals = (clone $base)->count();

        $approved = (clone $base)->whereIn('status', [Referral::STATUS_ACCEPTED, Referral::STATUS_COMPLETED, Referral::STATUS_CLOSED])->count();

        $pending = (clone $base)->where('status', Referral::STATUS_PENDING_ADVISER)->count();

        $declined = (clone $base)->where('status', Referral::STATUS_DECLINED)->count();

        $acceptanceRate = $totalReferrals > 0 ? round(($approved / ($approved + $declined)) * 100) : 0;

        return [
            'total' => $totalReferrals,
            'approved' => $approved,
            'pending' => $pending,
            'declined' => $declined,
            'acceptance_rate' => $acceptanceRate
        ];
    }

    /**
     * Get competency trends
     */
    private function getCompetencyTrends($helperIds, $helperId)
    {
        $query = HelperCompetencyHistory::with('helper')->whereIn('helper_id', $helperIds);

        if ($helperId) {
            $query->where('helper_id', $helperId);
        }

        $trends = $query->select(
            DB::raw(\App\Support\DatabaseHelper::monthStart('evaluation_date') . ' as month'),
            DB::raw('AVG(overall_score) as avg_score')
        )
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->limit(6)
            ->get();

        return $trends->map(function ($item) {
            return [
                'month' => \Illuminate\Support\Carbon::parse($item->month)->format('M Y'),
                'score' => round($item->avg_score, 2)
            ];
        });
    }

    /**
     * Get monthly trends from real session data — batched into fewer queries.
     */
    private function getMonthlyTrends($startDate, $endDate, $helperIds, $helperId)
    {
        $monthStart = $startDate->copy()->startOfMonth();

        $sessionsByMonth = Session::whereIn('helper_id', $helperIds)
            ->where('created_at', '>=', $monthStart)
            ->where('created_at', '<=', $endDate)
            ->when($helperId, fn ($q) => $q->where('helper_id', $helperId))
            ->selectRaw(
                \App\Support\DatabaseHelper::monthStart('created_at') . ' as month, '
                . 'COUNT(*) as total, '
                . "SUM(CASE WHEN session_status IN ('completed','evaluated') THEN 1 ELSE 0 END) as completed"
            )
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $satisfactionByMonth = HelpSeekerEvaluation::selectRaw(
            \App\Support\DatabaseHelper::monthStart('counseling_sessions.created_at') . ' as month, '
            . 'AVG(overall_score) as avg_score'
        )
            ->join('counseling_sessions', 'help_seeker_evaluations.session_id', '=', 'counseling_sessions.id')
            ->whereIn('counseling_sessions.helper_id', $helperIds)
            ->where('counseling_sessions.created_at', '>=', $monthStart)
            ->where('counseling_sessions.created_at', '<=', $endDate)
            ->when($helperId, fn ($q) => $q->where('counseling_sessions.helper_id', $helperId))
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $months = collect(range(5, 0))->map(function (int $offset) {
            return now()->startOfMonth()->subMonths($offset);
        })->filter(fn ($month) => $month->lte($endDate));

        return $months->map(function ($month) use ($sessionsByMonth, $satisfactionByMonth) {
            $key = \Illuminate\Support\Carbon::parse($month)->format('Y-m-01');
            $row = $sessionsByMonth->get($key);
            $total = $row->total ?? 0;
            $completed = $row->completed ?? 0;
            $sat = $satisfactionByMonth->get($key)?->avg_score ?? 0;

            return [
                'month' => $month->format('M Y'),
                'sessions' => $total,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100) : 0,
                'satisfaction' => $sat > 0 ? round($sat, 1) : 0,
            ];
        })->values();
    }

    /**
     * Get satisfaction scores — uses SQL AVG() instead of loading all records.
     */
    private function getSatisfactionScores($startDate, $endDate, $helperIds, $helperId)
    {
        $row = HelpSeekerEvaluation::selectRaw(
            'AVG(helpfulness_score) as avg_helpfulness, '
            . 'AVG(comfort_score) as avg_comfort, '
            . 'AVG(feeling_after_score) as avg_feeling, '
            . 'AVG(overall_score) as avg_overall'
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereHas('session', function ($query) use ($helperIds, $helperId) {
                $query->whereIn('helper_id', $helperIds);
                if ($helperId) {
                    $query->where('helper_id', $helperId);
                }
            })
            ->first();

        return [
            'helpfulness' => round((float) ($row->avg_helpfulness ?? 0), 1),
            'comfort' => round((float) ($row->avg_comfort ?? 0), 1),
            'feeling' => round((float) ($row->avg_feeling ?? 0), 1),
            'overall' => round((float) ($row->avg_overall ?? 0), 1),
        ];
    }

    /**
     * Get helper ranking — batched into aggregate queries instead of N+1.
     */
    private function getHelperRanking($startDate, $endDate, $helperIds)
    {
        $helpers = Helper::with('user')->whereIn('id', $helperIds)->get();

        $completedCounts = Session::whereIn('helper_id', $helperIds)
            ->where('session_status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('helper_id, COUNT(*) as total')
            ->groupBy('helper_id')
            ->pluck('total', 'helper_id');

        $latestCompetency = HelperCompetencyHistory::whereIn('helper_id', $helperIds)
            ->selectRaw('helper_id, overall_score, competency_level')
            ->orderBy('evaluation_date', 'desc')
            ->groupBy('helper_id', 'overall_score', 'competency_level')
            ->get()
            ->keyBy('helper_id');

        $avgRatings = \App\Models\HelpSeekerEvaluation::selectRaw('counseling_sessions.helper_id, AVG(help_seeker_evaluations.overall_score) as avg_rating')
            ->join('counseling_sessions', 'help_seeker_evaluations.session_id', '=', 'counseling_sessions.id')
            ->whereIn('counseling_sessions.helper_id', $helperIds)
            ->whereBetween('help_seeker_evaluations.created_at', [$startDate, $endDate])
            ->groupBy('counseling_sessions.helper_id')
            ->pluck('avg_rating', 'helper_id');

        $ranking = $helpers->map(function ($helper) use ($completedCounts, $latestCompetency, $avgRatings) {
            $competency = $latestCompetency->get($helper->id);

            return (object) [
                'name' => $helper->first_name . ' ' . $helper->last_name,
                'initials' => strtoupper(substr($helper->first_name, 0, 1) . substr($helper->last_name, 0, 1)),
                'sessions' => $completedCounts->get($helper->id, 0),
                'competency' => $competency ? round($competency->overall_score, 2) : 0,
                'rating' => round((float) ($avgRatings->get($helper->id, 0)), 1),
                'level' => $competency ? $competency->competency_level : 'Beginner',
            ];
        });

        return $ranking->sortByDesc('competency')->values();
    }
}
