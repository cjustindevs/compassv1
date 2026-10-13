<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class VoiceSignal implements ShouldBroadcastNow
{
    public function __construct(public int $sessionId, private int $recipientId, private array $signal) {}
    public function broadcastOn(): PrivateChannel { return new PrivateChannel('voice.'.$this->sessionId.'.'.$this->recipientId); }
    public function broadcastAs(): string { return 'VoiceSignal'; }
    public function broadcastWith(): array { return $this->signal; }
}
