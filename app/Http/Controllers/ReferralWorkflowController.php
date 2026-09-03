<?php

namespace App\Http\Controllers;

use App\Models\Adviser;
use App\Models\PsychologyProfessional;
use App\Models\Referral;
use App\Models\Session;
use App\Services\ReferralManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralWorkflowController extends Controller
{
    public function __construct(private ReferralManagementService $referrals) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'exists:counseling_sessions,id'],
            'reason' => ['required', 'string', 'max:2000'],
            'urgency' => ['nullable', 'in:low,moderate,high,emergency'],
        ]);

        $session = Session::with(['seeker', 'helper'])->findOrFail($validated['session_id']);
        $referral = $this->referrals->createReferral($session, $session->seeker, $validated);

        return response()->json(['success' => true, 'referral_id' => $referral->id, 'status' => $referral->status], 201);
    }

    public function review(Request $request, Referral $referral): JsonResponse
    {
        $validated = $request->validate([
            'approved' => ['required', 'boolean'],
            'adviser_id' => ['nullable', 'exists:advisers,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'decline_reason' => ['nullable', 'string', 'max:1000'],
            'consent_obtained' => ['nullable', 'boolean'],
        ]);

        $adviser = isset($validated['adviser_id'])
            ? Adviser::findOrFail($validated['adviser_id'])
            : $request->user()?->adviser;

        abort_unless($adviser, 403, 'Adviser profile required.');

        $referral = $this->referrals->reviewReferral($referral, $adviser, $validated);

        return response()->json(['success' => true, 'status' => $referral->status]);
    }

    public function consent(Request $request, Referral $referral): JsonResponse
    {
        $validated = $request->validate(['consent_given' => ['required', 'boolean']]);

        $referral = $this->referrals->processConsent($referral, (bool) $validated['consent_given']);

        return response()->json(['success' => true, 'status' => $referral->status]);
    }

    public function accept(Request $request, Referral $referral): JsonResponse
    {
        $validated = $request->validate(['professional_id' => ['nullable', 'exists:psychology_professionals,id']]);
        $professional = isset($validated['professional_id'])
            ? PsychologyProfessional::findOrFail($validated['professional_id'])
            : $request->user()?->psychologyProfessional;

        abort_unless($professional, 403, 'Professional profile required.');

        $referral = $this->referrals->acceptReferral($referral, $professional);

        return response()->json(['success' => true, 'status' => $referral->status]);
    }

    public function outcome(Request $request, Referral $referral): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:accepted,in_progress,completed,closed'],
            'outcome' => ['nullable', 'string', 'max:2000'],
            'follow_up_required' => ['nullable', 'boolean'],
            'follow_up_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $referral = $this->referrals->updateReferralOutcome($referral, $validated);

        return response()->json(['success' => true, 'status' => $referral->status]);
    }
}
