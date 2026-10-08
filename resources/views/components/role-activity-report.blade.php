@props(['report', 'showCases' => true])
<link rel="stylesheet" href="{{ asset('css/role-reports.css') }}?v={{ filemtime(public_path('css/role-reports.css')) }}">
<section class="rr-report" aria-label="Role-based activity report">
    <form method="GET" class="rr-filters">
        <label>From (Philippine Time)<input type="date" name="from" value="{{ $report['from'] }}"></label>
        <label>To (Philippine Time)<input type="date" name="to" value="{{ $report['to'] }}"></label>
        <label>Case status<select name="case_status"><option value="">All case statuses</option>@foreach(['active','completed','evaluated','waiting','helper_assigned','pending_review','emergency','cancelled','no_show'] as $state)<option value="{{ $state }}" @selected(request('case_status')===$state)>{{ ucwords(str_replace('_',' ',$state)) }}</option>@endforeach</select></label>
        @if($report['role']!=='helper')
        <label>Emergency status<select name="emergency_status"><option value="">All emergency statuses</option>@foreach(['open','triggered','pending','under_review','responding','acknowledged','escalated','resolved','closed'] as $state)<option value="{{ $state }}" @selected(request('emergency_status')===$state)>{{ ucwords(str_replace('_',' ',$state)) }}</option>@endforeach</select></label>
        <label>Emergency priority<select name="priority"><option value="">All priorities</option>@foreach(['emergency','high','moderate','low'] as $priority)<option value="{{ $priority }}" @selected(request('priority')===$priority)>{{ ucfirst($priority) }}</option>@endforeach</select></label>
        @endif
        @foreach(['helper_id','concern_id','referral_status','competency_metric','period'] as $filter)@if(request()->filled($filter))<input type="hidden" name="{{ $filter }}" value="{{ request($filter) }}">@endif
@endforeach
        <button type="submit">Apply filters</button>
    </form>
    @if($errors->any())<div role="alert" class="rr-errors">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <details class="rr-note"><summary>Reporting dates and calculation definitions</summary><p>{{ $report['from'] }} to {{ $report['to'] }} | Asia/Manila. Cases use end, start, submission or creation date; emergencies use trigger date. Timings use recorded business events, excluding missing, negative or future intervals. Adviser response combines emergency acknowledgment, referral review and submitted-summary review completed in the period. Queue-entry matching rate is matched entries / queue entries; historical eligibility at entry is not recorded. Current account totals and current Available Helpers are labelled separately from period activity. No data means no valid samples. Scheduled duty is planned coverage, not proof of attendance.</p></details>
    <dl class="rr-summary">@foreach($report['summary'] as $label=>$value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl>
    <div class="rr-distributions">@foreach($report['groups'] as $title=>$values)<article class="rr-card"><h2>{{ $title }}</h2><table><thead><tr><th>Category / period</th><th>Records</th></tr></thead><tbody>@forelse($values as $label=>$count)<tr><td>{{ ucwords(str_replace('_',' ',$label)) }}</td><td>{{ $count }}</td></tr>@empty<tr><td colspan="2">No records in this period.</td></tr>@endforelse</tbody></table></article>@endforeach</div>
    @foreach($report['tables'] as $table)
        @if($showCases || $table['title']!=='Case activity')
        <article class="rr-card"><h2>{{ $table['title'] }}</h2><div class="rr-table-wrap" role="region" aria-label="{{ $table['title'] }}" tabindex="0"><table><thead><tr>@foreach($table['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead><tbody>@forelse($table['records'] as $record)<tr>@php($cells = call_user_func($table['format'], $record))
@foreach($cells as $value)<td>{{ $value ?? 'Unrecorded' }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($table['columns']) }}">No records match these filters.</td></tr>@endforelse</tbody></table></div>{{ $table['records']->links() }}</article>
        @endif
    @endforeach
</section>
