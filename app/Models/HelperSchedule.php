<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class HelperSchedule extends Model
{
    protected $fillable = [
        'helper_id',
        'date',
        'shift_start',
        'shift_end',
        'is_recurring',
        'recurrence_pattern',
        'is_active',
        'is_exception',
        'exception_reason',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'date' => 'date',
        'is_recurring' => 'boolean',
        'recurrence_pattern' => 'array',
        'is_active' => 'boolean',
        'is_exception' => 'boolean',
        'approved_at' => 'datetime',
    ];

    /**
     * Approved default operating window (Monday to Saturday, 6:00 PM to
     * 11:00 PM). Only used as a fallback for planned-hours reporting on
     * legacy duty rows that predate required shift times.
     */
    public const OPERATING_START = '18:00';

    public const OPERATING_END = '23:00';

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', Carbon::parse($date)->toDateString());
    }

    public function helper(): BelongsTo
    {
        return $this->belongsTo(Helper::class, 'helper_id', 'id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Adviser::class, 'approved_by', 'id');
    }

    /**
     * A shift with no recorded times is legacy all-day duty and covers the
     * whole day. Otherwise the end is read as ending the following day when it
     * is not later than the start, so a 22:00 to 06:00 shift is overnight
     * rather than a rejected or negative-length block.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function window(): array
    {
        $tz = config('app.schedule_timezone', 'Asia/Manila');

        // Anchor on the calendar date as stored rather than converting the
        // cast value, which is midnight UTC and would land on the previous day
        // in any timezone behind UTC.
        $day = Carbon::parse(
            $this->date instanceof \DateTimeInterface
                ? $this->date->format('Y-m-d').' 00:00:00'
                : $this->date,
            $tz
        );

        if (! $this->hasShiftTimes()) {
            return [$day, $day->copy()->addDay()];
        }

        $start = $day->copy()->setTimeFromTimeString((string) $this->shift_start);
        $end = $day->copy()->setTimeFromTimeString((string) $this->shift_end);

        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        return [$start, $end];
    }

    public function hasShiftTimes(): bool
    {
        return ! empty($this->shift_start) && ! empty($this->shift_end);
    }

    public function crossesMidnight(): bool
    {
        if (! $this->hasShiftTimes()) {
            return false;
        }

        // Zero padded 24 hour times compare correctly as plain strings.
        return substr((string) $this->shift_end, 0, 5) <= substr((string) $this->shift_start, 0, 5);
    }

    /**
     * Whether the shift covers a given moment. Callers that need to honour a
     * shift spilling over midnight must also load the previous day's row.
     */
    public function covers(Carbon $moment): bool
    {
        if (! $this->is_active) {
            return false;
        }

        [$start, $end] = $this->window();

        return $start->lessThanOrEqualTo($moment) && $moment->lessThan($end);
    }

    /**
     * Duty is shift-based: a helper is on duty only while the clock is inside
     * one of their shifts. A legacy row with no shift times still counts for
     * the whole day.
     */
    public function isWithinShift(): bool
    {
        return $this->covers(now(config('app.schedule_timezone', 'Asia/Manila')));
    }

    /**
     * The active shift covering a moment, if any. An overnight shift is dated
     * by the day it starts, so the previous day is included in the lookup.
     */
    public static function coveringShiftFor(int $helperId, ?Carbon $at = null): ?self
    {
        $tz = config('app.schedule_timezone', 'Asia/Manila');
        $moment = ($at ? $at->copy()->setTimezone($tz) : now($tz));

        return static::where('helper_id', $helperId)
            ->where('is_active', true)
            ->whereDate('date', '>=', $moment->copy()->subDays(2)->toDateString())
            ->whereDate('date', '<=', $moment->toDateString())
            ->orderBy('date')
            ->get()
            ->first(fn (self $shift) => $shift->covers($moment));
    }

    public function getShiftDuration(): float
    {
        if (! $this->hasShiftTimes()) {
            $start = self::OPERATING_START;
            $end = self::OPERATING_END;
        } else {
            $start = (string) $this->shift_start;
            $end = (string) $this->shift_end;
        }

        $minutes = (int) round(
            Carbon::parse($start, 'UTC')->diffInMinutes(
                Carbon::parse($end, 'UTC'),
                false
            )
        );

        if ($minutes <= 0) {
            $minutes += 24 * 60;
        }

        return round($minutes / 60, 2);
    }

    public function isOnDuty(): bool
    {
        return $this->is_active && $this->isWithinShift();
    }

    public function getShiftLabelAttribute(): string
    {
        if (! $this->hasShiftTimes()) {
            return 'All day';
        }

        $format = static fn ($time) => Carbon::parse((string) $time)->format('h:i A');

        return $format($this->shift_start)
            .' - '
            .$format($this->shift_end)
            .($this->crossesMidnight() ? ' (next day)' : '');
    }
}
