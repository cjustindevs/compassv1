<?php

namespace App\Services;

use App\Events\QueueUpdated;
use App\Models\Helper;
use App\Models\QueueRequest;
use App\Models\User;
use App\Traits\BroadcastsSafely;
use Illuminate\Support\Facades\DB;

class QueueManagementService
{
    use BroadcastsSafely;

    protected int $maxQueueWaitMinutes = 30;

    protected array $priorityWeights = [
        'emergency' => 1,
        'high' => 2,
        'moderate' => 3,
        'low' => 4,
    ];

    public function addToQueue(QueueRequest $queue): void
    {
        $queue->queue_position = $this->calculateQueuePosition($queue);
        $queue->estimated_wait = $this->calculateEstimatedWait($queue->priority_level);
        $queue->save();

        app(HelperMatchingService::class)->processQueueRequest($queue);
        $this->broadcastQueueUpdated();
    }

    protected function calculateQueuePosition(QueueRequest $queue): int
    {
        return QueueRequest::where('request_status', 'waiting')
            ->whereIn('priority_level', $this->sameOrHigherPriorities($queue->priority_level))
            ->count() + 1;
    }

    protected function sameOrHigherPriorities(string $priority): array
    {
        $weight = $this->priorityWeights[$priority] ?? 4;

        return array_keys(array_filter($this->priorityWeights, fn (int $value) => $value <= $weight));
    }

    protected function calculateEstimatedWait(string $riskLevel): int
    {
        $baseWait = [
            'emergency' => 1,
            'high' => 3,
            'moderate' => 10,
            'low' => 20,
        ][$riskLevel] ?? 15;

        $availableHelpers = Helper::where('status', 'available')
            ->where('availability', 'available')
            ->where('is_ready', true)
            ->where('current_shift_sessions', '<', Helper::MAX_SESSIONS_PER_SHIFT)
            ->count();

        if ($availableHelpers > 3) {
            return max(2, $baseWait - 5);
        }

        if ($availableHelpers === 0) {
            return $baseWait + 15;
        }

        return $baseWait;
    }

    public function getQueueData(): array
    {
        return QueueRequest::where('request_status', 'waiting')
            ->with('seeker')
            ->orderByRaw("CASE priority_level WHEN 'emergency' THEN 1 WHEN 'high' THEN 2 WHEN 'moderate' THEN 3 ELSE 4 END")
            ->orderBy('request_date')
            ->get()
            ->map(fn (QueueRequest $item, int $index) => [
                'id' => $item->id,
                'seeker_alias' => $item->seeker->generated_alias ?? 'Anonymous',
                'risk_level' => $item->priority_level,
                'priority' => $this->priorityWeights[$item->priority_level] ?? 4,
                'position' => $index + 1,
                'waiting_time' => $item->request_date?->diffInMinutes(now()) ?? 0,
                'status' => $item->request_status,
                'estimated_wait' => $item->estimated_wait,
            ])
            ->toArray();
    }

    public function checkQueueAging(): void
    {
        QueueRequest::where('request_status', 'waiting')
            ->where('request_date', '<', now()->subMinutes($this->maxQueueWaitMinutes))
            ->get()
            ->each(function (QueueRequest $queue) {
                $minutesWaiting = $queue->request_date->diffInMinutes(now());
                $increments = (int) floor(($minutesWaiting - $this->maxQueueWaitMinutes) / 5);
                $currentWeight = $this->priorityWeights[$queue->priority_level] ?? 4;
                $newWeight = max(1, $currentWeight - $increments);

                if ($newWeight >= $currentWeight) {
                    return;
                }

                $queue->update([
                    'priority_level' => array_search($newWeight, $this->priorityWeights, true) ?: $queue->priority_level,
                    'aging_priority_increases' => (int) $queue->aging_priority_increases + 1,
                    'last_priority_increase_at' => now(),
                    'max_wait_reached' => true,
                ]);

                app(HelperMatchingService::class)->processQueueRequest($queue->refresh());
            });

        $this->broadcastQueueUpdated();
    }

    public function getQueueStats(): array
    {
        $pending = QueueRequest::where('request_status', 'waiting');

        return [
            'total_pending' => (clone $pending)->count(),
            'by_priority' => (clone $pending)->select('priority_level', DB::raw('count(*) as count'))
                ->groupBy('priority_level')
                ->get(),
            'oldest_waiting' => (clone $pending)->orderBy('request_date')->first()?->request_date,
            'avg_wait_time' => (clone $pending)->avg('estimated_wait') ?? 0,
        ];
    }

    public function removeFromQueue(QueueRequest $queue): void
    {
        $queue->update(['request_status' => 'cancelled']);
        $this->broadcastQueueUpdated();
    }

    protected function broadcastQueueUpdated(): void
    {
        foreach (User::where('role', 'moderator')->pluck('id') as $moderatorUserId) {
            $this->broadcastSafely(new QueueUpdated($moderatorUserId));
        }
    }
}
