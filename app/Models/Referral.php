<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
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
        'status',
        'closed_date',
        'decline_reason'
    ];

    protected $casts = [
        'referral_date' => 'datetime',
        'closed_date' => 'datetime',
        'help_seeker_consent' => 'boolean',
        'identity_disclosed' => 'boolean'
    ];

    // Status constants
    const STATUS_PENDING_ADVISER = 'pending_adviser';
    const STATUS_PENDING_PROFESSIONAL = 'pending_professional';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_DECLINED = 'declined';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CLOSED = 'closed';

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
        return $query->where('status', self::STATUS_PENDING_ADVISER);
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
}