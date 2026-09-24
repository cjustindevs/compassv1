<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
     * 11:00 PM). Used only for planned-hours reporting when a date-only
     * duty schedule no longer records explicit shift times.
     */
    public const OPERATING_START = '18:00';

    public const OPERATING_END = '23:00';

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
     * Duty is date-based: a helper on an active schedule is on duty for the
     * whole planned day. Shift times are informational only.
     */
    public function isWithinShift(): bool
    {
        $now = now(config('app.schedule_timezone', 'Asia/Manila'));

        return $this->is_active && $this->date?->format('Y-m-d') === $now->toDateString();
    }

    public function getShiftDuration(): float
    {
        $start = $this->shift_start ?? self::OPERATING_START;
        $end = $this->shift_end ?? self::OPERATING_END;

        return now()->setTimeFromTimeString((string) $start)
            ->diffInHours(now()->setTimeFromTimeString((string) $end));
    }

    public function isOnDuty(): bool
    {
        return $this->is_active && $this->isWithinShift();
    }

    public function getShiftLabelAttribute(): string
    {
        if (! $this->shift_start || ! $this->shift_end) {
            return 'All day';
        }

        return \Illuminate\Support\Carbon::parse($this->shift_start)->format('h:i A')
            .' - '
            .\Illuminate\Support\Carbon::parse($this->shift_end)->format('h:i A');
    }
}
