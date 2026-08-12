<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserResourceProgress extends Model
{
    protected $table = 'user_resource_progress';

    protected $fillable = [
        'user_id',
        'resource_id',
        'progress_percentage',
        'last_accessed',
        'completed_at',
    ];

    protected $casts = [
        'progress_percentage' => 'integer',
        'last_accessed' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(SelfHelpResource::class, 'resource_id', 'id');
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->progress_percentage >= 100 || ! is_null($this->completed_at);
    }
}