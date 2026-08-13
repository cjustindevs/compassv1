<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Adviser extends Model
{
    protected $table = 'advisers';

    protected $fillable = [
        'user_account_id',
        'first_name',
        'last_name',
        'email',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_account_id', 'id');
    }

    public function competencyEvaluations(): HasMany
    {
        return $this->hasMany(HelperCompetencyHistory::class, 'adviser_id', 'id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? '')) ?: ($this->email ?? 'Adviser');
    }
}