<?php

namespace App\Http\Controllers;

use App\Events\EmergencyRiskDetected;
use App\Events\HighRiskDetected;
use App\Models\HelpSeeker;
use App\Models\ScreeningResponse;
use App\Models\Session;
use App\Services\EmergencyEscalationService;
use App\Services\RiskClassificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RiskClassificationController extends Controller
{
    public function __construct(
        private RiskClassificationService $riskService,
        private EmergencyEscalationService $emergencyService,
    ) {}

    public function classify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'screening_responses' => ['required', 'array'],
            'seeker_id' => ['required', 'exists:help_seekers,id'],
            'session_id' => ['nullable', 'exists:counseling_sessions,id'],
        ]);

        try {
            $result = $this->riskService->classifyRisk($validated['screening_responses']);
            $seeker = HelpSeeker::findOrFail($validated['seeker_id']);
            $session = $this->resolveSession($seeker, $validated['session_id'] ?? null, $result);

            ScreeningResponse::create([
                'seeker_id' => $seeker->id,
                'session_id' => $session->id,
                'responses' => $validated['screening_responses'],
                'risk_level' => $result['risk_level'],
                'priority' => $result['priority'],
                'action' => $result['action'],
                'reason' => $result['reason'],
                'classified_at' => now(),
                'classified_by' => 'system',
            ]);

            $seeker->update([
                'current_risk_level' => $result['risk_level'],
                'risk_last_updated' => now(),
            ]);

            $this->handleRiskLevel($result, $seeker, $session);

            return response()->json(['success' => true] + $result);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => 'validation_error', 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'error' => 'contradiction_detected', 'message' => $e->getMessage(), 'requires_clarification' => true], 422);
        } catch (\Throwable $e) {
            Log::error('Risk classification failed', ['exception' => $e]);
            return response()->json(['success' => false, 'error' => 'system_error', 'message' => 'Unable to classify risk at this time'], 500);
        }
    }

    public function updateRiskClassification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'exists:counseling_sessions,id'],
            'new_risk_level' => ['required', 'in:low,moderate,high,emergency'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $session = Session::with('seeker')->findOrFail($validated['session_id']);
        $oldRisk = $session->risk_level;

        $session->update([
            'risk_level' => $validated['new_risk_level'],
            'risk_updated_at' => now(),
            'risk_update_reason' => $validated['reason'],
            'risk_updated_by' => $request->user()?->id,
            'escalation_required' => in_array($validated['new_risk_level'], ['high', 'emergency'], true),
        ]);

        if ($session->seeker) {
            $session->seeker->update([
                'current_risk_level' => $validated['new_risk_level'],
                'risk_last_updated' => now(),
            ]);

            if ($validated['new_risk_level'] === RiskClassificationService::RISK_EMERGENCY) {
                $this->emergencyService->escalateEmergency($session, $session->seeker, ['reason' => $validated['reason']]);
            }
        }

        return response()->json([
            'success' => true,
            'old_risk_level' => $oldRisk,
            'new_risk_level' => $validated['new_risk_level'],
        ]);
    }

    private function resolveSession(HelpSeeker $seeker, ?int $sessionId, array $result): Session
    {
        $session = $sessionId ? Session::where('seeker_id', $seeker->id)->findOrFail($sessionId) : null;

        return $session ?: Session::create([
            'seeker_id' => $seeker->id,
            'risk_level' => $result['risk_level'],
            'session_status' => Session::STATUS_SCREENING_COMPLETED,
            'session_type' => 'chat',
            'completion_status' => 'pending',
            'created_date' => now(),
        ]);
    }

    private function handleRiskLevel(array $result, HelpSeeker $seeker, Session $session): void
    {
        if ($result['risk_level'] === RiskClassificationService::RISK_EMERGENCY) {
            $this->emergencyService->escalateEmergency($session, $seeker, ['reason' => $result['reason']]);
            event(new EmergencyRiskDetected($seeker, $session, $result));
            return;
        }

        $session->update([
            'risk_level' => $result['risk_level'],
            'escalation_required' => $result['risk_level'] === RiskClassificationService::RISK_HIGH,
            'requires_adviser_review' => $result['risk_level'] === RiskClassificationService::RISK_HIGH,
            'elevated_priority' => $result['risk_level'] === RiskClassificationService::RISK_MODERATE,
            'requires_closer_monitoring' => $result['risk_level'] === RiskClassificationService::RISK_MODERATE,
        ]);

        if ($result['risk_level'] === RiskClassificationService::RISK_HIGH) {
            event(new HighRiskDetected($seeker, $session, $result));
        }
    }
}
