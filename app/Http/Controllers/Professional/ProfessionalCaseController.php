<?php

namespace App\Http\Controllers\Professional;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalNote;
use App\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfessionalCaseController extends Controller
{
    public function index()
    {
        $professional = Auth::user()->psychologyProfessional;

        if (! $professional) {
            abort(403, 'No psychology professional profile found for this account.');
        }

        $cases = Referral::with(['session', 'session.seeker', 'professionalNotes'])
            ->where('professional_id', $professional->id)
            ->whereIn('status', Referral::ACTIVE_STATUSES)
            ->orderByDesc('updated_at')
            ->get();

        return view('professional.cases', compact('cases'));
    }

    public function show($id)
    {
        $professional = Auth::user()->psychologyProfessional;

        if (! $professional) {
            abort(403, 'No psychology professional profile found for this account.');
        }

        $case = Referral::with([
            'session',
            'session.seeker',
            'session.helper',
            'session.concern',
            'helper',
            'adviser',
            'professionalNotes',
        ])
            ->where('professional_id', $professional->id)
            ->whereIn('status', Referral::ACTIVE_STATUSES)
            ->findOrFail($id);

        // Intervention history (chronological)
        $notes = ProfessionalNote::where('referral_id', $case->id)
            ->orderByDesc('created_at')
            ->get();

        // Next scheduled follow-up from the latest note with a future date
        $nextFollowUp = ProfessionalNote::where('referral_id', $case->id)
            ->whereNotNull('follow_up_date')
            ->where('follow_up_date', '>=', now()->toDateString())
            ->orderByDesc('follow_up_date')
            ->value('follow_up_date');

        return view('professional.case-detail', compact('case', 'notes', 'nextFollowUp'));
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:' . Referral::STATUS_IN_PROGRESS . ',' . Referral::STATUS_COMPLETED . ',' . Referral::STATUS_CLOSED,
        ]);

        $professional = Auth::user()->psychologyProfessional;

        $case = Referral::where('professional_id', $professional->id)
            ->findOrFail($id);

        $case->update([
            'status' => $validated['status'],
            'closed_date' => in_array($validated['status'], Referral::COMPLETED_STATUSES, true) ? now() : null,
        ]);

        // Notify the adviser when a case is finished
        if (in_array($validated['status'], Referral::COMPLETED_STATUSES, true) && $case->adviser?->user_account_id) {
            \App\Models\Notification::create([
                'user_account_id' => $case->adviser->user_account_id,
                'title' => 'Case ' . ($validated['status'] === Referral::STATUS_COMPLETED ? 'Completed' : 'Closed'),
                'message' => 'Professional intervention for ' . ($case->session?->seeker?->generated_alias ?? 'Anonymous') . ' has been ' . ($validated['status'] === Referral::STATUS_COMPLETED ? 'completed' : 'closed') . '.',
                'notification_type' => 'referral',
                'type_icon' => '🏁',
                'link' => '/adviser/referral/' . $case->id,
            ]);
        }

        $messages = [
            Referral::STATUS_IN_PROGRESS => 'Case marked as in progress.',
            Referral::STATUS_COMPLETED => 'Case marked as completed.',
            Referral::STATUS_CLOSED => 'Case closed.',
        ];

        return redirect()->route('professional.cases.show', $case->id)
            ->with('success', $messages[$validated['status']]);
    }

    public function addNotes(Request $request, $id)
    {
        $validated = $request->validate([
            'intervention_type' => 'required|string|max:50',
            'notes' => 'required|string|max:5000',
            'follow_up_plan' => 'nullable|string|max:5000',
            'follow_up_date' => 'nullable|date|after_or_equal:today',
        ]);

        $professional = Auth::user()->psychologyProfessional;

        $case = Referral::where('professional_id', $professional->id)
            ->findOrFail($id);

        ProfessionalNote::create([
            'referral_id' => $case->id,
            'professional_id' => $professional->id,
            'session_id' => $case->session_id,
            'intervention_type' => $validated['intervention_type'],
            'notes' => $validated['notes'],
            'follow_up_plan' => $validated['follow_up_plan'] ?? null,
            'follow_up_date' => $validated['follow_up_date'] ?? null,
        ]);

        // Opening a case for the first time moves it to in_progress
        if ($case->status === Referral::STATUS_ACCEPTED) {
            $case->update(['status' => Referral::STATUS_IN_PROGRESS]);
        }

        return redirect()->route('professional.cases.show', $case->id)
            ->with('success', 'Intervention note recorded successfully.');
    }
}
