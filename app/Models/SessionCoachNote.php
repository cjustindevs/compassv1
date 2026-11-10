<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A private note from the supervising Adviser to the Helper during a live session. */
class SessionCoachNote extends Model
{
    protected $fillable = ['session_id', 'adviser_id', 'helper_id', 'note', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    public function adviser(): BelongsTo
    {
        return $this->belongsTo(Adviser::class, 'adviser_id');
    }

    /** JSON shape shared by the Adviser watch page and the Helper chat panel. */
    public function toFeed(): array
    {
        return [
            'id' => $this->id,
            'note' => $this->note,
            'adviser' => $this->adviser?->full_name ?? 'Your Adviser',
            'time' => $this->created_at?->copy()->timezone('Asia/Manila')->format('g:i A'),
            'read' => $this->read_at !== null,
        ];
    }
}
