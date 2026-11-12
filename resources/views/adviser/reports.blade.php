@extends('layouts.app')
@section('title', 'Reports - COMPASS')
@section('content')
@php
    $resetHref = route('adviser.reports', ['tab' => $tab]);
    $chips = \App\Support\FilterChips::from(request(), [
        'period' => 'Period', 'from' => 'From', 'to' => 'To', 'helper_id' => 'Helper', 'concern_id' => 'Concern',
        'case_status' => 'Case status', 'referral_status' => 'Referral status', 'history' => 'History',
        'competency_metric' => 'Metric', 'emergency_status' => 'Emergency status', 'priority' => 'Priority',
    ], ['tab']);
    // Show names instead of raw ids on the Helper and Concern chips.
    $chips = array_map(function ($chip) use ($filterHelpers, $categories) {
        if (str_starts_with($chip['label'], 'Helper: ') && ($h = $filterHelpers->firstWhere('id', (int) substr($chip['label'], 8)))) {
            $chip['label'] = 'Helper: '.($h->full_name ?: 'Name not recorded');
        } elseif (str_starts_with($chip['label'], 'Concern: ') && ($c = $categories->firstWhere('id', (int) substr($chip['label'], 9)))) {
            $chip['label'] = 'Concern: '.$c->concern_name;
        }
        return $chip;
    }, $chips);
    $exportQuery = request()->except(['page', 'cases_page']);
@endphp
<div class="adviser-page-content reports-page">
<x-ui.page-header title="Reports" :description="'Authorized supervision records. '.$filters['start']->copy()->timezone('Asia/Manila')->format('M d, Y g:i A').' to '.$filters['end']->copy()->subMicrosecond()->timezone('Asia/Manila')->format('M d, Y g:i A').' (Philippine Time).'">
    <x-slot:actions>
        <a class="btn-secondary" href="{{ route('queue.performance', request()->only(['from','to','period'])) }}">Queue performance</a>
        <a class="btn-secondary" href="{{ route('adviser.reports.export', array_merge($exportQuery, ['format' => 'csv'])) }}"><x-ui-icon name="download" /> Export CSV</a>
        <a class="btn-secondary" href="{{ route('adviser.reports.export', array_merge($exportQuery, ['format' => 'pdf'])) }}"><x-ui-icon name="download" /> Export PDF</a>
    </x-slot:actions>
</x-ui.page-header>
@if(session('success'))<p class="ui-card" role="status">{{ session('success') }}</p>@endif
@if($errors->any())<p class="ui-card" role="alert">{{ $errors->first() }}</p>@endif
<nav class="ui-badge-row reports-tabs" aria-label="Report groups" style="margin-bottom:16px">
@foreach(['cases'=>'Sessions / Cases','performance'=>'Helper Performance','safety'=>'Emergency / Safety','activity'=>'Activity History'] as $key=>$label)<a class="btn {{ $tab===$key ? 'btn-primary' : 'btn-outline' }}" href="{{ route('adviser.reports', array_merge(request()->except(['page','cases_page','actions_page','emergency_page']),['tab'=>$key])) }}" @if($tab===$key) aria-current="page" @endif>{{ $label }}</a>@endforeach
</nav>
<x-ui.card>
<x-ui.filter-bar aria-label="Report filters">
<input type="hidden" name="tab" value="{{ $tab }}">
<x-ui.field label="Period" for="rp-period"><select id="rp-period" name="period" data-control="select">@foreach(['today'=>'Today','weekly'=>'Last 7 days','monthly'=>'Last 30 days','semester'=>'Semester','all'=>'All time','custom'=>'Custom','quarterly'=>'Last 90 days','yearly'=>'Last year'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['period'] ?? 'monthly')===$key) @disabled($key==='semester' && (!config('analytics.semester_start') || !config('analytics.semester_end')))>{{ $label }}</option>@endforeach</select></x-ui.field>
<x-ui.field label="From (Philippine Time)" for="rp-from"><input id="rp-from" name="from" type="date" data-control="date" value="{{ request('from') ? \Illuminate\Support\Carbon::parse(request('from'),'Asia/Manila')->format('Y-m-d') : '' }}"></x-ui.field>
<x-ui.field label="To (Philippine Time)" for="rp-to"><input id="rp-to" name="to" type="date" data-control="date" value="{{ request('to') ? \Illuminate\Support\Carbon::parse(request('to'),'Asia/Manila')->format('Y-m-d') : '' }}"></x-ui.field>
@if($tab!=='activity')
<x-ui.field label="Helper" for="rp-helper"><select id="rp-helper" name="helper_id" data-control="select"><option value="">All supervised Helpers</option>@foreach($filterHelpers as $helper)<option value="{{ $helper->id }}" @selected(request('helper_id')==$helper->id)>{{ $helper->full_name ?: 'Name not recorded' }}</option>@endforeach</select></x-ui.field>
<x-ui.field label="Concern" for="rp-concern"><select id="rp-concern" name="concern_id" data-control="select"><option value="">All concerns</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('concern_id')==$category->id)>{{ $category->concern_name }}</option>@endforeach</select></x-ui.field>
@endif
@if(in_array($tab,['cases','performance']))
<x-ui.field label="Case status" for="rp-case-status"><select id="rp-case-status" name="case_status" data-control="select"><option value="">All statuses</option>@foreach(['active','completed','evaluated','waiting','helper_assigned','pending_review','emergency','cancelled','no_show'] as $state)<option value="{{ $state }}" @selected(request('case_status')===$state)>{{ ucwords(str_replace('_',' ',$state)) }}</option>@endforeach</select></x-ui.field>
<x-ui.field label="Referral status" for="rp-referral-status"><select id="rp-referral-status" name="referral_status" data-control="select"><option value="">All referrals</option>@foreach(\App\Models\Referral::STATUSES as $status)<option value="{{ $status }}" @selected(request('referral_status')===$status)>{{ ucwords(str_replace('_',' ',$status)) }}</option>@endforeach</select></x-ui.field>
<x-ui.field label="Case history" for="rp-history"><select id="rp-history" name="history" data-control="select">@foreach(['current'=>'Unarchived records','archived'=>'Archived cases','all'=>'All records'] as $key=>$label)<option value="{{ $key }}" @selected(request('history','current')===$key)>{{ $label }}</option>@endforeach</select></x-ui.field>
@endif
@if($tab==='performance')<x-ui.field label="Competency metric" for="rp-metric"><select id="rp-metric" name="competency_metric" data-control="select">@foreach(['overall_score'=>'Overall competency'] + collect(\App\Services\CompetencyRubric::CRITERIA)->mapWithKeys(fn($c)=>[$c[2]=>$c[0]])->all() as $key=>$label)<option value="{{ $key }}" @selected(($filters['competency_metric'] ?? 'overall_score')===$key)>{{ $label }}</option>@endforeach</select></x-ui.field>@endif
@if($tab==='safety')
<x-ui.field label="Emergency status" for="rp-emergency-status"><select id="rp-emergency-status" name="emergency_status" data-control="select"><option value="">All statuses</option>@foreach(['open','triggered','pending','under_review','responding','acknowledged','escalated','resolved','closed'] as $state)<option value="{{ $state }}" @selected(request('emergency_status')===$state)>{{ ucwords(str_replace('_',' ',$state)) }}</option>@endforeach</select></x-ui.field>
<x-ui.field label="Priority" for="rp-priority"><select id="rp-priority" name="priority" data-control="select"><option value="">All priorities</option>@foreach(['emergency','high','moderate','low'] as $priority)<option value="{{ $priority }}" @selected(request('priority')===$priority)>{{ ucfirst($priority) }}</option>@endforeach</select></x-ui.field>
@endif
<x-slot:buttons><button type="submit" class="btn-primary">Apply filters</button><a class="btn-secondary" href="{{ $resetHref }}">Reset</a></x-slot:buttons>
</x-ui.filter-bar>
<x-ui.filter-chips :chips="$chips" :reset-href="$resetHref" />
</x-ui.card>
@if($tab==='cases')
<x-ui.kpi-grid>
@foreach(['Cases in period'=>'neutral','Completed cases'=>'green','Active sessions'=>'blue','Pending requests'=>'amber'] as $label=>$tone)<x-ui.kpi :label="$label" :value="$roleReport['summary'][$label]" :tone="$tone" />
@endforeach
</x-ui.kpi-grid>
<x-report-charts :groups="collect($roleReport['groups'])->only(['Cases by status','Case categories','Case outcome trend'])->all()" heading="Case outcomes and categories" caption="Selected period · Philippine Time" />
<x-ui.card title="Session records" :subtitle="$sessions->total().' records. Archiving preserves history and never reopens or closes a case.'">
<x-ui.table :stack="true" class="ui-table--wide"><thead><tr><th>Reference</th><th>Helper</th><th>Concern</th><th>Status</th><th>Activity date</th><th>Actions</th></tr></thead><tbody>
@forelse($sessions as $session)<tr><td data-label="Reference">{{ $session->reference_number }}</td><td data-label="Helper">{{ $session->helper?->full_name ?? 'Unassigned' }}</td><td data-label="Concern">{{ $session->concern?->concern_name ?? 'Not recorded' }}</td><td data-label="Status"><x-ui.badge>{{ ucwords(str_replace('_',' ',$session->session_status)) }}</x-ui.badge>@if($session->archived_at)<small>Archived</small>@endif</td><td data-label="Activity date">{{ ($session->end_time ?? $session->start_time ?? $session->submitted_at ?? $session->created_date ?? $session->created_at)?->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }}</td><td data-label="Actions" class="ui-actions"><a class="btn-secondary" href="{{ route('adviser.session.show',$session->id) }}">View documentation</a>@include('adviser.partials.archive-action',['record'=>$session])</td></tr>
@empty<tr><td colspan="6">No records match these filters.</td></tr>@endforelse
</tbody></x-ui.table>
{{ $sessions->links('vendor.pagination.compass', ['noun' => 'records']) }}
</x-ui.card>
@elseif($tab==='performance')
<x-ui.card title="Service performance" subtitle="Actual supervised service records in the selected period. Scheduled duty is planned coverage, not attendance.">
<x-ui.table :stack="true"><thead><tr><th>Measure</th><th>Recorded result</th></tr></thead><tbody>
@foreach(['total'=>['Session records',''],'completed'=>['Completed sessions',''],'completion_rate'=>['Completion rate','%'],'referral_rate'=>['Referral rate','%'],'response_minutes'=>['Helper first response',' min'],'waiting_minutes'=>['Queue waiting time',' min'],'duration_minutes'=>['Completed session duration',' min'],'helpfulness'=>['Helpfulness rating (1–5)',''],'referral_approval_rate'=>['Referral approval rate','%'],'duty_hours'=>['Scheduled duty coverage',' h'],'competency'=>['Average competency (1–5)',''],'workload'=>['Completed sessions per serving Helper',''],'documentation_rate'=>['Documentation completion rate','%'],'documentation_pending'=>['Pending documentation',''],'emergency_rate'=>['Emergency escalation rate','%']] as $key=>$label)<tr><td data-label="Measure">{{ $label[0] }}</td><td data-label="Recorded result">{{ \App\Services\SessionPerformance::number($metrics[$key], $label[1]) }}@if(isset($samples[$key]))<small>{{ $samples[$key] }} records measured</small>@endif</td></tr>@endforeach
</tbody></x-ui.table>
</x-ui.card>
@php
    $monthLabel = fn ($m) => \Carbon\Carbon::createFromFormat('Y-m', $m)->format('M Y');
    $activityChart = ['type' => 'column', 'labels' => collect($trends)->pluck('month')->map($monthLabel)->values()->all(),
        'datasets' => [['label' => 'Session records', 'data' => collect($trends)->pluck('sessions')->values()->all()], ['label' => 'Completed', 'data' => collect($trends)->pluck('completed')->values()->all()]]];
    $competencyChart = ['type' => 'line', 'scale' => [1, 5], 'unit' => '', 'labels' => collect($competency)->pluck('month')->map($monthLabel)->values()->all(),
        'datasets' => [['label' => 'Average score (1–5)', 'data' => collect($competency)->pluck('score')->map(fn ($v) => $v === null ? null : (float) $v)->values()->all()]]];
@endphp
<div class="analytics-chart-grid report-chart-grid">
<x-analytics-chart title="Monthly session activity" description="Supervised session records and completions per month · Philippine Time" :chart="$activityChart" />
<x-analytics-chart title="Competency in the selected period" :description="ucwords(str_replace('_',' ', $filters['competency_metric'])).' from recorded evaluations, monthly average on a 1–5 scale.'" :chart="$competencyChart" />
</div>
<details class="ui-card reports-details"><summary>Calculation definitions</summary>@foreach($definitions as $label=>$definition)<p class="ui-prose" style="margin-top:12px"><strong>{{ ucwords(str_replace('_',' ',$label)) }}:</strong> {{ $definition }}</p>@endforeach</details>
@else
@php
    $reportTable = collect($roleReport['tables'])->firstWhere('title', $tab==='safety' ? 'Emergency case activity' : 'Your recorded actions');
    $summaryLabels = $tab==='safety' ? ['Emergencies'=>'red','Resolved emergencies'=>'green','Unresolved emergencies'=>'amber','Emergency resolution time'=>'neutral'] : [];
@endphp
@if($summaryLabels)<x-ui.kpi-grid>@foreach($summaryLabels as $label=>$tone)<x-ui.kpi :label="$label" :value="$roleReport['summary'][$label]" :tone="$tone" />
@endforeach</x-ui.kpi-grid>@endif
@if($tab==='safety')<x-report-charts :groups="collect($roleReport['groups'])->only(['Emergency status','Emergency priority'])->all()" heading="Emergency overview" />@endif
<x-ui.card :title="$tab==='safety' ? 'Emergency case activity' : 'Your recorded actions'" :subtitle="$tab==='activity' ? 'Your auditable Adviser actions, including archive and restore events. Case and emergency records are available in their report groups.' : null">
<x-ui.table :stack="true" class="ui-table--wide"><thead><tr>@foreach($reportTable['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead><tbody>
@forelse($reportTable['records'] as $record)
@php
    $cells = call_user_func($reportTable['format'], $record);
@endphp
<tr>@foreach($cells as $cell)<td data-label="{{ $reportTable['columns'][$loop->index] ?? '' }}">{{ $cell ?? 'Not recorded' }}</td>@endforeach</tr>
@empty<tr><td colspan="{{ count($reportTable['columns']) }}">No records match these filters.</td></tr>@endforelse
</tbody></x-ui.table>
{{ $reportTable['records']->links('vendor.pagination.compass', ['noun' => 'records']) }}
</x-ui.card>
@endif
</div>
<style>
.reports-page .ui-table td small{display:block;font-size:13px;color:var(--compass-muted,#6b7280)}
.reports-page .reports-details>summary{cursor:pointer;font-size:18px;font-weight:700}
.reports-page .reports-details[open]>summary{margin-bottom:12px}
.reports-page .ui-badge{white-space:nowrap}
</style>
@endsection
