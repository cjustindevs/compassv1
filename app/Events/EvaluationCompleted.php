<?php

namespace App\Events;

use App\Models\HelperCompetencyHistory;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EvaluationCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public HelperCompetencyHistory $evaluation;

    /**
     * The USER account id of the helper whose evaluation was completed.
     */
    public int $userId;

    public function __construct(HelperCompetencyHistory $evaluation, int $userId)
    {
        $this->evaluation = $evaluation;
        $this->userId = $userId;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('helper.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'EvaluationCompleted';
    }

    public function broadcastWith(): array
    {
        return [
            'evaluation_id' => $this->evaluation->id,
            'helper_name' => $this->evaluation->helper?->full_name ?? 'Your',
            'overall_score' => $this->evaluation->overall_score,
            'competency_level' => $this->evaluation->competency_level ?? 'Reviewed',
            'period' => $this->evaluation->evaluation_period,
            'message' => 'Your competency evaluation is ready. Score: ' . $this->evaluation->overall_score . '/5',
            'link' => '/helper/competency',
        ];
    }
}