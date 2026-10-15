<section class="duty-card" style="margin-top:20px" aria-labelledby="helperWorkloadTitle" data-helper-workload="{{ route(auth()->user()->role === 'adviser' ? 'adviser.schedule.workload' : 'moderator.schedules.workload') }}">
    <h2 id="helperWorkloadTitle">Helper Workload <small>Live</small></h2>
    <p>Current assignments and remaining duty capacity. Duty capacity counts sessions started during the duty; a pending offer occupies the single live assignment slot. Duty dates are shown above.</p>
    <div class="duty-table"><table><thead><tr><th>Helper</th><th>Current status</th><th>Live assignments</th><th>Remaining duty capacity</th></tr></thead><tbody>
    @forelse($helpers as $helper)
        @php($operational = app(\App\Services\HelperEligibilityService::class)->status($helper))
        <tr data-helper-id="{{ $helper->id }}"><td>{{ $helper->full_name }}</td><td data-workload-status>{{ $operational['label'] }}</td><td data-workload-assignments>{{ $helper->activeSessions()->count() }} / 1</td><td data-workload-remaining>{{ $helper->getRemainingCapacity() }}</td></tr>
    @empty<tr><td colspan="4">No Helpers in your scheduling scope.</td></tr>@endforelse
    </tbody></table></div>
</section>
<script src="{{ asset('js/helper-workload.js') }}?v={{ filemtime(public_path('js/helper-workload.js')) }}" defer></script>
