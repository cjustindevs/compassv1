<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoiceCall extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $hidden = ['caller_user_id', 'callee_user_id'];
    protected $casts = ['session_id'=>'integer','caller_user_id'=>'integer','callee_user_id'=>'integer',
        'accepted_at'=>'datetime','connected_at'=>'datetime','ended_at'=>'datetime',
        'caller_connected_at'=>'datetime','callee_connected_at'=>'datetime','caller_seen_at'=>'datetime','callee_seen_at'=>'datetime'];
    public const OPEN = ['ringing', 'connecting', 'connected'];
}
