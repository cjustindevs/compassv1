<?php

namespace App\Services;

use App\Models\{Notification, ScreeningResponse, Session, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** One follow-up on the original single-question intake, not a conversation. */
class ScreeningFollowUp
{
    public function request(User $user, Session $session, ?string $note): bool
    {
        return DB::transaction(function () use ($user, $session, $note) {
            $session = Session::lockForUpdate()->findOrFail($session->id);
            abort_unless($user->role === 'adviser' && $user->is_active && $user->adviser
                && (int) $session->review_adviser_id === (int) $user->adviser->id, 403);
            $original = $this->pendingOriginal($session);
            if ($original->follow_up_requested_at) return false;

            $original->update(['follow_up_note'=>trim($note ?? '') ?: null,
                'follow_up_requested_by'=>$user->id, 'follow_up_requested_at'=>now()]);
            Notification::create(['user_account_id'=>$session->seeker->user_account_id,
                'title'=>'Adviser follow-up requested', 'message'=>'Your Adviser has one follow-up question. Open your support request to respond.',
                'notification_type'=>'system', 'link'=>route('seeker.requests.show', $session, false)]);
            SupportAudit::record('screening_follow_up_requested', $original);
            return true;
        });
    }

    public function respond(User $user, Session $session, int $screeningId, string $answer): bool
    {
        return DB::transaction(function () use ($user, $session, $screeningId, $answer) {
            $session = Session::lockForUpdate()->findOrFail($session->id);
            abort_unless($user->role === 'seeker' && $user->is_active && $user->helpSeeker
                && (int) $session->seeker_id === (int) $user->helpSeeker->id, 403);
            $original = $this->pendingOriginal($session);
            if ($original->id !== $screeningId || !$original->follow_up_requested_at) {
                throw ValidationException::withMessages(['follow_up_answer'=>'This follow-up is no longer available. Refresh your request.']);
            }
            if ($original->follow_up_answered_at) {
                if ($original->follow_up_answer === $answer) return false;
                throw ValidationException::withMessages(['follow_up_answer'=>'Your response has already been saved. It cannot be replaced by another submission.']);
            }
            $original->update(['follow_up_answer'=>$answer, 'follow_up_answered_at'=>now()]);
            $adviserUser = $session->reviewAdviser?->user_account_id;
            if ($adviserUser) Notification::create(['user_account_id'=>$adviserUser,
                'title'=>'Screening follow-up received', 'message'=>'A Seeker responded to your follow-up. Review the request before recording a decision.',
                'notification_type'=>'system', 'link'=>'/adviser/screenings#screening-'.$session->id]);
            // Sensitive answers stay in the screening record, outside notification/audit text.
            SupportAudit::record('screening_follow_up_answered', $original);
            return true;
        });
    }

    private function pendingOriginal(Session $session): ScreeningResponse
    {
        $original = $session->screeningResponses()->oldest('id')->lockForUpdate()->first();
        if (($session->workflow_state !== 'adviser_review_required' && !$session->permitsParallelScreeningSupport()) || !$session->requires_adviser_review
            || in_array($session->session_status, ['completed','evaluated','cancelled','no_show'], true)
            || $session->report?->reassessment_requested_at
            || $session->screeningResponses()->where('review_status','reviewed')->exists()
            || !$original || $original->instrument_version !== CompactScreening::SINGLE_VERSION
            || ($original->responses['suicidal_thoughts'] ?? null) !== 'prefer_not_to_say') {
            throw ValidationException::withMessages(['follow_up_answer'=>'This request is not awaiting a single-question follow-up. Refresh its status.']);
        }
        return $original;
    }
}
