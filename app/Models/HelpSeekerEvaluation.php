<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpSeekerEvaluation extends Model
{
    protected $table = 'help_seeker_evaluations';

    protected $fillable = [
        'answers',
        'instrument_version',
        'submitted_at',

        'session_id',
        'helpfulness_score',
        'comfort_score',
        'feeling_after_score',
        'understood_score',
        'reuse_score',
        'comments',
        'overall_score',
    ];

    protected $casts = [
        'answers' => 'array', 'submitted_at' => 'datetime',
        'overall_score' => 'float',
    ];

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id', 'id');
    }
}