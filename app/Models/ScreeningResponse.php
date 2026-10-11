<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreeningResponse extends Model
{
    protected $fillable = [
        'follow_up_note',
        'follow_up_requested_by',
        'follow_up_requested_at',
        'follow_up_answer',
        'follow_up_answered_at',
        'instrument_version',
        'rule_code',
        'review_status',
        'actor_id',
        'evidence_source',

        'seeker_id',
        'session_id',
        'responses',
        'risk_level',
        'priority',
        'action',
        'reason',
        'classified_at',
        'classified_by',
        'is_active',
        'is_complete',
    ];

    protected $casts = [
        'follow_up_requested_at' => 'datetime',
        'follow_up_answered_at' => 'datetime',
        'is_active' => 'boolean',
        'is_complete' => 'boolean',
        'responses' => 'array',
        'classified_at' => 'datetime',
        'priority' => 'integer',
    ];

    public function seeker(): BelongsTo
    {
        return $this->belongsTo(HelpSeeker::class, 'seeker_id', 'id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id', 'id');
    }
}
