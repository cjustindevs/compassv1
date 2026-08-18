<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\IncidentReport;
use App\Models\Referral;
use App\Models\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ModeratorSessionController extends Controller
{
    public function index()
    {
        $sessions = Session::with(['seeker', 'helper', 'concern', 'messages'])
            ->whereIn('session_status', ['active', 'helper_assigned'])
            ->orderByRaw("CASE WHEN session_status = 'active' THEN 0 ELSE 1 END")
            ->latest('created_date')
            ->get()
            ->map(function (Session $session) {
                $session->elapsed_label = $this->elapsed($session);
                $session->msg_count = $session->messages->count();

                return $session;
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
            'messages' => fn ($q) => $q->orderBy('created_at')->limit(50),
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
        $sessions = Session::with('helper')
            ->whereIn('session_status', ['active', 'helper_assigned'])
            ->orderByRaw("CASE WHEN session_status = 'active' THEN 0 ELSE 1 END")
            ->latest('created_date')
            ->get();

        return response()->json([
            'ongoing' => $sessions->where('session_status', 'active')->count(),
            'chat' => $sessions->where('session_status', 'active')->where('session_type', 'chat')->count(),
            'voice' => $sessions->where('session_status', 'active')->where('session_type', 'voice')->count(),
            'emergency_flagged' => $sessions->whereIn('risk_level', ['high', 'emergency'])->count(),
            'sessions' => $sessions->map(function (Session $session) {
                return [
                    'id' => $session->id,
                    'ref' => $session->reference_number,
                    'alias' => $session->seeker?->generated_alias ?? 'Anonymous',
                    'helper' => $session->helper?->full_name ?? 'Unassigned',
                    'risk' => $session->risk_level ?? 'low',
                    'type' => $session->session_type,
                    'status' => $session->session_status,
                    'elapsed' => $this->elapsed($session),
                    'msg_count' => $session->messages()->count(),
                ];
            }),
        ]);
    }

    private function getActivityFeed(): array
    {
        $activity = [];

        foreach (Session::where('session_status', 'active')->latest('start_time')->limit(4)->get() as $session) {
            $activity[] = [
                'type' => 'session',
                'icon' => 'fas fa-comments',
                'message' => ($session->seeker?->generated_alias ?? 'A seeker') . ' is in an active ' . $session->mode_label . ' session',
                'time' => $session->start_time?->diffForHumans(),
            ];
        }

        foreach (Session::where('session_status', 'helper_assigned')->latest('created_date')->limit(3)->get() as $session) {
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