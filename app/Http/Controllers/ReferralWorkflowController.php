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
        abort_unless($request->user()?->helper?->id === $session->helper_id, 403);
        $referral = $this->referrals->createReferral($session, $session->seeker, $validated);

        return response()->json(['success' => true, 'referral_id' => $referral->id, 'status' => $referral->status], 201);
    }

    public function review(Request $request, Referral $referral): JsonResponse
    {
        $validated = $request->validate([
            'approved' => ['required', 'boolean'],
            'adviser_id' => ['prohibited'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'decline_reason' => ['nullable', 'string', 'max:1000'],
            'consent_obtained' => ['prohibited'],
        ]);

        $adviser = app(\App\Services\AdviserScope::class)->actor();

        $referral = $this->referrals->reviewReferral($referral, $adviser, $validated);

        return response()->json(['success' => true, 'status' => $referral->status]);
    }

    public function consent(Request $request, Referral $referral): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        abort_unless($request->user()?->role==='seeker' && $request->user()?->is_active && $request->user()?->helpSeeker?->id === $referral->session?->seeker_id, 403);
        abort_unless($referral->status === Referral::STATUS_PENDING_CONSENT, 409);
        $validated = $request->validate(['consent_given' => ['required', 'boolean']]);

        $referral = $this->referrals->processConsent($referral, (bool) $validated['consent_given']);

        if (!$request->expectsJson()) return redirect()->route('seeker.referrals')->with('success','Referral decision recorded.');
        return response()->json(['success' => true, 'status' => $referral->status]);
    }

    // Consent-first flow: decide the consent_request of a referral created by a
    // helper before it is submitted for adviser review. Route: referral.consent-request
    public function consentRequest(Request $request, Referral $referral): JsonResponse
    {
        abort_unless($request->user()?->role === 'seeker' && $request->user()?->is_active && $request->user()?->helpSeeker?->id === $referral->session?->seeker_id, 403);

        $validated = $request->validate([
            'accepted' => ['required', 'boolean'],
        ]);

        $referral = $this->referrals->decideConsentRequest($referral, (bool) $validated['accepted']);

        return response()->json(['success' => true, 'status' => $referral->status, 'referral_id' => $referral->id]);
    }

    public function accept(Request $request, Referral $referral): JsonResponse
    {
        $validated = $request->validate(['professional_id' => ['nullable', 'exists:psychology_professionals,id']]);
        $professional = isset($validated['professional_id'])
            ? PsychologyProfessional::findOrFail($validated['professional_id'])
            : $request->user()?->psychologyProfessional;

        abort_unless($professional, 403, 'Professional profile required.');
        abort_unless($request->user()?->psychologyProfessional?->id === $professional->id && $referral->professional_id === $professional->id, 403);

        $referral = $this->referrals->acceptReferral($referral, $professional);

        return response()->json(['success' => true, 'status' => $referral->status]);
    }

    public function outcome(Request $request, Referral $referral): JsonResponse
    {
        abort_unless($request->user()?->psychologyProfessional?->id === $referral->professional_id && $referral->professional_id !== null, 403);
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
