<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncidentReport extends Model
{
    protected $table = 'incident_reports';

    protected $fillable = [
        'session_id',
        'user_account_id',
        'moderator_id',
        'incident_category',
        'description',
        'immediate_action',
        'risk_level',
        'recommendation',
        'comments',
        'status',
        'reported_at',
        'is_confidential',
        'reviewed_by',
        'reviewed_at',
        'review_comments',
        'escalated_at',
        'escalated_to',
        'escalation_reason',
        'resolved_at',
        'resolved_by',
        'resolution_summary',
        'corrective_actions',
        'closed_at',
        'closed_by',
        'closure_notes',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'is_confidential' => 'boolean',
        'reviewed_at' => 'datetime',
        'escalated_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_account_id', 'id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_account_id', 'id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by', 'id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(IncidentAttachment::class, 'incident_id', 'id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'under_review', 'escalated']);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeUnderReview(Builder $query): Builder
    {
        return $query->where('status', 'under_review');
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', 'resolved');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }

    public function scopeHighRisk(Builder $query): Builder
    {
        return $query->where('risk_level', 'high');
    }

    public function scopeEmergency(Builder $query): Builder
    {
        return $query->where('risk_level', 'emergency');
    }
}
