<?php

namespace App\Services;

use App\Models\{EmergencyAlert, Helper, QueueRequest, Session};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Different event cohorts are explicit; pending waits and inferred duty are never averaged. */
class QueuePerformance
{
    public function report(Request $request): array
    {
        $user = $request->user();
        abort_unless($user?->is_active && in_array($user->role, ['moderator','adviser'], true), 403);
        $filters = $request->validate(['risk'=>'nullable|in:low,moderate,high,emergency', 'concern_id'=>'nullable|integer|exists:concern_categories,id',
            'from'=>'required_if:period,custom|nullable|date_format:Y-m-d', 'to'=>'required_if:period,custom|nullable|date_format:Y-m-d']);
        $filters = array_intersect_key($filters, array_flip(['risk','concern_id']));
        $period = AnalyticsPeriod::fromRequest($request);
        $asOf = CarbonImmutable::now('UTC');
        $start = $period->start->utc(); $end = $period->end->utc()->min($asOf);
        $helpers = Helper::query(); $sessions = Session::where('is_test_record',false)->whereNull('duplicate_of_id'); $alerts = EmergencyAlert::query();
        if ($user->role === 'adviser') {
            $actor = app(AdviserScope::class)->actor($user);
            $helpers->where('adviser_id', $actor->id);
            $sessions->where(fn ($q) => $q->where('review_adviser_id', $actor->id)
                ->orWhereHas('helper', fn ($h) => $h->where('adviser_id', $actor->id))
                ->orWhereHas('emergencyAlerts', fn ($a) => $a->where('adviser_id', $actor->id)));
            $alerts->where('adviser_id', $actor->id);
        }
        if ($filters['risk'] ?? null) $sessions->where('risk_level', $filters['risk']);
        if ($filters['concern_id'] ?? null) $sessions->where('concern_id', $filters['concern_id']);
        $queues = QueueRequest::where('is_test_record', false)->whereNull('duplicate_of_id')
            ->whereIn('id', (clone $sessions)->whereNotNull('queue_request_id')->select('queue_request_id'));
        $validIds = (clone $queues)->pluck('id');
        $queueRows = (clone $queues)->get()->keyBy('id');
        $started = (clone $sessions)->whereIn('queue_request_id', $validIds)->where('start_time','>=',$start)->where('start_time','<',$period->end->utc())->where('start_time','<=',$asOf)->get();
        $waits = []; $outsideWaits = []; $invalidWaits = 0;
        foreach ($started as $session) {
            $queue = $queueRows[$session->queue_request_id];
            $entry = $queue->queued_at;
            if (!$entry || $entry->gt($session->start_time)) { $invalidWaits++; continue; }
            $minutes = $entry->diffInSeconds($session->start_time) / 60;
            if ($this->outsideHours($entry)) $outsideWaits[] = $minutes;
            else $waits[] = $minutes;
        }
        $processed = (clone $queues)->where('first_processed_at','>=',$start)->where('first_processed_at','<',$period->end->utc())->where('first_processed_at','<=',$asOf)->get();
        $attempts = DB::table('queue_assignment_attempts')->whereIn('queue_request_id', $processed->pluck('id'))->where('offered_at','<=',$asOf)->orderBy('id')->get()->groupBy('queue_request_id');
        $emergencyQueueIds = EmergencyAlert::whereIn('session_id', (clone $sessions)->pluck('id'))->pluck('session_id');
        $regular = $processed->reject(function ($queue) use ($attempts, $emergencyQueueIds) {
            if ($queue->priority_level === 'emergency') return true;
            if (($attempts[$queue->id] ?? collect())->contains('emergency_coordination', true)) return true;
            return Session::where('queue_request_id', $queue->id)->where(fn ($q) => $q->where('requires_immediate_action', true)->orWhereIn('id', $emergencyQueueIds))->exists();
        });
        $successful = $pending = 0;
        foreach ($regular as $queue) {
            $attempt = ($attempts[$queue->id] ?? collect())->last();
            $evidence = $attempt ? json_decode($attempt->eligibility_evidence, true) : [];
            $session = $attempt ? Session::find($attempt->session_id) : null;
            if ($attempt && $attempt->outcome === 'accepted' && $attempt->accepted_at && CarbonImmutable::parse($attempt->accepted_at)->lte($asOf)
                && !in_array($queue->request_status,['cancelled','expired','failed'],true)
                && ($evidence['verified'] ?? false) && ($evidence['accepted_eligible'] ?? false) && $evidence['readiness_id'] && $evidence['duty_id']
                && $session && (int)$session->helper_id === (int)$attempt->helper_id && $session->helper_accepted_at
                && in_array($session->session_status, ['helper_assigned','scheduled','active','completed','evaluated'], true)) $successful++;
            elseif (in_array($queue->request_status, ['waiting','assigned'], true)) $pending++;
        }
        $alertRows = $alerts->whereIn('session_id', (clone $sessions)->whereIn('queue_request_id', $validIds)->pluck('id'))
            ->where('triggered_at','>=',$start)->where('triggered_at','<',$period->end->utc())->where('triggered_at','<=',$asOf)->get();
        // Emergencies without a queue are still measured, provided they are in the authorized scope and not marked test.
        $unlinked = EmergencyAlert::query()->whereIn('session_id', (clone $sessions)->whereNull('queue_request_id')->pluck('id'))
            ->when($user->role === 'adviser', fn ($q) => $q->where('adviser_id', $actor->id))
            ->where('triggered_at','>=',$start)->where('triggered_at','<',$period->end->utc())->where('triggered_at','<=',$asOf)->get();
        $alertRows = $alertRows->merge($unlinked)->unique('id');
        $responses = []; $unacknowledged = 0; $invalidAcknowledgments = 0;
        foreach ($alertRows as $alert) {
            if (!$alert->acknowledged_at) { $unacknowledged++; continue; }
            if (!$alert->acknowledged_by || $alert->acknowledged_at->lt($alert->triggered_at) || $alert->acknowledged_at->gt($asOf)) { $invalidAcknowledgments++; continue; }
            $responses[] = $alert->triggered_at->diffInSeconds($alert->acknowledged_at) / 60;
        }
        // Utilization describes the authorized Helper pool, independent of case risk/concern filters.
        $helperIds = $helpers->pluck('id');
        $intervals = DB::table('helper_service_intervals')->whereIn('helper_id',$helperIds)->where('started_at','<',$end)->where('valid_until','>',$start)->get()->groupBy('helper_id');
        $active = Session::where('is_test_record',false)->whereNull('duplicate_of_id')->whereIn('helper_id',$helperIds)->whereNotNull('start_time')->where('start_time','<',$end)
            ->where(fn ($q) => $q->where('end_time','>',$start)->orWhere(fn ($a) => $a->whereNull('end_time')->where('session_status','active')))
            ->where(fn ($q) => $q->whereNull('queue_request_id')->orWhereIn('queue_request_id', QueueRequest::where('is_test_record',false)->whereNull('duplicate_of_id')->select('id')))->get()->groupBy('helper_id');
        $dutySeconds = $activeSeconds = 0;
        foreach ($intervals as $helperId => $rows) {
            $duty = $this->merge($rows->map(fn ($row) => [max($start->timestamp, strtotime($row->started_at.' UTC')), min($end->timestamp, strtotime($row->valid_until.' UTC'), $row->ended_at ? strtotime($row->ended_at.' UTC') : $end->timestamp)])->all());
            $work = $this->merge(($active[$helperId] ?? collect())->map(fn ($s) => [$s->start_time->timestamp, ($s->end_time ?? $asOf)->timestamp])->all());
            foreach ($duty as [$a,$b]) {
                $dutySeconds += $b-$a;
                foreach ($work as [$c,$d]) $activeSeconds += max(0, min($b,$d)-max($a,$c));
            }
        }
        $cards = [
            ['label'=>'Average Waiting Time','value'=>SessionPerformance::display(SessionPerformance::average($waits),' min'),'sample'=>count($waits).' in-hours sessions started'],
            ['label'=>'Maximum Waiting Time','value'=>SessionPerformance::display($waits ? max($waits) : null,' min'),'sample'=>'Same started-session cohort'],
            ['label'=>'Helper Utilization Rate','value'=>SessionPerformance::display($dutySeconds ? 100*$activeSeconds/$dutySeconds : null,'%'),'sample'=>round($activeSeconds/60,2).' active / '.round($dutySeconds/60,2).' evidenced duty minutes'],
            ['label'=>'Successful Assignment Rate','value'=>SessionPerformance::display(SessionPerformance::ratio($successful,$regular->count()),'%'),'sample'=>$successful.' accepted / '.$regular->count().' processed regular requests; '.$pending.' pending'],
            ['label'=>'Emergency Response Time','value'=>SessionPerformance::display(SessionPerformance::average($responses),' min'),'sample'=>count($responses).' acknowledged; '.$unacknowledged.' unacknowledged'],
        ];
        $raw = [SessionPerformance::average($waits), $waits ? max($waits) : null,
            $dutySeconds ? 100*$activeSeconds/$dutySeconds : null,
            SessionPerformance::ratio($successful, $regular->count()), SessionPerformance::average($responses)];
        foreach ($cards as $i => &$card) {
            $card['raw'] = $raw[$i]; $card['unit'] = in_array($i, [2,3], true) ? '%' : ' min';
        }
        unset($card);
        $quality = ['after_hours_started'=>count($outsideWaits), 'after_hours_average'=>SessionPerformance::average($outsideWaits),
            'missing_wait_evidence'=>$invalidWaits, 'invalid_acknowledgments'=>$invalidAcknowledgments,
            'legacy_processed_unknown'=>(clone $queues)->whereNull('first_processed_at')->whereBetween('request_date',[$start,$end])->count()];
        return compact('cards','quality','asOf','period','filters');
    }

    private function outsideHours($at): bool
    {
        $time = $at->copy()->timezone('Asia/Manila');
        return $time->dayOfWeekIso === 7 || $time->format('H:i') < '18:00' || $time->format('H:i') >= '22:30';
    }

    private function merge(array $intervals): array
    {
        usort($intervals, fn ($a,$b) => $a[0]<=>$b[0]); $result=[];
        foreach ($intervals as [$a,$b]) {
            if ($b<=$a) continue;
            $last=count($result)-1;
            if ($last>=0 && $a<=$result[$last][1]) $result[$last][1]=max($result[$last][1],$b);
            else $result[]=[$a,$b];
        }
        return $result;
    }
}
