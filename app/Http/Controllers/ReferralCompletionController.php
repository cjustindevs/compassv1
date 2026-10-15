<?php

namespace App\Http\Controllers;

use App\Models\Adviser;
use App\Models\Referral;
use App\Models\ReferralCompletionReview;
use App\Services\ReferralCompletionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ReferralCompletionController extends Controller
{
    public function submit(Request $request, Referral $referral, ReferralCompletionService $service)
    {
        $this->transition(fn () => $service->submit($referral, $request->only(['outcome', 'follow_up_required', 'follow_up_notes'])));

        return back()->with('success', 'Completion report submitted. Awaiting Adviser resolution; the referral and any emergency remain open.');
    }

    public function review(Request $request, ReferralCompletionReview $review, ReferralCompletionService $service)
    {
        $this->transition(fn () => $service->review($review, $request->only(['decision', 'review_notes', 'appointments_action', 'appointments_reason'])));

        return back()->with('success', $request->input('decision') === 'confirmed' ? 'Referral completion confirmed. Any linked emergency is resolved separately.' : 'Follow-up requested. The Professional has been notified.');
    }

    public function assign(Request $request, Referral $referral, ReferralCompletionService $service)
    {
        $data = $request->validate(['adviser_id' => 'required|integer|exists:advisers,id']);
        $this->transition(fn () => $service->assignReviewer($referral, Adviser::findOrFail($data['adviser_id'])));

        return back()->with('success', 'Completion reviewer assigned and notified.');
    }

    private function transition(callable $action): void
    {
        try {
            $action();
        } catch (HttpExceptionInterface $e) {
            if (! in_array($e->getStatusCode(), [409, 422], true)) {
                throw $e;
            }throw ValidationException::withMessages(['completion' => $e->getMessage() ?: 'Refresh the case before trying this action again.']);
        }
    }
}
