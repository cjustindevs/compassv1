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
            ->open()
            ->orderByRaw("CASE risk_level WHEN 'emergency' THEN 0 WHEN 'high' THEN 1 WHEN 'moderate' THEN 2 ELSE 3 END")
            ->latest('created_at')
            ->paginate(20);

        $resolved30d = IncidentReport::where('status', 'resolved')
            ->where('resolved_at', '>=', now()->subDays(30))
            ->count();

        $avgResponse = $this->getAverageResponseTime();

        $stats = [
            'open' => $openIncidents->total(),
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

        $contacts = EmergencyResource::published()->get();

        $workflow = [
            'detected' => $openIncidents->total(),
            'notified' => $openIncidents->getCollection()->whereIn('status', ['under_review', 'escalated'])->count(),
            'reviewing' => $openIncidents->getCollection()->where('status', 'under_review')->count(),
            'referral' => $openIncidents->getCollection()->where('status', 'escalated')->count(),
            'closed' => IncidentReport::whereIn('status', ['resolved', 'closed'])->count(),
        ];

        return view('moderator.emergency', compact('openIncidents', 'stats', 'priorityDistribution', 'contacts', 'workflow'));
    }

    public function escalate(int $id): RedirectResponse
    {
        abort(403, 'Moderators cannot escalate incidents. Emergency review belongs to the responsible Adviser.');
    }

    public function resolve(int $id): RedirectResponse
    {
        abort(403, 'Emergency resolution requires the responsible Adviser.');
    }

    public function stats(): JsonResponse
    {
        $open = IncidentReport::open();

        return response()->json([
            'open' => (clone $open)->count(),
            'active_incident_ids' => (clone $open)->pluck('id'),
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
