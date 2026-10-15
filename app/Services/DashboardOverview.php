<?php

namespace App\Services;

use App\Models\{User, Session, EmergencyAlert, Helper, QueueRequest, Referral, AuditLog};
use Illuminate\Support\Collection;

/** Scoped live counters and historical events within the selected Philippine date range. */
class DashboardOverview
{
    public function forUser(User $user): array
    {
        abort_unless($user->is_active && in_array($user->role, ['admin', 'moderator', 'adviser'], true), 403);
        if ($user->role === 'moderator') return app(ModeratorOperations::class)->overview();
        $sessions = Session::query()->select(['id', 'helper_id', 'concern_id', 'concern_category', 'session_status', 'created_date', 'created_at', 'start_time', 'end_time'])->with('concern');
        $alerts = EmergencyAlert::query()->select(['id', 'session_id', 'adviser_id', 'status', 'risk_level', 'professional_referred', 'triggered_at', 'acknowledged_at', 'resolved_at', 'created_at']);
        if ($user->role === 'adviser') {
            $adviser = app(AdviserScope::class)->actor($user);
            $sessions->where(fn ($q) => $q->whereHas('helper', fn ($h) => $h->where('adviser_id', $adviser->id))
                ->orWhere('review_adviser_id', $adviser->id)
                ->orWhereHas('referrals', fn ($r) => $r->forAdviser($adviser->id)));
            $alerts->where(fn ($q) => $q->where('adviser_id', $adviser->id)
                ->orWhereHas('session.helper', fn ($h) => $h->where('adviser_id', $adviser->id)));
        }
        $period = AnalyticsPeriod::fromRequest(request());
        $caseRows = $sessions->get();
        $emergencies = $alerts->get();
        $open = $emergencies->whereNotIn('status', ['resolved', 'closed']);
        $resolved = $emergencies->whereIn('status', ['resolved', 'closed']);
        $cases = $caseRows->reject(fn ($s) => in_array($s->session_status, ['cancelled', 'no_show'], true));
        $caseStatus = ['Active' => 0, 'Pending' => 0, 'Resolved' => 0, 'Escalated' => 0];
        foreach ($cases as $case) {
            $key = in_array($case->session_status, ['completed', 'evaluated'], true) ? 'Resolved'
                : ($open->contains('session_id', $case->id) ? 'Escalated'
                : ($case->session_status === 'active' ? 'Active' : 'Pending'));
            $caseStatus[$key]++;
        }
        $cards = [];
        $charts = [];
        $add = function ($label, $value, $definition) use (&$cards) { $cards[] = compact('label', 'value', 'definition'); };
        $chart = function ($title, array $values, $definition) use (&$charts) { $charts[] = compact('title', 'values', 'definition'); };
        $resolution = $this->averageMinutes($resolved, 'triggered_at', 'resolved_at');
        $add('Active cases', $caseStatus['Active'] + $caseStatus['Escalated'], 'Live: active support sessions plus requests with an open emergency review. Pending requests are shown separately.');
        $add('Resolved cases', $cases->filter(fn ($case) => in_array($case->session_status, ['completed','evaluated'], true) && $case->end_time && $case->end_time->gte($period->start) && $case->end_time->lt($period->end) && $case->end_time->lte(now()))->count(), 'Completed or evaluated sessions ending in the selected period; missing end dates are excluded.');
        $add('Pending cases', $caseStatus['Pending'], 'Live: nonterminal support requests not active and without an open emergency alert.');
        $add('Escalated cases', $caseStatus['Escalated'], 'Live: support requests with an open emergency alert.');
        if ($user->role === 'adviser') {
            $analytics = app(AdviserAnalytics::class)->report(['start'=>\Illuminate\Support\Carbon::instance($period->start->utc()), 'end'=>\Illuminate\Support\Carbon::instance($period->end->utc())], $adviser->id);
            $metrics = $analytics['metrics'];
            foreach ([
                'Completed sessions' => ['completed', '', 'Completed/evaluated sessions in the selected activity cohort.'],
                'Average Helper Response Time' => ['response_minutes', ' min', 'Request submission to first actual assigned Helper message.'],
                'Satisfaction rating' => ['satisfaction', '/5', 'Submitted Seeker feedback converted from 1–10 to 1–5.'],
                'Average competency score' => ['competency', '/5', 'Recorded finalized evaluations in the selected period.'],
                'Average waiting time' => ['waiting_minutes', ' min', 'Queue entry to actual session start; ongoing waits excluded.'],
                'Session completion rate' => ['completion_rate', '%', 'Completed started sessions / all started sessions in the same activity cohort.'],
                'Referral rate' => ['referral_rate', '%', 'Completed sessions with a recommendation, counted once per session.'],
                'Referral approval rate' => ['referral_approval_rate', '%', 'Approved recommendations / reviewed recommendations. Pending reviews excluded.'],
                'Emergency escalation rate' => ['emergency_rate', '%', 'Unique emergency-flagged requests / requests in the selected activity cohort.'],
                'Average Helper workload' => ['workload', '', 'Completed sessions per Helper who served during the selected activity cohort.'],
                'Scheduled duty hours' => ['duty_hours', ' hr', 'Planned active shifts overlapping the selected dates; not verified attendance.'],
                'Documentation completion rate' => ['documentation_rate', '%', 'Required ended sessions with both submitted summary and reflection. Not an on-time rate.'],
            ] as $label => [$key, $unit, $definition]) {
                $add($label, SessionPerformance::display($metrics[$key], $unit), $definition.' Current records; archived sessions are available in Reports.');
                $cards[array_key_last($cards)]['link'] = route('adviser.reports', $period->dates());
                if (isset($analytics['samples'][$key])) $cards[array_key_last($cards)]['samples'] = $analytics['samples'][$key];
            }
            $referrals = Referral::whereIn('session_id', $caseRows->pluck('id'))->forAdviser($adviser->id)->get();
            // One first response per support case; notification reads are not actions.
            $events = $emergencies->map(fn ($row) => ['case' => $row->session_id ?: 'alert-'.$row->id, 'start' => $row->triggered_at, 'end' => $row->acknowledged_at, 'open' => ! in_array($row->status, ['resolved', 'closed'], true)])
                ->merge($referrals->map(fn ($row) => ['case' => $row->session_id, 'start' => $row->created_at, 'end' => $row->reviewed_at, 'open' => $row->isOpen()]));
            $firstResponses = $events->filter(fn ($event) => $event['start'] && $event['end'] && $event['end']->gte($event['start']) && $event['end']->lte(now()))
                ->sortBy(fn ($event) => $event['end']->timestamp)->unique('case');
            $samples = $firstResponses->filter(fn ($event) => $event['end']->gte($period->start) && $event['end']->lt($period->end))
                ->map(fn ($event) => $event['start']->diffInSeconds($event['end']) / 60);
            $waiting = $events->filter(fn ($event) => $event['open'] && $event['start'] && ! $event['end'] && $event['start']->gte($period->start) && $event['start']->lt($period->end))
                ->reject(fn ($event) => $firstResponses->contains('case', $event['case']))->unique('case')->count();
            $add('Average Adviser Response Time', $this->formatAverage($samples), 'First recorded emergency acknowledgement or referral review per support case; responses in the selected period. '.$samples->count().' cases measured. Notification reads do not count.');
            $cards[array_key_last($cards)]['samples'] = $samples->count();
            $add('Awaiting response', $waiting, 'Unanswered cases becoming actionable in the selected period; excluded from average response time.');
            $add('Average resolution', $resolution, 'Emergency trigger to resolution in minutes; selected period by resolution date. Support-session completion is not substituted for Adviser resolution.');
            $evaluations = \App\Models\HelperCompetencyHistory::with('helper')->whereHas('helper', fn ($q) => $q->where('adviser_id', $adviser->id))
                ->where('evaluation_date', '>=', $period->start->utc())->where('evaluation_date', '<', $period->end->utc())->where('evaluation_date', '<=', now())->get()
                ->filter(fn ($evaluation) => $evaluation->normalized_score >= 1 && $evaluation->normalized_score <= 5);
            $chart('Helper competency comparison', $evaluations->groupBy('helper_id')->mapWithKeys(fn ($rows) => [($rows->first()->helper?->full_name ?: 'Helper').' #'.$rows->first()->helper_id => round($rows->avg('normalized_score'), 2)])->all(), 'Average recorded evaluation rating out of five in the selected period. Helpers without evaluations are excluded.');
        }
        $chart('Cases by status', $caseStatus, 'Support requests, excluding cancelled/no-show records. Each appears once. Emergency review is separate from chat closure.');
        $historicalCases = $cases->filter(fn ($case) => ($at = $case->created_date ?? $case->created_at) && $at->gte($period->start) && $at->lt($period->end) && $at->lte(now()));
        $chart('Case trend', $this->months($historicalCases, 'created_date'), 'Requests created in the selected period, excluding cancelled/no-show records.');
        $chart('Case categories', $historicalCases->groupBy(fn ($s) => $s->concern?->concern_name ?: ($s->concern_category ?: 'Unrecorded'))->map->count()->all(), 'Recorded categories for requests created in the selected period.');
        if ($user->role === 'admin') {
            $users = User::select('id', 'role', 'is_active', 'created_at')->get();
            $add('Total users', $users->count(), 'Registered accounts, all roles.');
            $add('Active accounts', $users->where('is_active', true)->count(), 'Enabled accounts; this does not represent online presence.');
            $add('Available Helpers', app(HelperEligibilityService::class)->countAvailable(Helper::all()), 'Helpers passing the same current eligibility checks used by matching.');
            $add('Emergency cases', $emergencies->count(), 'All emergency alerts; includes resolved alerts.');
            $queues = QueueRequest::all();
            $matched = $queues->whereNotNull('matched_date')->count();
            $add('Successful matches', $matched, 'Queue requests with a recorded matching timestamp. Does not imply session completion.');
            $add('Matching success rate', $queues->count() ? round($matched / $queues->count() * 100, 1).'%' : 'No data', 'Matched requests divided by all queue entries. Eligibility-at-entry is not historically recorded, so this is queue-entry success, not an eligible-Seeker rate.');
            $add('Average match response', $this->averageMinutes($queues, 'request_date', 'matched_date'), 'Minutes from queue entry to matching; last 30 days by matched date.');
            $chart('Users by role', $users->groupBy(fn ($u) => ucfirst($u->role))->map->count()->all(), 'All actual roles, including Professionals.');
            $chart('User registration trend', $this->months($users, 'created_at'), 'New accounts per month in Asia/Manila; not cumulative totals.');
            $chart('System activity trend', $this->months(AuditLog::select('created_at')->where('created_at', '>=', now('Asia/Manila')->startOfMonth()->subMonths(5)->utc())->get(), 'created_at'), 'Recorded audit events per month. No event narrative or identity data is shown.');
        }
        $activity = $caseRows;
        $recent = $activity->sortByDesc(fn ($r) => ($r->resolved_at ?? $r->acknowledged_at ?? $r->end_time ?? $r->start_time ?? $r->triggered_at ?? $r->created_date ?? $r->created_at)?->timestamp)
            ->take(5)->map(fn ($r) => [
                'label' => ($r instanceof EmergencyAlert ? 'Emergency review' : 'Support case').' - '.ucwords(str_replace('_', ' ', $r->status ?? $r->session_status)),
                'at' => ($r->resolved_at ?? $r->acknowledged_at ?? $r->end_time ?? $r->start_time ?? $r->triggered_at ?? $r->created_date ?? $r->created_at)?->copy()->timezone('Asia/Manila')->format('M d, Y g:i A'),
            ])->all();
        return compact('cards', 'charts', 'recent');
    }

    private function durations(Collection $rows, string $start, string $end): Collection
    {
        return $rows->filter(fn ($r) => $r->$start && $r->$end && $r->$end->gte(AnalyticsPeriod::fromRequest(request())->start) && $r->$end->lt(AnalyticsPeriod::fromRequest(request())->end) && $r->$end->gte($r->$start) && $r->$end->lte(now()))
            ->map(fn ($r) => $r->$start->diffInSeconds($r->$end) / 60);
    }
    private function formatAverage(Collection $samples): string
    {
        return $samples->isEmpty() ? 'No data' : round($samples->avg(), 1).' min';
    }
    private function averageMinutes(Collection $rows, string $start, string $end): string
    {
        return $this->formatAverage($this->durations($rows, $start, $end));
    }
    private function months(Collection $rows, string $field): array
    {
        $period = AnalyticsPeriod::fromRequest(request());
        $daily = $period->start->diffInDays($period->end) <= 62;
        $format = $daily ? 'Y-m-d' : 'Y-m';
        $counts = $rows->filter(fn ($r) => $r->$field && $r->$field->gte($period->start) && $r->$field->lt($period->end) && $r->$field->lte(now()))
            ->groupBy(fn ($r) => $r->$field->copy()->timezone('Asia/Manila')->format($format))->map->count();
        $values = [];
        for ($date = $daily ? $period->start : $period->start->startOfMonth(); $date->lt($period->end); $date = $daily ? $date->addDay() : $date->addMonth()) {
            $values[$date->format($daily ? 'M j, Y' : 'M Y')] = $counts->get($date->format($format), 0);
        }
        return $values;
    }
}
