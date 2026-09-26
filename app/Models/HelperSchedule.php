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
     * 11:00 PM). Duty itself is a whole day; this is only used as a fallback
     * for planned-hours reporting.
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
     * Duty is scheduled per day, so a row covers the whole of its date. The
     * legacy start and end columns are no longer written.
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

        return [$day, $day->copy()->addDay()];
    }

    /**
     * Whether the duty day covers a given moment.
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
     * Duty is per day, so a helper is on duty for the whole of a date they are
     * rostered on.
     */
    public function isWithinShift(): bool
    {
        return $this->covers(now(config('app.schedule_timezone', 'Asia/Manila')));
    }

    /**
     * The active duty day covering a moment, if any.
     */
    public static function coveringShiftFor(int $helperId, ?Carbon $at = null): ?self
    {
        $tz = config('app.schedule_timezone', 'Asia/Manila');
        $moment = ($at ? $at->copy()->setTimezone($tz) : now($tz));

        return static::where('helper_id', $helperId)
            ->where('is_active', true)
            ->whereDate('date', $moment->toDateString())
            ->orderBy('id')
            ->get()
            ->first(fn (self $shift) => $shift->covers($moment));
    }

    public function getShiftDuration(): float
    {
        $minutes = (int) round(
            Carbon::parse(self::OPERATING_START, 'UTC')->diffInMinutes(
                Carbon::parse(self::OPERATING_END, 'UTC'),
                false
            )
        );

        return round($minutes / 60, 2);
    }

    public function isOnDuty(): bool
    {
        return $this->is_active && $this->isWithinShift();
    }

    public function getShiftLabelAttribute(): string
    {
        return 'All day';
    }
}
