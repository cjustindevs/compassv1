<?php

namespace App\Services;

use App\Models\{User, Session, EmergencyAlert, Helper, QueueRequest, Referral, AuditLog, SessionReport};
use Illuminate\Support\Collection;

/** Dashboard summaries only. Counts are current/all-time; charts cover six Manila months. */
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
        $add('Active cases', $caseStatus['Active'] + $caseStatus['Escalated'], 'Active support sessions plus requests with an open emergency review. Pending requests are shown separately.');
        $add('Resolved cases', $caseStatus['Resolved'], 'Completed or evaluated peer-support sessions. Cancelled/no-show requests are excluded.');
        $add('Pending cases', $caseStatus['Pending'], 'Nonterminal support requests not active and without an open emergency alert.');
        $add('Escalated cases', $caseStatus['Escalated'], 'Support requests with an open emergency alert.');
        if ($user->role === 'adviser') {
            $referrals = Referral::whereIn('session_id', $caseRows->pluck('id'))->forAdviser($adviser->id)->get();
            $reviews = SessionReport::whereIn('session_id', $caseRows->pluck('id'))->get();
            $samples = $this->durations($emergencies, 'triggered_at', 'acknowledged_at')
                ->merge($this->durations($referrals, 'created_at', 'reviewed_at'))
                ->merge($this->durations($reviews, 'summary_submitted_at', 'reviewed_date'));
            $add('Average Adviser response', $this->formatAverage($samples), 'Minutes to first emergency acknowledgment, referral review, or submitted-summary review; last 30 days by response date. Only explicit recorded events are included.');
            $add('Average resolution', $resolution, 'Emergency trigger to resolution in minutes; last 30 days by resolution date. Support-session completion is not substituted for Adviser resolution.');
        }
        $chart('Cases by status', $caseStatus, 'Support requests, excluding cancelled/no-show records. Each appears once. Emergency review is separate from chat closure.');
        $chart('Case trend', $this->months($cases, 'created_date'), 'Support requests created per month, excluding cancelled/no-show records.');
        $chart('Case categories', $cases->groupBy(fn ($s) => $s->concern?->concern_name ?: ($s->concern_category ?: 'Unrecorded'))->map->count()->all(), 'Actual recorded concern categories; unrecorded categories are not guessed.');
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
        return $rows->filter(fn ($r) => $r->$start && $r->$end && $r->$end->gte(now()->subDays(30)) && $r->$end->gte($r->$start) && $r->$end->lte(now()))
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
        $counts = $rows->filter(fn ($r) => $r->$field)->groupBy(fn ($r) => $r->$field->copy()->timezone('Asia/Manila')->format('Y-m'))->map->count();
        $values = [];
        foreach (range(5, 0) as $offset) {
            $month = now('Asia/Manila')->startOfMonth()->subMonths($offset);
            $values[$month->format('M Y')] = $counts->get($month->format('Y-m'), 0);
        }
        return $values;
    }
}
