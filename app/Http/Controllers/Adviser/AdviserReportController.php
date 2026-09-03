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
use Illuminate\Support\Facades\DB;

class AdviserReportController extends Controller
{
    /**
     * Show reports and analytics page
     */
    public function index(Request $request)
    {
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
        $completedQuery = (clone $sessionsQuery)->where('session_status', 'completed');
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

        $sessions = Session::with(['seeker', 'helper', 'concern'])
            ->whereIn('helper_id', $helperIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($selectedHelperId, function ($query) use ($selectedHelperId) {
                return $query->where('helper_id', $selectedHelperId);
            })
            ->get();

        $filename = 'compass-report-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($sessions) {
            $file = fopen('php://output', 'w');

            // Headers
            fputcsv($file, [
                'Session ID',
                'Seeker',
                'Helper',
                'Concern',
                'Type',
                'Risk Level',
                'Status',
                'Created',
                'Duration (min)',
                'Rating'
            ]);

            // Data
            foreach ($sessions as $session) {
                $evaluation = $session->evaluation;
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
                    $evaluation ? $evaluation->overall_score : 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get date range based on period
     */
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
     * Get monthly trends from real session data.
     */
    private function getMonthlyTrends($startDate, $endDate, $helperIds, $helperId)
    {
        $months = collect(range(5, 0))->map(function (int $offset) {
            return now()->startOfMonth()->subMonths($offset);
        })->filter(function ($month) use ($startDate, $endDate) {
            return $month->lte($endDate);
        });

        return $months->map(function ($month) use ($startDate, $endDate, $helperIds, $helperId) {
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            if ($monthStart->lt($startDate)) {
                $monthStart = $startDate->copy();
            }
            if ($monthEnd->gt($endDate)) {
                $monthEnd = $endDate->copy();
            }

            $sessionsQuery = Session::whereIn('helper_id', $helperIds)->whereBetween('created_at', [$monthStart, $monthEnd]);
            if ($helperId) {
                $sessionsQuery->where('helper_id', $helperId);
            }

            $total = (clone $sessionsQuery)->count();
            $completed = (clone $sessionsQuery)->whereIn('session_status', ['completed', 'evaluated'])->count();

            $satisfaction = HelpSeekerEvaluation::whereHas('session', function ($query) use ($monthStart, $monthEnd, $helperIds, $helperId) {
                $query->whereIn('helper_id', $helperIds)
                    ->whereBetween('created_at', [$monthStart, $monthEnd]);
                if ($helperId) {
                    $query->where('helper_id', $helperId);
                }
            })->avg('overall_score') ?? 0;

            return [
                'month' => $month->format('M Y'),
                'sessions' => $total,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100) : 0,
                'satisfaction' => $satisfaction > 0 ? round($satisfaction, 1) : 0,
            ];
        })->values();
    }

    /**
     * Get satisfaction scores
     */
    private function getSatisfactionScores($startDate, $endDate, $helperIds, $helperId)
    {
        $scores = HelpSeekerEvaluation::whereBetween('created_at', [$startDate, $endDate])
            ->whereHas('session', function ($query) use ($helperIds, $helperId) {
                $query->whereIn('helper_id', $helperIds);
                if ($helperId) {
                    $query->where('helper_id', $helperId);
                }
            })
            ->get();

        $avgHelpfulness = $scores->avg('helpfulness_score') ?? 0;
        $avgComfort = $scores->avg('comfort_score') ?? 0;
        $avgFeeling = $scores->avg('feeling_after_score') ?? 0;
        $avgOverall = $scores->avg('overall_score') ?? 0;

        return [
            'helpfulness' => round($avgHelpfulness, 1),
            'comfort' => round($avgComfort, 1),
            'feeling' => round($avgFeeling, 1),
            'overall' => round($avgOverall, 1)
        ];
    }

    /**
     * Get helper ranking
     */
    private function getHelperRanking($startDate, $endDate, $helperIds)
    {
        $helpers = Helper::with('user')->whereIn('id', $helperIds)->get();

        $ranking = $helpers->map(function ($helper) {
            $sessions = Session::where('helper_id', $helper->id)
                ->where('session_status', 'completed')
                ->count();

            $competency = HelperCompetencyHistory::where('helper_id', $helper->id)
                ->latest()
                ->first();

            $rating = HelpSeekerEvaluation::whereHas('session', function ($query) use ($helper) {
                $query->where('helper_id', $helper->id);
            })->avg('overall_score') ?? 0;

            return (object) [
                'name' => $helper->first_name . ' ' . $helper->last_name,
                'initials' => strtoupper(substr($helper->first_name, 0, 1) . substr($helper->last_name, 0, 1)),
                'sessions' => $sessions,
                'competency' => $competency ? round($competency->overall_score, 2) : 0,
                'rating' => round($rating, 1),
                'level' => $competency ? $competency->competency_level : 'Beginner'
            ];
        });

        return $ranking->sortByDesc('competency')->values();
    }
}
