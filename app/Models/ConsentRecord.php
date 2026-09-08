<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsentRecord extends Model
{
    protected $table = 'consent_records';

    protected $fillable = [
        'seeker_id',
        'document_type',
        'ip_address',
        'user_agent',
        'version',
        'consent_given',
        'consent_date',
        'withdrawn',
        'withdrawn_date'
    ];

    protected $casts = [
        'consent_date' => 'datetime',
        'withdrawn_date' => 'datetime',
        'consent_given' => 'boolean',
        'withdrawn' => 'boolean'
    ];

    public function seeker()
    {
        return $this->belongsTo(HelpSeeker::class, 'seeker_id', 'id');
    }
}
