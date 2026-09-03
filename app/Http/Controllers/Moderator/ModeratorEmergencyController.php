<?php

namespace App\Http\Controllers\Moderator;

use App\Events\EmergencyTriggered;
use App\Events\ModeratorAlert;
use App\Http\Controllers\Controller;
use App\Models\EmergencyResource;
use App\Models\HelperCompetencyHistory;
use App\Models\IncidentReport;
use App\Models\Notification;
use App\Models\Session;
use App\Models\Referral;
use App\Traits\BroadcastsSafely;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModeratorEmergencyController extends Controller
{
    use BroadcastsSafely;

    public function index()
    {
        $openIncidents = IncidentReport::with(['session', 'session.seeker', 'session.helper', 'user'])
            ->whereIn('status', ['open', 'under_review', 'escalated'])
            ->orderByRaw("CASE risk_level WHEN 'emergency' THEN 0 WHEN 'high' THEN 1 WHEN 'moderate' THEN 2 ELSE 3 END")
            ->latest('created_at')
            ->get();

        $resolved30d = IncidentReport::where('status', 'resolved')
            ->where('resolved_at', '>=', now()->subDays(30))
            ->count();

        $avgResponse = $this->getAverageResponseTime();

        $stats = [
            'open' => $openIncidents->count(),
            'escalated_today' => IncidentReport::where('status', 'escalated')
                ->where('created_at', '>=', now()->startOfDay())
                ->count(),
            'avg_response' => $avgResponse,
            'resolved_30d' => $resolved30d,
        ];

        $priorityDistribution = IncidentReport::selectRaw('risk_level, COUNT(*) as total')
            ->groupBy('risk_level')
            ->pluck('total', 'risk_level')
            ->toArray();

        $contacts = EmergencyResource::where('status', 'active')->get();

        $workflow = [
            'detected' => $openIncidents->count(),
            'notified' => $openIncidents->whereIn('status', ['under_review', 'escalated'])->count(),
            'reviewing' => $openIncidents->where('status', 'under_review')->count(),
            'referral' => $openIncidents->where('status', 'escalated')->count(),
            'closed' => IncidentReport::whereIn('status', ['resolved', 'closed'])->count(),
        ];

        return view('moderator.emergency', compact('openIncidents', 'stats', 'priorityDistribution', 'contacts', 'workflow'));
    }

    public function escalate(int $id): RedirectResponse
    {
        $incident = IncidentReport::findOrFail($id);

        $incident->update([
            'status' => 'escalated',
            'recommendation' => $incident->recommendation ?: 'Escalated by the moderator for immediate professional review.',
        ]);

        // Notify the adviser responsible for the session helper.
        $session = $incident->session;
        $adviserUser = $session?->helper?->adviser?->user_account_id;
        $adviserUserId = $adviserUser ?? \App\Models\User::where('role', 'adviser')->value('id');

        if ($adviserUserId) {
            Notification::create([
                'user_account_id' => $adviserUserId,
                'title' => '🚨 Emergency Escalated',
                'message' => ($session?->seeker?->generated_alias ?? 'A seeker') . ' - escalated by the moderator. Review immediately.',
                'notification_type' => 'emergency',
                'type_icon' => '🚨',
                'link' => '/adviser/dashboard',
                'status' => 'unread',
            ]);
        }

        $this->broadcastSafely(new ModeratorAlert(Auth::id(), 'emergency', 'Emergency escalated', 'Case escalated to the adviser for immediate review.', '/moderator/emergency'));

        // Notify the adviser in real time so they get an immediate toast.
        if ($adviserUserId && $incident->session) {
            try {
                $this->broadcastSafely(new EmergencyTriggered($incident->session, $incident, $adviserUserId));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', 'Emergency case #' . $incident->id . ' escalated for immediate review.');
    }

    public function resolve(int $id): RedirectResponse
    {
        $incident = IncidentReport::findOrFail($id);

        $incident->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        return back()->with('success', 'Emergency case #' . $incident->id . ' marked as resolved.');
    }

    public function stats(): JsonResponse
    {
        $open = IncidentReport::whereIn('status', ['open', 'under_review', 'escalated']);

        return response()->json([
            'open' => $open->count(),
            'escalated_today' => IncidentReport::where('status', 'escalated')
                ->where('created_at', '>=', now()->startOfDay())
                ->count(),
            'avg_response' => $this->getAverageResponseTime(),
            'resolved_30d' => IncidentReport::where('status', 'resolved')
                ->where('resolved_at', '>=', now()->subDays(30))
                ->count(),
        ]);
    }

    private function getAverageResponseTime(): string
    {
        $avg = IncidentReport::whereNotNull('resolved_at')
            ->selectRaw('AVG(' . \App\Support\DatabaseHelper::secondsBetween('resolved_at', 'created_at') . ') as avg_response')
            ->first();

        if ($avg && $avg->avg_response) {
            $minutes = floor($avg->avg_response / 60);

            return $minutes . 'm';
        }

        return '—';
    }
}
