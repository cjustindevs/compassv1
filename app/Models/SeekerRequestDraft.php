<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeekerRequestDraft extends Model
{
    public const RETENTION_DAYS = 7;
    protected $fillable = ['seeker_id', 'instrument_version', 'stage', 'session_id', 'payload', 'expires_at'];
    protected $hidden = ['payload'];
    protected $casts = ['payload' => 'encrypted:array', 'expires_at' => 'datetime'];
}
