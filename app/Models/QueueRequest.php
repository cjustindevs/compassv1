<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueRequest extends Model
{
    protected $table = 'queue_requests';

    protected $fillable = [
        'seeker_id',
        'moderator_id',
        'request_date',
        'scheduled_date',
        'request_status',
        'priority_level',
        'preferred_session_type',
        'assigned_helper_id',
        'queue_position',
        'estimated_wait',
        'matched_date',
    ];

    protected $casts = [
        'request_date' => 'datetime',
        'scheduled_date' => 'datetime',
        'matched_date' => 'datetime',
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