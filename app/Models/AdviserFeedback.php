<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdviserFeedback extends Model
{
    protected $table = 'adviser_feedback';

    protected $fillable = [
        'report_id',
        'adviser_id',
        'status',
        'feedback_text',
        'strengths',
        'improvement_areas',
        'competency_rating',
        'competency_level',
        'training_recommendation',
        'follow_up_action',
        'created_date',
    ];

    protected $casts = [
        'created_date' => 'datetime',
        'competency_rating' => 'integer',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(SessionReport::class, 'report_id', 'id');
    }

    public function adviser(): BelongsTo
    {
        return $this->belongsTo(Adviser::class, 'adviser_id', 'id');
    }
}
