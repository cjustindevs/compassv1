<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\Adviser;
use App\Models\Referral;
use App\Services\ReferralManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Resolves referrals raised by a Helper who has no assigned Adviser. The
 * recommendation is always preserved; only the Adviser assignment is missing.
 */
class ModeratorReferralController extends Controller
{
    public function index(): View
    {
        $referrals = Referral::with(['session', 'helper.user'])
            ->where('status', Referral::STATUS_PENDING_ADVISER_ASSIGNMENT)
            ->orderByRaw("CASE priority_level WHEN 'emergency' THEN 0 WHEN 'high' THEN 1 WHEN 'moderate' THEN 2 ELSE 3 END")
            ->orderBy('created_at')
            ->get();

        $advisers = Adviser::with('user')
            ->whereHas('user', fn ($q) => $q->where('role', 'adviser')->where('is_active', true))
            ->withCount(['helpers as supervised_helpers_count'])
            ->orderBy('user_account_id')
            ->get();

        return view('moderator.unassigned-referrals', compact('referrals', 'advisers'));
    }

    public function assign(Request $request, Referral $referral): RedirectResponse
    {
        $validated = $request->validate([
            'adviser_id' => ['required', 'integer', 'exists:advisers,id'],
        ]);

        $adviser = Adviser::with('user')->findOrFail($validated['adviser_id']);

        app(ReferralManagementService::class)->assignAdviserToReferral($referral, $adviser);

        return back()->with('success', 'Referral #'.$referral->id.' assigned to '.($adviser->user->name ?? 'the selected Adviser').'. They have been notified.');
    }
}
