<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\Helper;
use App\Models\HelperSchedule;
use App\Models\ReadinessCheck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Duty is scheduled per day. A date can carry as many helpers as are rostered on
 * it, but each helper holds one duty record for that date. Timed duties enforce
 * shift times when configured. Both roles roster duty through this service
 * so the two roles cannot drift apart again.
 */
class HelperShiftService
{
    public function shiftsFor(int $helperId, string $date): Collection
    {
        return HelperSchedule::where('helper_id', $helperId)
            ->forDate($date)
            ->orderBy('id')
            ->get();
    }

    /**
     * Roster a helper for a whole day, or update the day they already hold when
     * $shiftId is given. A helper cannot be rostered twice for the same date.
     *
     * $errorKey is the form field a clash is reported against, which is the date
     * field in both scheduling forms.
     *
     * @throws ValidationException
     */
    public function scheduleDuty(Helper $helper, string $date, ?int $shiftId = null, array $attributes = [], string $errorKey = 'date'): HelperSchedule
    {
        $day = Carbon::parse($date, config('app.schedule_timezone', 'Asia/Manila'))->toDateString();

        $existing = $shiftId
            ? HelperSchedule::where('helper_id', $helper->id)->whereKey($shiftId)->first()
            : null;

        if ($shiftId && ! $existing) {
            throw ValidationException::withMessages([
                'shift_id' => 'That duty day no longer exists for this helper.',
            ]);
        }

        return DB::transaction(function () use ($helper, $day, $existing, $attributes, $errorKey) {
            // Serialise writes for this helper so two concurrent scheduling
            // requests cannot both find the day free and both insert.
            Helper::whereKey($helper->id)->lockForUpdate()->firstOrFail();
            if (! $existing) {
                $cancelled = HelperSchedule::where('helper_id', $helper->id)->whereDate('date', $day)->where('is_active', false)->first();
                if ($cancelled) {
                    SupportAudit::record('helper_duty_rescheduled', $cancelled, ['previous' => $cancelled->only(['date', 'shift_start', 'shift_end', 'duty_notes', 'is_active'])]);
                    $existing = $cancelled;
                }
            }

            $clash = HelperSchedule::where('helper_id', $helper->id)
                ->whereDate('date', $day)
                ->when($existing, fn ($query) => $query->where('id', '!=', $existing->id))
                ->exists();

            if ($clash) {
                throw ValidationException::withMessages([
                    $errorKey => $helper->full_name.' is already on duty on '.Carbon::parse($day)->format('M j, Y').'.',
                ]);
            }

            $shift = $existing ?? new HelperSchedule(['helper_id' => $helper->id]);
            $shift->fill(array_merge([
                'date' => $day,
                'is_active' => true,
            ], $attributes))->save();

            return $shift;
        }, 3);
    }

    /**
     * @throws ValidationException
     */
    public function removeDuty(Helper $helper, int $shiftId, bool $notify = true): void
    {
        $shift = HelperSchedule::where('helper_id', $helper->id)->whereKey($shiftId)->first();

        if (! $shift) {
            throw ValidationException::withMessages([
                'shift_id' => 'That duty day no longer exists for this helper.',
            ]);
        }

        DB::transaction(function () use ($helper, $shift, $notify) {
            Helper::whereKey($helper->id)->lockForUpdate()->firstOrFail();
            $shift = HelperSchedule::lockForUpdate()->findOrFail($shift->id);
            if (! $shift->is_active) {
                return;
            }
            if ($helper->activeSessions()->exists() && $shift->isOnDuty()) {
                throw ValidationException::withMessages(['shift_id' => 'This Helper has an active assignment. Complete or hand off that assignment before removing today’s duty.']);
            }
            $shift->update(['is_active' => false]);
            ReadinessCheck::where('helper_schedule_id', $shift->id)->update(['is_active' => false]);
            if (! $helper->activeSessions()->exists() && ! $helper->getCurrentReadiness()?->isReady()) {
                $helper->update(['status' => 'offline', 'availability' => 'unavailable', 'is_ready' => false, 'available_since' => null]);
            }
            CalendarEvent::where('helper_schedule_id', $shift->id)->delete();
            if ($notify) {
                app(DutySchedulingService::class)->notify($helper, 'cancelled', $shift->date->format('M d, Y'));
            }
            SupportAudit::record('helper_duty_cancelled', $shift);
        });
    }
}
