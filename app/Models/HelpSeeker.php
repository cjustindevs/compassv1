<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpSeeker extends Model
{
    protected $table = 'help_seekers';

    protected $fillable = [
        'user_account_id',
        'generated_alias',
        'age',
        'gender',
        'account_created'
    ];

    protected $casts = [
        'account_created' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_account_id', 'id');
    }

    public function sessions()
    {
        return $this->hasMany(Session::class, 'seeker_id', 'id');
    }

    public function consentRecords()
    {
        return $this->hasMany(ConsentRecord::class, 'seeker_id', 'id');
    }

    public function evaluations()
    {
        return $this->hasManyThrough(
            HelpSeekerEvaluation::class,
            Session::class,
            'seeker_id',
            'session_id',
            'id',
            'id'
        );
    }

    public function identityVault()
    {
        return $this->hasOne(IdentityVault::class, 'seeker_id', 'id');
    }

    public function queueRequests()
    {
        return $this->hasMany(QueueRequest::class, 'seeker_id', 'id');
    }
}