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
        'assessment_date',
        'breathing_exercise',
    ];

    protected $casts = [
        'shift_start' => 'datetime',
        'shift_end' => 'datetime',
        'assessment_date' => 'datetime',
        'emotionally_ready' => 'boolean',
        'willing_to_listen' => 'boolean',
    ];

    public function helper(): BelongsTo
    {
        return $this->belongsTo(Helper::class, 'helper_id', 'id');
    }

    public function scopeReady($query)
    {
        return $query->where('assessment_result', 'ready');
    }

    public function scopeAvailable($query)
    {
        return $query->where('availability_status', 'available');
    }

    public function isReady(): bool
    {
        return $this->assessment_result === 'ready';
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
