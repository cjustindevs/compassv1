<?php

namespace App\Http\Controllers\Professional;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalNote;
use App\Models\Referral;
use App\Services\ReferralManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class ProfessionalCaseController extends Controller
{
    private function caseId($reference): int
    {
        if (ctype_digit((string) $reference) && strlen((string) $reference) < 20) {
            return (int) $reference;
        }
        try {
            $value = Crypt::decryptString(hex2bin($reference));
            abort_unless(ctype_digit($value), 404);

            return (int) $value;
        } catch (\Throwable $e) {
            abort(404);
        }
    }

    public function index()
    {
        $professional = Auth::user()->psychologyProfessional;

        if (! $professional) {
            abort(403, 'No psychology professional profile found for this account.');
        }

        $cases = Referral::with(['session.seeker:id,id,generated_alias', 'professionalNotes'])
            ->professionalAuthorized()->where('professional_id', $professional->id)
            ->whereIn('status', Referral::ACTIVE_STATUSES)
            ->orderByDesc('updated_at')
            ->limit(50)
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
            ->professionalAuthorized()->where('professional_id', $professional->id)
            ->whereIn('status', array_merge(Referral::ACTIVE_STATUSES, Referral::COMPLETED_STATUSES))
            ->findOrFail($this->caseId($id));

        if (ctype_digit((string) $id) && strlen((string) $id) < 20) {
            return redirect()->route('professional.cases.show', $case->case_reference);
        }

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
            'status' => 'required|in:'.Referral::STATUS_IN_PROGRESS.','.Referral::STATUS_COMPLETED.','.Referral::STATUS_CLOSED,
            'outcome' => 'nullable|string|min:10|max:2000', 'follow_up_required' => 'nullable|boolean', 'follow_up_notes' => 'nullable|string|max:2000',
        ]);

        $professional = Auth::user()->psychologyProfessional;

        $case = Referral::professionalAuthorized()->where('professional_id', $professional->id)
            ->findOrFail($this->caseId($id));

        app(ReferralManagementService::class)->updateReferralOutcome($case, $validated);

        $messages = [
            Referral::STATUS_IN_PROGRESS => 'Case marked as in progress.',
            Referral::STATUS_COMPLETED => 'Completion report submitted. Awaiting Adviser resolution.',
            Referral::STATUS_CLOSED => 'Completion report submitted. Awaiting Adviser resolution.',
        ];

        return redirect()->route('professional.cases.show', $case->case_reference)
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

        $case = Referral::professionalAuthorized()->where('professional_id', $professional->id)
            ->findOrFail($this->caseId($id));

        DB::transaction(function () use ($case, $professional, $validated) {
            // Serialize notes against completion submission and confirmation.
            $case = Referral::lockForUpdate()->findOrFail($case->id);
            abort_unless($case->professional_id === $professional->id && $case->approved_at && $case->help_seeker_consent, 409);
            // A concluded case must not accrue new clinical notes, which would
            // rewrite the record of work that already ended.
            abort_unless(
                in_array($case->status, [Referral::STATUS_ACCEPTED, Referral::STATUS_IN_PROGRESS], true),
                409,
                'Notes can only be added while the case is active.'
            );

            abort_if($case->hasPendingCompletion(), 409, 'Awaiting Adviser completion review. Follow-up notes can be added after the Adviser requests follow-up.');

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
                app(ReferralManagementService::class)->updateReferralOutcome($case, ['status' => Referral::STATUS_IN_PROGRESS]);
            }

        }, 3);

        return redirect()->route('professional.cases.show', $case->case_reference)
            ->with('success', 'Intervention note recorded successfully.');
    }
}
