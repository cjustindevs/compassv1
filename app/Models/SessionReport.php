<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionReport extends Model
{
    protected $table = 'session_reports';

    protected $fillable = [
        'session_id',
        'help_seeker_condition',
        'referral_recommended',
        'session_summary',
        'observations',
        'actions_taken',
        'risk_level_assessed',
        'personal_reflection',
        'skills_applied',
        'adviser_reviewed',
        'reviewed_date',
        'created_date',
        'documented_at',
        'documentation_late',
    ];

    protected $casts = [
        'referral_recommended' => 'boolean',
        'adviser_reviewed' => 'boolean',
        'reviewed_date' => 'datetime',
        'created_date' => 'datetime',
        'documented_at' => 'datetime',
        'documentation_late' => 'boolean',
        'skills_applied' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id', 'id');
    }

    public function getSkillsAttribute(): array
    {
        return is_array($this->skills_applied) ? $this->skills_applied : [];
    }
}
