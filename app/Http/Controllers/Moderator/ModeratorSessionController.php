<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\IncidentReport;
use App\Models\Referral;
use App\Models\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ModeratorSessionController extends Controller
{
    public function index()
    {
        $sessions = Session::with(['seeker:id,id,generated_alias', 'helper:id,id,first_name,last_name', 'concern:id,concern_name'])
            ->whereIn('session_status', ['active', 'helper_assigned'])
            ->orderByRaw("CASE WHEN session_status = 'active' THEN 0 ELSE 1 END")
            ->latest('created_date')
            ->limit(50)
            ->get();

        $sessionIds = $sessions->pluck('id')->all();
        $messageCounts = DB::table('messages')
            ->whereIn('session_id', $sessionIds)
            ->select('session_id', DB::raw('COUNT(*) as msg_count'))
            ->groupBy('session_id')
            ->pluck('msg_count', 'session_id');

        $sessions->each(function (Session $session) use ($messageCounts) {
            $session->elapsed_label = $this->elapsed($session);
            $session->msg_count = $messageCounts->get($session->id, 0);
        });

        $stats = [
            'ongoing' => Session::where('session_status', 'active')->count(),
            'chat' => Session::where('session_status', 'active')->where('session_type', 'chat')->count(),
            'voice' => Session::where('session_status', 'active')->where('session_type', 'voice')->count(),
            'emergency_flagged' => Session::whereIn('session_status', ['active', 'helper_assigned'])
                ->whereIn('risk_level', ['high', 'emergency'])
                ->count(),
            'referrals_pending' => Referral::where('status', Referral::STATUS_PENDING_ADVISER)->count(),
        ];

        $recentActivity = $this->getActivityFeed();

        return view('moderator.sessions', compact('sessions', 'stats', 'recentActivity'));
    }

    public function show($id)
    {
        $session = Session::with([
            'seeker',
            'helper',
            'helper.latestCompetency',
            'concern',
            'messages' => fn ($q) => $q->orderBy('created_at')->limit(100),
            'callLog',
            'report',
            'evaluation',
            'incidents' => fn ($q) => $q->latest()->limit(5),
        ])->findOrFail($id);

        $session->elapsed_label = $this->elapsed($session);

        return view('moderator.session-detail', compact('session'));
    }

    public function stats(): JsonResponse
    {
        $activeQuery = Session::where('session_status', 'active');

        return response()->json([
            'ongoing' => (clone $activeQuery)->count(),
            'chat' => (clone $activeQuery)->where('session_type', 'chat')->count(),
            'voice' => $activeQuery->where('session_type', 'voice')->count(),
            'emergency_flagged' => Session::whereIn('session_status', ['active', 'helper_assigned'])
                ->whereIn('risk_level', ['high', 'emergency'])
                ->count(),
            'sessions' => Session::with('helper:id,id,first_name,last_name')
                ->whereIn('session_status', ['active', 'helper_assigned'])
                ->orderByRaw("CASE WHEN session_status = 'active' THEN 0 ELSE 1 END")
                ->latest('created_date')
                ->limit(50)
                ->get()
                ->map(fn (Session $session) => [
                    'id' => $session->id,
                    'ref' => $session->reference_number,
                    'alias' => $session->seeker?->generated_alias ?? 'Anonymous',
                    'helper' => $session->helper?->full_name ?? 'Unassigned',
                    'risk' => $session->risk_level ?? 'low',
                    'type' => $session->session_type,
                    'status' => $session->session_status,
                    'elapsed' => $this->elapsed($session),
                ]),
        ]);
    }

    private function getActivityFeed(): array
    {
        $activity = [];

        foreach (Session::with(['seeker:id,generated_alias', 'helper:id,first_name,last_name'])
            ->where('session_status', 'active')
            ->latest('start_time')
            ->limit(4)
            ->get() as $session) {
            $activity[] = [
                'type' => 'session',
                'icon' => 'fas fa-comments',
                'message' => ($session->seeker?->generated_alias ?? 'A seeker') . ' is in an active ' . $session->mode_label . ' session',
                'time' => $session->start_time?->diffForHumans(),
            ];
        }

        foreach (Session::with(['seeker:id,generated_alias', 'helper:id,first_name,last_name'])
            ->where('session_status', 'helper_assigned')
            ->latest('created_date')
            ->limit(3)
            ->get() as $session) {
            $activity[] = [
                'type' => 'assignment',
                'icon' => 'fas fa-user-check',
                'message' => ($session->seeker?->generated_alias ?? 'A seeker') . ' was assigned to ' . ($session->helper?->full_name ?? 'a helper'),
                'time' => $session->created_at?->diffForHumans(),
            ];
        }

        foreach (IncidentReport::latest()->limit(2)->get() as $incident) {
            $activity[] = [
                'type' => 'emergency',
                'icon' => 'fas fa-exclamation-triangle',
                'message' => 'Incident flagged: ' . Str::limit($incident->incident_category, 45),
                'time' => $incident->created_at?->diffForHumans(),
            ];
        }

        usort($activity, fn ($a, $b) => strcmp($b['time'] ?? '', $a['time'] ?? ''));

        return array_slice($activity, 0, 8);
    }

    private function elapsed(Session $session): string
    {
        if ($session->start_time && in_array($session->session_status, ['active', 'helper_assigned'])) {
            $minutes = (int) floor(now()->diffInMinutes($session->start_time));
        } else {
            $minutes = (int) $session->duration;
        }

        if ($minutes <= 0) {
            return 'Just now';
        }

        return $minutes >= 60
            ? floor($minutes / 60) . 'h ' . ($minutes % 60) . 'm'
            : $minutes . 'm';
    }
}