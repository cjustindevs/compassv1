<?php

namespace App\Services;

use App\Models\{Helper, HelperSchedule};
use Illuminate\Support\Facades\DB;

/** Observed verified willingness to serve; being busy does not erase duty time. */
class HelperDutyEvidence
{
    public function observe(Helper $helper): void
    {
        DB::transaction(function () use ($helper) {
            $helper = Helper::lockForUpdate()->find($helper->id);
            if (!$helper) return;
            $duty = HelperSchedule::coveringShiftFor($helper->id);
            $readiness = $helper->getCurrentReadiness();
            $eligible = $helper->user?->is_active && $helper->user?->role === 'helper' && $helper->adviser?->user?->is_active
                && $helper->verification_status === 'verified' && $helper->verified_at && $helper->verified_by && $helper->training_verified
                && !$helper->is_under_review && $helper->availability === 'available' && $duty && $readiness?->assessment_result === 'ready';
            $current = DB::table('helper_service_intervals')->where('helper_id', $helper->id)->whereNull('ended_at')->lockForUpdate()->get();
            $until = $eligible ? $duty->window()[1]->utc()->min($readiness->valid_until ?? $readiness->assessment_date->copy()->addHours(4)) : null;
            if ($current->count() === 1 && $eligible && $current[0]->helper_schedule_id === $duty->id
                && $current[0]->readiness_check_id === $readiness->id && \Carbon\Carbon::parse($current[0]->valid_until)->gt(now())
                && \Carbon\Carbon::parse($current[0]->valid_until)->equalTo($until)) return;
            DB::table('helper_service_intervals')->where('helper_id', $helper->id)->whereNull('ended_at')->update(['ended_at'=>now()]);
            if ($eligible && $until->gt(now())) DB::table('helper_service_intervals')->insert([
                'helper_id'=>$helper->id, 'helper_schedule_id'=>$duty->id, 'readiness_check_id'=>$readiness->id,
                'started_at'=>now(), 'valid_until'=>$until,
            ]);
        });
    }
}
