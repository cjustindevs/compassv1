<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConcernCategory extends Model
{
    protected $table = 'concern_categories';

    protected $fillable = [
        'concern_name',
        'description'
    ];

    public function sessions()
    {
        return $this->hasMany(Session::class, 'concern_id', 'id');
    }
}