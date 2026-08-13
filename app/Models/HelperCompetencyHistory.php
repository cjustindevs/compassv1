<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelperCompetencyHistory extends Model
{
    protected $table = 'helper_competency_history';

    protected $fillable = [
        'helper_id',
        'adviser_id',
        'evaluation_date',
        'active_listening_score',
        'empathy_score',
        'respect_score',
        'ethical_practices_score',
        'referral_accuracy_score',
        'appeal_status',
        'overall_score',
        'competency_level',
        'evaluation_period',
        'remarks',
    ];

    protected $casts = [
        'evaluation_date' => 'datetime',
        'active_listening_score' => 'float',
        'empathy_score' => 'float',
        'respect_score' => 'float',
        'ethical_practices_score' => 'float',
        'referral_accuracy_score' => 'float',
        'overall_score' => 'float',
    ];

    public function helper(): BelongsTo
    {
        return $this->belongsTo(Helper::class, 'helper_id', 'id');
    }

    public function adviser(): BelongsTo
    {
        return $this->belongsTo(Adviser::class, 'adviser_id', 'id');
    }

    public function getScoreBreakdownAttribute(): array
    {
        return [
            'active_listening' => (float) ($this->active_listening_score ?? 0),
            'empathy' => (float) ($this->empathy_score ?? 0),
            'respect' => (float) ($this->respect_score ?? 0),
            'ethical_practices' => (float) ($this->ethical_practices_score ?? 0),
            'referral_accuracy' => (float) ($this->referral_accuracy_score ?? 0),
        ];
    }

    public function getLevelLabelAttribute(): string
    {
        return ucfirst((string) $this->competency_level)
            ?: match (true) {
                ($this->overall_score ?? 0) >= 90 => 'Expert',
                ($this->overall_score ?? 0) >= 80 => 'Advanced',
                ($this->overall_score ?? 0) >= 70 => 'Proficient',
                default => 'Developing',
            };
    }
}
