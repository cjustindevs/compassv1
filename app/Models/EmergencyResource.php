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

    public function scopePublished($query) { return $query->where('status','active')->where('visibility','public')->whereNull('archived_at')->where(fn($q)=>$q->whereNull('review_date')->orWhereDate('review_date','>=',now('Asia/Manila')->toDateString())); }

    public function getHotlineLabelAttribute(): string
    {
        return $this->hotline ?: 'N/A';
    }
}
