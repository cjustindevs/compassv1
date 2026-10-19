@extends('layouts.app')
@section('title','Queue Performance - COMPASS')
@section('content')
@include('partials.management-ui-styles')
<style>.qp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,230px),1fr));gap:14px}.qp-grid .cm-card{margin:0}.qp-value{font-size:27px;font-weight:700;margin:10px 0}.qp-filters{display:flex;flex-wrap:wrap;gap:12px;align-items:end}.qp-filters label{display:grid;gap:6px}.qp-filters input,.qp-filters select{padding:10px;border:1px solid #d4e2d9;border-radius:8px}.qp-filters details{flex-basis:100%}.qp-definitions{line-height:1.7}.qp-definitions dt{font-weight:600;margin-top:10px}.qp-definitions dd{margin:0}.qp-muted{font-size:13px;color:#63766a}</style>
<div class="cm-page">
    <header class="cm-header"><div><h1>Queue Performance</h1><p>Recorded waits, accepted assignments, verified duty time and emergency acknowledgment.</p></div><a class="cm-button" href="{{ route(auth()->user()->role.'.reports') }}">Back to reports</a></header>
    @if($errors->any())<div role="alert">{{ $errors->first() }}</div>@endif
    <form class="cm-card qp-filters" method="GET">
        <label>Period<select name="period" onchange="if(this.value!=='custom'){this.form.elements.from.value='';this.form.elements.to.value='';}">@foreach(['today'=>'Today','weekly'=>'Last 7 days','monthly'=>'Last 30 days','semester'=>'Semester','custom'=>'Custom dates'] as $value=>$label)<option value="{{ $value }}" @selected(request('period','monthly')===$value)>{{ $label }}</option>@endforeach</select></label>
        <label>From (PHT)<input type="date" onchange="this.form.elements.period.value='custom'" name="from" value="{{ request('from') }}"></label>
        <label>To (PHT)<input type="date" onchange="this.form.elements.period.value='custom'" name="to" max="{{ now('Asia/Manila')->toDateString() }}" value="{{ request('to') }}"></label>
        <button class="cm-button cm-primary" type="submit">Apply</button><a class="cm-button" href="{{ route('queue.performance') }}">Reset</a>
        <details @if(request('risk') || request('concern_id')) open @endif><summary>Risk and concern filters</summary><div class="qp-filters">
            <label>Case risk<select name="risk"><option value="">All risks</option>@foreach(['low','moderate','high','emergency'] as $risk)<option @selected(request('risk')===$risk) value="{{ $risk }}">{{ ucfirst($risk) }}</option>@endforeach</select></label>
            <label>Concern<select name="concern_id"><option value="">All concerns</option>@foreach($concerns as $concern)<option value="{{ $concern->id }}" @selected(request('concern_id')==$concern->id)>{{ $concern->concern_name }}</option>@endforeach</select></label>
        </div></details>
    </form>
    <p class="qp-muted">{{ $period->dates()['from'] }} to {{ $period->dates()['to'] }} · As of {{ $asOf->timezone('Asia/Manila')->format('M d, Y g:i:s A') }} PHT. {{ auth()->user()->role==='adviser' ? 'Your authorized supervision scope.' : 'System operational scope.' }}</p>
    <div class="qp-grid">@foreach($cards as $card)<section class="cm-card"><h2>{{ $card['label'] }}</h2><p class="qp-value">{{ $card['value'] }}</p><p class="qp-muted">{{ $card['sample'] }}</p></section>@endforeach</div>
    <section class="cm-card"><h2>Evidence and separate reporting</h2>
        <p>After-hours / cut-off requests that started: {{ $quality['after_hours_started'] }}; average wait: {{ \App\Services\SessionPerformance::display($quality['after_hours_average'],' min') }}.</p>
        <p class="qp-muted">{{ $quality['missing_wait_evidence'] }} started sessions missing valid queue timestamps; {{ $quality['invalid_acknowledgments'] }} acknowledgments missing valid actor/time evidence; {{ $quality['legacy_processed_unknown'] }} queue entries without recorded processing history.</p>
        <p>Assignment and verified-duty evidence is collected from this revision onward. Older missing evidence is not reconstructed. Test and duplicate queue records are excluded.</p>
    </section>
    <details class="cm-card"><summary>Definitions and reporting boundaries</summary><dl class="qp-definitions">
        <dt>Waiting time</dt><dd>Active queue entry to actual session start, grouped by session-start date. Still-waiting requests are excluded. Monday–Saturday entries from 6 PM to before 10:30 PM are shown in the main waiting metrics; other entries are reported separately. Testing-hours settings do not rewrite these reporting labels.</dd>
        <dt>Successful assignment</dt><dd>Final verified eligible offer accepted by the Helper and proceeding to scheduled/active support. One count per request, grouped by first matching-processing date; failed requests remain in the denominator. Pending outcomes are visible. Emergency coordination is excluded even when peer support also continues.</dd>
        <dt>Helper utilization</dt><dd>Union of actual session time overlapping observed verified available duty, divided by the union of those duty intervals. Busy service time stays in the denominator. This pool-level measure uses your authorized Helper scope and date range, independently of risk/concern case filters.</dd>
        <dt>Emergency response</dt><dd>Identification to the first recorded authorized acknowledgment, grouped by emergency-identification date. This is not external emergency-service arrival time or Helper first-message response time.</dd>
        <dt>Interpretation</dt><dd>No data is not zero. These measures support operational review and do not automatically trigger disciplinary actions.</dd>
    </dl></details>
    <a class="cm-button" href="{{ route('queue.performance.export',request()->query()) }}">Export filtered CSV</a>
</div>
@endsection
