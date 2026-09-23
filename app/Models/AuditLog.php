<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public function actor(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class,'user_account_id'); }

    protected $casts = ['metadata' => 'array'];

    protected static function booted(): void {
        static::updating(fn () => throw new \LogicException('Audit events are append-only.'));
        static::deleting(fn () => throw new \LogicException('Audit events are append-only.'));
    }

    protected $fillable = [
        'target_type',
        'target_id',
        'outcome',
        'metadata',

        'user_account_id',
        'action',
        'module',
        'description',
        'ip_address',
        'user_agent',
    ];
}
