<x-ui.card id="helperWorkload" title="Helper Workload" :subtitle="'Current duty: '.now('Asia/Manila')->format('M d, Y').'.'" class="helper-workload-card" data-helper-workload="{{ route(auth()->user()->role === 'adviser' ? 'adviser.schedule.workload' : 'moderator.schedules.workload') }}">
    <x-slot:actions>
        <x-ui.filter-bar data-workload-filter class="helper-workload-filter">
            @foreach(request()->except(['workload_page','workload_search','workload_status']) as $key=>$value) @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
            <x-ui.field label="Search Helper" for="workload-search"><input id="workload-search" data-control="search" name="workload_search" maxlength="80" value="{{ request('workload_search') }}" placeholder="Helper name"></x-ui.field>
            <x-ui.field label="Status" for="workload-status"><select id="workload-status" data-control="select" name="workload_status">@foreach(['all'=>'All statuses','available'=>'Available','busy'=>'Assigned / reserved','unavailable'=>'Unavailable'] as $key=>$label)<option value="{{ $key }}" @selected(request('workload_status','all')===$key)>{{ $label }}</option>@endforeach</select></x-ui.field>
            <x-slot:buttons><button type="submit" class="btn-secondary">Filter</button></x-slot:buttons>
        </x-ui.filter-bar>
    </x-slot:actions>
    <p class="helper-workload-note">Maximum 2 sessions per duty and 1 live assignment at a time. Future appointments reserve capacity only when they could overlap a new session.</p>
    <x-ui.table :stack="true">
        <thead><tr><th>Helper</th><th>Current status</th><th>Sessions in duty</th><th>Current assignment</th><th>Remaining</th></tr></thead>
        <tbody data-workload-body>
        @forelse($workload as $row)
            <tr data-helper-id="{{ $row['id'] }}"><td data-label="Helper">{{ $row['name'] }}</td><td data-label="Current status" title="{{ $row['reason'] }}">{{ $row['status'] }}</td><td data-label="Sessions in duty">{{ $row['used'] }} / {{ $row['limit'] }}</td><td data-label="Current assignment">{{ $row['assignments'] ? 'Assigned / reserved' : 'None' }} ({{ $row['assignments'] }} / 1)</td><td data-label="Remaining">{{ $row['remaining'] }}</td></tr>
        @empty<tr><td colspan="5">No Helpers match these filters.</td></tr>@endforelse
        </tbody>
    </x-ui.table>
    <p class="sr-only" data-workload-summary role="status">Showing {{ $workload->firstItem() ?? 0 }}–{{ $workload->lastItem() ?? 0 }} of {{ $workload->total() }} Helpers</p>
    <div data-workload-pages>{{ $workload->links('vendor.pagination.compass', ['noun' => 'helpers']) }}</div>
</x-ui.card>
<style>.helper-workload-card{margin-top:24px}.helper-workload-card .ui-card-head{align-items:flex-start}.helper-workload-filter{margin-bottom:0}.helper-workload-card .helper-workload-filter .ui-field :is(input,select){min-height:44px;font-size:15px;border-radius:12px;padding:0 12px}.helper-workload-card .helper-workload-filter .ui-field select{padding-right:40px}.helper-workload-card .helper-workload-filter .ui-field input[data-control=search]{padding-left:40px}.helper-workload-note{margin:0 0 16px;font-size:13px;color:var(--compass-muted,#6b7280)}.helper-workload-card .ui-card-actions{max-width:100%}</style>
<script src="{{ asset('js/helper-workload.js') }}?v={{ filemtime(public_path('js/helper-workload.js')) }}" defer></script>
