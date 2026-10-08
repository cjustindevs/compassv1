<?php

namespace App\Services;

use App\Models\QueueRequest;
use App\Models\Referral;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ModeratorOperations
{
    public function overview(): array
    {
        $service = app(ModeratorEmergencyCases::class);
        $all = $service->query()->get();
        $active = $service->active()->get();
        $status = ['Awaiting acknowledgment' => 0, 'Responding' => 0, 'Escalated' => 0];
        foreach ($active as $case) {
            $status[$service->statusLabel($case)]++;
        }
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now('Asia/Manila')->startOfMonth()->subMonths($i);
            $months[$month->format('M Y')] = $all->filter(fn ($e) => Carbon::parse($e->triggered_at)->timezone('Asia/Manila')->format('Y-m') === $month->format('Y-m'))->count();
        }

        return ['cards' => [
            ['label' => 'Active emergencies', 'value' => $active->count(), 'definition' => 'Unarchived, nonterminal operational emergency cases. Alert/incident mirrors count once.'],
            ['label' => 'Resolved emergencies', 'value' => $all->whereIn('status', ['resolved', 'closed'])->count(), 'definition' => 'Resolved or closed cases, including retained archives.'],
            ['label' => 'Pending / unassigned', 'value' => $active->filter(fn ($e) => ! $e->acknowledged_at || ! $e->adviser_id)->count(), 'definition' => 'Active cases without staff acknowledgment or an assigned Adviser.'],
        ], 'charts' => [
            ['title' => 'Active emergency status', 'values' => $status, 'definition' => 'Current active cases only. The chart total equals Active emergencies.'],
            ['title' => 'Severity / priority', 'values' => $active->groupBy('risk_level')->map->count()->all(), 'definition' => 'Recorded severity of the same active cases.'],
            ['title' => 'Emergency trend', 'values' => $months, 'definition' => 'Emergency cases detected per month in Philippine Time, including historical cases.'],
        ], 'recent' => []];
    }

    public function report(Request $request, bool $export = false): array
    {
        abort_unless($request->user()?->is_active && $request->user()?->role === 'moderator', 403);
        $request->validate(['date_range' => 'nullable|string|max:24']);
        if ($request->filled('date_range')) {
            if (! preg_match('/^(\d{4}-\d{2}-\d{2}) to (\d{4}-\d{2}-\d{2})$/', $request->string('date_range'), $m)) {
                throw ValidationException::withMessages(['date_range' => 'Choose a valid start and end date.']);
            }
            $request->merge(['from' => $m[1], 'to' => $m[2]]);
        }
        $data = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'case_status' => 'nullable|in:active,completed,evaluated,waiting,helper_assigned,pending_review,emergency,cancelled,no_show,scheduled,screening_completed,preferences_set',
            'emergency_status' => 'nullable|in:active,pending,notified,acknowledged,responding,under_review,open,escalated,referred,resolved,closed,cancelled,archived',
            'priority' => 'nullable|in:low,moderate,high,emergency', 'activity' => 'nullable|in:cases,emergencies,queue,actions',
            'search' => 'nullable|string|max:100', 'archive' => 'nullable|in:all,archived,current',
            'overview' => 'nullable|in:status,categories,emergencies,referrals', 'tab' => 'nullable|in:operations,safety,activity',
            'cases_page' => 'nullable|integer|min:1', 'queue_page' => 'nullable|integer|min:1', 'emergency_page' => 'nullable|integer|min:1', 'activity_page' => 'nullable|integer|min:1']);
        $from = $data['from'] ?? now('Asia/Manila')->subDays(29)->toDateString();
        $to = $data['to'] ?? now('Asia/Manila')->toDateString();
        $start = Carbon::parse($from, 'Asia/Manila')->startOfDay()->utc();
        $end = Carbon::parse($to, 'Asia/Manila')->endOfDay()->utc();
        if ($end->lt($start)) {
            throw ValidationException::withMessages(['date_range' => 'The end date must follow the start date.']);
        }
        if ($start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['date_range' => 'Choose a date range of no more than 366 days.']);
        }
        $caseStatusFilter = function ($query, $status) {
            if ($status === 'scheduled') {
                return $query->where(fn ($q) => $q->where('session_status', 'scheduled')->orWhere(fn ($q) => $q->whereIn('session_status', Session::PENDING_STATUSES)->where('scheduled_start', '>', now())));
            }

            return $query->where('session_status', $status);
        };
        $cases = Session::with('concern', 'helper')->whereBetween(\DB::raw('COALESCE(submitted_at,created_date,created_at)'), [$start, $end])->when($data['case_status'] ?? null, $caseStatusFilter)->when($data['priority'] ?? null, fn ($q, $v) => $q->where('risk_level', $v));
        $emergencies = app(ModeratorEmergencyCases::class)->query()->whereBetween('triggered_at', [$start, $end])->when($data['priority'] ?? null, fn ($q, $v) => $q->where('risk_level', $v));
        if (($data['emergency_status'] ?? null) === 'active') {
            $emergencies->whereNotIn('status', ModeratorEmergencyCases::TERMINAL)->whereNull('archived_at');
        } elseif (($data['emergency_status'] ?? null) === 'archived') {
            $emergencies->where(fn ($q) => $q->whereNotNull('archived_at')->orWhere('status', 'archived'));
        } elseif (! empty($data['emergency_status'])) {
            $emergencies->where('status', $data['emergency_status']);
        }
        $queues = QueueRequest::with('assignedHelper')->whereBetween('request_date', [$start, $end])->when($data['priority'] ?? null, fn ($q, $v) => $q->where('priority_level', $v));
        foreach ([$cases, $emergencies, $queues] as $query) {
            if (($data['archive'] ?? 'all') === 'archived') {
                $query->whereNotNull('archived_at');
            }
            if (($data['archive'] ?? 'all') === 'current') {
                $query->whereNull('archived_at');
            }
        }
        $caseRows = (clone $cases)->get();
        $emergencyRows = (clone $emergencies)->get();
        $active = $emergencyRows->whereNotIn('status', ModeratorEmergencyCases::TERMINAL)->whereNull('archived_at');
        $summary = ['Cases in period' => $caseRows->count(), 'Completed cases' => $caseRows->whereIn('session_status', ['completed', 'evaluated'])->count(),
            'Pending requests' => $caseRows->whereIn('session_status', Session::PENDING_STATUSES)->count(), 'Emergencies' => $emergencyRows->count(),
            'Unresolved emergencies' => $active->count(), 'Resolved emergencies' => $emergencyRows->whereIn('status', ['resolved', 'closed'])->count(),
            'Queue entries' => (clone $queues)->count(), 'Successful matches' => (clone $queues)->whereNotNull('matched_date')->count()];
        $groups = ['Cases by status' => $caseRows->groupBy('session_status')->map->count()->all(), 'Case categories' => $caseRows->groupBy(fn ($s) => $s->concern?->concern_name ?? 'Unrecorded')->map->count()->all(),
            'Emergency status' => $emergencyRows->groupBy(fn ($e) => app(ModeratorEmergencyCases::class)->statusLabel($e))->map->count()->all(), 'Emergency priority' => $emergencyRows->groupBy('risk_level')->map->count()->all()];
        $caseStatus = ['Completed' => 0, 'Pending' => 0, 'Scheduled' => 0, 'Active' => 0, 'Cancelled' => 0, 'No show' => 0];
        foreach ($caseRows as $case) {
            $label = match (true) {
                in_array($case->session_status, ['completed', 'evaluated']) => 'Completed',
                $case->session_status === 'scheduled' || (in_array($case->session_status, Session::PENDING_STATUSES) && $case->scheduled_start && $case->scheduled_start->isFuture()) => 'Scheduled',
                in_array($case->session_status, Session::PENDING_STATUSES) || $case->session_status === 'pending_review' => 'Pending',
                default => ucfirst(str_replace('_', ' ', $case->session_status ?: 'Unrecorded')),
            };
            $caseStatus[$label] = ($caseStatus[$label] ?? 0) + 1;
        }
        $summary['Pending requests'] = $caseStatus['Pending'];
        // Aggregate lifecycle data only: no referral narratives or identity information.
        $referrals = Referral::query()->whereBetween(\DB::raw('COALESCE(referral_date,created_at)'), [$start, $end])
            ->when($data['priority'] ?? null, fn ($q, $v) => $q->where('priority_level', $v))
            ->when($data['case_status'] ?? null, fn ($q, $v) => $q->whereHas('session', fn ($q) => $caseStatusFilter($q, $v)))
            ->when(($data['archive'] ?? 'all') !== 'all', fn ($q) => $q->whereHas('session', fn ($q) => ($data['archive'] === 'archived' ? $q->whereNotNull('archived_at') : $q->whereNull('archived_at'))))
            ->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total', 'status')
            ->mapWithKeys(fn ($count, $status) => [ucwords(str_replace('_', ' ', $status)) => (int) $count])->all();
        $groups['Referral status'] = $referrals;
        $charts = ['status' => ['title' => 'Case status', 'values' => $caseStatus],
            'categories' => ['title' => 'Case categories', 'values' => $groups['Case categories']],
            'emergencies' => ['title' => 'Emergency status', 'values' => $groups['Emergency status']],
            'referrals' => ['title' => 'Referral status', 'values' => $referrals]];
        $date = fn ($v) => $v ? Carbon::parse($v)->timezone('Asia/Manila')->format('M d, Y g:i A') : 'Not recorded';
        $mean = function ($rows, $a, $b) {
            $values = $rows->filter(fn ($r) => $r->$a && $r->$b && Carbon::parse($r->$b)->gte(Carbon::parse($r->$a)) && Carbon::parse($r->$b)->lte(now()))->map(fn ($r) => Carbon::parse($r->$a)->diffInSeconds(Carbon::parse($r->$b)) / 60);

            return $values->isEmpty() ? 'No data' : round($values->avg(), 1).' min';
        };
        $summary += ['Emergency acknowledgment time' => $mean($emergencyRows, 'triggered_at', 'acknowledged_at'), 'Emergency resolution time' => $mean($emergencyRows, 'triggered_at', 'resolved_at')];
        $records = fn ($query, $page) => $export ? $query->get() : $query->paginate(15, ['*'], $page)->withQueryString();
        $tables = [
            ['title' => 'Case activity', 'group' => 'operations', 'columns' => ['Reference', 'Status', 'Category', 'Date (Philippine Time)'], 'records' => $records($cases->orderByDesc('created_date'), 'cases_page'), 'format' => fn ($s) => [$s->reference_number, $s->archived_at ? 'Archived ('.$s->session_status.')' : ucfirst($s->session_status), $s->concern?->concern_name ?? 'Unrecorded', $date($s->submitted_at ?? $s->created_date)], 'type' => 'counseling_sessions'],
            ['title' => 'Incoming queue activity', 'group' => 'operations', 'columns' => ['Reference', 'Status', 'Priority', 'Helper', 'Entered', 'Matched'], 'records' => $records($queues->latest('request_date'), 'queue_page'), 'format' => fn ($q) => ['Queue #'.$q->id, $q->archived_at ? 'Archived ('.$q->request_status.')' : ucfirst($q->request_status), ucfirst($q->priority_level), $q->assignedHelper?->full_name ?? 'Unassigned', $date($q->request_date), $date($q->matched_date)], 'type' => 'queue_requests'],
            ['title' => 'Emergency case activity', 'group' => 'safety', 'columns' => ['Reference', 'Status', 'Priority', 'Helper', 'Detected', 'Acknowledged', 'Resolved'], 'records' => $records($emergencies->orderByDesc('triggered_at'), 'emergency_page'), 'format' => fn ($e) => [($e->source === 'incident_reports' ? 'Incident #' : 'Emergency #').$e->id, app(ModeratorEmergencyCases::class)->statusLabel($e), ucfirst($e->risk_level), trim($e->helper_first_name.' '.$e->helper_last_name) ?: 'Unassigned', $date($e->triggered_at), $date($e->acknowledged_at), $date($e->resolved_at)], 'type' => 'emergency'],
        ];
        $activity = \DB::query()->fromSub(app(ModeratorActivity::class)->query(), 'report_activity')->whereBetween('occurred_at', [$start, $end])->when($data['activity'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($data['search'] ?? null, function ($q, $value) {
                if (preg_match('/^R-(\d+)$/i', $value, $reference)) {
                    $q->where('record_type', 'counseling_sessions')->where('record_id', (int) $reference[1]);
                } else {
                    $q->where(fn ($q) => $q->whereRaw('LOWER(action) LIKE ?', ['%'.strtolower(str_replace(' ', '_', $value)).'%'])
                        ->orWhereRaw('LOWER(actor) LIKE ?', ['%'.strtolower($value).'%'])
                        ->orWhereRaw('LOWER(record_type) LIKE ?', ['%'.strtolower($value).'%'])
                        ->orWhereRaw('CAST(record_id AS TEXT) LIKE ?', ['%'.$value.'%']));
                }
            });
        $activity->when($data['priority'] ?? null, fn ($q, $v) => $q->where('priority', $v))
            ->when($data['case_status'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->whereNull('record_type')->orWhere('record_type', '!=', 'counseling_sessions')->orWhere(function ($q) use ($v) {
                if ($v === 'scheduled') {
                    $q->where('record_status', 'scheduled')->orWhere(fn ($q) => $q->whereIn('record_status', Session::PENDING_STATUSES)->where('scheduled_start', '>', now()));
                } else {
                    $q->where('record_status', $v);
                }
            })))
            ->when($data['emergency_status'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->whereNull('record_type')->orWhereNotIn('record_type', ['emergency_alerts', 'incident_reports'])->orWhere(function ($q) use ($v) {
                    if ($v === 'active') {
                        $q->whereNotIn('record_status', ModeratorEmergencyCases::TERMINAL)->whereNull('record_archived_at');
                    } elseif ($v === 'archived') {
                        $q->where(fn ($q) => $q->whereNotNull('record_archived_at')->orWhere('record_status', 'archived'));
                    } else {
                        $q->where('record_status', $v);
                    }
                });
            }))
            ->when(($data['archive'] ?? 'all') === 'archived', fn ($q) => $q->whereNotNull('record_archived_at'))
            ->when(($data['archive'] ?? 'all') === 'current', fn ($q) => $q->whereNull('record_archived_at'));
        $tables[] = ['title' => 'Activity Log', 'group' => 'activity', 'columns' => ['Reference', 'Activity', 'Type', 'Current status', 'Priority', 'Date (Philippine Time)', 'Actor / helper', 'Event outcome'], 'records' => $records($activity->orderByDesc('occurred_at')->orderByDesc('id'), 'activity_page'), 'format' => fn ($a) => [$a->record_type === 'counseling_sessions' ? 'R-'.str_pad((string) $a->record_id, 4, '0', STR_PAD_LEFT) : ucwords(str_replace('_', ' ', $a->record_type ?: 'Account')).' #'.$a->record_id, ucwords(str_replace('_', ' ', $a->action)), ucfirst($a->type), $a->record_archived_at ? 'Archived ('.ucwords(str_replace('_', ' ', $a->record_status ?? 'Not recorded')).')' : ucwords(str_replace('_', ' ', $a->record_status ?? 'Not recorded')), ucfirst($a->priority ?? 'Not recorded'), $date($a->occurred_at), $a->actor.' / '.(trim($a->helper_first_name.' '.$a->helper_last_name) ?: 'Unassigned'), ucfirst($a->status)], 'type' => null];

        return compact('summary', 'groups', 'tables', 'from', 'to', 'charts') + ['role' => 'moderator'];
    }
}
