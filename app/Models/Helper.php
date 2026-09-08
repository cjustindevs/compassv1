<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Helper extends Model
{
    public function getPublicAliasAttribute(): string
    {
        return 'Peer Helper ' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    protected $table = 'helpers';

    public ?float $matching_score = null;

    public ?array $matching_details = null;

    protected $fillable = [
        'user_account_id',
        'adviser_id',
        'first_name',
        'last_name',
        'email',
        'status',
        'competency_level',
        'competency_score',
        'competency_risk_level',
        'availability',
        'available_since',
        'break_started_at',
        'is_ready',
        'last_readiness_at',
        'max_concurrent_sessions',
        'active_sessions_count',
        'total_sessions_handled',
        'current_shift_sessions',
        'feedback_count',
        'avg_rating',
        'specializations',
        'specialties',
        'bio',
        'phone',
        'preferred_language',
        'languages',
        'timezone',
        'default_shift_start',
        'default_shift_end',
        'is_under_review',
        'review_reason',
    ];

    protected $casts = [
        'competency_level' => 'integer',
        'competency_score' => 'decimal:2',
        'competency_risk_level' => 'integer',
        'max_concurrent_sessions' => 'integer',
        'active_sessions_count' => 'integer',
        'total_sessions_handled' => 'integer',
        'current_shift_sessions' => 'integer',
        'feedback_count' => 'integer',
        'avg_rating' => 'decimal:2',
        'available_since' => 'datetime',
        'break_started_at' => 'datetime',
        'last_readiness_at' => 'datetime',
        'is_ready' => 'boolean',
        'is_under_review' => 'boolean',
        'languages' => 'array',
        'specialties' => 'array',
    ];

    public const MAX_SESSIONS_PER_SHIFT = 2;
    public const MAX_HELPERS_PER_ADVISER = 15;
    public const BREAK_TIMEOUT_MINUTES = 30;
    public const PRE_SESSION_BRIEF_MINUTES = 5;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_account_id', 'id');
    }

    public function adviser(): BelongsTo
    {
        return $this->belongsTo(Adviser::class, 'adviser_id', 'id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class, 'helper_id', 'id');
    }

    public function activeSessions(): HasMany
    {
        return $this->hasMany(Session::class, 'helper_id', 'id')
            ->whereIn('session_status', ['scheduled', 'helper_assigned', 'active']);
    }

    public function pendingSessions(): HasMany
    {
        return $this->hasMany(Session::class, 'helper_id', 'id')
            ->where('session_status', Session::STATUS_HELPER_ASSIGNED);
    }

    public function completedSessions(): HasMany
    {
        return $this->hasMany(Session::class, 'helper_id', 'id')
            ->whereIn('session_status', [Session::STATUS_COMPLETED, Session::STATUS_EVALUATED]);
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

    public function currentReadiness(): HasOne
    {
        return $this->hasOne(ReadinessCheck::class, 'helper_id', 'id')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('valid_until', '>', now())
                    ->orWhere(function ($query) {
                        $query->whereNull('valid_until')
                            ->where('assessment_date', '>=', now()->subHours(4));
                    });
            })
            ->latestOfMany('assessment_date');
    }

    public function helperSpecialties(): HasMany
    {
        return $this->hasMany(HelperSpecialty::class, 'helper_id', 'id');
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(HelperSchedule::class, 'helper_id', 'id')
            ->whereDate('date', now()->toDateString())
            ->where('is_active', true);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(HelperSchedule::class, 'helper_id', 'id');
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
            $q->ready();
        });
    }

    /**
     * Find the most suitable available helper for the given risk level:
     * online, passed their latest readiness check, competent enough,
     * and not at their concurrent session limit.
     */
    public static function findAvailableForRisk(?string $riskLevel, ?int $excludeHelperId = null): ?Helper
    {
        return app(\App\Services\HelperMatchingService::class)
            ->findBestMatchForRisk($riskLevel ?? 'low', $excludeHelperId);
    }

    public function getActiveSessionsCount(): int
    {
        return $this->activeSessions()->count();
    }

    public function hasCapacity(): bool
    {
        $limit = min(self::MAX_SESSIONS_PER_SHIFT, (int) ($this->max_concurrent_sessions ?: self::MAX_SESSIONS_PER_SHIFT));
        $activeSessionCount = $this->activeSessions()->count();
        $currentShiftSessions = $this->currentAssignedSessionsCount();

        if ((int) $this->active_sessions_count !== $activeSessionCount || (int) $this->current_shift_sessions !== $currentShiftSessions) {
            $this->syncSessionCounters($activeSessionCount, $currentShiftSessions);
        }

        return $activeSessionCount < $limit
            && $currentShiftSessions < self::MAX_SESSIONS_PER_SHIFT;
    }

    public function getRemainingCapacity(): int
    {
        return max(0, self::MAX_SESSIONS_PER_SHIFT - $this->currentAssignedSessionsCount());
    }

    public static function canAddHelperToAdviser(int $adviserId): bool
    {
        return self::getRemainingSlotsForAdviser($adviserId) > 0;
    }

    public static function getRemainingSlotsForAdviser(int $adviserId): int
    {
        return max(0, self::MAX_HELPERS_PER_ADVISER - self::where('adviser_id', $adviserId)->count());
    }

    public function isReady(): bool
    {
        return (bool) ($this->getCurrentReadiness()?->isReady() || $this->latestReadiness?->isReady());
    }

    /**
     * The helper's most recent readiness check that is still active and valid.
     */
    public function getCurrentReadiness(): ?ReadinessCheck
    {
        return $this->readinessChecks()
            ->where('is_active', true)
            ->where('valid_until', '>', now())
            ->latest('assessment_date')
            ->first();
    }

    /**
     * Derived readiness state used to decide access:
     * 'ready', 'not_ready', or 'not_assessed'.
     */
    public function getReadinessStatus(): string
    {
        if (! $this->isReady()) {
            return $this->readinessChecks()->exists() ? 'not_ready' : 'not_assessed';
        }

        return 'ready';
    }

    public function isOnline(): bool
    {
        return $this->availability === 'available' || $this->status === 'available';
    }

    public function canAcceptSessions(): bool
    {
        return $this->isAvailable();
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

    public function isAvailable(): bool
    {
        if (! in_array($this->status, ['available', 'busy'], true)) {
            return false;
        }

        if ($this->availability !== 'available') {
            return false;
        }

        if ($this->is_under_review) {
            return false;
        }

        if (! $this->isReady() || ! $this->hasCapacity()) {
            return false;
        }

        $schedule = $this->schedule;

        return $schedule && $schedule->isOnDuty();
    }

    public function canHandleRiskLevel(?string $riskLevel): bool
    {
        $effectiveCompetency = max((int) $this->competency_risk_level, (int) $this->competency_level);

        return $effectiveCompetency >= $this->getRequiredCompetencyForRisk($riskLevel ?? 'low');
    }

    public function getRequiredCompetencyForRisk(string $riskLevel): int
    {
        return match ($riskLevel) {
            'emergency' => 4,
            'high' => 3,
            'moderate' => 2,
            default => 1,
        };
    }

    public function getCompetencyLevelFromScore(float $score): int
    {
        if ($score >= 4.50) {
            return 4;
        }
        if ($score >= 3.50) {
            return 3;
        }
        if ($score >= 2.50) {
            return 2;
        }

        return 1;
    }

    public function updateCompetency(HelperCompetencyHistory $evaluation): void
    {
        $score = (float) $evaluation->overall_score;
        $normalizedScore = $score > 5 ? $score / 20 : $score;

        $this->update([
            'competency_score' => $normalizedScore,
            'competency_level' => $this->getCompetencyLevelFromScore($normalizedScore),
            'competency_risk_level' => $this->getCompetencyLevelFromScore($normalizedScore),
        ]);
    }

    public function incrementShiftSessions(): void
    {
        $this->increment('current_shift_sessions');
        $this->increment('active_sessions_count');
        $this->increment('total_sessions_handled');
    }

    public function decrementShiftSessions(): void
    {
        $this->syncSessionCounters();
    }

    public function resetShiftSessions(): void
    {
        $this->update(['current_shift_sessions' => 0]);
    }

    public function syncSessionCounters(?int $activeSessionCount = null, ?int $currentShiftSessions = null): void
    {
        $this->update([
            'active_sessions_count' => $activeSessionCount ?? $this->activeSessions()->count(),
            'current_shift_sessions' => $currentShiftSessions ?? $this->currentAssignedSessionsCount(),
        ]);
    }

    protected function currentAssignedSessionsCount(): int
    {
        return $this->sessions()
            ->where('session_status', Session::STATUS_ACTIVE)
            ->count();
    }

    public function setAvailability(string $availability, ?string $reason = null): void
    {
        $previous = $this->availability ?? ($this->status === 'available' ? 'available' : 'unavailable');

        $updates = ['availability' => $availability];

        if ($availability === 'available') {
            $updates['status'] = 'available';
            $updates['available_since'] = now();
            $updates['break_started_at'] = null;
        } elseif ($availability === 'break') {
            $updates['status'] = 'offline';
            $updates['break_started_at'] = now();
            $updates['is_ready'] = false;
        } else {
            $updates['status'] = 'offline';
            $updates['available_since'] = null;
            $updates['is_ready'] = false;
        }

        $this->update($updates);

        HelperAvailabilityLog::create([
            'helper_id' => $this->id,
            'previous_status' => $previous,
            'new_status' => $availability,
            'changed_at' => now(),
            'reason' => $reason,
            'changed_by' => auth()->id(),
        ]);
    }

    public function isBreakTimedOut(): bool
    {
        return $this->availability === 'break'
            && $this->break_started_at
            && $this->break_started_at->diffInMinutes(now()) >= self::BREAK_TIMEOUT_MINUTES;
    }

    public function getSpecialtyMatchScore(string $category): int
    {
        if ($category === '') {
            return 0;
        }

        $specialty = $this->helperSpecialties->firstWhere('category', $category)
            ?? $this->helperSpecialties()->where('category', $category)->first();

        if ($specialty) {
            return min(100, 100 + (((int) $specialty->proficiency_level - 1) * 5));
        }

        foreach ($this->getRelatedSpecialties($category) as $relatedCategory) {
            if ($this->helperSpecialties->firstWhere('category', $relatedCategory)
                || $this->helperSpecialties()->where('category', $relatedCategory)->exists()) {
                return 75;
            }
        }

        return 0;
    }

    private function getRelatedSpecialties(string $category): array
    {
        return [
            'stress' => ['anxiety', 'academic'],
            'anxiety' => ['stress', 'health'],
            'academic' => ['stress', 'health'],
            'relationships' => ['family', 'stress'],
            'family' => ['relationships', 'stress'],
            'health' => ['anxiety', 'stress'],
            'depression' => ['anxiety', 'stress'],
            'grief' => ['depression', 'stress'],
            'trauma' => ['depression', 'anxiety'],
        ][$category] ?? [];
    }

    public function getLanguageMatchScore(string $preferredLanguage): int
    {
        if ($preferredLanguage === '') {
            return 100;
        }

        $languages = $this->languages ?: array_filter([$this->preferred_language]);

        if (in_array($preferredLanguage, $languages, true)) {
            return 100;
        }

        if ($preferredLanguage === 'en-tl' && (in_array('en', $languages, true) || in_array('tl', $languages, true))) {
            return 75;
        }

        return 0;
    }

    public function getExperienceScore(): int
    {
        $sessions = (int) ($this->total_sessions_handled ?: $this->completedSessions()->count());

        return match (true) {
            $sessions >= 50 => 100,
            $sessions >= 30 => 80,
            $sessions >= 15 => 60,
            $sessions >= 5 => 40,
            default => 20,
        };
    }

    public function getAvailabilityScore(): float
    {
        if (! $this->available_since) {
            return 0;
        }

        return (min($this->available_since->diffInMinutes(now()), 60) / 60) * 100;
    }

    public function calculateWorkloadScore(): float
    {
        $currentSessions = (int) $this->current_shift_sessions;

        if ($currentSessions >= self::MAX_SESSIONS_PER_SHIFT) {
            return 0;
        }

        $score = ((self::MAX_SESSIONS_PER_SHIFT - $currentSessions) / self::MAX_SESSIONS_PER_SHIFT) * 100;

        return min($currentSessions === 0 ? $score + 20 : $score, 100);
    }

    public function calculateMatchingScore(string $riskLevel, ?string $concernCategory, ?string $language): float
    {
        return (((float) $this->competency_score / 5) * 100 * 0.30)
            + ($this->calculateWorkloadScore() * 0.20)
            + ($this->getSpecialtyMatchScore($concernCategory ?? '') * 0.15)
            + ($this->getLanguageMatchScore($language ?? '') * 0.15)
            + ($this->getAvailabilityScore() * 0.10)
            + ($this->getExperienceScore() * 0.10);
    }
}
