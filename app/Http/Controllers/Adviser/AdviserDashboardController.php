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
use Illuminate\Support\Facades\Auth;

class AdviserDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $adviser = $user->adviser;

        // Get pending evaluations (sessions with reports not yet reviewed)
        $pendingEvaluations = SessionReport::where('adviser_reviewed', false)
            ->with(['session', 'session.seeker', 'session.helper'])
            ->get();

        $pendingEvaluationCount = $pendingEvaluations->count();

        // Get high-risk cases that still need attention (not yet completed)
        $highRiskCases = Session::whereIn('risk_level', ['high', 'emergency'])
            ->whereIn('session_status', ['active', 'helper_assigned', 'waiting', 'screening_completed', 'preferences_set'])
            ->with(['seeker', 'helper'])
            ->latest()
            ->limit(5)
            ->get();

        // Get helper stats
        $totalHelpers = Helper::count();
        $activeHelpers = Helper::where('status', 'available')->count();

        // Get pending referrals
        $pendingReferrals = Referral::where('status', Referral::STATUS_PENDING_ADVISER)
            ->with(['session', 'session.seeker', 'helper'])
            ->get();

        $pendingReferralCount = $pendingReferrals->count();

        // Get recent competency evaluations
        $recentEvaluations = HelperCompetencyHistory::with(['helper', 'adviser'])
            ->latest()
            ->limit(5)
            ->get();

        // Get unread notifications count
        $unreadNotifications = Notification::where('user_account_id', $user->id)
            ->unread()
            ->count();

        // Get session statistics
        $totalSessions = Session::count();
        $completedSessions = Session::whereIn('session_status', ['completed', 'evaluated'])->count();
        $activeSessions = Session::where('session_status', 'active')->count();

        // Get session waiting time stats
        $waitingTimeStats = $this->getWaitingTimeStats();

        // Get helper progress
        $helperProgress = $this->getHelperProgress();

        // Get recent activity
        $recentActivity = $this->getRecentActivity();

        // Open emergency incidents
        $openIncidents = IncidentReport::whereIn('status', ['open', 'under_review', 'escalated'])
            ->with('session')
            ->latest()
            ->limit(5)
            ->get();

        return view('dashboard.adviser', compact(
            'user',
            'adviser',
            'pendingEvaluations',
            'pendingEvaluationCount',
            'highRiskCases',
            'totalHelpers',
            'activeHelpers',
            'pendingReferrals',
            'pendingReferralCount',
            'recentEvaluations',
            'unreadNotifications',
            'totalSessions',
            'completedSessions',
            'activeSessions',
            'waitingTimeStats',
            'helperProgress',
            'recentActivity',
            'openIncidents'
        ));
    }

    /**
     * Real waiting-time data: sessions queued or awaiting a helper.
     */
    private function getWaitingTimeStats(): array
    {
        $stats = [];

        foreach (Session::with(['helper', 'seeker'])
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
    private function getHelperProgress(): array
    {
        return SessionReport::with(['session', 'session.helper'])
            ->where('adviser_reviewed', false)
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
    private function getRecentActivity(): array
    {
        $activity = [];

        // Recent emergency incidents
        foreach (IncidentReport::with('session')
            ->where('risk_level', 'emergency')
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
        foreach (HelperCompetencyHistory::with('helper')
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
        foreach (Referral::with(['session', 'session.seeker'])
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
        foreach (Helper::latest('updated_at')->limit(2)->get() as $helper) {
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