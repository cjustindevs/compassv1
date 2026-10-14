<?php

namespace App\Services;

use App\Models\Helper;
use App\Models\HelperSchedule;

class HelperDutySummary
{
    public function forHelper(Helper $helper): array
    {
        $today = now('Asia/Manila')->toDateString();
        $present = function (HelperSchedule $duty) use ($today) {
            [, $end] = $duty->window();
            $isToday = $duty->date->format('Y-m-d') === $today;

            return ['id' => $duty->id, 'date' => $duty->date->format('D, M d'), 'time' => $duty->shift_label.' PHT', 'notes' => $duty->duty_notes,
                'status' => ! $isToday ? 'Upcoming' : ($duty->isOnDuty() ? 'Duty today' : ($end->isPast() ? 'Duty ended' : 'Scheduled today'))];
        };
        $duties = HelperSchedule::where('helper_id', $helper->id)->where('is_active', true)->whereDate('date', '>=', $today)->orderBy('date')->limit(6)->get();

        return ['testing' => ! config('app.enforce_duty_hours'), 'today' => $duties->filter(fn ($d) => $d->date->format('Y-m-d') === $today)->map($present)->values(),
            'upcoming' => $duties->filter(fn ($d) => $d->date->format('Y-m-d') > $today)->take(5)->map($present)->values()];
    }
}
