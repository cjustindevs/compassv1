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
        if ($user->role === 'moderator') return app(ModeratorOperations::class)->overview($user);
        $sessions = Session::where('is_test_record', false)->whereNull('duplicate_of_id')->select(['id', 'helper_id', 'concern_id', 'concern_category', 'session_status', 'created_date', 'created_at', 'start_time', 'end_time'])->with('concern');
        $alerts = EmergencyAlert::whereHas('session', fn ($q) => $q->where('is_test_record', false)->whereNull('duplicate_of_id'))->select(['id', 'session_id', 'adviser_id', 'status', 'risk_level', 'professional_referred', 'triggered_at', 'acknowledged_at', 'resolved_at', 'created_at']);
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
        $add('Escalated cases', $open->pluck('session_id')->unique()->count(), 'Live: authorized support requests with an open emergency alert, including requests awaiting a Helper.');
        if ($user->role === 'adviser') {
            $analytics = app(AdviserAnalytics::class)->report(['start'=>\Illuminate\Support\Carbon::instance($period->start->utc()), 'end'=>\Illuminate\Support\Carbon::instance($period->end->utc())], $adviser->id);
            $metrics = $analytics['metrics'];
            foreach ([
                'Completed sessions' => ['completed', '', 'Valid started sessions completed/evaluated with end time in the selected period.'],
                'Average Helper Response Time' => ['response_minutes', ' min', 'Session start to first assigned Helper message or connected voice call; by session start date.'],
                'Positive helpfulness feedback' => ['helpfulness_rate', '%', 'Helpful or Very Helpful / valid original helpfulness answers submitted in the period.'],
                'Average competency score' => ['competency', '/5', 'Finalized, evidenced current-rubric evaluations by evaluation date; same records as the comparison.'],
                'Average waiting time' => ['waiting_minutes', ' min', 'Queue entry to actual session start; ongoing waits excluded.'],
                'Session completion rate' => ['completion_rate', '%', 'Completed started sessions / all started sessions in the same activity cohort.'],
                'Referral rate' => ['referral_rate', '%', 'Completed sessions with a recommendation, counted once per session.'],
                'Referral approval rate' => ['referral_approval_rate', '%', 'Approved recommendations / reviewed recommendations. Pending reviews excluded.'],
                'Emergency escalation rate' => ['emergency_rate', '%', 'Unique emergency-flagged requests / requests in the selected activity cohort.'],
                'Average Helper workload' => ['workload', '', 'Sessions completed in the period / Helpers with a recorded start or completion in the period.'],
                'Scheduled duty hours' => ['duty_hours', ' hr', 'Planned active shifts overlapping the selected dates; not verified attendance.'],
                'Documentation completion rate' => ['documentation_rate', '%', 'Required ended sessions with both submitted summary and reflection. Not an on-time rate.'],
            ] as $label => [$key, $unit, $definition]) {
                $add($label, SessionPerformance::display($metrics[$key], $unit), $definition);
                $cards[array_key_last($cards)]['raw'] = $metrics[$key];
                $cards[array_key_last($cards)]['unit'] = $unit;
                $cards[array_key_last($cards)]['link'] = route('adviser.reports', $period->dates());
                if (isset($analytics['samples'][$key])) $cards[array_key_last($cards)]['samples'] = $analytics['samples'][$key];
            }
            $referrals = Referral::whereIn('session_id', $caseRows->pluck('id'))->forAdviser($adviser->id)->get();
            $reviewSamples = $referrals->filter(fn ($r) => $r->created_at && $r->reviewed_at && $r->reviewed_at->gte($r->created_at)
                && $r->reviewed_at->gte($period->start) && $r->reviewed_at->lt($period->end) && $r->reviewed_at->lte(now()))
                ->map(fn ($r) => $r->created_at->diffInSeconds($r->reviewed_at) / 60);
            $add('Average referral review time', $this->formatAverage($reviewSamples), 'Recommendation creation to recorded review; by review date. Separate from emergency acknowledgment.');
            $cards[array_key_last($cards)] += ['raw'=>SessionPerformance::average($reviewSamples->all()), 'unit'=>' min', 'samples'=>$reviewSamples->count()];
            $reportRequest = clone request();
            $reportRequest->setUserResolver(fn () => $user);
            $emergencyMetric = collect(app(QueuePerformance::class)->report($reportRequest)['cards'])->firstWhere('label', 'Emergency Response Time');
            $add('Emergency acknowledgment time', $emergencyMetric['value'], $emergencyMetric['sample'].'; trigger to authorized acknowledgment, by emergency identification date.');
            $cards[array_key_last($cards)] += ['raw'=>$emergencyMetric['raw'], 'unit'=>' min'];
            $add('Average emergency resolution', $resolution, 'Emergency trigger to resolution; by resolution date.');
            $add('Screening reviews pending', Session::pendingScreeningReviewForAdviser($adviser->id)->count(), 'Live assigned screening and reassessment reviews.');
            $add('Referral reviews pending', Referral::forAdviser($adviser->id)->whereIn('status', [Referral::STATUS_PENDING_ADVISER, Referral::STATUS_PENDING_ADVISER_ASSIGNMENT])->count(), 'Live recommendations awaiting review.');
            $add('Evaluations pending', \App\Models\SessionReport::pendingEvaluationForAdviser($adviser->id)->count(), 'Live documentation awaiting competency evaluation.');
            $add('Helpers at capacity', Helper::where('adviser_id', $adviser->id)->get()->filter(fn ($h) => $h->currentAssignedSessionsCount() >= Helper::MAX_SESSIONS_PER_SHIFT)->count(), 'Current duty limit reached; completed sessions still consume duty allowance.');
            $add('Feedback coverage', SessionPerformance::display($metrics['feedback_coverage'], '%'), 'Completed sessions in period with submitted feedback by reporting cutoff / valid completed sessions.');
            $competencyComparison = app(AdviserCompetencyComparison::class)->forAdviser($adviser->id, $period, request());
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
        return compact('cards', 'charts', 'recent') + ['competency_comparison' => $competencyComparison ?? null];
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
