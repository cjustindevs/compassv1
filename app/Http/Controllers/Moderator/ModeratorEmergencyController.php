<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\EmergencyResource;
use App\Services\ModeratorEmergencyCases;
use App\Traits\BroadcastsSafely;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class ModeratorEmergencyController extends Controller
{
    use BroadcastsSafely;

    public function index()
    {
        $service = app(ModeratorEmergencyCases::class);
        $openIncidents = $service->active()->orderBy('triggered_at')->paginate(5)->withQueryString();
        $all = $service->query()->get();
        $active = $service->active()->get();
        $stats = $this->metrics($all, $active);
        $workflow = ['Detected' => 0, 'Notified' => 0, 'Reviewing' => 0, 'Referral' => 0, 'Closed' => $stats['resolved_30d']];
        foreach ($active as $case) {
            $stage = $case->professional_referred_at || $case->professional_referred || $case->status === 'referred' ? 'Referral'
                : ($case->acknowledged_at || in_array($case->status, ['under_review', 'acknowledged', 'responding', 'escalated']) ? 'Reviewing'
                    : ($case->adviser_notified_at || $case->adviser_notified || $case->status === 'notified' ? 'Notified' : 'Detected'));
            $workflow[$stage]++;
        }
        $priorityDistribution = array_fill_keys(['Emergency', 'High', 'Moderate', 'Low'], 0);
        foreach ($active as $case) {
            $label = ucfirst($case->risk_level ?: 'Unrecorded');
            $priorityDistribution[$label] = ($priorityDistribution[$label] ?? 0) + 1;
        }
        $emergencySignature = $this->signature($all);
        $contacts = EmergencyResource::published()->get();

        return view('moderator.emergency', compact('openIncidents', 'stats', 'priorityDistribution', 'contacts', 'emergencySignature', 'workflow'));
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
        $service = app(ModeratorEmergencyCases::class);
        $active = $service->active()->get();

        return response()->json(['open' => $active->count(), 'active_incident_ids' => $active->pluck('id'), 'signature' => $this->signature($service->query()->get())]);
    }

    private function metrics($all, $active): array
    {
        $now = now();
        $resolved = $all->whereIn('status', ['resolved', 'closed'])->filter(fn ($case) => $case->resolved_at && Carbon::parse($case->resolved_at)->betweenIncluded($now->copy()->subDays(30), $now));
        $responses = $all->filter(fn ($case) => $case->acknowledged_at && Carbon::parse($case->acknowledged_at)->betweenIncluded($now->copy()->subDays(30), $now) && Carbon::parse($case->acknowledged_at)->gte(Carbon::parse($case->triggered_at)))
            ->map(fn ($case) => Carbon::parse($case->triggered_at)->diffInSeconds(Carbon::parse($case->acknowledged_at)) / 60);

        return ['open' => $active->count(),
            'escalated_today' => $all->filter(fn ($case) => $case->escalated_at && Carbon::parse($case->escalated_at)->betweenIncluded(now('Asia/Manila')->startOfDay()->utc(), $now))->count(),
            'resolved_30d' => $resolved->count(),
            'average_response' => $responses->isEmpty() ? 'No data' : round($responses->avg(), 1).' min',
        ];
    }

    private function signature($cases): string
    {
        return hash('sha256', json_encode([$cases->sortBy(fn ($case) => $case->source.'-'.$case->id)->values(), $this->metrics($cases, $cases->whereNotIn('status', ModeratorEmergencyCases::TERMINAL)->whereNull('archived_at'))]));
    }
}
