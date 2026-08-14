<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Helper extends Model
{
    protected $table = 'helpers';

    protected $fillable = [
        'user_account_id',
        'adviser_id',
        'first_name',
        'last_name',
        'email',
        'status',
        'competency_level',
        'max_concurrent_sessions',
        'specializations',
        'bio',
        'phone',
        'preferred_language',
        'timezone',
    ];

    protected $casts = [
        'competency_level' => 'integer',
        'max_concurrent_sessions' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_account_id', 'id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class, 'helper_id', 'id');
    }

    public function activeSessions(): HasMany
    {
        return $this->hasMany(Session::class, 'helper_id', 'id')
            ->whereIn('session_status', ['scheduled', 'active']);
    }

    public function readinessChecks(): HasMany
    {
        return $this->hasMany(ReadinessCheck::class, 'helper_id', 'id');
    }

    public function latestReadiness()
    {
        return $this->hasOne(ReadinessCheck::class, 'helper_id', 'id')
            ->latestOfMany();
    }

    public function competencyHistory(): HasMany
    {
        return $this->hasMany(HelperCompetencyHistory::class, 'helper_id', 'id');
    }

    public function latestCompetency()
    {
        return $this->hasOne(HelperCompetencyHistory::class, 'helper_id', 'id')
            ->latestOfMany();
    }

    public function reports(): HasMany
    {
        return $this->hasManyThrough(
            SessionReport::class,
            Session::class,
            'helper_id',
            'session_id',
            'id',
            'id'
        );
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Helpers whose latest readiness check resulted in 'ready'.
     */
    public function scopeReady($query)
    {
        return $query->whereHas('latestReadiness', function ($q) {
            $q->where('assessment_result', 'ready');
        });
    }

    /**
     * Find the most suitable available helper for the given risk level:
     * online, passed their latest readiness check, competent enough,
     * and not at their concurrent session limit.
     */
    public static function findAvailableForRisk(?string $riskLevel, ?int $excludeHelperId = null): ?Helper
    {
        $requiredCompetency = match ($riskLevel) {
            'emergency', 'high' => 3,
            'moderate' => 2,
            default => 1,
        };

        $query = self::query()
            ->where('status', 'available')
            ->whereHas('latestReadiness', function ($q) {
                $q->where('assessment_result', 'ready');
            })
            ->where('competency_level', '>=', $requiredCompetency)
            ->withCount('activeSessions as active_sessions_count')
            ->orderBy('active_sessions_count');

        if ($excludeHelperId) {
            $query->where('id', '!=', $excludeHelperId);
        }

        return $query->get()
            ->first(fn (Helper $helper) => $helper->active_sessions_count < (int) $helper->max_concurrent_sessions);
    }

    public function getActiveSessionsCount(): int
    {
        return $this->activeSessions()->count();
    }

    public function hasCapacity(): bool
    {
        return $this->getActiveSessionsCount() < (int) $this->max_concurrent_sessions;
    }

    public function isReady(): bool
    {
        $readiness = $this->latestReadiness;

        return $readiness && $readiness->assessment_result === 'ready';
    }

    public function isOnline(): bool
    {
        return $this->status === 'available';
    }

    public function canAcceptSessions(): bool
    {
        return $this->isOnline() && $this->isReady() && $this->hasCapacity();
    }

    public function getAvailabilityLabelAttribute(): string
    {
        return ucfirst($this->status ?? 'offline');
    }

    public function queueRequests(): HasMany
    {
        return $this->hasMany(QueueRequest::class, 'assigned_helper_id', 'id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }
}