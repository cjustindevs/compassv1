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
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_NO_SHOW = 'no_show';
    const STATUS_SCHEDULED = 'scheduled';

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
        'concern_id',
        'scheduled_start',
        'session_type',
        'session_status',
        'voice_recording_consent',
        'risk_level',
        'escalation_required',
        'start_time',
        'end_time',
        'duration',
        'created_date',
        'completion_status',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'created_date' => 'datetime',
        'voice_recording_consent' => 'boolean',
        'escalation_required' => 'boolean',
        'duration' => 'integer',
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
        return in_array($this->session_status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true);
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
            'cancelled' => 'Cancelled',
            'no_show' => 'No Show',
            'scheduled' => 'Scheduled',
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