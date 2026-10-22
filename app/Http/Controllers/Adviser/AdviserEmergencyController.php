<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\EmergencyAlert;
use App\Models\Helper;
use App\Models\Notification;
use App\Models\PsychologyProfessional;
use App\Models\Referral;
use App\Services\AdviserEmergencyService;
use App\Services\AdviserScope;
use App\Services\ReferralManagementService;
use App\Services\SupportAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AdviserEmergencyController extends Controller
{
    public function index(Request $request): View
    {
        $adviser = app(AdviserScope::class)->actor();
        $filters = $request->validate(['tab'=>'nullable|in:active,resolved','q'=>'nullable|string|max:100']);
        $tab = $filters['tab'] ?? 'active';
        $search = trim($filters['q'] ?? '');
        $baseQuery = EmergencyAlert::with(['session.seeker', 'session.helper.adviser', 'adviser', 'referral'])
            ->visibleForAdviser($adviser->id);
        $activeCount = (clone $baseQuery)->whereNotIn('status',['resolved','closed'])->count();
        $resolvedCount = (clone $baseQuery)->where('status','resolved')->count();
        $totalEmergencies = (clone $baseQuery)->count();
        if ($search !== '') $baseQuery->where(function ($q) use ($search) {
            if (ctype_digit(ltrim($search,'#'))) $q->orWhere('id',(int)ltrim($search,'#'));
            $term = '%'.mb_strtolower($search).'%';
            $q->orWhereHas('session.seeker',fn ($s) => $s->whereRaw('LOWER(generated_alias) LIKE ?',[$term]))
                ->orWhereHas('session.helper',fn ($h) => $h->whereRaw('LOWER(first_name) LIKE ?',[$term])->orWhereRaw('LOWER(last_name) LIKE ?',[$term]));
        });

        $openAlerts = (clone $baseQuery)->whereNotIn('status', ['resolved', 'closed'])->latest('triggered_at')->latest('id')->paginate(10, ['*'], 'open_page')->withQueryString();
        $resolvedAlerts = (clone $baseQuery)->where('status', 'resolved')->orderByRaw('CASE WHEN resolved_at IS NULL THEN 1 ELSE 0 END')->latest('resolved_at')->latest('id')->paginate(10, ['*'], 'resolved_page')->withQueryString();

        return view('adviser.emergencies', compact('openAlerts', 'resolvedAlerts', 'totalEmergencies','activeCount','resolvedCount','tab','search'));
    }

    public function show(int $id): View
    {
        $adviser = app(AdviserScope::class)->actor();
        $alert = EmergencyAlert::with(['session.seeker', 'session.helper', 'adviser'])->visibleForAdviser($adviser->id)->findOrFail($id);
        if ((int) $alert->adviser_id !== (int) $adviser->id && (int) $alert->session?->helper?->adviser_id !== (int) $adviser->id) {
            return view('adviser.emergency-coordination', compact('alert'));
        }
        $this->authorizeAlert($alert);
        $alert->load(['session.report', 'session.screeningResponses', 'session.concern', 'referral']);
        $professionals = PsychologyProfessional::where('is_available', true)
            ->whereHas('user', fn ($query) => $query->where('is_active', true)->where('role', 'professional'))
            ->orderBy('first_name')->orderBy('last_name')->orderBy('id')->get();

        SupportAudit::record('emergency_documentation_viewed', $alert, ['purpose' => 'emergency_review']);

        $coordinators = app(\App\Services\EmergencyCoordinator::class)->candidates()->where('id', '!=', $alert->adviser_id ?? 0)
            ->orderBy('first_name')->orderBy('last_name')->get();
        return view('adviser.emergency-detail', compact('alert', 'professionals', 'coordinators'));
    }

    public function transferCoordinator(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['adviser_id' => 'required|integer|exists:advisers,id',
            'expected_adviser_id' => 'present|nullable|integer', 'handoff_reason' => 'required|string|min:10|max:1000']);
        abort_unless(mb_strlen(trim($data['handoff_reason'])) >= 10, 422);
        app(\App\Services\EmergencyCoordinator::class)->transfer(EmergencyAlert::findOrFail($id), (int) $data['adviser_id'],
            isset($data['expected_adviser_id']) ? (int) $data['expected_adviser_id'] : null, trim($data['handoff_reason']));
        return redirect()->route('adviser.emergencies.show', $id)->with('success', 'Emergency coordinator updated. The new coordinator was notified.');
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

    public function raiseToProfessional(Request $request, int $id): RedirectResponse
    {
        $alert = EmergencyAlert::findOrFail($id);
        $this->authorizeAlert($alert);
        $data = $request->validate(['professional_id' => 'required|integer|exists:psychology_professionals,id', 'reason' => 'required|string|min:10|max:1000']);
        try {
            DB::transaction(function () use ($alert, $data) {
                // Match the assignment service's lock order: referral, then emergency.
                abort_unless($alert->referral_id, 409, 'A Helper recommendation must be submitted and approved before Professional assignment.');
                $referral = Referral::lockForUpdate()->findOrFail($alert->referral_id);
                $current = EmergencyAlert::lockForUpdate()->findOrFail($alert->id);
                $this->authorizeAlert($current);
                abort_if(in_array($current->status, ['resolved', 'closed'], true) || $current->archived_at, 409, 'This emergency review has concluded.');
                abort_unless($current->referral_id === $referral->id && $current->session_id === $referral->session_id, 409, 'The linked referral changed. Refresh the emergency review.');
                app(ReferralManagementService::class)->assignProfessional($referral, (int) $data['professional_id'], $data['reason']);
                DB::table('emergency_review_actions')->insert(['emergency_alert_id' => $current->id, 'actor_id' => auth()->id(), 'action' => 'professional_raised', 'notes' => $data['reason'], 'created_at' => now()]);
                SupportAudit::record('emergency_professional_raised', $current, ['referral_id' => $referral->id, 'professional_id' => (int) $data['professional_id']]);
            }, 3);
        } catch (HttpExceptionInterface $exception) {
            if (! in_array($exception->getStatusCode(), [409, 422], true)) {
                throw $exception;
            }
            throw ValidationException::withMessages(['professional_id' => $exception->getMessage() ?: 'The referral is not ready for Professional assignment. Refresh its status and complete the pending steps.']);
        }

        return back()->with('success', 'Emergency raised to the selected Professional. An urgent in-system notification was sent. Identity release remains a separate action.');
    }

    private function helperIds()
    {
        app(AdviserScope::class)->actor();

        return Helper::where('adviser_id', Auth::user()->adviser?->id)->pluck('id');
    }
}
