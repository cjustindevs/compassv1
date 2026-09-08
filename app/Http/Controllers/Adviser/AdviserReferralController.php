<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\Helper;
use App\Models\Referral;
use App\Models\Session;
use App\Models\Notification;
use App\Models\PsychologyProfessional;
use App\Events\ReferralApproved;
use App\Traits\BroadcastsSafely;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdviserReferralController extends Controller
{
    use BroadcastsSafely;

    /**
     * Show the referral queue
     */
    public function index()
    {
        $helperIds = Helper::where('adviser_id', Auth::user()->adviser?->id)->pluck('id');

        // Get pending referrals (awaiting adviser review)
        $pendingReferrals = Referral::with(['session.seeker:id,id,generated_alias', 'helper:id,id,first_name,last_name', 'helper.user:id,id,name'])
            ->where('status', Referral::STATUS_PENDING_ADVISER)
            ->whereIn('helper_id', $helperIds)
            ->orderBy('priority_level', 'desc')
            ->orderBy('created_at', 'asc')
            ->limit(50)
            ->get();

        // Get approved referrals (awaiting professional)
        $approvedReferrals = Referral::with(['session.seeker:id,id,generated_alias', 'helper:id,id,first_name,last_name', 'professional:id,id,first_name,last_name'])
            ->where('status', Referral::STATUS_PENDING_PROFESSIONAL)
            ->whereIn('helper_id', $helperIds)
            ->orderBy('created_at', 'asc')
            ->limit(50)
            ->get();

        // Get completed referrals
        $completedReferrals = Referral::with(['session.seeker:id,id,generated_alias', 'helper:id,id,first_name,last_name', 'professional:id,id,first_name,last_name'])
            ->whereIn('status', [Referral::STATUS_COMPLETED, Referral::STATUS_CLOSED])
            ->whereIn('helper_id', $helperIds)
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        // Get statistics
        $totalPending = $pendingReferrals->count();
        $totalApproved = $approvedReferrals->count();
        $emergencyCount = $pendingReferrals->filter(function ($r) {
            return $r->priority_level === Referral::PRIORITY_EMERGENCY;
        })->count();

        // Get professionals list for assignment
        $professionals = PsychologyProfessional::with('user')->limit(50)->get();

        return view('adviser.referrals', compact(
            'pendingReferrals',
            'approvedReferrals',
            'completedReferrals',
            'totalPending',
            'totalApproved',
            'emergencyCount',
            'professionals'
        ));
    }

    /**
     * Show referral detail
     */
    public function show($id)
    {
        $referral = Referral::with([
            'session',
            'session.seeker',
            'session.helper',
            'session.concern',
            'helper',
            'helper.user',
            'adviser',
            'professional'
        ])->findOrFail($id);

        $this->authorizeReferral($referral);

        // Get the session report if exists
        $sessionReport = $referral->session->report ?? null;

        return view('adviser.referral-detail', compact('referral', 'sessionReport'));
    }

    /**
     * Approve a referral
     */
    public function approve(Request $request, $id)
    {
        $referral = Referral::findOrFail($id);

        $this->authorizeReferral($referral);
        abort_unless($referral->status === Referral::STATUS_PENDING_ADVISER, 409, 'This referral has already been reviewed.');

        $request->validate([
            'professional_id' => 'nullable|exists:psychology_professionals,id',
            'notes' => 'nullable|string|max:500'
        ]);

        // Update referral status
        $referral->update([
            'adviser_id' => Auth::user()->adviser->id ?? null,
            'status' => Referral::STATUS_PENDING_CONSENT,
            'help_seeker_consent' => false,
            'consent_obtained_at' => null,
            'consent_requested_at' => now(),
            'professional_id' => $request->professional_id ?? null,
            'reviewed_at' => now(),
            'approved_at' => now(),
            'review_notes' => $request->notes,
        ]);

        // Create notification for helper
        Notification::create([
            'user_account_id' => $referral->session->seeker->user_account_id,
            'title' => 'Referral consent requested',
            'message' => 'An adviser approved a referral. Please review consent.',
            'notification_type' => 'referral',
            'link' => '/referrals/' . $referral->id . '/identity',
        ]);
        Notification::create([
            'user_account_id' => $referral->helper->user_account_id,
            'title' => 'Referral Approved ✅',
            'message' => 'Your referral has been approved by the adviser.',
            'notification_type' => 'referral',
            'type_icon' => '✅',
            'link' => '/helper/cases'
        ]);

        $this->broadcastSafely(new ReferralApproved($referral, $referral->helper->user_account_id));

        // If professional assigned, notify them
        if ($request->professional_id && $referral->help_seeker_consent) {
            $professional = PsychologyProfessional::find($request->professional_id);
            if ($professional) {
                Notification::create([
                    'user_account_id' => $professional->user_account_id,
                    'title' => 'New Referral Assigned',
                    'message' => 'A referral has been assigned to you for review.',
                    'notification_type' => 'referral',
                    'type_icon' => '📋',
                    'link' => '/professional/dashboard'
                ]);
            }
        }

        return redirect()->route('adviser.referrals')
            ->with('success', 'Referral approved successfully!');
    }

    /**
     * Reject a referral
     */
    public function reject(Request $request, $id)
    {
        $referral = Referral::findOrFail($id);

        $this->authorizeReferral($referral);

        $request->validate([
            'rejection_reason' => 'required|string|max:500'
        ]);

        // Update referral status
        $referral->update([
            'adviser_id' => Auth::user()->adviser->id ?? null,
            'status' => Referral::STATUS_DECLINED,
            'reviewed_at' => now(),
            'declined_at' => now(),
            'decline_reason' => $request->rejection_reason,
            'closed_date' => now(),
        ]);

        // Create notification for helper
        Notification::create([
            'user_account_id' => $referral->helper->user_account_id,
            'title' => 'Referral Declined ❌',
            'message' => 'Your referral has been declined. Reason: ' . $request->rejection_reason,
            'notification_type' => 'referral',
            'type_icon' => '❌',
            'link' => '/helper/cases'
        ]);

        return redirect()->route('adviser.referrals')
            ->with('info', 'Referral declined.');
    }

    /**
     * Request more information
     */
    public function requestInfo(Request $request, $id)
    {
        $referral = Referral::findOrFail($id);

        $this->authorizeReferral($referral);

        $request->validate([
            'info_request' => 'required|string|max:500'
        ]);

        // Create notification for helper
        Notification::create([
            'user_account_id' => $referral->helper->user_account_id,
            'title' => 'Additional Information Requested',
            'message' => 'The adviser needs more information about your referral: ' . $request->info_request,
            'notification_type' => 'referral',
            'type_icon' => '📝',
            'link' => '/helper/cases/' . $referral->session_id
        ]);

        return redirect()->route('adviser.referrals')
            ->with('info', 'Additional information requested from helper.');
    }

    /**
     * Assign a professional to a referral
     */
    public function assignProfessional(Request $request, $id)
    {
        $referral = Referral::findOrFail($id);

        $this->authorizeReferral($referral);
        abort_unless($referral->approved_at && $referral->help_seeker_consent, 409, 'Approval and seeker consent are required.');

        $request->validate([
            'professional_id' => 'required|exists:psychology_professionals,id'
        ]);

        $referral->update([
            'professional_id' => $request->professional_id,
            'status' => Referral::STATUS_PENDING_PROFESSIONAL
        ]);

        // Notify professional
        $professional = PsychologyProfessional::find($request->professional_id);
        if ($professional) {
            Notification::create([
                'user_account_id' => $professional->user_account_id,
                'title' => 'Referral Assigned',
                'message' => 'A referral has been assigned to you.',
                'notification_type' => 'referral',
                'type_icon' => '📋',
                'link' => '/professional/dashboard'
            ]);
        }

        return redirect()->back()
            ->with('success', 'Professional assigned successfully.');
    }

    private function authorizeReferral(Referral $referral): void
    {
        $adviserId = Auth::user()->adviser?->id;

        abort_unless($adviserId, 403);
        $allowed = $referral->adviser_id === $adviserId
            || ($referral->helper_id && \App\Models\Helper::where('id', $referral->helper_id)->where('adviser_id', $adviserId)->exists());

        abort_unless($allowed, 403, 'You are not authorized to manage this referral.');
    }
}
