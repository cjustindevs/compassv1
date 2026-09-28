<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionReconnection extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['detected_at' => 'datetime', 'requested_at' => 'datetime', 'offered_at' => 'datetime', 'resolved_at' => 'datetime'];

    public function session()
    {
        return $this->belongsTo(Session::class);
    }
}
