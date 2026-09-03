<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Session extends Model
{
    // ── Status flow for the request-support journey ──
    // screening_completed → preferences_set → waiting → helper_assigned → active → completed
    const STATUS_SCREENING_COMPLETED = 'screening_completed';
    const STATUS_PREFERENCES_SET = 'preferences_set';
    const STATUS_WAITING = 'waiting';
    const STATUS_HELPER_ASSIGNED = 'helper_assigned';
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_EVALUATED = 'evaluated';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_NO_SHOW = 'no_show';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_EMERGENCY = 'emergency';
    const STATUS_PENDING_REVIEW = 'pending_review';

    /**
     * Statuses that count as an in-progress request (not yet started or finished).
     */
    public const PENDING_STATUSES = [
        self::STATUS_SCREENING_COMPLETED,
        self::STATUS_PREFERENCES_SET,
        self::STATUS_WAITING,
        self::STATUS_HELPER_ASSIGNED,
    ];

    protected $table = 'counseling_sessions';

    protected $fillable = [
        'seeker_id',
        'helper_id',
        'moderator_id',
        'queue_request_id',
        'concern_id',
        'scheduled_start',
        'pre_session_brief_expires_at',
        'session_type',
        'voice_consent_obtained',
        'match_method',
        'matched_by',
        'matching_details',
        'session_status',
        'voice_recording_consent',
        'risk_level',
        'concern_category',
        'escalation_required',
        'requires_immediate_action',
        'requires_adviser_review',
        'elevated_priority',
        'requires_closer_monitoring',
        'emergency_triggered_at',
        'risk_updated_at',
        'risk_update_reason',
        'risk_updated_by',
        'start_time',
        'end_time',
        'duration',
        'created_date',
        'completion_status',
        'seeker_evaluation_submitted',
        'auto_completed',
        'auto_completed_at',
        'no_show',
        'abandoned',
        'abandoned_at',
        'transcript_verified',
        'transcript_verified_by',
        'transcript_verified_at',
        'transcript_generated_at',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'pre_session_brief_expires_at' => 'datetime',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'created_date' => 'datetime',
        'voice_recording_consent' => 'boolean',
        'voice_consent_obtained' => 'boolean',
        'escalation_required' => 'boolean',
        'requires_immediate_action' => 'boolean',
        'requires_adviser_review' => 'boolean',
        'elevated_priority' => 'boolean',
        'requires_closer_monitoring' => 'boolean',
        'emergency_triggered_at' => 'datetime',
        'risk_updated_at' => 'datetime',
        'duration' => 'integer',
        'seeker_evaluation_submitted' => 'boolean',
        'auto_completed' => 'boolean',
        'auto_completed_at' => 'datetime',
        'no_show' => 'boolean',
        'abandoned' => 'boolean',
        'abandoned_at' => 'datetime',
        'matching_details' => 'array',
        'transcript_verified' => 'boolean',
        'transcript_verified_at' => 'datetime',
        'transcript_generated_at' => 'datetime',
    ];

    public function seeker(): BelongsTo
    {
        return $this->belongsTo(HelpSeeker::class, 'seeker_id', 'id');
    }

    public function helper(): BelongsTo
    {
        return $this->belongsTo(Helper::class, 'helper_id', 'id');
    }

    public function concern(): BelongsTo
    {
        return $this->belongsTo(ConcernCategory::class, 'concern_id', 'id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'session_id', 'id');
    }

    public function report(): HasOne
    {
        return $this->hasOne(SessionReport::class, 'session_id', 'id');
    }

    public function callLog(): HasOne
    {
        return $this->hasOne(CallLog::class, 'session_id', 'id');
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(HelpSeekerEvaluation::class, 'session_id', 'id');
    }

    public function queue(): BelongsTo
    {
        return $this->belongsTo(QueueRequest::class, 'queue_request_id', 'id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(IncidentReport::class, 'session_id', 'id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'session_id', 'id');
    }

    public function screeningResponses(): HasMany
    {
        return $this->hasMany(ScreeningResponse::class, 'session_id', 'id');
    }

    public function emergencyAlerts(): HasMany
    {
        return $this->hasMany(EmergencyAlert::class, 'session_id', 'id');
    }

    public function scopeForHelper($query, int $helperId)
    {
        return $query->where('helper_id', $helperId);
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('session_status', $status);
    }

    /**
     * The latest in-progress request for a seeker, if any.
     */
    public function scopePendingForSeeker($query, int $seekerId)
    {
        return $query->where('seeker_id', $seekerId)
            ->whereIn('session_status', self::PENDING_STATUSES)
            ->orderByDesc('created_date')
            ->orderByDesc('id');
    }

    public function isPending(): bool
    {
        return in_array($this->session_status, self::PENDING_STATUSES, true);
    }

    public function isCompleted(): bool
    {
        return in_array($this->session_status, [self::STATUS_COMPLETED, self::STATUS_EVALUATED, self::STATUS_CANCELLED], true);
    }

    /**
     * Sessions still awaiting a helper that have sat untouched for 24+ hours.
     */
    public function scopeAbandoned($query)
    {
        return $query->whereIn('session_status', [self::STATUS_PREFERENCES_SET, self::STATUS_WAITING, self::STATUS_HELPER_ASSIGNED])
            ->where('created_date', '<', now()->subHours(24));
    }

    /**
     * Mark long-stale pending sessions as cancelled (called on login/flow entry).
     */
    public function scopeMarkAbandoned($query): void
    {
        $query->whereIn('session_status', [self::STATUS_PREFERENCES_SET, self::STATUS_WAITING, self::STATUS_HELPER_ASSIGNED])
            ->where('created_date', '<', now()->subHours(24))
            ->update([
                'session_status' => self::STATUS_CANCELLED,
                'completion_status' => 'cancelled',
                'end_time' => now(),
            ]);
    }

    public function isWaitingForHelper(): bool
    {
        return $this->session_status === self::STATUS_WAITING;
    }

    public function isHelperAssigned(): bool
    {
        return $this->session_status === self::STATUS_HELPER_ASSIGNED;
    }

    public function isActive(): bool
    {
        return $this->session_status === self::STATUS_ACTIVE;
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'screening_completed' => 'Screening Done',
            'preferences_set' => 'Awaiting Helper',
            'waiting' => 'In Queue',
            'helper_assigned' => 'Helper Assigned',
            'active' => 'Active',
            'completed' => 'Completed',
            'evaluated' => 'Evaluated',
            'cancelled' => 'Cancelled',
            'no_show' => 'No Show',
            'scheduled' => 'Scheduled',
            'emergency' => 'Emergency',
            'pending_review' => 'Pending Review',
        ];

        return $labels[$this->session_status] ?? ucfirst(str_replace('_', ' ', $this->session_status));
    }

    public function getReferenceNumberAttribute(): string
    {
        return 'R-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function getModeLabelAttribute(): string
    {
        return $this->session_type === 'voice' ? 'Voice' : 'Chat';
    }
}
