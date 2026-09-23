<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\EmergencyAlert;
use App\Models\Helper;
use App\Services\AdviserScope;
use App\Services\SupportAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $openAlerts = (clone $baseQuery)->where('status', '!=', 'resolved')->paginate(15, ['*'], 'open_page')->withQueryString();
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

        app(\App\Services\AdviserEmergencyService::class)->record(EmergencyAlert::findOrFail($id),'resolved',$validated['resolution_notes']);

        return redirect()->route('adviser.emergencies')->with('success', 'Emergency action documented and marked resolved.');
    }

    public function action(Request $request,int $id): RedirectResponse {
        $data=$request->validate(['action'=>'required|in:acknowledged,instruction,coordination','notes'=>'required|string|max:2000']);
        app(\App\Services\AdviserEmergencyService::class)->record(EmergencyAlert::findOrFail($id),$data['action'],$data['notes']);
        return back()->with('success','Emergency action recorded.');
    }

    private function authorizeAlert(EmergencyAlert $alert): void
    {
        app(AdviserScope::class)->emergency($alert);
        $adviserId = Auth::user()->adviser->id;
        $helperId = $alert->session?->helper_id;

        abort_unless(
            $alert->adviser_id === $adviserId || ($helperId && $this->helperIds()->contains($helperId)),
            403,
            'You are not authorized to manage this emergency alert.'
        );
    }

    private function helperIds()
    {
        app(AdviserScope::class)->actor();

        return Helper::where('adviser_id', Auth::user()->adviser?->id)->pluck('id');
    }
}
