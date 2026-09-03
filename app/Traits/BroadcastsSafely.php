<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait BroadcastsSafely
{
    protected function broadcastSafely(object $event): bool
    {
        try {
            event($event);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Broadcast failed but request completed: '.$e->getMessage(), [
                'event' => $event::class,
            ]);

            return false;
        }
    }
}
