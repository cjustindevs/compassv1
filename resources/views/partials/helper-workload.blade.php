<section class="duty-card" id="helperWorkload" style="margin-top:20px" aria-labelledby="helperWorkloadTitle" data-helper-workload="{{ route(auth()->user()->role === 'adviser' ? 'adviser.schedule.workload' : 'moderator.schedules.workload') }}">
    <div class="duty-list-heading"><div><h2 id="helperWorkloadTitle">Helper Workload <small>Live</small></h2><p>Current duty: {{ now('Asia/Manila')->format('M d, Y') }}. Maximum 2 sessions per duty and 1 live assignment at a time. Future appointments reserve capacity only when they could overlap a new session.</p></div></div>
    <form method="GET" data-workload-filter class="duty-workload-filter">
        @foreach(request()->except(['workload_page','workload_search','workload_status']) as $key=>$value) @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
        <label>Search Helper<input name="workload_search" maxlength="80" value="{{ request('workload_search') }}" placeholder="Helper name"></label>
        <label>Status<select name="workload_status">@foreach(['all'=>'All statuses','available'=>'Available','busy'=>'Assigned / reserved','unavailable'=>'Unavailable'] as $key=>$label)<option value="{{ $key }}" @selected(request('workload_status','all')===$key)>{{ $label }}</option>@endforeach</select></label>
        <button type="submit" class="duty-link-button">Filter</button>
    </form>
    <div class="duty-table"><table><thead><tr><th>Helper</th><th>Current status</th><th>Sessions this duty</th><th>Current assignment</th><th>Remaining</th></tr></thead><tbody data-workload-body>
    @forelse($workload as $row)
        <tr data-helper-id="{{ $row['id'] }}"><td>{{ $row['name'] }}</td><td title="{{ $row['reason'] }}">{{ $row['status'] }}</td><td>{{ $row['used'] }} / {{ $row['limit'] }}</td><td>{{ $row['assignments'] ? 'Assigned / reserved' : 'None' }} ({{ $row['assignments'] }} / 1)</td><td>{{ $row['remaining'] }}</td></tr>
    @empty<tr><td colspan="5">No Helpers match these filters.</td></tr>@endforelse
    </tbody></table></div>
    <p data-workload-summary role="status">Showing {{ $workload->firstItem() ?? 0 }}-{{ $workload->lastItem() ?? 0 }} of {{ $workload->total() }} Helpers</p>
    <div data-workload-pages>{{ $workload->links() }}</div>
</section>
<style>.duty-workload-filter{display:flex;align-items:end;gap:10px;flex-wrap:wrap;margin:12px 0}.duty-workload-filter label{display:grid;gap:4px;font-size:12px}.duty-workload-filter :is(input,select){border:1px solid #d5e0d9;border-radius:8px;background:#fff;padding:8px 10px;max-width:100%}</style>
<script src="{{ asset('js/helper-workload.js') }}?v={{ filemtime(public_path('js/helper-workload.js')) }}" defer></script>
