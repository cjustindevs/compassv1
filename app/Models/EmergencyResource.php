<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmergencyResource extends Model
{
    protected $table = 'emergency_resources';

    protected $fillable = [
        'agency_name',
        'hotline',
        'description',
        'status',
    ];

    public function getHotlineLabelAttribute(): string
    {
        return $this->hotline ?: 'N/A';
    }
}
