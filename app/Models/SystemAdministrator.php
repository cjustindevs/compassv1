<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemAdministrator extends Model
{
    protected $fillable = ['user_account_id', 'first_name', 'last_name', 'email'];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_account_id');
    }
}
