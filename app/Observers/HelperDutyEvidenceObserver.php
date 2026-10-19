<?php

namespace App\Observers;

use App\Models\{Helper, HelperSchedule, ReadinessCheck, User};
use App\Services\HelperDutyEvidence;
use Illuminate\Database\Eloquent\Model;

class HelperDutyEvidenceObserver
{
    public function saved(Model $model): void
    {
        // Profile, password and heartbeat updates do not change duty evidence.
        if ($model instanceof User && !$model->wasChanged(['is_active','role'])) return;
        if ($model instanceof Helper && !$model->wasRecentlyCreated && !$model->wasChanged([
            'availability','status','adviser_id','verification_status','verified_at','verified_by','training_verified','is_under_review',
        ])) return;
        $this->record($model);
    }
    public function deleted(Model $model): void { $this->record($model); }

    private function record(Model $model): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('helper_service_intervals')) return;
        $query = Helper::query();
        if ($model instanceof Helper) $query->whereKey($model->id);
        elseif ($model instanceof User) $query->where(fn ($q) => $q->where('user_account_id', $model->id)->orWhereHas('adviser', fn ($a) => $a->where('user_account_id', $model->id)));
        else $query->whereKey($model->helper_id);
        $query->each(fn ($helper) => app(HelperDutyEvidence::class)->observe($helper));
    }
}
