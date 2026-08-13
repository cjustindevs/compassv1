<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallLog extends Model
{
    protected $table = 'call_logs';

    protected $fillable = [
        'session_id',
        'recording_consent',
        'call_start',
        'call_end',
        'duration',
        'transcript_path',
        'recording_path',
        'recording_deleted_at',
        'retention_expiry',
        'review_status',
    ];

    protected $casts = [
        'recording_consent' => 'boolean',
        'call_start' => 'datetime',
        'call_end' => 'datetime',
        'recording_deleted_at' => 'datetime',
        'retention_expiry' => 'datetime',
        'duration' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id', 'id');
    }

    public function getDurationLabelAttribute(): string
    {
        if ($this->duration) {
            $minutes = floor($this->duration / 60);
            $seconds = $this->duration % 60;

            return $minutes > 0
                ? sprintf('%dm %02ds', $minutes, $seconds)
                : sprintf('%ds', $seconds);
        }

        return '—';
    }
}