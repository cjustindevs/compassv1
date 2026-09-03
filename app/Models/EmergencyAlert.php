<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyAlert extends Model
{
    protected $fillable = [
        'seeker_id',
        'session_id',
        'adviser_id',
        'referral_id',
        'alert_type',
        'risk_level',
        'triggered_at',
        'triggered_by',
        'trigger_reason',
        'status',
        'notification_sent',
        'adviser_notified',
        'professional_referred',
        'adviser_notified_at',
        'notification_sent_at',
        'professional_referred_at',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'triggered_at' => 'datetime',
        'adviser_notified_at' => 'datetime',
        'notification_sent_at' => 'datetime',
        'professional_referred_at' => 'datetime',
        'resolved_at' => 'datetime',
        'notification_sent' => 'boolean',
        'adviser_notified' => 'boolean',
        'professional_referred' => 'boolean',
    ];

    public function seeker(): BelongsTo
    {
        return $this->belongsTo(HelpSeeker::class, 'seeker_id', 'id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id', 'id');
    }

    public function adviser(): BelongsTo
    {
        return $this->belongsTo(Adviser::class, 'adviser_id', 'id');
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class, 'referral_id', 'id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', 'resolved');
    }
}
