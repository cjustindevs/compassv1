<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    public function releaseIdentity(): void
    {
        app(\App\Services\IdentityVaultService::class)->releaseForReferral($this);
    }

    protected $table = 'referrals';

    protected $fillable = [
        'session_id',
        'helper_id',
        'moderator_id',
        'adviser_id',
        'professional_id',
        'priority_level',
        'help_seeker_consent',
        'identity_disclosed',
        'referral_reason',
        'referral_date',
        'reviewed_at',
        'review_notes',
        'approved_at',
        'declined_at',
        'consent_requested_at',
        'consent_obtained_at',
        'consent_declined_at',
        'professional_notified_at',
        'accepted_at',
        'outcome',
        'follow_up_required',
        'follow_up_notes',
        'completed_at',
        'status',
        'closed_date',
        'closure_notes',
        'decline_reason'
    ];

    protected $casts = [
        'referral_date' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'declined_at' => 'datetime',
        'consent_requested_at' => 'datetime',
        'consent_obtained_at' => 'datetime',
        'consent_declined_at' => 'datetime',
        'professional_notified_at' => 'datetime',
        'accepted_at' => 'datetime',
        'completed_at' => 'datetime',
        'closed_date' => 'datetime',
        'help_seeker_consent' => 'boolean',
        'identity_disclosed' => 'boolean',
        'follow_up_required' => 'boolean'
    ];

    // Status constants
    const STATUS_PENDING_ADVISER = 'pending_adviser';
    const STATUS_PENDING_CONSENT = 'pending_consent';
    const STATUS_PENDING_PROFESSIONAL = 'pending_professional';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_DECLINED = 'declined';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CLOSED = 'closed';
    const STATUS_NO_PROFESSIONAL_AVAILABLE = 'no_professional_available';

    // Statuses treated as an "active case" for the professional
    const ACTIVE_STATUSES = [
        self::STATUS_ACCEPTED,
        self::STATUS_IN_PROGRESS,
    ];

    // Statuses treated as finished work
    const COMPLETED_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_CLOSED,
    ];

    // Priority constants
    const PRIORITY_LOW = 'low';
    const PRIORITY_MODERATE = 'moderate';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_EMERGENCY = 'emergency';

    // Relationships
    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id', 'id');
    }

    public function helper()
    {
        return $this->belongsTo(Helper::class, 'helper_id', 'id');
    }

    public function adviser()
    {
        return $this->belongsTo(Adviser::class, 'adviser_id', 'id');
    }

    public function professional()
    {
        return $this->belongsTo(PsychologyProfessional::class, 'professional_id', 'id');
    }

    public function moderator()
    {
        return $this->belongsTo(Moderator::class, 'moderator_id', 'id');
    }

    public function professionalNotes()
    {
        return $this->hasMany(ProfessionalNote::class, 'referral_id', 'id')->latest();
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING_ADVISER, self::STATUS_PENDING_CONSENT, self::STATUS_PENDING_PROFESSIONAL]);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeEmergency($query)
    {
        return $query->where('priority_level', self::PRIORITY_EMERGENCY);
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING_ADVISER, self::STATUS_PENDING_CONSENT, self::STATUS_PENDING_PROFESSIONAL], true);
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CLOSED], true);
    }
}
