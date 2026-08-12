<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSavedResource extends Model
{
    protected $table = 'user_saved_resources';

    protected $fillable = [
        'user_id',
        'resource_id',
        'saved_at',
    ];

    protected $casts = [
        'saved_at' => 'datetime',
    ];

    public $timestamps = true;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(SelfHelpResource::class, 'resource_id', 'id');
    }
}