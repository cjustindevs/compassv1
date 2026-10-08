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
        $openIncidents = $service->active()->orderBy('triggered_at')->paginate(15)->withQueryString();
        $all = $service->query()->get();
        $active = $service->active()->get();
        $stats = ['open' => $active->count(), 'escalated_today' => $active->whereIn('status', ['escalated', 'referred'])->filter(fn ($a) => Carbon::parse($a->triggered_at)->gte(now('Asia/Manila')->startOfDay()->utc()))->count(),
            'resolved_30d' => $all->whereIn('status', ['resolved', 'closed'])->filter(fn ($a) => $a->resolved_at && Carbon::parse($a->resolved_at)->gte(now()->subDays(30)))->count()];
        $priorityDistribution = $active->groupBy('risk_level')->map->count()->all();
        $emergencySignature = $this->signature($active);
        $contacts = EmergencyResource::published()->get();

        return view('moderator.emergency', compact('openIncidents', 'stats', 'priorityDistribution', 'contacts', 'emergencySignature'));
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

        return response()->json(['open' => $active->count(), 'active_incident_ids' => $active->pluck('id'), 'signature' => $this->signature($active)]);
    }

    private function signature($cases): string
    {
        return hash('sha256', $cases->sortBy(fn ($case) => $case->source.'-'.$case->id)->values()->toJson());
    }
}
