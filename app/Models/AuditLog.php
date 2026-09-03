<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_account_id',
        'action',
        'module',
        'description',
        'ip_address',
        'user_agent',
    ];
}
