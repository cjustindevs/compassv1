<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelperSpecialty extends Model
{
    protected $fillable = [
        'helper_id',
        'category',
        'proficiency_level',
        'sessions_handled',
        'avg_rating',
        'verified_by_adviser',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'proficiency_level' => 'integer',
        'sessions_handled' => 'integer',
        'avg_rating' => 'decimal:2',
        'verified_by_adviser' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function helper(): BelongsTo
    {
        return $this->belongsTo(Helper::class, 'helper_id', 'id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(Adviser::class, 'verified_by', 'id');
    }
}
