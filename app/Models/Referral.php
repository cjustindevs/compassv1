<?php

namespace App\Models;

use App\Services\IdentityVaultService;
use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    public function scopeForAdviser($query, int $adviserId)
    {
        return $query->where(fn ($q) => $q->where('adviser_id', $adviserId)
            ->orWhereHas('helper', fn ($helper) => $helper->where('adviser_id', $adviserId))
            ->orWhere(fn ($unassigned) => $unassigned->whereNull('adviser_id')
                ->whereHas('session', fn ($session) => $session->where('review_adviser_id', $adviserId))));
    }

    public function scopeProfessionalAuthorized($query)
    {
        // Concluded work stays readable so a closed case does not disappear
        // from the professional's history; writes are still blocked separately.
        return $query->whereNotNull('approved_at')->where('help_seeker_consent', true)->whereIn('status', array_merge(
            [self::STATUS_PENDING_PROFESSIONAL],
            self::ACTIVE_STATUSES,
            self::COMPLETED_STATUSES,
        ));
    }

    public function releaseIdentity(): void
    {
        app(IdentityVaultService::class)->releaseForReferral($this);
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
        'decline_reason',
    ];

    protected $casts = [
        'recommendation_form' => 'encrypted:array',
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
        'follow_up_required' => 'boolean',
    ];

    // Status constants
    const STATUS_PENDING_ADVISER = 'pending_adviser';

    // Raised when a recommendation exists but no Adviser could be resolved, so
    // a Moderator/Administrator must complete the assignment.
    const STATUS_PENDING_ADVISER_ASSIGNMENT = 'pending_adviser_assignment';

    const STATUS_PENDING_CONSENT = 'pending_consent';

    const STATUS_CONSENT_REQUESTED = 'consent_requested';

    const STATUS_PENDING_PROFESSIONAL = 'pending_professional';

    const STATUS_ACCEPTED = 'accepted';

    const STATUS_IN_PROGRESS = 'in_progress';

    const STATUS_DECLINED = 'declined';

    const STATUS_COMPLETED = 'completed';

    const STATUS_CLOSED = 'closed';

    const STATUS_NO_PROFESSIONAL_AVAILABLE = 'no_professional_available';

    /**
     * Every status the referrals table accepts, in workflow order. Filters,
     * dropdowns and label maps must read from this so a newly added status
     * cannot be silently missing from a report or an admin filter.
     */
    const STATUSES = [
        self::STATUS_PENDING_ADVISER,
        self::STATUS_PENDING_ADVISER_ASSIGNMENT,
        self::STATUS_PENDING_CONSENT,
        self::STATUS_CONSENT_REQUESTED,
        self::STATUS_PENDING_PROFESSIONAL,
        self::STATUS_NO_PROFESSIONAL_AVAILABLE,
        self::STATUS_ACCEPTED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CLOSED,
        self::STATUS_DECLINED,
    ];

    // Statuses treated as an "active case" for the professional
    const ACTIVE_STATUSES = [
        self::STATUS_ACCEPTED,
        self::STATUS_IN_PROGRESS,
    ];

    // Statuses treated as finished work. A professional keeps read access to
    // these so a closed case does not vanish from their history.
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
    public function appointments()
    {
        return $this->hasMany(ReferralAppointment::class)->latest('id');
    }

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

    public function consentRecords()
    {
        return $this->hasMany(ConsentRecord::class, 'referral_id', 'id');
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

    public function isOpen(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING_ADVISER,
            self::STATUS_PENDING_CONSENT,
            self::STATUS_CONSENT_REQUESTED,
            self::STATUS_PENDING_PROFESSIONAL,
            self::STATUS_NO_PROFESSIONAL_AVAILABLE,
            self::STATUS_ACCEPTED,
            self::STATUS_IN_PROGRESS,
        ], true);
    }

    /**
     * A seeker may store identity details and an adviser may authorize release
     * only after approval, with recorded consent, and while the referral is open.
     */
    public function canProvideIdentity(): bool
    {
        return (bool) $this->approved_at && (bool) $this->help_seeker_consent && $this->isOpen();
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CLOSED], true);
    }
}
