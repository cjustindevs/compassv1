<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use App\Models\HelperCompetencyHistory;
use App\Models\Notification;
use App\Models\ReadinessCheck;
use App\Models\Session;
use Illuminate\Support\Facades\Auth;

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

        // ── STATS FROM DATABASE ──────────────────────────────────────────
        $totalSessions = Session::where('helper_id', $helper->id)->count();
        $activeSessions = Session::where('helper_id', $helper->id)
            ->where('session_status', 'active')
            ->count();
        $pendingRequests = Session::where('helper_id', $helper->id)
            ->where('session_status', 'helper_assigned')
            ->count();

        // ── COMPETENCY FROM DATABASE ─────────────────────────────────────
        $competency = HelperCompetencyHistory::where('helper_id', $helper->id)
            ->latest('evaluation_date')
            ->first();
        $competencyScore = $competency ? (int) round((float) $competency->overall_score) : 0;

        // ── READINESS FROM DATABASE ──────────────────────────────────────
        $readiness = ReadinessCheck::where('helper_id', $helper->id)
            ->latest('assessment_date')
            ->first();
        $availabilityStatus = $readiness && $readiness->availability_status
            ? ucfirst($readiness->availability_status)
            : ucfirst($helper->status ?? 'offline');

        // ── ACTIVE CASES FROM DATABASE ───────────────────────────────────
        $activeCases = Session::with(['seeker', 'concern'])
            ->where('helper_id', $helper->id)
            ->whereIn('session_status', ['active', 'helper_assigned'])
            ->orderByDesc('created_date')
            ->get()
            ->map(function (Session $session) {
                return [
                    'reference' => $session->reference_number,
                    'id' => $session->id,
                    'alias' => $session->seeker->generated_alias ?? 'Unknown',
                    'risk' => ucfirst($session->risk_level ?? 'Low'),
                    'mode' => $session->mode_label,
                    'language' => $session->seeker?->user?->preferred_language ?: 'English',
                    'waiting' => $session->created_at?->diffForHumans(),
                    'notes' => $session->concern->concern_name ?? 'No concern specified',
                    'status' => $session->session_status,
                ];
            });

        // ── ACTIVE SESSION FROM DATABASE ─────────────────────────────────
        $activeSession = Session::with(['seeker'])
            ->where('helper_id', $helper->id)
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
        ] : null;

        // ── EMERGENCY CASES FROM DATABASE ────────────────────────────────
        $emergencyCases = Session::with(['seeker', 'concern'])
            ->where('helper_id', $helper->id)
            ->where('risk_level', 'emergency')
            ->whereIn('session_status', ['active', 'helper_assigned', 'waiting'])
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
            $recentActivity = Session::with('seeker')
                ->where('helper_id', $helper->id)
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
            ['icon' => 'fa-phone', 'label' => 'Start Voice Call', 'route' => 'helper.voice', 'params' => []],
            ['icon' => 'fa-edit', 'label' => 'Log Reflection', 'route' => 'helper.notes', 'params' => []],
        ];

        $stats = [
            'total_sessions' => $totalSessions,
            'active_sessions' => $activeSessions,
            'pending_requests' => $pendingRequests,
            'competency_score' => $competencyScore,
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