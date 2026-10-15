<?php

namespace App\Services;

use App\Events\ReferralCompleted;
use App\Models\Adviser;
use App\Models\EmergencyAlert;
use App\Models\Notification;
use App\Models\Referral;
use App\Models\ReferralCompletionReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReferralCompletionService
{
    public function submit(Referral $referral, array $data): ReferralCompletionReview
    {
        abort_unless(auth()->user()?->is_active && auth()->user()->role === 'professional', 403);
        foreach (['outcome', 'follow_up_notes'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }
        $data = Validator::make($data, [
            'outcome' => 'required|string|min:10|max:2000', 'follow_up_required' => 'required|boolean',
            'follow_up_notes' => 'nullable|required_if:follow_up_required,1,true|string|min:10|max:2000',
        ])->validate();

        return DB::transaction(function () use ($referral, $data) {
            $referral = Referral::lockForUpdate()->findOrFail($referral->id);
            abort_unless($referral->professional_id && auth()->user()->psychologyProfessional?->id === $referral->professional_id, 403);
            $this->requireActive($referral);
            $last = $referral->completionReviews()->latest('id')->first();
            if ($last && $last->review_status === 'pending' && $last->professional_id === $referral->professional_id) {
                return $last;
            }
            if ($last && $last->review_status === 'pending') {
                $last->update(['review_status' => 'superseded']);
            }
            $report = $referral->completionReviews()->create([
                'professional_id' => $referral->professional_id, 'submitted_by' => auth()->id(),
                'summary' => trim($data['outcome']), 'follow_up_required' => (bool) $data['follow_up_required'],
                'follow_up_notes' => $data['follow_up_notes'] ?? null, 'review_status' => 'pending',
            ]);
            $this->notifyReviewer($referral);
            foreach (EmergencyAlert::where('referral_id', $referral->id)->whereNotIn('status', ['resolved', 'closed'])->whereNull('archived_at')->get() as $alert) {
                if ($alert->adviser_id !== $referral->adviser_id && $alert->adviser?->user?->is_active) {
                    $this->notify($alert->adviser->user_account_id, 'Professional completion submitted', 'A linked referral has a completion report. Review the emergency separately.', '/adviser/emergencies/'.$alert->id);
                }
            }
            SupportAudit::record('referral_completion_submitted', $report, ['referral_id' => $referral->id]);

            return $report;
        }, 3);
    }

    public function review(ReferralCompletionReview $report, array $data): void
    {
        $actor = app(AdviserScope::class)->actor();
        foreach (['review_notes', 'appointments_reason'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }
        $data = Validator::make($data, ['decision' => 'required|in:confirmed,follow_up_requested', 'review_notes' => 'required|string|min:10|max:2000',
            'appointments_action' => 'nullable|in:completed,cancelled', 'appointments_reason' => 'nullable|string|min:10|max:1000',
        ])->validate();
        DB::transaction(function () use ($report, $data, $actor) {
            $referral = Referral::lockForUpdate()->findOrFail($report->referral_id);
            abort_unless($referral->adviser_id === $actor->id, 403);
            $report = ReferralCompletionReview::lockForUpdate()->findOrFail($report->id);
            $this->requireActive($referral);
            abort_unless($referral->professional_id === $report->professional_id, 409, 'The Professional assignment changed. A current completion report is required.');
            abort_unless($referral->completionReviews()->latest('id')->value('id') === $report->id, 409, 'A newer completion report needs review.');
            if ($report->review_status === $data['decision']) {
                return;
            }
            abort_unless($report->review_status === 'pending', 409, 'This completion report has already been reviewed.');
            if ($data['decision'] === 'confirmed') {
                if (($report->follow_up_required || $referral->professionalNotes()->whereDate('follow_up_date', '>=', now('Asia/Manila')->toDateString())->exists()) && empty($data['appointments_reason'])) {
                    throw ValidationException::withMessages(['appointments_reason' => 'Document how the outstanding follow-up will be handled before confirming completion.']);
                }
                $appointments = $referral->appointments()->where('status', 'scheduled')->lockForUpdate()->get();
                if ($appointments->isNotEmpty()) {
                    if (empty($data['appointments_action']) || empty($data['appointments_reason'])) {
                        throw ValidationException::withMessages(['appointments_action' => 'Address outstanding appointments and document the disposition before completing this referral.']);
                    }
                    if ($data['appointments_action'] === 'completed' && $appointments->contains(fn ($row) => $row->ends_at->isFuture())) {
                        throw ValidationException::withMessages(['appointments_action' => 'A future or ongoing appointment cannot be marked completed. Choose cancellation with a reason or request follow-up.']);
                    }
                    foreach ($appointments as $appointment) {
                        $appointment->update(['status' => $data['appointments_action']]);
                        SupportAudit::record('appointment_completion_disposition', $appointment, ['disposition' => $data['appointments_action'], 'reason_documented' => true]);
                    }
                }
                $referral->update(['status' => Referral::STATUS_COMPLETED, 'completed_at' => now(), 'closed_date' => now(), 'outcome' => 'Professional completion report #'.$report->id.' confirmed by Adviser.',
                    'follow_up_required' => $report->follow_up_required, 'follow_up_notes' => $report->follow_up_required ? 'See authorized completion report #'.$report->id : null]);
                foreach (DB::table('referral_professional_transfers')->where('referral_id', $referral->id)->where('status', 'pending')->lockForUpdate()->get() as $transfer) {
                    DB::table('referral_professional_transfers')->where('id', $transfer->id)->update(['status' => 'cancelled', 'responded_at' => now(), 'updated_at' => now()]);
                    $recipient = \App\Models\PsychologyProfessional::find($transfer->to_professional_id)?->user_account_id;
                    if ($recipient) $this->notify($recipient, 'Professional transfer cancelled', 'The Adviser confirmed case completion before the transfer was accepted. No transfer action remains.', '/professional/transfers/'.$transfer->id);
                    SupportAudit::record('professional_transfer_cancelled_on_completion', $referral, ['transfer_id' => $transfer->id]);
                }
            }
            $report->update(['review_status' => $data['decision'], 'reviewed_by' => auth()->id(), 'review_notes' => $data['review_notes'], 'reviewed_at' => now(), 'appointment_disposition_notes' => $data['appointments_reason'] ?? null]);
            $this->notify($referral->professional->user_account_id, $data['decision'] === 'confirmed' ? 'Referral completion confirmed' : 'Referral follow-up requested', 'Your Adviser reviewed your completion report. Open the case to see the decision.', '/professional/referral/'.$referral->id);
            if ($data['decision'] === 'confirmed') {
                foreach (array_filter([$referral->session?->seeker?->user_account_id, $referral->helper?->user_account_id]) as $recipient) {
                    $this->notify($recipient, 'Referral support completed', 'The Adviser confirmed completion of the referral support. Any linked emergency is reviewed separately.', $recipient === $referral->helper?->user_account_id ? '/helper/cases' : '/seeker/referrals');
                }
                DB::afterCommit(fn () => event(new ReferralCompleted($referral)));
            }
            SupportAudit::record('referral_completion_'.$data['decision'], $report, ['referral_id' => $referral->id]);
        }, 3);
    }

    public function assignReviewer(Referral $referral, Adviser $adviser): void
    {
        abort_unless(auth()->user()?->is_active && in_array(auth()->user()->role, ['moderator', 'admin'], true), 403);
        DB::transaction(function () use ($referral, $adviser) {
            $referral = Referral::lockForUpdate()->findOrFail($referral->id);
            $this->requireActive($referral);
            abort_if($referral->adviser?->user?->is_active && $referral->adviser->user->role === 'adviser', 409, 'An active referral Adviser is already assigned.');
            abort_unless($adviser->user?->is_active && $adviser->user->role === 'adviser', 422);
            abort_unless($referral->completionReviews()->where('review_status', 'pending')->exists(), 409);
            $referral->update(['adviser_id' => $adviser->id]);
            $this->notifyReviewer($referral->fresh());
            SupportAudit::record('completion_reviewer_assigned', $referral, ['adviser_id' => $adviser->id]);
        }, 3);
    }

    private function requireActive(Referral $referral): void
    {
        abort_unless($referral->approved_at && $referral->help_seeker_consent && in_array($referral->status, Referral::ACTIVE_STATUSES, true), 409, 'This referral is no longer active or authorized. Refresh its status.');
    }

    private function notifyReviewer(Referral $referral): void
    {
        if ($referral->adviser?->user?->is_active && $referral->adviser->user->role === 'adviser') {
            $this->notify($referral->adviser->user_account_id, 'Referral completion review requested', 'A Professional submitted a completion report. Review it before confirming referral completion.', '/adviser/referral/'.$referral->id);
        } else {
            foreach (User::where('is_active', true)->whereIn('role', ['moderator', 'admin'])->pluck('id') as $id) {
                $this->notify($id, 'Completion reviewer assignment needed', 'A referral completion report needs an active Adviser. Open the assignment queue.', '/moderator/referrals/unassigned');
            }
        }
    }

    private function notify(int $userId,string $title,string $message,string $link): void
    {
        Notification::create(['user_account_id' => $userId, 'title' => $title, 'message' => $message, 'link' => $link, 'notification_type' => 'referral', 'status' => 'unread']);
    }
}
