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

    public function isWithinShift(): bool
    {
        if (! $this->is_active || ! $this->date?->isToday()) {
            return false;
        }
        $now = now();
        $start = now()->setTimeFromTimeString((string) $this->shift_start);
        $end = now()->setTimeFromTimeString((string) $this->shift_end);

        return $now->between($start, $end);
    }

    public function getShiftDuration(): float
    {
        return now()->setTimeFromTimeString((string) $this->shift_start)
            ->diffInHours(now()->setTimeFromTimeString((string) $this->shift_end));
    }

    public function isOnDuty(): bool
    {
        return $this->is_active && $this->isWithinShift();
    }
}
