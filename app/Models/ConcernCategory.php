<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConcernCategory extends Model
{
    protected $table = 'concern_categories';

    protected $fillable = [
        'concern_name',
        'description',
        'is_active'
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($query) { return $query->where('is_active', true); }

    public function sessions()
    {
        return $this->hasMany(Session::class, 'concern_id', 'id');
    }
}