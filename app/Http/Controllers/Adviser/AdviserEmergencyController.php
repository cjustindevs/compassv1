<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\EmergencyAlert;
use App\Models\Helper;
use App\Models\Notification;
use App\Services\AdviserEmergencyService;
use App\Services\AdviserScope;
use App\Services\SupportAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdviserEmergencyController extends Controller
{
    public function index(): View
    {
        $helperIds = $this->helperIds();

        $baseQuery = EmergencyAlert::with(['session', 'session.seeker', 'session.helper', 'referral'])
            ->where(function ($query) use ($helperIds) {
                $query->where('adviser_id', Auth::user()->adviser?->id)
                    ->orWhereHas('session', fn ($session) => $session->whereIn('helper_id', $helperIds));
            })
            ->latest('triggered_at');

        $openAlerts = (clone $baseQuery)->whereNotIn('status', ['resolved', 'closed'])->paginate(15, ['*'], 'open_page')->withQueryString();
        $resolvedAlerts = (clone $baseQuery)->where('status', 'resolved')->paginate(15, ['*'], 'resolved_page')->withQueryString();
        $totalEmergencies = $baseQuery->count();

        return view('adviser.emergencies', compact('openAlerts', 'resolvedAlerts', 'totalEmergencies'));
    }

    public function show(int $id): View
    {
        $alert = EmergencyAlert::with(['session', 'session.seeker', 'session.helper', 'session.report', 'referral'])->findOrFail($id);
        $this->authorizeAlert($alert);

        SupportAudit::record('emergency_documentation_viewed', $alert, ['purpose' => 'emergency_review']);

        return view('adviser.emergency-detail', compact('alert'));
    }

    public function resolve(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'resolution_notes' => 'required|string|max:1000',
        ]);

        app(AdviserEmergencyService::class)->record(EmergencyAlert::findOrFail($id), 'resolved', $validated['resolution_notes']);

        return redirect()->route('adviser.emergencies')->with('success', 'Emergency action documented and marked resolved.');
    }

    public function action(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['action' => 'required|in:acknowledged,instruction,coordination,rejected', 'notes' => 'required|string|max:2000']);
        app(AdviserEmergencyService::class)->record(EmergencyAlert::findOrFail($id), $data['action'], $data['notes']);

        return back()->with('success', 'Emergency action recorded.');
    }

    private function authorizeAlert(EmergencyAlert $alert): void
    {
        app(AdviserScope::class)->emergency($alert);
        $adviserId = Auth::user()->adviser->id;
        $helperId = $alert->session?->helper_id;

        abort_unless($alert->adviser_id === $adviserId || ($helperId && $this->helperIds()->contains($helperId)), 403, 'You are not authorized to manage this emergency alert.');
    }

    public function requestReferral(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['notes' => 'required|string|min:10|max:2000']);
        $alert = EmergencyAlert::findOrFail($id);
        $this->authorizeAlert($alert);
        DB::transaction(function () use ($alert, $data) {
            $alert = EmergencyAlert::lockForUpdate()->findOrFail($alert->id);
            $this->authorizeAlert($alert);
            abort_if(in_array($alert->status, ['resolved', 'closed']), 409, 'This emergency review has concluded.');
            if (! $alert->session?->isReferralEligible() || ! $alert->session?->helper?->user?->is_active) {
                throw ValidationException::withMessages(['notes' => 'An active assigned Helper and a live or concluded support session are required for this recommendation.']);
            }
            if (DB::table('emergency_review_actions')->where('emergency_alert_id', $alert->id)->where('action', 'referral_requested')->exists()) {
                return;
            }
            app(AdviserEmergencyService::class)->record($alert, 'coordination', $data['notes']);
            DB::table('emergency_review_actions')->insert(['emergency_alert_id' => $alert->id, 'actor_id' => auth()->id(), 'action' => 'referral_requested', 'notes' => $data['notes'], 'created_at' => now()]);
            Notification::create(['user_account_id' => $alert->session->helper->user_account_id, 'title' => 'Urgent referral recommendation requested',
                'message' => 'Your Adviser requested an urgent recommendation for the reported emergency. Open the session and document the recommendation for review.',
                'notification_type' => 'emergency', 'link' => '/helper/chat/'.$alert->session_id.'?referral=1']);
            SupportAudit::record('emergency_referral_requested', $alert);
        });

        return back()->with('success', 'The Helper has been asked to submit the recommendation. Adviser review and Seeker consent remain required.');
    }

    private function helperIds()
    {
        app(AdviserScope::class)->actor();

        return Helper::where('adviser_id', Auth::user()->adviser?->id)->pluck('id');
    }
}
