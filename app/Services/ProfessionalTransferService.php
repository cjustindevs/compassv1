<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\PsychologyProfessional;
use App\Models\Referral;
use App\Models\ReferralAppointment;
use Illuminate\Support\Facades\DB;

class ProfessionalTransferService
{
    public function cancel(Referral $referral): void
    {
        DB::transaction(function () use ($referral) {
            $referral = Referral::lockForUpdate()->findOrFail($referral->id);
            app(AdviserScope::class)->referral($referral);
            $transfer = DB::table('referral_professional_transfers')->where('referral_id', $referral->id)->where('status', 'pending')->lockForUpdate()->first();
            abort_unless($transfer && in_array($referral->status, Referral::ACTIVE_STATUSES, true), 409, 'No active pending transfer to cancel.');
            DB::table('referral_professional_transfers')->where('id', $transfer->id)->update(['status' => 'cancelled', 'responded_at' => now(), 'updated_at' => now()]);
            $recipient = PsychologyProfessional::find($transfer->to_professional_id)?->user_account_id;
            if ($recipient) {
                Notification::create(['user_account_id' => $recipient, 'notification_type' => 'update', 'title' => 'Professional transfer cancelled', 'message' => 'The transfer request was withdrawn. The original Professional remains responsible.', 'link' => '/professional/referrals']);
            }
            SupportAudit::record('professional_transfer_cancelled', $referral, ['transfer_id' => $transfer->id]);
        }, 3);
    }

    public function request(Referral $referral, int $recipient, string $reason): void
    {
        DB::transaction(function () use ($referral, $recipient, $reason) {
            $referral = Referral::lockForUpdate()->findOrFail($referral->id);
            app(AdviserScope::class)->referral($referral);
            abort_unless(mb_strlen(trim($reason)) >= 10 && mb_strlen($reason) <= 1000, 422, 'Provide a transfer reason of 10 to 1000 characters.');
            abort_unless(in_array($referral->status, Referral::ACTIVE_STATUSES, true) && $referral->professional_id && $referral->help_seeker_consent && ! $referral->hasPendingCompletion(), 409, 'Only active cases without a pending completion review can be transferred.');
            abort_if($recipient === $referral->professional_id, 422, 'Select a different Professional.');
            $professional = PsychologyProfessional::whereKey($recipient)->where('is_available', true)->whereHas('user', fn ($q) => $q->where('is_active', true)->where('role', 'professional'))->firstOrFail();
            abort_if(DB::table('referral_professional_transfers')->where('referral_id', $referral->id)->where('status', 'pending')->exists(), 409, 'A transfer is already awaiting a response.');
            $id = DB::table('referral_professional_transfers')->insertGetId(['referral_id' => $referral->id, 'from_professional_id' => $referral->professional_id,
                'to_professional_id' => $recipient, 'requested_by' => auth()->id(), 'reason' => encrypt($reason), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            Notification::create(['user_account_id' => $professional->user_account_id, 'notification_type' => 'referral', 'title' => 'Professional transfer awaiting acceptance',
                'message' => 'An Adviser requested transfer of an active referral. The current Professional remains responsible until you accept.', 'link' => '/professional/transfers/'.$id]);
            $this->notifyOwner($referral, 'Professional transfer requested');
            SupportAudit::record('professional_transfer_requested', $referral, ['transfer_id' => $id, 'to_professional_id' => $recipient]);
        }, 3);
    }

    public function respond(int $id, bool $accept): void
    {
        $row = DB::table('referral_professional_transfers')->find($id);
        abort_unless($row && auth()->user()?->is_active && auth()->user()?->role === 'professional' && auth()->user()->psychologyProfessional?->id === $row->to_professional_id, 403);
        DB::transaction(function () use ($row, $accept) {
            $professional = PsychologyProfessional::lockForUpdate()->findOrFail($row->to_professional_id);
            $referral = Referral::lockForUpdate()->findOrFail($row->referral_id);
            $transfer = DB::table('referral_professional_transfers')->where('id', $row->id)->lockForUpdate()->first();
            abort_unless($transfer->status === 'pending' && $referral->professional_id === $transfer->from_professional_id && in_array($referral->status, Referral::ACTIVE_STATUSES, true) && ! $referral->hasPendingCompletion() && $referral->help_seeker_consent && app(ConsentService::class)->valid($referral->session->seeker, 'referral', $referral->id), 409, 'This transfer is no longer actionable. Refresh the referral status.');
            if ($accept) {
                abort_unless($professional->is_available, 409, 'Set your Professional availability before accepting a transfer.');
                $appointments = ReferralAppointment::where('referral_id', $referral->id)->where('status', 'scheduled')->lockForUpdate()->get();
                foreach ($appointments as $appointment) {
                    abort_if($appointment->starts_at->lte(now()) && $appointment->ends_at->gt(now()), 409, 'An appointment is underway. Complete it before transferring responsibility.');
                    abort_if($appointment->ends_at->gt(now()) && ReferralAppointment::where('professional_id', $professional->id)->where('referral_id', '!=', $referral->id)->where('status', 'scheduled')->where('starts_at', '<', $appointment->ends_at)->where('ends_at', '>', $appointment->starts_at)->exists(), 409, 'A transferred appointment conflicts with your calendar. Coordinate rescheduling before acceptance.');
                }
                $oldUser = $referral->professional?->user_account_id;
                $referral->update(['professional_id' => $professional->id, 'identity_disclosed' => false]);
                $referral->unsetRelation('professional');
                ReferralAppointment::where('referral_id', $referral->id)->where('status', 'scheduled')->where('starts_at', '>', now())->update(['professional_id' => $professional->id]);
                if ($oldUser) {
                    Notification::create(['user_account_id' => $oldUser, 'notification_type' => 'update', 'title' => 'Professional transfer accepted', 'message' => 'The receiving Professional accepted responsibility. Your access to the transferred case has ended.', 'link' => '/professional/referrals']);
                }
                Notification::create(['user_account_id' => $referral->session->seeker->user_account_id, 'notification_type' => 'referral', 'title' => 'Assigned Professional updated', 'message' => 'Your referral Professional changed. Review the assigned Professional and appointment details.', 'link' => '/seeker/referrals']);
            }
            DB::table('referral_professional_transfers')->where('id', $row->id)->update(['status' => $accept ? 'accepted' : 'declined', 'responded_at' => now(), 'updated_at' => now()]);
            $this->notifyOwner($referral, $accept ? 'Professional transfer accepted' : 'Professional transfer declined');
            SupportAudit::record($accept ? 'professional_transfer_accepted' : 'professional_transfer_declined', $referral, ['transfer_id' => $row->id]);
        }, 3);
    }

    private function notifyOwner(Referral $referral, string $title): void
    {
        if ($id = $referral->adviser?->user_account_id) {
            Notification::create(['user_account_id' => $id, 'notification_type' => 'referral', 'title' => $title, 'message' => 'Review the Professional assignment. A new recipient requires a separate authorized identity release.', 'link' => '/adviser/referral/'.$referral->id]);
        }
    }
}
