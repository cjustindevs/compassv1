<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreeningResponse extends Model
{
    protected $fillable = [
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
