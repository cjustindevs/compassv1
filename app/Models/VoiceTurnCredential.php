<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoiceTurnCredential extends Model
{
    protected $guarded = [];
    protected $hidden = ['payload'];
    protected $casts = ['payload'=>'encrypted:array','ready_at'=>'datetime','expires_at'=>'datetime'];
}
