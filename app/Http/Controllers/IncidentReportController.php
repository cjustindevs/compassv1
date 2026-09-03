<?php

namespace App\Http\Controllers;

use App\Models\IncidentReport;
use App\Services\IncidentReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncidentReportController extends Controller
{
    public function __construct(private IncidentReportService $incidents) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['nullable', 'exists:counseling_sessions,id'],
            'category' => ['required', 'string'],
            'description' => ['required', 'string', 'max:2000'],
            'immediate_action' => ['nullable', 'string', 'max:1000'],
            'risk_level' => ['nullable', 'in:low,moderate,high,emergency'],
            'recommendation' => ['nullable', 'string', 'max:1000'],
            'is_confidential' => ['nullable', 'boolean'],
        ]);

        $incident = $this->incidents->createIncidentReport($validated);

        return response()->json(['success' => true, 'incident_id' => $incident->id, 'status' => $incident->status], 201);
    }

    public function review(Request $request, IncidentReport $incident): JsonResponse
    {
        $validated = $request->validate([
            'reviewer_id' => ['nullable', 'exists:users,id'],
            'comments' => ['nullable', 'string', 'max:2000'],
        ]);

        $incident = $this->incidents->reviewIncident($incident, $validated);

        return response()->json(['success' => true, 'status' => $incident->status]);
    }

    public function escalate(Request $request, IncidentReport $incident): JsonResponse
    {
        $validated = $request->validate([
            'escalated_to' => ['required', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $incident = $this->incidents->escalateIncident($incident, $validated);

        return response()->json(['success' => true, 'status' => $incident->status]);
    }

    public function resolve(Request $request, IncidentReport $incident): JsonResponse
    {
        $validated = $request->validate([
            'resolution_summary' => ['nullable', 'string', 'max:2000'],
            'corrective_actions' => ['nullable', 'string', 'max:2000'],
        ]);

        $incident = $this->incidents->resolveIncident($incident, $validated);

        return response()->json(['success' => true, 'status' => $incident->status]);
    }

    public function close(Request $request, IncidentReport $incident): JsonResponse
    {
        $validated = $request->validate(['closure_notes' => ['nullable', 'string', 'max:2000']]);

        $incident = $this->incidents->closeIncident($incident, $validated);

        return response()->json(['success' => true, 'status' => $incident->status]);
    }
}
