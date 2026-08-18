<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalNote extends Model
{
    protected $table = 'professional_notes';

    protected $fillable = [
        'referral_id',
        'professional_id',
        'session_id',
        'intervention_type',
        'notes',
        'follow_up_plan',
        'follow_up_date',
    ];

    protected $casts = [
        'follow_up_date' => 'date',
    ];

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class, 'referral_id', 'id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(PsychologyProfessional::class, 'professional_id', 'id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id', 'id');
    }

    public function getInterventionTypeLabelAttribute(): string
    {
        $labels = [
            'initial_assessment' => 'Initial Assessment',
            'individual_therapy' => 'Individual Therapy',
            'group_therapy' => 'Group Therapy',
            'counseling_session' => 'Counseling Session',
            'crisis_intervention' => 'Crisis Intervention',
            'psychoeducation' => 'Psychoeducation',
            'family_therapy' => 'Family Therapy',
            'telehealth_session' => 'Telehealth Session',
        ];

        return $labels[$this->intervention_type] ?? ucfirst(str_replace('_', ' ', $this->intervention_type));
    }
}
