<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $table = 'messages';

    protected $fillable = [
        'session_id',
        'sender_id',
        'sender',
        'message_text',
        'sent_datetime',
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

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id', 'id');
    }

    public function scopeInSession($query, int $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeOrderBySent($query)
    {
        return $query->orderBy('sent_datetime', 'asc')->orderBy('id', 'asc');
    }

    public function scopeFrom($query, string $sender)
    {
        return $query->where('sender', $sender);
    }

    public function getTimeFormattedAttribute(): string
    {
        return $this->sent_datetime
            ? $this->sent_datetime->format('h:i A')
            : now()->format('h:i A');
    }

    public function getDateFormattedAttribute(): string
    {
        return $this->sent_datetime
            ? $this->sent_datetime->format('M d, Y')
            : now()->format('M d, Y');
    }

    public function getIsHelperAttribute(): bool
    {
        return $this->sender === 'helper';
    }

    public function getSenderRoleAttribute(): string
    {
        return $this->is_helper ? 'helper' : 'seeker';
    }

    /**
     * Human-friendly sender label (seeker alias / helper full name).
     */
    public function senderName(): string
    {
        if ($this->senderUser) {
            return $this->senderUser->displayName();
        }

        return $this->is_helper
            ? ($this->session?->helper?->full_name ?: 'Peer Helper')
            : ($this->session?->seeker?->generated_alias ?: 'Seeker');
    }
}