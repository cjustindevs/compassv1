<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueRequest extends Model
{
    protected $table = 'queue_requests';

    // P1–P4 priority classes. P1 is the most urgent tier.
    public const PRIORITY_CLASS_LABELS = [
        'emergency' => 'P1',
        'high' => 'P2',
        'moderate' => 'P3',
        'low' => 'P4',
    ];

    public static function priorityClass(string $level): string
    {
        return self::PRIORITY_CLASS_LABELS[$level] ?? 'P4';
    }

    protected $fillable = [
        'wait_urgency',
        'queued_at',
        'matching_started_at',
        'helper_proposed_at',
        'helper_accepted_at',
        'helper_declined_at',
        'assigned_at',
        'cancelled_at',
        'expired_at',
        'completed_at',

        'seeker_id',
        'moderator_id',
        'request_date',
        'scheduled_date',
        'request_status',
        'priority_level',
        'preferred_session_type',
        'voice_consent',
        'assigned_helper_id',
        'queue_position',
        'estimated_wait',
        'aging_priority_increases',
        'last_priority_increase_at',
        'max_wait_reached',
        'matched_date',
    ];

    protected $casts = [
        'request_date' => 'datetime',
        'scheduled_date' => 'datetime',
        'matched_date' => 'datetime',
        'last_priority_increase_at' => 'datetime',
        'max_wait_reached' => 'boolean',
        'voice_consent' => 'boolean',
    ];

    public function seeker(): BelongsTo
    {
        return $this->belongsTo(HelpSeeker::class, 'seeker_id', 'id');
    }

    public function assignedHelper(): BelongsTo
    {
        return $this->belongsTo(Helper::class, 'assigned_helper_id', 'id');
    }
}
