<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ephemeral encrypted negotiation data, never audio or chat content. */
class VoiceSignalPacket extends Model
{
    protected $table = 'voice_signals';
    public $timestamps = false;
    protected $guarded = [];
    protected $hidden = ['payload'];
    protected $casts = ['payload'=>'encrypted:array', 'expires_at'=>'datetime'];
}
