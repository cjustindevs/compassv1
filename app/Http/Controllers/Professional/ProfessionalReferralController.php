<?php

namespace App\Http\Controllers\Professional;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Referral;
use App\Models\SessionReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfessionalReferralController extends Controller
{
    public function schedule(Request $request, Referral $referral)
    {
        $data = $request->validate(['starts_at'=>'required|date_format:Y-m-d\\TH:i','ends_at'=>'required|date_format:Y-m-d\\TH:i','meeting_details'=>'required|string|max:2000']);
        app(\App\Services\ReferralAppointmentService::class)->schedule($referral, $data);
        return back()->with('success','Appointment saved. The seeker has been notified.');
    }

    public function index()
    {
        $professional = Auth::user()->psychologyProfessional;

        if (! $professional) {
            abort(403, 'No psychology professional profile found for this account.');
        }

        $referrals = Referral::with(['session.seeker:id,id,generated_alias', 'helper:id,id,first_name,last_name', 'adviser:id,id,first_name,last_name'])
            ->professionalAuthorized()->where('professional_id', $professional->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        // Group by pipeline stage
        $pending = $referrals->where('status', Referral::STATUS_PENDING_PROFESSIONAL);
        $accepted = $referrals->where('status', Referral::STATUS_ACCEPTED);
        $inProgress = $referrals->where('status', Referral::STATUS_IN_PROGRESS);
        $completed = $referrals->whereIn('status', Referral::COMPLETED_STATUSES);
        $declined = $referrals->where('status', Referral::STATUS_DECLINED);

        return view('professional.referrals', compact(
            'referrals',
            'pending',
            'accepted',
            'inProgress',
            'completed',
            'declined'
        ));
    }

    public function show($id)
    {
        $professional = Auth::user()->psychologyProfessional;

        if (! $professional) {
            abort(403, 'No psychology professional profile found for this account.');
        }

        $referral = Referral::with([
            'session',
            'session.seeker',
            'session.helper',
            'session.concern',
            'helper',
            'adviser',
            'professional',
            'professionalNotes',
        ])
            ->professionalAuthorized()->where('professional_id', $professional->id)
            ->findOrFail($id);

        // Session documentation from the helper flow
        $sessionReports = SessionReport::where('session_id', $referral->session_id)
            ->orderByDesc('created_at')
            ->get();

        return view('professional.referral-detail', compact('referral', 'sessionReports'));
    }

    public function accept($id)
    {
        $professional = Auth::user()->psychologyProfessional;

        $referral = Referral::professionalAuthorized()->where('professional_id', $professional->id)
            ->where('status', Referral::STATUS_PENDING_PROFESSIONAL)
            ->findOrFail($id);

        app(\App\Services\ReferralManagementService::class)->acceptReferral($referral, $professional);

        $alias = $referral->session?->seeker?->generated_alias ?? 'Anonymous';

        // Notify the adviser
        if ($referral->adviser?->user_account_id) {
            Notification::create([
                'user_account_id' => $referral->adviser->user_account_id,
                'title' => 'Referral Accepted',
                'message' => 'A professional has accepted the referral for ' . $alias . '.',
                'notification_type' => 'referral',
                'type_icon' => 'fa-circle-check',
                'link' => '/adviser/referral/' . $referral->id,
            ]);
        }

        // Notify the helper who made the referral
        if ($referral->helper?->user_account_id) {
            Notification::create([
                'user_account_id' => $referral->helper->user_account_id,
                'title' => 'Referral Accepted',
                'message' => 'A psychology professional accepted your referral for ' . $alias . '.',
                'notification_type' => 'referral',
                'type_icon' => 'fa-circle-check',
                'link' => '/helper/cases',
            ]);
        }

        return redirect()->route('professional.referrals')
            ->with('success', 'Referral accepted successfully. Start the case when ready.');
    }

    public function decline(Request $request, $id)
    {
        $validated = $request->validate([
            'decline_reason' => 'required|string|max:500',
        ]);

        $professional = Auth::user()->psychologyProfessional;

        $referral = Referral::professionalAuthorized()->where('professional_id', $professional->id)
            ->where('status', Referral::STATUS_PENDING_PROFESSIONAL)
            ->findOrFail($id);

        app(\App\Services\ReferralManagementService::class)->declineProfessional($referral, $validated['decline_reason']);

        return redirect()->route('professional.referrals')
            ->with('info', 'Referral declined. The adviser has been notified.');
    }

    public function startCase($id)
    {
        $professional = Auth::user()->psychologyProfessional;

        $referral = Referral::professionalAuthorized()->where('professional_id', $professional->id)
            ->where('status', Referral::STATUS_ACCEPTED)
            ->findOrFail($id);

        app(\App\Services\ReferralManagementService::class)->updateReferralOutcome($referral, ['status'=>Referral::STATUS_IN_PROGRESS]);

        // Notify the adviser that work has begun
        if ($referral->adviser?->user_account_id) {
            Notification::create([
                'user_account_id' => $referral->adviser->user_account_id,
                'title' => 'Case Started',
                'message' => 'Professional intervention has started for ' . ($referral->session?->seeker?->generated_alias ?? 'Anonymous') . '.',
                'notification_type' => 'referral',
                'type_icon' => '▶️',
                'link' => '/adviser/referral/' . $referral->id,
            ]);
        }

        return redirect()->route('professional.cases')
            ->with('success', 'Case started successfully.');
    }
}
