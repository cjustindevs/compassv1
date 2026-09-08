<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use App\Models\HelperCompetencyHistory;
use App\Models\Notification;
use App\Models\ReadinessCheck;
use App\Models\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class HelperDashboardController extends Controller
{
    /**
     * Show the helper dashboard — all data comes from the database.
     */
    public function index()
    {
        $user = Auth::user();
        $helper = $user->helper;

        if (! $helper) {
            return view('dashboard.helper')
                ->with('helperProfileMissing', true);
        }

        $helperId = $helper->id;

        // ── CACHED STATS ────────────────────────────────────────────────
        $stats = Cache::remember('helper_stats_' . $helperId, 60, function () use ($helperId) {
            return [
                'total_sessions' => Session::where('helper_id', $helperId)->count(),
                'active_sessions' => Session::where('helper_id', $helperId)
                    ->where('session_status', 'active')
                    ->count(),
                'pending_requests' => Session::where('helper_id', $helperId)
                    ->where('session_status', 'helper_assigned')
                    ->count(),
            ];
        });

        // ── COMPETENCY FROM DATABASE ─────────────────────────────────────
        $competency = Cache::remember('helper_competency_' . $helperId, 600, function () use ($helperId) {
            return HelperCompetencyHistory::where('helper_id', $helperId)
                ->latest('evaluation_date')
                ->first();
        });
        $competencyScore = $competency ? (int) round((float) $competency->overall_score) : 0;
        $stats['competency_score'] = $competencyScore;

        // ── READINESS FROM DATABASE ──────────────────────────────────────
        $readiness = Cache::remember('helper_readiness_' . $helperId, 300, function () use ($helperId) {
            return ReadinessCheck::where('helper_id', $helperId)
                ->latest('assessment_date')
                ->first();
        });
        $availabilityStatus = $readiness && $readiness->availability_status
            ? ucfirst($readiness->availability_status)
            : ucfirst($helper->status ?? 'offline');

        // ── ACTIVE CASES FROM DATABASE ───────────────────────────────────
        $activeCases = Session::with([
            'seeker:id,id,user_account_id,generated_alias',
            'seeker.user:id,id,preferred_language',
            'concern:id,concern_name',
        ])
            ->where('helper_id', $helperId)
            ->whereIn('session_status', ['active', 'helper_assigned'])
            ->orderByDesc('created_date')
            ->limit(20)
            ->get()
            ->map(function (Session $session) {
                return [
                    'reference' => $session->reference_number,
                    'id' => $session->id,
                    'alias' => $session->seeker->generated_alias ?? 'Unknown',
                    'risk' => 'Assigned support',
                    'mode' => $session->mode_label,
                    'language' => $session->seeker?->user?->preferred_language ?: 'English',
                    'waiting' => $session->created_at?->diffForHumans(),
                    'notes' => $session->concern->concern_name ?? 'No concern specified',
                    'status' => $session->session_status,
                ];
            });

        // ── ACTIVE SESSION FROM DATABASE ─────────────────────────────────
        $activeSession = Session::with(['seeker:id,id,generated_alias'])
            ->where('helper_id', $helperId)
            ->where('session_status', 'active')
            ->orderByDesc('start_time')
            ->first();

        $activeSessionData = $activeSession ? [
            'id' => $activeSession->id,
            'reference' => $activeSession->reference_number,
            'alias' => $activeSession->seeker->generated_alias ?? 'Seeker',
            'mode' => $activeSession->mode_label,
            'elapsed' => $activeSession->start_time
                ? sprintf('%dm', max(1, (int) $activeSession->start_time->diffInMinutes(now())))
                : '0m',
            'elapsed_minutes' => $activeSession->start_time ? max(1, (int) $activeSession->start_time->diffInMinutes(now())) : 0,
        ] : null;

        // ── EMERGENCY CASES FROM DATABASE ────────────────────────────────
        $emergencyCases = Session::with([
            'seeker:id,id,generated_alias',
            'concern:id,concern_name',
        ])
            ->where('helper_id', $helperId)
            ->where('risk_level', 'emergency')
            ->whereIn('session_status', ['active', 'helper_assigned', 'waiting'])
            ->orderByDesc('created_date')
            ->limit(10)
            ->get()
            ->map(function (Session $session) {
                return [
                    'id' => $session->id,
                    'reference' => $session->reference_number,
                    'alias' => $session->seeker->generated_alias ?? 'Unknown',
                    'incident' => $session->concern->concern_name ?? 'Emergency risk case',
                    'time' => $session->created_at?->diffForHumans() ?? 'just now',
                ];
            });

        // ── RECENT ACTIVITY FROM DATABASE ────────────────────────────────
        $recentActivity = Notification::where('user_account_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(4)
            ->get()
            ->map(fn (Notification $n) => [
                'type' => in_array($n->notification_type, ['emergency', 'assignment', 'evaluation', 'reminder'])
                    ? $n->notification_type
                    : 'system',
                'message' => $n->title,
                'detail' => $n->message,
                'time' => $n->created_at?->diffForHumans() ?? 'just now',
            ]);

        if ($recentActivity->isEmpty()) {
            $recentActivity = Session::with('seeker:id,id,generated_alias')
                ->where('helper_id', $helperId)
                ->orderByDesc('created_date')
                ->limit(4)
                ->get()
                ->map(fn (Session $session) => [
                    'type' => 'assignment',
                    'message' => 'Session ' . $session->reference_number,
                    'detail' => ucfirst(str_replace('_', ' ', $session->session_status))
                        . ' - ' . ($session->seeker->generated_alias ?? 'Seeker'),
                    'time' => $session->created_at?->diffForHumans() ?? 'just now',
                ]);
        }

        $quickActions = [
            ['icon' => 'fa-comment-dots', 'label' => 'Open Chat', 'route' => 'helper.chat', 'params' => []],
            ['icon' => 'fa-phone', 'label' => 'Start Voice Call', 'route' => $activeSessionData ? 'helper.session.voice' : 'helper.cases', 'params' => $activeSessionData ? ['id' => $activeSessionData['id']] : []],
            ['icon' => 'fa-edit', 'label' => 'Session Notes', 'route' => $activeSessionData ? 'helper.session.notes' : 'helper.cases', 'params' => $activeSessionData ? ['id' => $activeSessionData['id']] : []],
        ];

        return view('dashboard.helper', compact(
            'user',
            'helper',
            'stats',
            'activeCases',
            'emergencyCases',
            'activeSessionData',
            'recentActivity',
            'quickActions',
            'competency',
            'readiness'
        ));
    }
}
