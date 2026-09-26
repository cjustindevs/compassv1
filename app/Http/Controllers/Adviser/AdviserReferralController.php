<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\Helper;
use App\Models\Notification;
use App\Models\PsychologyProfessional;
use App\Models\Referral;
use App\Models\Session;
use App\Services\AdviserScope;
use App\Services\ReferralManagementService;
use App\Traits\BroadcastsSafely;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdviserReferralController extends Controller
{
    use BroadcastsSafely;

    /**
     * Show the referral queue
     */
    public function index()
    {
        $adviserId = app(AdviserScope::class)->actor()->id;

        // Get pending referrals (awaiting adviser review)
        $pendingReferrals = Referral::with(['session.seeker:id,id,generated_alias', 'helper:id,id,first_name,last_name', 'helper.user:id,id,name'])
            ->whereIn('status', [Referral::STATUS_PENDING_ADVISER,Referral::STATUS_CONSENT_REQUESTED])
            ->forAdviser($adviserId)
            ->orderBy('priority_level', 'desc')
            ->orderBy('created_at', 'asc')
            ->paginate(15,['*'],'pending_page')->withQueryString();

        // Get approved referrals (awaiting professional)
        $approvedReferrals = Referral::with(['session.seeker:id,id,generated_alias', 'helper:id,id,first_name,last_name', 'professional:id,id,first_name,last_name'])
            ->whereIn('status', [Referral::STATUS_PENDING_CONSENT,Referral::STATUS_PENDING_PROFESSIONAL,Referral::STATUS_NO_PROFESSIONAL_AVAILABLE,Referral::STATUS_ACCEPTED,Referral::STATUS_IN_PROGRESS])
            ->forAdviser($adviserId)
            ->orderBy('created_at', 'asc')
            ->paginate(15,['*'],'approved_page')->withQueryString();

        // Get completed referrals
        $completedReferrals = Referral::with(['session.seeker:id,id,generated_alias', 'helper:id,id,first_name,last_name', 'professional:id,id,first_name,last_name'])
            ->whereIn('status', [Referral::STATUS_COMPLETED, Referral::STATUS_CLOSED,Referral::STATUS_DECLINED])
            ->forAdviser($adviserId)
            ->orderBy('updated_at', 'desc')
            ->paginate(15,['*'],'completed_page')->withQueryString();

        // Get statistics
        $totalPending = $pendingReferrals->total();
        $totalApproved = $approvedReferrals->total();
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
            'professional',
        ])->findOrFail($id);

        $this->authorizeReferral($referral);

        // Get the session report if exists
        $sessionReport = $referral->session->report ?? null;

        $professionals = PsychologyProfessional::where('is_available',true)->whereHas('user',fn($q)=>$q->where('is_active',true)->where('role','professional'))->get();
        return view('adviser.referral-detail', compact('referral', 'sessionReport','professionals'));
    }

    /**
     * Approve a referral
     */
    public function approve(Request $request, $id)
    {
        $referral = Referral::findOrFail($id);
        $this->authorizeReferral($referral);
        $data = $request->validate(['review_notes' => 'required|string|max:1000', 'professional_id' => 'prohibited', 'consent_obtained' => 'prohibited']);
        $this->transition(fn () => app(ReferralManagementService::class)->reviewReferral($referral, Auth::user()->adviser, ['approved' => true, 'notes' => $data['review_notes']]), 'review_notes');

        return redirect()->route('adviser.referrals')->with('success', 'Referral approved. The Help Seeker must decide consent before professional assignment.');
    }

    /**
     * Reject a referral
     */
    public function reject(Request $request, $id)
    {
        $referral = Referral::findOrFail($id);
        $this->authorizeReferral($referral);
        $data = $request->validate(['rejection_reason' => 'required|string|min:10|max:1000']);
        $this->transition(fn () => app(ReferralManagementService::class)->clarify($referral, $data['rejection_reason']), 'rejection_reason');

        return redirect()->route('adviser.referrals')->with('info', 'Recommendation returned to the Helper with your comments. Approval remains pending until the revision is reviewed.');
    }

    /**
     * Request more information
     */
    public function requestInfo(Request $request, $id)
    {
        $referral = Referral::findOrFail($id);

        $this->authorizeReferral($referral);

        $request->validate([
            'info_request' => 'required|string|min:10|max:2000',
        ]);

        $this->transition(fn () => app(ReferralManagementService::class)->clarify($referral, $request->info_request), 'info_request');

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
        $data = $request->validate(['professional_id' => 'required|integer|exists:psychology_professionals,id', 'reason' => 'required|string|max:1000']);
        $this->transition(fn () => app(ReferralManagementService::class)->assignProfessional($referral, (int) $data['professional_id'], $data['reason']), 'professional_id');

        return back()->with('success', 'Professional assignment recorded.');
    }

    private function transition(callable $action, string $field): void
    {
        try {
            $action();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            // Browser forms should explain unmet workflow prerequisites on the page.
            // Keep authorization failures and unexpected server errors unchanged.
            if (!in_array($exception->getStatusCode(), [409, 422], true)) {
                throw $exception;
            }
            throw \Illuminate\Validation\ValidationException::withMessages([
                $field => $exception->getMessage() ?: 'This referral has changed. Refresh its status before trying again.',
            ]);
        }
    }

    private function authorizeReferral(Referral $referral): void
    {
        app(AdviserScope::class)->referral($referral);
    }
}
