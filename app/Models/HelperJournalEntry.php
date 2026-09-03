<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelperJournalEntry extends Model
{
    protected $table = 'helper_journal_entries';

    protected $fillable = [
        'helper_id',
        'mood',
        'content',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function helper(): BelongsTo
    {
        return $this->belongsTo(Helper::class, 'helper_id');
    }
}
