<?php

namespace App\Services;

use App\Models\HelperSchedule;
use App\Models\Session;
use Illuminate\Database\Eloquent\Model;

/** Archive visibility only; never changes a clinical or scheduling status. */
class AdviserArchive
{
    public function canArchive(Model $record): bool
    {
        if ($record instanceof HelperSchedule) {
            return $record->window()[1]->lte(now());
        }
        if (! $record instanceof Session || ! in_array($record->session_status, ['completed', 'evaluated', 'cancelled', 'no_show'], true)) {
            return false;
        }
        if (in_array($record->session_status, ['completed', 'evaluated'], true) && ! $record->report?->adviser_reviewed) {
            return false;
        }
        return ! $record->emergencyAlerts->contains(fn ($alert) => ! in_array($alert->status, ['resolved', 'closed', 'cancelled', 'archived'], true))
            && ! $record->referrals->contains(fn ($referral) => ! in_array($referral->status, ['closed', 'completed', 'declined'], true))
            && ! $record->incidents()->open()->exists();
    }

    public function authorize(Model $record): void
    {
        $scope = app(AdviserScope::class);
        if ($record instanceof Session) {
            $scope->session($record);
        } else {
            abort_unless($record instanceof HelperSchedule && $record->helper?->adviser_id === $scope->actor()->id, 403);
        }
    }
}
