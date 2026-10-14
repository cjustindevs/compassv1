<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffMail extends Model
{
    protected $table = 'staff_mail_outbox';

    protected $guarded = [];

    protected $hidden = ['payload'];

    protected $casts = ['payload' => 'encrypted:array', 'available_at' => 'datetime', 'sent_at' => 'datetime'];
}
