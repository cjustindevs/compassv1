<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $table = 'messages';

    protected $fillable = [
        'session_id',
        'message_text',
        'sent_datetime',
        'sender',
        'is_reviewed',
    ];

    protected $casts = [
        'sent_datetime' => 'datetime',
        'is_reviewed' => 'boolean',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id', 'id');
    }

    public function getTimeFormattedAttribute(): string
    {
        return $this->sent_datetime
            ? $this->sent_datetime->format('h:i A')
            : now()->format('h:i A');
    }
}