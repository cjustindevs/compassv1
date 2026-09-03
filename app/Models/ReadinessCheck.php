<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadinessCheck extends Model
{
    protected $table = 'readiness_checks';

    protected $fillable = [
        'helper_id',
        'availability_status',
        'shift_start',
        'shift_end',
        'assessment_result',
        'emotionally_ready',
        'willing_to_listen',
        'stress_level',
        'physical_condition',
        'notes',
        'assessment_date',
        'valid_until',
        'breathing_exercise',
        'is_active',
        'validated_at',
        'validated_by',
    ];

    protected $casts = [
        'shift_start' => 'datetime',
        'shift_end' => 'datetime',
        'assessment_date' => 'datetime',
        'valid_until' => 'datetime',
        'validated_at' => 'datetime',
        'emotionally_ready' => 'boolean',
        'willing_to_listen' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function helper(): BelongsTo
    {
        return $this->belongsTo(Helper::class, 'helper_id', 'id');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * Whether this check currently represents a valid, ready helper who is
     * allowed to take sessions.
     */
    public function isValid(): bool
    {
        if ($this->exists && $this->is_active === false) {
            return false;
        }

        return $this->assessment_result === 'ready' && $this->isCurrentlyValid();
    }

    public function scopeReady($query)
    {
        return $query->where('assessment_result', 'ready')
            ->where(function ($query) {
                $query->where('valid_until', '>', now())
                    ->orWhere(function ($query) {
                        $query->whereNull('valid_until')
                            ->where('assessment_date', '>=', now()->subHours(4));
                    });
            });
    }

    public function scopeAvailable($query)
    {
        return $query->where('availability_status', 'available');
    }

    public function isReady(): bool
    {
        return $this->assessment_result === 'ready' && $this->isCurrentlyValid();
    }

    public function isCurrentlyValid(): bool
    {
        if ($this->valid_until) {
            return $this->valid_until->isFuture();
        }

        return $this->assessment_date?->greaterThanOrEqualTo(now()->subHours(4)) ?? false;
    }

    public function getStatusAttribute(): string
    {
        return (string) ($this->availability_status ?? 'available');
    }

    public function getResultLabelAttribute(): string
    {
        return match ($this->assessment_result) {
            'ready' => 'Ready',
            'not_ready' => 'Needs Rest',
            default => ucfirst((string) $this->assessment_result),
        };
    }
}
