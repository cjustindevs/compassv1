<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\Helper;
use App\Models\IncidentReport;
use App\Models\Notification;
use App\Models\QueueRequest;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ModeratorDashboardController extends Controller
{
    public function index(Request $request)
    {
        $queueWaiting = QueueRequest::where('request_status', 'waiting')->count();
        $queueAssigned = QueueRequest::where('request_status', 'assigned')->count();
        $avgWait = $this->getAverageWaitTime();
        $unserved = QueueRequest::where('request_status', 'waiting')
            ->where('request_date', '<', now()->subMinutes(30))
            ->count();

        $activeSessions = Session::where('session_status', 'active')->count();
        $chatSessions = Session::where('session_status', 'active')->where('session_type', 'chat')->count();
        $voiceSessions = Session::where('session_status', 'active')->where('session_type', 'voice')->count();

        $emergencyCount = IncidentReport::whereIn('status', ['open', 'under_review', 'escalated'])->count();

        $sessions = Session::with(['seeker', 'helper', 'concern'])
            ->whereIn('session_status', ['active', 'helper_assigned'])
            ->orderByRaw("CASE WHEN session_status = 'active' THEN 0 ELSE 1 END")
            ->latest('created_date')
            ->limit(10)
            ->get();

        $availableHelpers = Helper::where('status', 'available')->count();
        $busyHelpers = Helper::where('status', 'busy')->count();

        $recentActivity = $this->getRecentActivity();

        $recentNotifications = Notification::where('user_account_id', Auth::id())
            ->latest()
            ->limit(5)
            ->get();

        $highRiskSessions = Session::whereIn('risk_level', ['high', 'emergency'])
            ->whereIn('session_status', ['active', 'helper_assigned'])
            ->with('seeker')
            ->latest('created_date')
            ->limit(5)
            ->get();

        $monitorSession = $request->get('monitor');

        return view('moderator.dashboard', compact(
            'queueWaiting',
            'queueAssigned',
            'avgWait',
            'unserved',
            'activeSessions',
            'chatSessions',
            'voiceSessions',
            'emergencyCount',
            'sessions',
            'availableHelpers',
            'busyHelpers',
            'recentActivity',
            'recentNotifications',
            'highRiskSessions',
            'monitorSession'
        ));
    }

    /**
     * Real activity feed: incidents, queue movements, assignments and
     * evaluations pulled straight from the database.
     */
    private function getRecentActivity(): array
    {
        $activity = [];

        foreach (IncidentReport::with(['session', 'session.seeker'])
            ->latest()
            ->limit(3)
            ->get() as $incident) {
            $activity[] = [
                'type' => 'emergency',
                'icon' => 'fas fa-exclamation-triangle',
                'title' => 'Emergency case ' . str_replace('_', ' ', $incident->status),
                'message' => ($incident->session?->seeker?->generated_alias ?? 'A seeker') . ' · ' . Str::limit($incident->description, 70),
                'time' => $incident->created_at?->diffForHumans(),
                'link' => '/moderator/emergency',
            ];
        }

        foreach (QueueRequest::with('seeker')
            ->latest('updated_at')
            ->limit(4)
            ->get() as $queue) {
            $activity[] = [
                'type' => 'queue',
                'icon' => $queue->request_status === 'assigned' ? 'fas fa-user-check' : 'fas fa-hourglass-half',
                'title' => $queue->request_status === 'assigned' ? 'Seeker matched with helper' : 'New seeker in queue',
                'message' => ($queue->seeker?->generated_alias ?? 'Anonymous') . ' · ' . ucfirst($queue->priority_level) . ' priority',
                'time' => $queue->updated_at?->diffForHumans(),
                'link' => '/moderator/queue',
            ];
        }

        foreach (\App\Models\HelperCompetencyHistory::with('helper')
            ->latest()
            ->limit(2)
            ->get() as $evaluation) {
            $activity[] = [
                'type' => 'feedback',
                'icon' => 'fas fa-star',
                'title' => 'Evaluation submitted',
                'message' => ($evaluation->helper?->full_name ?? 'A helper') . ' scored ' . ($evaluation->overall_score ? number_format($evaluation->overall_score, 1) : '—') . '/100',
                'time' => $evaluation->created_at?->diffForHumans(),
                'link' => '/moderator/manage',
            ];
        }

        usort($activity, fn ($a, $b) => strcmp($b['time'] ?? '', $a['time'] ?? ''));

        return array_slice($activity, 0, 8);
    }

    public function stats(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'queue_waiting' => QueueRequest::where('request_status', 'waiting')->count(),
            'active_sessions' => Session::where('session_status', 'active')->count(),
            'emergency_open' => IncidentReport::whereIn('status', ['open', 'under_review', 'escalated'])->count(),
            'helpers_available' => Helper::where('status', 'available')->count(),
            'unserved' => QueueRequest::where('request_status', 'waiting')
                ->where('request_date', '<', now()->subMinutes(30))
                ->count(),
            'avg_wait' => $this->getAverageWaitTime(),
        ]);
    }

    private function getAverageWaitTime(): string
    {
        $avg = QueueRequest::where('request_status', 'assigned')
            ->whereNotNull('matched_date')
            ->selectRaw('AVG(' . \App\Support\DatabaseHelper::secondsBetween('matched_date', 'request_date') . ') as avg_wait')
            ->first();

        if ($avg && $avg->avg_wait) {
            $minutes = floor($avg->avg_wait / 60);
            $seconds = floor($avg->avg_wait % 60);

            return $minutes . 'm ' . $seconds . 's';
        }

        return '0m 0s';
    }
}