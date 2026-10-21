<?php

namespace App\Services;

use App\Models\{Helper, QueueRequest, Session};

class QueueHelperOptions
{
    private array $baseStates = [];
    private array $scores = [];

    public function forRequest(QueueRequest $queue, iterable $helpers): array
    {
        $session = $queue->relationLoaded('supportSession') ? $queue->getRelation('supportSession')
            : Session::with('concern')->where('queue_request_id', $queue->id)->latest('id')->first();
        if (! $session) return ['helpers'=>[], 'unavailable'=>[], 'reason'=>'This request has no linked support session. Refresh or review the request before assigning.'];
        if (!in_array($session->session_status, ['waiting', 'helper_assigned'], true) || $session->helper_accepted_at) {
            return ['helpers'=>[], 'unavailable'=>[], 'reason'=>'This request is no longer awaiting assignment. Open its current session status.'];
        }
        return $this->forSession($session, $helpers, $queue->assigned_helper_id);
    }

    public function forSession(Session $session, iterable $helpers, ?int $exclude = null): array
    {
        $eligible = collect(); $unavailable = []; $reasons = [];
        foreach ($helpers as $helper) {
            if ((int) $helper->id === (int) $exclude) continue;
            $eligibility = app(HelperEligibilityService::class);
            // Reuse duty/readiness queries within this HTTP response, never across requests.
            $state = $this->baseStates[$helper->id] ??= $eligibility->status($helper);
            $reason = $state['reason'] ?? $eligibility->requestReasons($helper, $session)[0] ?? null;
            if ($reason) {
                $label = $eligibility->labelForReason($reason); $reasons[$label] = ($reasons[$label] ?? 0) + 1;
                $unavailable[] = ['id'=>$helper->id, 'name'=>$helper->full_name, 'status'=>$label, 'reason'=>$reason,
                    'readiness_passed'=>$helper->getCurrentReadiness()?->assessment_result === 'ready'];
                continue;
            }
            $scoreKey = json_encode([$helper->id, $session->risk_level, $session->concern?->concern_name, $session->preferred_language]);
            $eligible->push(['id'=>$helper->id, 'label'=>$helper->full_name.' | Ready | '.$helper->getRemainingCapacity().'/'.Helper::MAX_SESSIONS_PER_SHIFT.' remaining | '.$helper->competency_level.'/5',
                'score'=>$this->scores[$scoreKey] ??= $helper->calculateMatchingScore($session->matchingRiskLevel(), $session->concern?->concern_name, $session->preferred_language)]);
        }
        arsort($reasons);
        return ['helpers'=>$eligible->sortByDesc('score')->values()->map(fn ($row) => ['id'=>$row['id'],'label'=>$row['label']])->all(), 'unavailable'=>$unavailable,
            'reason'=>$eligible->isNotEmpty() ? 'Eligible Helpers for this request. Availability is rechecked on assignment.'
                : 'No eligible Helper for this request. '.collect($reasons)->map(fn ($count, $label) => $label.': '.$count)->implode('; ').'.'];
    }
}
