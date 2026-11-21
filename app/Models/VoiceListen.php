<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An Adviser listening live (receive-only) to a connected call. No audio is stored. */
class VoiceListen extends Model
{
    protected $fillable = ['call_id', 'session_id', 'adviser_user_id', 'purpose', 'reason', 'started_at', 'ended_at'];

    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime', 'session_id' => 'integer', 'adviser_user_id' => 'integer'];

    public function scopeActive($query)
    {
        return $query->whereNull('ended_at');
    }
}
