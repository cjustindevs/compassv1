<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Models\Helper;
use App\Models\HelperCompetencyHistory;
use App\Models\Referral;
use App\Models\SessionReport;
use App\Models\Notification;
use App\Models\IncidentReport;
use App\Models\QueueRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class AdviserDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $adviser = $user->adviser;
        $helperIds = Helper::where('adviser_id', $adviser?->id)->pluck('id');

        if ($helperIds->isEmpty()) {
            return view('dashboard.adviser', array_merge([
                'user' => $user,
                'adviser' => $adviser,
                'pendingEvaluations' => collect(),
                'pendingEvaluationCount' => 0,
                'highRiskCases' => collect(),
                'totalHelpers' => 0,
                'activeHelpers' => 0,
                'pendingReferrals' => collect(),
                'pendingReferralCount' => 0,
                'recentEvaluations' => collect(),
                'unreadNotifications' => Notification::where('user_account_id', $user->id)->unread()->count(),
                'totalSessions' => 0,
                'completedSessions' => 0,
                'activeSessions' => 0,
                'waitingTimeStats' => [],
                'helperProgress' => [],
                'recentActivity' => [],
                'openIncidents' => collect(),
                'helpers' => collect(),
                'matchingStats' => ['total_helpers' => 0, 'available_helpers' => 0, 'average_competency' => 0, 'total_sessions_today' => 0, 'avg_matching_score' => 0],
                'helpersAtCapacity' => collect(),
                'helpersExpiredReadiness' => collect(),
                'assignedQueueCount' => 0,
                'recentAssignments' => collect(),
            ]));
        }

        // Pending evaluations (small, always fresh)
        $pendingEvaluations = SessionReport::where('adviser_reviewed', false)
            ->whereHas('session', fn ($query) => $query->whereIn('helper_id', $helperIds))
            ->with(['session.seeker:id,id,generated_alias', 'session.helper:id,id,first_name,last_name'])
            ->latest()
            ->limit(20)
            ->get();

        $pendingEvaluationCount = $pendingEvaluations->count();

        // High-risk cases (small)
        $highRiskCases = Session::whereIn('risk_level', ['high', 'emergency'])
            ->whereIn('helper_id', $helperIds)
            ->whereIn('session_status', ['active', 'helper_assigned', 'waiting', 'screening_completed', 'preferences_set'])
            ->with(['seeker:id,id,generated_alias', 'helper:id,id,first_name,last_name'])
            ->latest()
            ->limit(5)
            ->get();

        // Helper stats
        $totalHelpers = $helperIds->count();
        $activeHelpers = Helper::whereIn('id', $helperIds)->where('status', 'available')->count();

        // Pending referrals
        $pendingReferrals = Referral::where('status', Referral::STATUS_PENDING_ADVISER)
            ->whereIn('helper_id', $helperIds)
            ->with(['session.seeker:id,id,generated_alias', 'helper:id,id,first_name,last_name'])
            ->latest()
            ->limit(10)
            ->get();

        $pendingReferralCount = $pendingReferrals->count();

        // Recent competency evaluations
        $recentEvaluations = HelperCompetencyHistory::with(['helper:id,id,first_name,last_name', 'adviser:id,id,first_name,last_name'])
            ->whereIn('helper_id', $helperIds)
            ->latest()
            ->limit(5)
            ->get();

        // Unread notifications
        $unreadNotifications = Notification::where('user_account_id', $user->id)
            ->unread()
            ->count();

        // Session statistics (cached 60s)
        $sessionStats = Cache::remember('adviser_session_stats_' . ($adviser?->id ?? 'none'), 60, function () use ($helperIds) {
            return [
                'total' => Session::whereIn('helper_id', $helperIds)->count(),
                'completed' => Session::whereIn('helper_id', $helperIds)->whereIn('session_status', ['completed', 'evaluated'])->count(),
                'active' => Session::whereIn('helper_id', $helperIds)->where('session_status', 'active')->count(),
            ];
        });

        // Waiting time stats (small)
        $waitingTimeStats = $this->getWaitingTimeStats($helperIds);

        // Helper progress
        $helperProgress = $this->getHelperProgress($helperIds);

        // Recent activity
        $recentActivity = $this->getRecentActivity($helperIds);

        $helpers = Helper::whereIn('id', $helperIds)
            ->with(['user:id,id,name', 'currentReadiness', 'schedule'])
            ->limit(50)
            ->get();

        $matchingStats = $this->getMatchingStatistics($helperIds);
        $helpersAtCapacity = $helpers->filter(fn (Helper $helper) => $helper->current_shift_sessions >= Helper::MAX_SESSIONS_PER_SHIFT);
        $helpersExpiredReadiness = $helpers->filter(fn (Helper $helper) => $helper->getReadinessStatus() !== 'ready');
        $assignedQueueCount = QueueRequest::whereIn('assigned_helper_id', $helperIds)->where('request_status', 'assigned')->count();

        $recentAssignments = Session::whereIn('helper_id', $helperIds)
            ->where('created_at', '>=', now()->subHours(24))
            ->with(['seeker:id,id,generated_alias', 'helper.user:id,id,name'])
            ->latest()
            ->limit(10)
            ->get();

        // Open emergency incidents
        $openIncidents = IncidentReport::whereIn('status', ['open', 'under_review', 'escalated'])
            ->whereHas('session', fn ($query) => $query->whereIn('helper_id', $helperIds))
            ->with('session')
            ->latest()
            ->limit(5)
            ->get();

        return view('dashboard.adviser', [
            'user' => $user,
            'adviser' => $adviser,
            'pendingEvaluations' => $pendingEvaluations,
            'pendingEvaluationCount' => $pendingEvaluationCount,
            'highRiskCases' => $highRiskCases,
            'totalHelpers' => $totalHelpers,
            'activeHelpers' => $activeHelpers,
            'pendingReferrals' => $pendingReferrals,
            'pendingReferralCount' => $pendingReferralCount,
            'recentEvaluations' => $recentEvaluations,
            'unreadNotifications' => $unreadNotifications,
            'totalSessions' => $sessionStats['total'],
            'completedSessions' => $sessionStats['completed'],
            'activeSessions' => $sessionStats['active'],
            'waitingTimeStats' => $waitingTimeStats,
            'helperProgress' => $helperProgress,
            'recentActivity' => $recentActivity,
            'openIncidents' => $openIncidents,
            'helpers' => $helpers,
            'matchingStats' => $matchingStats,
            'helpersAtCapacity' => $helpersAtCapacity,
            'helpersExpiredReadiness' => $helpersExpiredReadiness,
            'assignedQueueCount' => $assignedQueueCount,
            'recentAssignments' => $recentAssignments,
        ]);
    }

    private function getMatchingStatistics($helperIds): array
    {
        $helpers = Helper::whereIn('id', $helperIds);

        $matchingScores = Session::whereIn('helper_id', $helperIds)
            ->whereDate('created_at', today())
            ->whereNotNull('matching_details')
            ->pluck('matching_details')
            ->map(function ($details) {
                $details = is_array($details) ? $details : json_decode($details, true);

                return (float) ($details['total_score'] ?? 0);
            })
            ->filter(fn (float $score) => $score > 0);

        return [
            'total_helpers' => (clone $helpers)->count(),
            'available_helpers' => (clone $helpers)->where('status', 'available')->where('availability', 'available')->count(),
            'average_competency' => round((clone $helpers)->avg('competency_score') ?? 0, 2),
            'total_sessions_today' => Session::whereIn('helper_id', $helperIds)->whereDate('created_at', today())->count(),
            'avg_matching_score' => round($matchingScores->avg() ?? 0, 2),
        ];
    }

    /**
     * Real waiting-time data: sessions queued or awaiting a helper.
     */
    private function getWaitingTimeStats($helperIds): array
    {
        $stats = [];

        foreach (Session::with(['helper:id,id,first_name,last_name', 'seeker:id,id,generated_alias'])
            ->whereIn('helper_id', $helperIds)
            ->whereIn('session_status', ['waiting', 'helper_assigned', 'active'])
            ->latest('created_date')
            ->limit(6)
            ->get() as $session) {
            $label = match ($session->session_status) {
                'active' => 'In Progress',
                'helper_assigned' => 'Helper Assigned',
                default => 'In Queue',
            };

            $start = $session->created_date ?? $session->created_at;

            $stats[$session->reference_number] = [
                'reference' => $session->reference_number,
                'helper' => $session->helper?->full_name ?? '—',
                'alias' => $session->seeker?->generated_alias ?? 'Anonymous',
                'status' => $label,
                'waiting' => $start ? $start->diffForHumans(now(), true) : '—',
            ];
        }

        return $stats;
    }

    /**
     * Real helper progress: latest reports still pending review.
     */
    private function getHelperProgress($helperIds): array
    {
        return SessionReport::with(['session.helper:id,id,first_name,last_name'])
            ->where('adviser_reviewed', false)
            ->whereHas('session', fn ($query) => $query->whereIn('helper_id', $helperIds))
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (SessionReport $report) {
                return [
                    'session' => $report->session?->reference_number ?? 'S-?',
                    'helper' => $report->session?->helper?->full_name ?? 'Unknown',
                    'feedback' => \Illuminate\Support\Str::limit($report->session_summary ?? 'No summary', 60),
                ];
            })
            ->all();
    }

    /**
     * Real recent activity from the database (incidents, evaluations, referrals).
     */
    private function getRecentActivity($helperIds): array
    {
        $activity = [];

        // Recent emergency incidents
        foreach (IncidentReport::with('session.seeker:id,id,generated_alias')
            ->where('risk_level', 'emergency')
            ->whereHas('session', fn ($query) => $query->whereIn('helper_id', $helperIds))
            ->latest()
            ->limit(2)
            ->get() as $incident) {
            $activity[] = [
                'type' => 'emergency',
                'message' => 'Emergency case flagged',
                'detail' => $incident->session?->seeker?->generated_alias ?? 'A seeker' . ' - ' . \Illuminate\Support\Str::limit($incident->description, 60),
                'time' => $incident->created_at?->diffForHumans(),
            ];
        }

        // Recent competency evaluations
        foreach (HelperCompetencyHistory::with('helper:id,id,first_name,last_name')
            ->whereIn('helper_id', $helperIds)
            ->latest()
            ->limit(2)
            ->get() as $evaluation) {
            $activity[] = [
                'type' => 'evaluation',
                'message' => 'New evaluation submitted',
                'detail' => ($evaluation->helper?->full_name ?? 'Helper') . ' - ' . ($evaluation->overall_score ? 'Score ' . $evaluation->overall_score . '/5' : 'Reviewed'),
                'time' => $evaluation->created_at?->diffForHumans(),
            ];
        }

        // Recent referrals
        foreach (Referral::with(['session.seeker:id,id,generated_alias'])
            ->whereIn('helper_id', $helperIds)
            ->latest()
            ->limit(2)
            ->get() as $referral) {
            $activity[] = [
                'type' => 'referral',
                'message' => 'Referral ' . ucfirst(str_replace('_', ' ', $referral->status)),
                'detail' => ($referral->session?->seeker?->generated_alias ?? 'A seeker') . ' - ' . \Illuminate\Support\Str::limit($referral->referral_reason, 60),
                'time' => $referral->created_at?->diffForHumans(),
            ];
        }

        // Recent helper availability changes
        foreach (Helper::whereIn('id', $helperIds)->latest('updated_at')->limit(2)->get() as $helper) {
            $activity[] = [
                'type' => 'helper',
                'message' => 'Helper availability updated',
                'detail' => ($helper->full_name ?? 'A helper') . ' is now ' . $helper->status,
                'time' => $helper->updated_at?->diffForHumans(),
            ];
        }

        // Most recent first
        usort($activity, fn ($a, $b) => strcmp($b['time'] ?? '', $a['time'] ?? ''));

        return array_slice($activity, 0, 8);
    }
}
