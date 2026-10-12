@props(['action', 'dates', 'reportUrl' => null])
<div class="analytics-toolbar">
    <form method="GET" action="{{ $action }}" class="analytics-period-form">
        <label>From<input type="date" name="from" value="{{ $dates['from'] }}" required></label>
        <label>To<input type="date" name="to" value="{{ $dates['to'] }}" max="{{ now('Asia/Manila')->toDateString() }}" required></label>
        <button type="submit" class="analytics-button analytics-button-primary">Apply dates</button>
        <a href="{{ $action }}" class="analytics-button">Last 30 days</a>
    </form>
    @if($reportUrl)<a href="{{ $reportUrl }}" class="analytics-button">View report</a>@endif
</div>
@if($errors->any())<div class="analytics-feedback" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<p class="analytics-period-caption">{{ $dates['from'] }} – {{ $dates['to'] }} · Philippine Time. Current workload and account totals are independent of these dates.</p>
