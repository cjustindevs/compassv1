<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Session extends Model
{
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

    public function evaluation(): HasOne
    {
        return $this->hasOne(HelpSeekerEvaluation::class, 'session_id', 'id');
    }
}