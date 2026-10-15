@props(['action', 'dates', 'reportUrl' => null])
@once
<style>.analytics-period-form [hidden]{display:none!important}.analytics-period-form label{display:grid;gap:4px;font-size:12px}.analytics-period-form :is(select,input){border:1px solid #d5e0d9;border-radius:8px;padding:8px 10px;background:#fff;font:inherit}.analytics-period-form .analytics-button{padding:8px 12px;border:1px solid #d5e0d9;border-radius:8px;text-decoration:none;color:#087341}.analytics-period-form .analytics-button-primary{background:#078749;color:#fff}.analytics-period-caption{font-size:12px;color:#64748b;margin:8px 0 14px}.co-kpi>small{display:block;margin-top:8px;color:#64748b;font-size:12px}</style>
@endonce
<div class="analytics-toolbar" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin:12px 0">
    <form method="GET" action="{{ $action }}" class="analytics-period-form" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
        @php($selectedPeriod = request()->filled('from') ? 'custom' : request('period', 'monthly'))
        <label>Period<select name="period" onchange="const custom=this.value==='custom';this.form.querySelectorAll('[data-custom-date]').forEach(el=>{el.hidden=!custom;el.querySelector('input').disabled=!custom})">@foreach(['today'=>'Today','weekly'=>'Last 7 days','monthly'=>'Last 30 days','semester'=>'Semester','all'=>'All time','custom'=>'Custom'] as $value=>$label)<option value="{{ $value }}" @selected($selectedPeriod === $value) @disabled($value === 'semester' && (!config('analytics.semester_start') || !config('analytics.semester_end')))>{{ $label }}{{ $value === 'semester' && (!config('analytics.semester_start') || !config('analytics.semester_end')) ? ' (use Custom dates)' : '' }}</option>@endforeach</select></label>
        <label data-custom-date @if($selectedPeriod !== 'custom') hidden @endif>From<input type="date" name="from" value="{{ $dates['from'] }}" required @disabled($selectedPeriod !== 'custom')></label>
        <label data-custom-date @if($selectedPeriod !== 'custom') hidden @endif>To<input type="date" name="to" value="{{ $dates['to'] }}" max="{{ now('Asia/Manila')->toDateString() }}" required @disabled($selectedPeriod !== 'custom')></label>
        <button type="submit" class="analytics-button analytics-button-primary">Apply dates</button>
        <a href="{{ $action }}" class="analytics-button">Reset</a>
    </form>
    @if($reportUrl)<a href="{{ $reportUrl }}" class="analytics-button">View report</a>@endif
</div>
@if($errors->any())<div class="analytics-feedback" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<p class="analytics-period-caption">{{ $dates['from'] }} – {{ $dates['to'] }} · Philippine Time. Live workload and account totals are independent of these dates.</p>
