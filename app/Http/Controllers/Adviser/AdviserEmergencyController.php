<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\EmergencyAlert;
use App\Models\Helper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdviserEmergencyController extends Controller
{
    public function index(): View
    {
        $helperIds = $this->helperIds();

        $alerts = EmergencyAlert::with(['session', 'session.seeker', 'session.helper', 'referral'])
            ->where(function ($query) use ($helperIds) {
                $query->where('adviser_id', Auth::user()->adviser?->id)
                    ->orWhereHas('session', fn ($session) => $session->whereIn('helper_id', $helperIds));
            })
            ->latest('triggered_at')
            ->get();

        $openAlerts = $alerts->where('status', '!=', 'resolved');
        $resolvedAlerts = $alerts->where('status', 'resolved');
        $totalEmergencies = $alerts->count();

        return view('adviser.emergencies', compact('openAlerts', 'resolvedAlerts', 'totalEmergencies'));
    }

    public function show(int $id): View
    {
        $alert = EmergencyAlert::with(['session', 'session.seeker', 'session.helper', 'session.messages', 'referral'])->findOrFail($id);
        $this->authorizeAlert($alert);

        return view('adviser.emergency-detail', compact('alert'));
    }

    public function resolve(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'resolution_notes' => 'required|string|max:1000',
        ]);

        $alert = EmergencyAlert::findOrFail($id);
        $this->authorizeAlert($alert);

        $alert->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_notes' => $validated['resolution_notes'],
        ]);

        return redirect()->route('adviser.emergencies')->with('success', 'Emergency action documented and marked resolved.');
    }

    private function authorizeAlert(EmergencyAlert $alert): void
    {
        $adviserId = Auth::user()->adviser?->id;
        $helperId = $alert->session?->helper_id;

        abort_unless(
            $alert->adviser_id === $adviserId || ($helperId && $this->helperIds()->contains($helperId)),
            403,
            'You are not authorized to manage this emergency alert.'
        );
    }

    private function helperIds()
    {
        return Helper::where('adviser_id', Auth::user()->adviser?->id)->pluck('id');
    }
}
