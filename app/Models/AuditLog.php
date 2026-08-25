<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /**
     * The user who caused the event, when the actor was a user account.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_account_id', 'id');
    }
}
