@props([
    'logs',
    'filtersApplied' => false,
])

<div class="audit-table-shell">
    <table class="audit-log-table">
        <thead>
            <tr>
                <th scope="col">Actor</th>
                <th scope="col">Action</th>
                <th scope="col">Target</th>
                <th scope="col">Time</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="audit-actor-cell">
                        <strong>{{ $log['actor'] }}</strong>
                    </td>
                    <td>
                        <span class="audit-action">{{ $log['action'] }}</span>
                    </td>
                    <td class="audit-target-cell">{{ $log['target'] }}</td>
                    <td class="audit-time-cell">
                        <time datetime="{{ $log['timestampIso'] }}" title="{{ $log['timestampTitle'] }}">
                            {{ $log['timeLabel'] }}
                        </time>
                    </td>
                </tr>
            @empty
                <tr class="audit-empty-row">
                    <td colspan="4">
                        <div class="audit-empty-state">
                            <span><x-admin.icon name="file-text" :size="25" /></span>
                            <strong>{{ $filtersApplied ? 'No matching audit events' : 'No audit events found' }}</strong>
                            <p>
                                {{ $filtersApplied
                                    ? 'Try changing your search or filters.'
                                    : 'System activity will appear here as actions are recorded.' }}
                            </p>
                            @if ($filtersApplied)
                                <a href="{{ route('admin.audit-logs') }}">Clear filters</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
