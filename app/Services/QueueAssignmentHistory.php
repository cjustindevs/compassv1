<?php

namespace App\Services;

use App\Models\{Helper, QueueRequest, Session};
use Illuminate\Support\Facades\DB;

/** Forward-only evidence: legacy assignments are never backfilled as verified. */
class QueueAssignmentHistory
{
    public function processed(QueueRequest $queue): void
    {
        DB::table('queue_requests')->where('id', $queue->id)->whereNull('first_processed_at')->update(['first_processed_at'=>now()]);
    }

    public function offered(QueueRequest $queue, Session $session, Helper $helper, string $method, bool $override): void
    {
        $this->processed($queue);
        DB::table('queue_assignment_attempts')->where('session_id', $session->id)->whereIn('outcome', ['offered','accepted'])
            ->update(['outcome'=>'reassigned', 'ended_at'=>now()]);
        DB::table('queue_assignment_attempts')->insert([
            'queue_request_id'=>$queue->id, 'session_id'=>$session->id, 'helper_id'=>$helper->id, 'method'=>$method,
            'emergency_coordination'=>$session->requires_immediate_action || $session->risk_level === 'emergency',
            'eligibility_evidence'=>json_encode(['verified'=>!$override, 'readiness_id'=>$helper->getCurrentReadiness()?->id,
                'duty_id'=>\App\Models\HelperSchedule::coveringShiftFor($helper->id)?->id, 'remaining_capacity'=>$helper->getRemainingCapacity()]),
            'offered_at'=>now(), 'outcome'=>'offered',
        ]);
    }

    public function synchronize(Session $session): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('queue_assignment_attempts')) return;
        $row = DB::table('queue_assignment_attempts')->where('session_id', $session->id)->whereIn('outcome', ['offered','accepted'])->latest('id')->first();
        if (!$row) return;
        if ((int)$row->helper_id !== (int)$session->helper_id || in_array($session->session_status, ['cancelled','no_show'], true)) {
            DB::table('queue_assignment_attempts')->where('id', $row->id)->update(['outcome'=>match ($session->match_status) {'declined'=>'declined','expired'=>'expired','eligibility_changed'=>'ineligible',default=>'cancelled'}, 'ended_at'=>now()]);
        } elseif ($session->helper_accepted_at && !$row->accepted_at) {
            $evidence = json_decode($row->eligibility_evidence, true);
            $evidence['accepted_eligible'] = $session->helper && app(HelperEligibilityService::class)->allows($session->helper, $session);
            DB::table('queue_assignment_attempts')->where('id', $row->id)->update(['outcome'=>'accepted', 'accepted_at'=>$session->helper_accepted_at, 'eligibility_evidence'=>json_encode($evidence)]);
        }
    }
}
