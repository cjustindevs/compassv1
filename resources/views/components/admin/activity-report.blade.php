@props(['report'])
@php
    $headlineLabels = ['Total users', 'Active accounts', 'Cases in period', 'Emergencies'];
    $headlines = array_intersect_key($report['summary'], array_flip($headlineLabels));
    $additional = array_diff_key($report['summary'], $headlines);
@endphp
<section class="admin-operational-report" aria-label="System activity report">
    <form method="GET" action="{{ route('admin.reports') }}" class="admin-report-filters">
        <label>From (Philippine Time)<input type="date" name="from" value="{{ $report['from'] }}"></label>
        <label>To (Philippine Time)<input type="date" name="to" value="{{ $report['to'] }}"></label>
        <label>Case status<select name="case_status"><option value="">All case statuses</option>@foreach(['active','completed','evaluated','waiting','helper_assigned','pending_review','emergency','cancelled','no_show'] as $state)<option value="{{ $state }}" @selected(request('case_status') === $state)>{{ Str::headline($state) }}</option>@endforeach</select></label>
        <label>Emergency status<select name="emergency_status"><option value="">All emergency statuses</option>@foreach(['open','triggered','pending','under_review','responding','acknowledged','escalated','resolved','closed'] as $state)<option value="{{ $state }}" @selected(request('emergency_status') === $state)>{{ Str::headline($state) }}</option>@endforeach</select></label>
        <label>Emergency priority<select name="priority"><option value="">All priorities</option>@foreach(['emergency','high','moderate','low'] as $priority)<option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ ucfirst($priority) }}</option>@endforeach</select></label>
        @foreach(['helper_id','concern_id','referral_status','competency_metric','period'] as $filter)
            @if(request()->filled($filter))<input type="hidden" name="{{ $filter }}" value="{{ request($filter) }}">@endif
        @endforeach
        <button class="admin-button admin-button-primary" type="submit">Apply filters</button>
        <a class="admin-button admin-button-secondary" href="{{ route('admin.reports') }}">Reset</a>
    </form>
    @if($errors->any())<div class="admin-flash" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <p class="admin-report-period">Activity from {{ $report['from'] }} to {{ $report['to'] }} (Philippine Time). Account totals describe current accounts.</p>
    <dl class="admin-report-headlines">@foreach($headlines as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl>
    <details class="admin-report-details">
        <summary>Additional operational measures</summary>
        <dl class="admin-report-measures">@foreach($additional as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl>
    </details>
    <details class="admin-report-details">
        <summary>Distributions and trends</summary>
        <div class="admin-report-distributions">@foreach($report['groups'] as $title => $values)<section><h3>{{ $title }}</h3><table><thead><tr><th scope="col">Category / period</th><th scope="col">Records</th></tr></thead><tbody>@forelse($values as $label => $count)<tr><td>{{ App\Models\User::ROLE_LABELS[$label] ?? Str::headline($label) }}</td><td>{{ $count }}</td></tr>@empty<tr><td colspan="2">No records in this period.</td></tr>@endforelse</tbody></table></section>@endforeach</div>
    </details>
    @foreach($report['tables'] as $table)
        @php($pageName = $table['records']->getPageName())
        <details class="admin-report-details admin-report-records" @if(($loop->first && !request()->hasAny(['emergency_page','queue_page','duty_page','actions_page'])) || request()->has($pageName)) open @endif>
            <summary>{{ $table['title'] }} <span>{{ number_format($table['records']->total()) }} records</span></summary>
            <div class="admin-table-scroll" tabindex="0" role="region" aria-label="{{ $table['title'] }}">
                <table><thead><tr>@foreach($table['columns'] as $column)<th scope="col">{{ $column }}</th>@endforeach</tr></thead><tbody>
                    @forelse($table['records'] as $record)
                        @php($cells = call_user_func($table['format'], $record))
                        <tr>@foreach($cells as $value)<td>{{ $value ?? 'Unrecorded' }}</td>@endforeach</tr>
                    @empty
                        <tr><td colspan="{{ count($table['columns']) }}" class="admin-table-empty">No records match these filters.</td></tr>
                    @endforelse
                </tbody></table>
            </div>
            <div class="admin-report-pagination">{{ $table['records']->links() }}</div>
        </details>
    @endforeach
    <details class="admin-report-definitions"><summary>Report calculations and date scope</summary><p>Cases use end, start, submission, or creation date. Emergencies use trigger date. Timings use recorded events and exclude missing, negative, and future intervals. Queue matching rate is matched queue entries divided by queue entries; historical eligibility is not recorded. Planned duty is not proof of attendance. No data means no valid samples.</p></details>
</section>
