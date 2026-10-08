<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\EmergencyAlert;
use App\Models\Helper;
use App\Models\HelperAvailabilityLog;
use App\Models\HelperSchedule;
use App\Models\QueueRequest;
use App\Models\Referral;
use App\Models\Session;
use App\Models\SessionReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RoleActivityReport
{
    private function records($query, bool $export, string $page)
    {
        return $export ? $query->get() : $query->paginate(15, ['*'], $page)->withQueryString();
    }

    public function report(Request $request, bool $export = false): array
    {
        $user = $request->user();
        abort_unless($user && $user->is_active && in_array($user->role, ['helper', 'adviser', 'moderator', 'admin'], true), 403);
        if ($user->role === 'moderator') return app(ModeratorOperations::class)->report($request, $export);
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from',
            'case_status' => 'nullable|in:active,completed,evaluated,waiting,helper_assigned,pending_review,emergency,cancelled,no_show',
            'emergency_status' => 'nullable|in:open,triggered,pending,under_review,responding,acknowledged,escalated,resolved,closed',
            'priority' => 'nullable|in:low,moderate,high,emergency', 'helper_id' => 'nullable|integer', 'concern_id' => 'nullable|integer|exists:concern_categories,id', 'referral_status' => 'nullable|in:'.implode(',', Referral::STATUSES)]);
        $start = ! empty($data['from']) ? Carbon::parse($data['from'], 'Asia/Manila') : now('Asia/Manila')->subDays(30)->startOfDay();
        $end = ! empty($data['to']) ? Carbon::parse($data['to'], 'Asia/Manila') : now('Asia/Manila');
        if (strlen($data['to'] ?? '') === 10) {
            $end->endOfDay();
        }
        $from = $start->format('Y-m-d');
        $to = $end->format('Y-m-d');
        $start = $start->utc();
        $end = $end->utc();
        $sessions = $user->role === 'adviser' ? app(AdviserAnalytics::class)->scoped() : Session::query();
        $helpers = Helper::query();
        if ($user->role === 'helper') {
            abort_unless($user->helper, 403);
            $sessions->where('helper_id', $user->helper->id);
            $helpers->whereKey($user->helper->id);
        } elseif ($user->role === 'adviser') {
            $helpers->where('adviser_id', app(AdviserScope::class)->actor()->id);
        }
        if (! empty($data['helper_id'])) {
            abort_unless((clone $helpers)->whereKey($data['helper_id'])->exists(), 403);
            $sessions->where('helper_id', $data['helper_id']);
            $helpers->whereKey($data['helper_id']);
        }
        $sessions->when($data['concern_id'] ?? null, fn ($q, $id) => $q->where('concern_id', $id))
            ->when($data['referral_status'] ?? null, fn ($q, $status) => $q->whereHas('referrals', fn ($r) => $r->where('status', $status)));
        $helperIds = $helpers->pluck('id');
        $authorizedIds = (clone $sessions)->select('id');
        $alerts = EmergencyAlert::query();
        if ($user->role === 'adviser') {
            $adviser = app(AdviserScope::class)->actor();
            $alerts->where(fn ($q) => $q->where('adviser_id', $adviser->id)->orWhereHas('session.helper', fn ($h) => $h->where('adviser_id', $adviser->id)));
        } elseif ($user->role === 'helper') {
            $alerts->whereIn('session_id', $authorizedIds);
        }
        $sessions->whereBetween(DB::raw('COALESCE(end_time,start_time,submitted_at,created_date,created_at)'), [$start, $end])
            ->when($data['case_status'] ?? null, fn ($q, $status) => $q->where('session_status', $status));
        $alerts->whereBetween(DB::raw('COALESCE(triggered_at,created_at)'), [$start, $end])
            ->when($data['emergency_status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($data['priority'] ?? null, fn ($q, $priority) => $q->where('risk_level', $priority));
        if (! empty($data['concern_id']) || ! empty($data['referral_status'])) {
            $alerts->whereIn('session_id', $authorizedIds);
        }
        if (! empty($data['helper_id'])) {
            $alerts->whereHas('session', fn ($q) => $q->where('helper_id', $data['helper_id']));
        }
        $rows = (clone $sessions)->with('concern')->get();
        $emergencies = (clone $alerts)->get();
        $mean = function ($records, $a, $b) {
            $samples = $records->filter(fn ($r) => $r->$a && $r->$b && Carbon::parse($r->$b)->gte(Carbon::parse($r->$a)) && Carbon::parse($r->$b)->lte(now()))->map(fn ($r) => Carbon::parse($r->$a)->diffInSeconds(Carbon::parse($r->$b)) / 60);

            return $samples->isEmpty() ? 'No data' : round($samples->avg(), 1).' min';
        };
        $summary = ['Cases in period' => $rows->count(), 'Completed cases' => $rows->whereIn('session_status', ['completed', 'evaluated'])->count(),
            'Active sessions' => $rows->where('session_status', 'active')->count(), 'Pending requests' => $rows->whereIn('session_status', Session::PENDING_STATUSES)->where('session_status', '!=', 'active')->count(),
            'Cancelled / no-show' => $rows->whereIn('session_status', ['cancelled', 'no_show'])->count(),
            'Helper acceptance time' => $mean($rows, 'submitted_at', 'helper_accepted_at'), 'Completed session duration' => $mean($rows->whereIn('session_status', ['completed', 'evaluated']), 'start_time', 'end_time')];
        $groups = ['Cases by status' => $rows->groupBy('session_status')->map->count()->all(),
            'Case categories' => $rows->groupBy(fn ($s) => $s->concern?->concern_name ?? $s->concern_category ?? 'Unrecorded')->map->count()->all(),
            'Case outcome trend' => $rows->groupBy(fn ($s) => ($s->end_time ?? $s->start_time ?? $s->submitted_at ?? $s->created_date ?? $s->created_at)->timezone('Asia/Manila')->format('Y-m'))->sortKeys()->map->count()->all()];
        $tables = [];
        $tables[] = ['title' => 'Case activity', 'columns' => ['Reference', 'Status', 'Category', 'Activity date (Philippine Time)'],
            'records' => $this->records((clone $sessions)->with('concern')->orderByDesc('id'), $export, 'cases_page'),
            'format' => fn ($s) => [$s->reference_number, ucwords(str_replace('_', ' ', $s->session_status)), $s->concern?->concern_name ?? $s->concern_category ?? 'Unrecorded', ($s->end_time ?? $s->start_time ?? $s->submitted_at ?? $s->created_date ?? $s->created_at)?->timezone('Asia/Manila')->format('M d, Y g:i A')]];
        if ($user->role !== 'helper') {
            $terminal = $emergencies->whereIn('status', ['resolved', 'closed']);
            $summary += ['Emergencies' => $emergencies->count(), 'Resolved emergencies' => $terminal->count(), 'Unresolved emergencies' => $emergencies->count() - $terminal->count(),
                'Emergency acknowledgment time' => $mean($emergencies, 'triggered_at', 'acknowledged_at'), 'Emergency resolution time' => $mean($terminal, 'triggered_at', 'resolved_at')];
            $groups += ['Emergency status' => $emergencies->groupBy('status')->map->count()->all(), 'Emergency priority' => $emergencies->groupBy('risk_level')->map->count()->all()];
            $tables[] = ['title' => 'Emergency case activity', 'columns' => ['Reference', 'Status', 'Priority', 'Helper assignment', 'Triggered (Philippine Time)', 'Acknowledged', 'Resolved'],
                'records' => $this->records((clone $alerts)->with('session.helper')->orderByDesc('triggered_at'), $export, 'emergency_page'),
                'format' => fn ($a) => ['Emergency #'.$a->id, ucwords(str_replace('_', ' ', $a->status)), ucfirst($a->risk_level), $a->session?->helper?->public_alias ?? 'Unassigned', $a->triggered_at?->timezone('Asia/Manila')->format('M d, Y g:i A') ?? 'Unrecorded', $a->acknowledged_at?->timezone('Asia/Manila')->format('M d, Y g:i A') ?? 'Not acknowledged', $a->resolved_at?->timezone('Asia/Manila')->format('M d, Y g:i A') ?? 'Not resolved']];
        }
        if ($user->role === 'adviser') {
            $referrals = Referral::whereIn('session_id', $rows->pluck('id'))->forAdviser(app(AdviserScope::class)->actor()->id)->get();
            $reviews = SessionReport::whereIn('session_id', $rows->pluck('id'))->get();
            $samples = collect();
            foreach ([[$emergencies, 'triggered_at', 'acknowledged_at'], [$referrals, 'created_at', 'reviewed_at'], [$reviews, 'summary_submitted_at', 'reviewed_date']] as [$events,$a,$b]) {
                foreach ($events as $event) {
                    if ($event->$a && $event->$b && Carbon::parse($event->$b)->gte(Carbon::parse($event->$a)) && Carbon::parse($event->$b)->between($start, $end) && Carbon::parse($event->$b)->lte(now())) {
                        $samples->push(Carbon::parse($event->$a)->diffInSeconds(Carbon::parse($event->$b)) / 60);
                    }
                }
            }
            $summary['Adviser acknowledgment / review time'] = $samples->isEmpty() ? 'No data' : round($samples->avg(), 1).' min';
            $summary['Escalated cases'] = $emergencies->whereNotIn('status', ['resolved', 'closed'])->pluck('session_id')->intersect($rows->pluck('id'))->unique()->count();
            $summary['Adviser emergency resolution time'] = $mean($emergencies->whereIn('status', ['resolved', 'closed']), 'triggered_at', 'resolved_at');
        }
        if (in_array($user->role, ['moderator', 'admin'])) {
            $queues = QueueRequest::whereBetween('request_date', [$start, $end]);
            $all = (clone $queues)->count();
            $matched = (clone $queues)->whereNotNull('matched_date')->count();
            $summary['Queue matching time'] = $mean((clone $queues)->get(), 'request_date', 'matched_date');
            $groups['Referral coordination status'] = Referral::whereBetween('created_at', [$start, $end])->get()->groupBy('status')->map->count()->all();
            $summary += ['Queue entries' => $all, 'Successful matches' => $matched, 'Queue-entry matching rate' => $all ? round($matched / $all * 100, 1).'%' : 'No data'];
            $tables[] = ['title' => 'Incoming queue activity', 'columns' => ['Queue reference', 'Status', 'Priority', 'Helper', 'Entered (Philippine Time)', 'Matched'],
                'records' => $this->records((clone $queues)->with('assignedHelper')->latest('request_date'), $export, 'queue_page'),
                'format' => fn ($q) => ['Queue #'.$q->id, ucfirst($q->request_status), ucfirst($q->priority_level), $q->assignedHelper?->public_alias ?? 'Unassigned', $q->request_date?->timezone('Asia/Manila')->format('M d, Y g:i A'), $q->matched_date?->timezone('Asia/Manila')->format('M d, Y g:i A') ?? 'Not matched']];
        }
        if ($user->role === 'helper' || $user->role === 'admin') {
            $logs = HelperAvailabilityLog::whereIn('helper_id', $helperIds)->whereBetween('changed_at', [$start, $end]);
            $tables[] = ['title' => 'Helper availability history', 'columns' => ['Helper reference', 'Previous status', 'New status', 'Changed (Philippine Time)'],
                'records' => $this->records($logs->latest('changed_at'), $export, 'availability_page'),
                'format' => fn ($l) => ['Helper #'.$l->helper_id, ucfirst($l->previous_status), ucfirst($l->new_status), $l->changed_at?->timezone('Asia/Manila')->format('M d, Y g:i A')]];
        }
        if ($user->role === 'helper') {
            $duty = HelperSchedule::whereIn('helper_id', $helperIds)->where('is_active', true)->whereBetween('date', [$start->copy()->timezone('Asia/Manila')->toDateString(), $end->copy()->timezone('Asia/Manila')->toDateString()]);
            $summary['Scheduled duty days'] = (clone $duty)->count();
            $tables[] = ['title' => 'Duty history', 'columns' => ['Duty date', 'Status'], 'records' => $this->records($duty->latest('date'), $export, 'duty_page'),
                'format' => fn ($d) => [$d->date->format('M d, Y'), $d->isOnDuty() ? 'On duty now' : ($d->window()[1]->isPast() ? 'Past scheduled duty' : 'Scheduled')]];
        }
        if (in_array($user->role, ['adviser', 'moderator', 'admin'])) {
            $actions = AuditLog::whereBetween('created_at', [$start, $end]);
            if ($user->role !== 'admin') {
                $actions->where('user_account_id', $user->id);
            }
            $tables[] = ['title' => $user->role === 'admin' ? 'System activity' : 'Your recorded actions', 'columns' => ['Action', 'Outcome', 'Recorded (Philippine Time)'],
                'records' => $this->records($actions->latest('created_at'), $export, 'actions_page'),
                'format' => fn ($a) => [ucwords(str_replace('_', ' ', $a->action)), ucfirst($a->outcome ?? 'Unrecorded'), $a->created_at?->timezone('Asia/Manila')->format('M d, Y g:i A')]];
        }
        if ($user->role === 'admin') {
            $users = User::query();
            $registrations = User::whereBetween('created_at', [$start, $end])->get();
            $summary += ['Total users' => $users->count(), 'Active accounts' => (clone $users)->where('is_active', true)->count(), 'Available Helpers' => app(HelperEligibilityService::class)->countAvailable(Helper::with('user', 'adviser.user')->get())];
            $groups['System activity trend'] = AuditLog::whereBetween('created_at', [$start, $end])->get(['created_at'])->groupBy(fn ($a) => $a->created_at->copy()->timezone('Asia/Manila')->format('Y-m'))->sortKeys()->map->count()->all();
            $groups += ['Users by role' => $users->get()->groupBy('role')->map->count()->all(), 'Registration trend' => $registrations->groupBy(fn ($u) => $u->created_at->timezone('Asia/Manila')->format('Y-m'))->sortKeys()->map->count()->all()];
        }

        return compact('summary','groups','tables','from','to') + ['role' => $user->role];
    }
}
