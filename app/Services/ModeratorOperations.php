<?php

namespace App\Services;

use App\Models\QueueRequest;
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
        if ($request->filled('date_range')) {
            if (! preg_match('/^(\d{4}-\d{2}-\d{2}) to (\d{4}-\d{2}-\d{2})$/', $request->string('date_range'), $m)) {
                throw ValidationException::withMessages(['date_range' => 'Choose a valid start and end date.']);
            }
            $request->merge(['from' => $m[1], 'to' => $m[2]]);
        }
        $data = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'case_status' => 'nullable|in:active,completed,evaluated,waiting,helper_assigned,pending_review,emergency,cancelled,no_show',
            'emergency_status' => 'nullable|in:active,pending,notified,acknowledged,responding,under_review,open,escalated,referred,resolved,closed,cancelled,archived',
            'priority' => 'nullable|in:low,moderate,high,emergency', 'activity' => 'nullable|in:cases,emergencies,queue,actions',
            'search' => 'nullable|string|max:100', 'archive' => 'nullable|in:all,archived,current']);
        $from = $data['from'] ?? now('Asia/Manila')->subDays(29)->toDateString();
        $to = $data['to'] ?? now('Asia/Manila')->toDateString();
        $start = Carbon::parse($from, 'Asia/Manila')->startOfDay()->utc();
        $end = Carbon::parse($to, 'Asia/Manila')->endOfDay()->utc();
        if ($end->lt($start)) {
            throw ValidationException::withMessages(['date_range' => 'The end date must follow the start date.']);
        }
        $cases = Session::with('concern', 'helper')->whereBetween(\DB::raw('COALESCE(submitted_at,created_date,created_at)'), [$start, $end])->when($data['case_status'] ?? null, fn ($q, $s) => $q->where('session_status', $s))->when($data['priority'] ?? null, fn ($q, $v) => $q->where('risk_level', $v));
        $emergencies = app(ModeratorEmergencyCases::class)->query()->whereBetween('triggered_at', [$start, $end])->when($data['priority'] ?? null, fn ($q, $v) => $q->where('risk_level', $v));
        if (($data['emergency_status'] ?? null) === 'active') {
            $emergencies->whereNotIn('status', ModeratorEmergencyCases::TERMINAL)->whereNull('archived_at');
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
        $activity = app(ModeratorActivity::class)->query()->whereBetween('occurred_at', [$start, $end])->when($data['activity'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($data['search'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('action', 'like', '%'.$v.'%')->orWhere('actor', 'like', '%'.$v.'%')->orWhere('record_type', 'like', '%'.$v.'%')->orWhereRaw('CAST(record_id AS TEXT) LIKE ?', ['%'.$v.'%'])));
        $tables[] = ['title' => 'Activity Log', 'group' => 'activity', 'columns' => ['Activity', 'Related record', 'Actor', 'Status / outcome', 'Date (Philippine Time)'], 'records' => $records($activity->orderByDesc('occurred_at')->orderByDesc('id'), 'activity_page'), 'format' => fn ($a) => [ucwords(str_replace('_', ' ', $a->action)), ucwords(str_replace('_', ' ', $a->record_type)).' #'.$a->record_id, $a->actor, ucfirst($a->status), $date($a->occurred_at)], 'type' => null];

        return compact('summary','groups','tables','from','to') + ['role' => 'moderator'];
    }
}
