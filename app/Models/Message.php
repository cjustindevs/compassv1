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
        'transcript',
        'is_transcript',
        'transcript_generated_at',
        'transcript_verified_by',
        'transcript_verified_at',
        'audio_url',
        'voice_consent_obtained',
        'voice_consent_obtained_at',
        'sent_datetime',
        'is_reviewed',
    ];

    protected $casts = [
        'sent_datetime' => 'datetime',
        'transcript_generated_at' => 'datetime',
        'transcript_verified_at' => 'datetime',
        'voice_consent_obtained_at' => 'datetime',
        'is_transcript' => 'boolean',
        'voice_consent_obtained' => 'boolean',
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

    public function transcriptVerifier(): BelongsTo
    {
        return $this->belongsTo(Adviser::class, 'transcript_verified_by', 'id');
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

    public function getSenderTypeAttribute(): string
    {
        return $this->sender_role;
    }

    public function getMessageAttribute(): string
    {
        return (string) $this->message_text;
    }

    public function getSentAtAttribute()
    {
        return $this->sent_datetime;
    }

    public function markAsTranscript(?string $transcriptText = null): void
    {
        $this->is_transcript = true;
        $this->transcript = $transcriptText ?: $this->message_text;
        $this->transcript_generated_at = now();
        $this->save();
    }

    public function verifyTranscript(int $adviserId): void
    {
        $this->transcript_verified_by = $adviserId;
        $this->transcript_verified_at = now();
        $this->save();
    }

    public function isTranscriptVerified(): bool
    {
        return $this->transcript_verified_at !== null;
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
            ? ($this->session?->helper?->public_alias ?: 'Peer Helper')
            : ($this->session?->seeker?->generated_alias ?: 'Seeker');
    }
}
