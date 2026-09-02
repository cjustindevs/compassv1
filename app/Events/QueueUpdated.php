<?php

namespace App\Events;

use App\Models\QueueRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QueueUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;

    public array $payload;

    /**
     * @param  int  $userId  The user whose "user.{id}" channel should receive the update.
     * @param  array|null  $stats  Optional pre-computed stats; otherwise pulled from the DB.
     */
    public function __construct(int $userId, ?array $stats = null)
    {
        $this->userId = $userId;
        $this->payload = $stats ?? self::computeStats();
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('moderator.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'QueueUpdated';
    }

    public function broadcastWith(): array
    {
        return array_merge($this->payload, [
            'message' => 'Queue updated',
        ]);
    }

    public static function computeStats(): array
    {
        $items = QueueRequest::whereIn('request_status', ['waiting', 'assigned'])->get();

        $matched = $items->where('request_status', 'assigned')->filter(
            fn ($q) => $q->request_date && $q->matched_date
        );

        $avgWait = '0m 0s';
        if ($matched->count()) {
            $total = $matched->reduce(
                fn ($carry, $q) => $carry + $q->matched_date->diffInSeconds($q->request_date),
                0
            );
            $seconds = floor($total / $matched->count());
            $avgWait = floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
        }

        return [
            'waiting' => $items->where('request_status', 'waiting')->count(),
            'assigned' => $items->where('request_status', 'assigned')->count(),
            'avg_wait' => $avgWait,
            'unserved' => $items->where('request_status', 'waiting')
                ->where('request_date', '<', now()->subMinutes(30))
                ->count(),
            'queue_size' => $items->count(),
        ];
    }
}
