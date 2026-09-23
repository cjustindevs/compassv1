<?php

namespace App\Policies;

use App\Models\AdviserFeedback;
use App\Models\Helper;
use App\Models\HelperCompetencyHistory;
use App\Models\HelperSchedule;
use App\Models\User;

class HelperRecordPolicy
{
    public function view(User $user, $record): bool
    {
        if ($user->role !== 'helper' || ! $user->is_active || ! $user->helper) {
            return false;
        }
        $helperId = $record instanceof Helper ? $record->id : ($record->helper_id ?? $record->session?->helper_id ?? $record->report?->session?->helper_id);

        return (int) $helperId === (int) $user->helper->id;
    }

    public function update(User $user, $record): bool
    {
        return ! ($record instanceof HelperCompetencyHistory || $record instanceof HelperSchedule || $record instanceof AdviserFeedback) && $this->view($user, $record);
    }
}
