@props(['report', 'sections', 'selectedSection' => 'cases'])
@php
    $periodLabels = ['Registrations in period', 'Requests submitted in period', 'Sessions completed in period'];
    $filterNames = ['activity_date','case_status','emergency_status','priority','helper_id','concern_id','referral_status','competency_metric','period'];
    $navigationFilters = request()->only(array_merge(['from','to'], $filterNames));
    $measureLabels = [
        'cases' => ['Active sessions','Pending requests','Cancelled / no-show','Helper acceptance time','Completed session duration'],
        'emergencies' => ['Resolved emergencies','Unresolved emergencies','Emergency acknowledgment time','Emergency resolution time'],
        'queue' => ['Successful matches','Queue-entry matching rate','Queue matching time'],
        'registrations' => ['Total users','Active accounts','Available Helpers'],
    ];
    $descriptions = [
        'cases' => 'Support cases within the selected dates. Choose which case event to report.',
        'emergencies' => 'Emergency records by trigger date, with their recorded acknowledgment and resolution.',
        'queue' => 'Matching queue entries by request date. Matching rate describes these entries, not historical Helper eligibility.',
        'availability' => 'Recorded Helper status changes within the selected dates.',
        'actions' => 'Recorded system actions within the selected dates.',
        'registrations' => 'New accounts registered within the selected dates. Account status and account totals describe current accounts.',
    ];
@endphp
<section class="admin-operational-report" aria-label="System activity report">
    <div data-report-period-controls @if($selectedSection === 'catalog') hidden @endif>
        <form method="GET" action="{{ route('admin.reports') }}" class="admin-report-filters admin-report-date-controls">
            <input type="hidden" name="section" value="{{ $selectedSection }}">
            <label>From (Philippine Time)<input type="date" name="from" value="{{ $report['from'] }}"></label>
            <label>To (Philippine Time)<input type="date" name="to" value="{{ $report['to'] }}"></label>
            @foreach($filterNames as $filter)
                @if(request()->filled($filter) && is_scalar(request($filter)))<input type="hidden" name="{{ $filter }}" value="{{ request($filter) }}">@endif
            @endforeach
            <div class="admin-report-filter-actions">
                <button class="admin-button admin-button-primary" type="submit">Apply dates</button>
                <a class="admin-button admin-button-secondary" href="{{ route('admin.reports', ['section' => $selectedSection]) }}">Reset</a>
            </div>
        </form>
        @if($errors->any())<div class="admin-flash" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <p class="admin-report-period">Period overview · {{ $report['from'] }} to {{ $report['to'] }} (Philippine Time). These totals cover the whole period; record filters apply below.</p>
        <dl class="admin-report-headlines admin-report-period-totals">
            @foreach($periodLabels as $label)<div><dt>{{ $label }}</dt><dd>{{ number_format($report['summary'][$label]) }}</dd></div>@endforeach
        </dl>
    </div>

    <nav class="admin-report-navigation" aria-label="Report categories">
        @foreach($sections as $key => $section)
            <a class="admin-button admin-button-secondary {{ $selectedSection === $key ? 'is-active' : '' }}" data-report-section-link="{{ $key }}" href="{{ route('admin.reports', $navigationFilters + ['section' => $key]) }}" @if($selectedSection === $key) aria-current="page" @endif>{{ $section['label'] }}</a>
        @endforeach
    </nav>

    @foreach($report['tables'] as $table)
        @if($table['records']->getPageName() === ($sections[$selectedSection]['page'] ?? null))
            <section class="admin-report-panel admin-report-records" data-report-panel="{{ $selectedSection }}" aria-labelledby="admin-report-records-title">
                <header class="admin-report-panel-heading">
                    <div><h2 id="admin-report-records-title">{{ $table['title'] }}</h2><p>{{ $descriptions[$selectedSection] }}</p></div>
                    <span class="admin-report-count">{{ number_format($table['records']->total()) }} records</span>
                </header>

                @if(in_array($selectedSection, ['cases','emergencies'], true))
                    <form method="GET" action="{{ route('admin.reports') }}" class="admin-report-record-filters">
                        <input type="hidden" name="from" value="{{ $report['from'] }}">
                        <input type="hidden" name="to" value="{{ $report['to'] }}">
                        <input type="hidden" name="section" value="{{ $selectedSection }}">
                        @foreach($filterNames as $filter)
                            @if(!in_array($filter, $selectedSection === 'cases' ? ['activity_date','case_status'] : ['emergency_status','priority'], true) && request()->filled($filter) && is_scalar(request($filter)))<input type="hidden" name="{{ $filter }}" value="{{ request($filter) }}">@endif
                        @endforeach
                        @if($selectedSection === 'cases')
                            <fieldset class="admin-report-date-basis">
                                <legend>Case date basis</legend>
                                <div>@foreach(['activity'=>'Latest activity','submitted'=>'Request submission','completed'=>'Session completion'] as $key => $label)
                                    <label><input type="radio" name="activity_date" value="{{ $key }}" @checked(request('activity_date', 'activity') === $key)><span>{{ $label }}</span></label>
                                @endforeach</div>
                            </fieldset>
                            <label class="admin-report-select">Case status<select name="case_status"><option value="">All case statuses</option>@foreach(['active','completed','evaluated','waiting','helper_assigned','pending_review','emergency','cancelled','no_show'] as $state)<option value="{{ $state }}" @selected(request('case_status') === $state)>{{ Str::headline($state) }}</option>@endforeach</select></label>
                        @else
                            <label class="admin-report-select">Emergency status<select name="emergency_status"><option value="">All emergency statuses</option>@foreach(['open','triggered','pending','under_review','responding','acknowledged','escalated','resolved','closed'] as $state)<option value="{{ $state }}" @selected(request('emergency_status') === $state)>{{ Str::headline($state) }}</option>@endforeach</select></label>
                            <label class="admin-report-select">Priority<select name="priority"><option value="">All priorities</option>@foreach(['emergency','high','moderate','low'] as $priority)<option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ ucfirst($priority) }}</option>@endforeach</select></label>
                        @endif
                        <button class="admin-button admin-button-primary" type="submit">Apply filters</button>
                    </form>
                @endif

                @if(!empty($measureLabels[$selectedSection]))
                    <dl class="admin-report-context-measures">
                        @foreach($measureLabels[$selectedSection] as $label)<div><dt>{{ $label }}</dt><dd>{{ $report['summary'][$label] }}</dd></div>@endforeach
                    </dl>
                    @if($selectedSection === 'cases')<p class="admin-report-scope-note">Measures describe the filtered case records. Timings use valid recorded timestamps only.</p>@endif
                @endif

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
                <div class="admin-report-pagination">
                    {{ $table['records']->appends(['section' => $selectedSection])->links() }}
                </div>
                <p class="admin-report-scope-note">Archived records remain in historical reports. Missing timestamps are not counted as zero. <a class="admin-button admin-button-secondary" href="{{ route('admin.dashboard', ['from' => $report['from'], 'to' => $report['to']]) }}">View dashboard trends</a></p>
            </section>
        @endif
    @endforeach
</section>
