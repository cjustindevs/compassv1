<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    protected $table = 'calendar_events';

    protected $fillable = [
        'title',
        'description',
        'event_date',
        'start_time',
        'end_time',
        'event_type',
        'created_by',
        'color',
    ];

    protected $casts = [
        'event_date' => 'date',
    ];

    public const TYPE_SESSION = 'session';
    public const TYPE_EVALUATION = 'evaluation';
    public const TYPE_TRAINING = 'training';
    public const TYPE_MEETING = 'meeting';
    public const TYPE_OTHER = 'other';

    public const EVENT_TYPES = [
        self::TYPE_SESSION,
        self::TYPE_EVALUATION,
        self::TYPE_TRAINING,
        self::TYPE_MEETING,
        self::TYPE_OTHER,
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->event_type) {
            self::TYPE_SESSION => 'Session',
            self::TYPE_EVALUATION => 'Evaluation',
            self::TYPE_TRAINING => 'Training',
            self::TYPE_MEETING => 'Meeting',
            default => 'Other',
        };
    }
}
