<?php

namespace App\Services;

use App\Models\Helper;
use App\Models\HelperSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Duty scheduling is shift-based. A helper may hold several non-overlapping
 * shifts on the same date, and both the Adviser and the Moderator create them
 * through this service so the two roles cannot drift apart again.
 *
 * Overlap is resolved to absolute instants before comparing, because a shift
 * that ends earlier than it starts runs past midnight and can collide with a
 * shift recorded against the following day. A database unique index cannot
 * express that, which is why the rule lives here.
 */
class HelperShiftService
{
    public function shiftsFor(int $helperId, string $date): Collection
    {
        return HelperSchedule::where('helper_id', $helperId)
            ->forDate($date)
            ->orderByRaw('COALESCE(shift_start, \'00:00:00\')')
            ->orderBy('id')
            ->get();
    }

    /**
     * Create or update one shift. When $shiftId is given that row is edited and
     * excluded from its own overlap check, so a shift can be nudged without
     * first being deleted.
     *
     * @throws ValidationException
     */
    public function saveShift(Helper $helper, string $date, ?string $start, ?string $end, ?int $shiftId = null, array $attributes = []): HelperSchedule
    {
        $day = Carbon::parse($date, config('app.schedule_timezone', 'Asia/Manila'))->toDateString();

        [$start, $end] = $this->normaliseTimes($start, $end);

        $existing = $shiftId
            ? HelperSchedule::where('helper_id', $helper->id)->whereKey($shiftId)->first()
            : null;

        if ($shiftId && ! $existing) {
            throw ValidationException::withMessages([
                'shift_id' => 'That shift no longer exists for this helper.',
            ]);
        }

        return DB::transaction(function () use ($helper, $day, $start, $end, $existing, $attributes) {
            // Serialise writes for this helper so two concurrent scheduling
            // requests cannot both pass the overlap check and both insert.
            Helper::whereKey($helper->id)->lockForUpdate()->firstOrFail();

            $this->guardNoOverlap($helper, $day, $start, $end, $existing?->id);

            $shift = $existing ?? new HelperSchedule(['helper_id' => $helper->id, 'date' => $day]);
            $shift->fill(array_merge([
                'date' => $day,
                'shift_start' => $start,
                'shift_end' => $end,
                'is_active' => true,
            ], $attributes))->save();

            return $shift;
        }, 3);
    }

    /**
     * @throws ValidationException
     */
    public function deleteShift(Helper $helper, int $shiftId): void
    {
        $shift = HelperSchedule::where('helper_id', $helper->id)->whereKey($shiftId)->first();

        if (! $shift) {
            throw ValidationException::withMessages([
                'shift_id' => 'That shift no longer exists for this helper.',
            ]);
        }

        $shift->delete();
    }

    /**
     * @return array{0: ?string, 1: ?string}
     *
     * @throws ValidationException
     */
    private function normaliseTimes(?string $start, ?string $end): array
    {
        // Duty rows created before shifts became the scheduling unit carry no
        // times and mean the whole day, so both may be omitted together.
        if (blank($start) && blank($end)) {
            return [null, null];
        }

        if (blank($start) || blank($end)) {
            throw ValidationException::withMessages([
                'shift_end' => 'A shift needs both a start and an end time.',
            ]);
        }

        if (substr((string) $end, 0, 5) === substr((string) $start, 0, 5)) {
            throw ValidationException::withMessages([
                'shift_end' => 'The shift end must differ from the shift start.',
            ]);
        }

        return [$start, $end];
    }

    /**
     * @throws ValidationException
     */
    private function guardNoOverlap(Helper $helper, string $day, ?string $start, ?string $end, ?int $ignoreId): void
    {
        $candidate = new HelperSchedule([
            'date' => $day,
            'shift_start' => $start,
            'shift_end' => $end,
            'is_active' => true,
        ]);
        [$windowStart, $windowEnd] = $candidate->window();

        // The previous two days matter because a shift may run past midnight
        // into the day being scheduled. Compared with whereDate because the
        // column is a date, and a plain equality against a Y-m-d string misses
        // the stored value on SQLite.
        $neighbours = HelperSchedule::where('helper_id', $helper->id)
            ->where('is_active', true)
            ->whereDate('date', '>=', Carbon::parse($day)->subDays(2)->toDateString())
            ->whereDate('date', '<=', $day)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->get();

        foreach ($neighbours as $neighbour) {
            [$otherStart, $otherEnd] = $neighbour->window();

            if ($otherStart->lessThan($windowEnd) && $otherEnd->greaterThan($windowStart)) {
                throw ValidationException::withMessages([
                    'shift_start' => 'This shift overlaps '.$neighbour->shift_label.' already scheduled on '
                        .Carbon::parse($neighbour->date)->format('M j, Y').'.',
                ]);
            }
        }
    }
}
