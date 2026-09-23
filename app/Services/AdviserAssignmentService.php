<?php

namespace App\Services;

use App\Models\Adviser;
use App\Models\Helper;
use App\Models\Notification;
use App\Models\Referral;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdviserAssignmentService
{
    public function transfer(array $helperIds, int $targetId, string $reason): void
    {
        $actor = app(AdviserScope::class)->actor();
        abort_unless(trim($reason) !== '' && mb_strlen($reason) <= 1000, 422);
        abort_if($actor->id === $targetId, 422, 'Choose a different adviser.');
        DB::transaction(function () use ($actor, $helperIds, $targetId, $reason) {
            $advisers = Adviser::whereIn('id', [$actor->id, $targetId])->orderBy('id')->lockForUpdate()->get();
            $target = $advisers->firstWhere('id', $targetId);
            abort_unless($target && $target->user?->is_active && $target->user?->role === 'adviser', 422);
            $helpers = Helper::whereIn('id', $helperIds)->orderBy('id')->lockForUpdate()->get();
            abort_unless($helpers->count() === count(array_unique($helperIds)) && $helpers->isNotEmpty(), 422);
            abort_if($helpers->contains(fn ($helper) => $helper->adviser_id !== $actor->id), 403);
            if (Helper::where('adviser_id', $targetId)->count() + $helpers->count() > Helper::MAX_HELPERS_PER_ADVISER) {
                throw ValidationException::withMessages(['adviser_id' => 'The receiving Adviser has insufficient supervision capacity (maximum 15 Helpers).']);
            }
            foreach ($helpers as $helper) {
                $helper->assignmentReason=$reason;
                $helper->update(['adviser_id' => $targetId]);
                foreach (Referral::where('helper_id', $helper->id)->where('adviser_id', $actor->id)
                    ->whereNotIn('status', ['completed', 'closed', 'declined'])->lockForUpdate()->get() as $referral) {
                    $referral->update(['adviser_id' => $targetId]);
                    SupportAudit::record('referral_supervision_transferred', $referral, ['previous_adviser_id' => $actor->id, 'adviser_id' => $targetId, 'reason' => $reason]);
                }
                SupportAudit::record('helper_supervision_transferred', $helper, ['previous_adviser_id' => $actor->id, 'adviser_id' => $targetId, 'reason' => $reason]);
                foreach (array_unique([$helper->user_account_id, $target->user_account_id, $actor->user_account_id]) as $userId) {
                    Notification::create(['user_account_id' => $userId, 'title' => 'Supervision updated', 'message' => 'A Helper supervision assignment has changed.', 'notification_type' => 'system', 'link' => $userId === $helper->user_account_id ? '/helper/profile' : '/adviser/helpers']);
                }
            }
        }, 3);
    }
}
