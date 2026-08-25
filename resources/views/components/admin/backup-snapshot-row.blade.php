@props(['snapshot'])

@php
    $statusIcon = match ($snapshot['status']) {
        'completed' => 'check-circle',
        'running' => 'clock',
        'restoring' => 'backup',
        default => 'alert-circle',
    };
@endphp

<article class="backup-snapshot-row backup-status-{{ $snapshot['status'] }}" data-backup-snapshot>
    <span class="backup-snapshot-status" aria-hidden="true">
        <x-admin.icon :name="$statusIcon" :size="21" />
    </span>

    <div class="backup-snapshot-copy">
        <p>
            <time datetime="{{ $snapshot['createdAtIso'] }}" title="{{ $snapshot['createdAtTitle'] }}">
                {{ $snapshot['createdAtLabel'] }}
            </time>
            <span aria-hidden="true">·</span>
            <span>{{ $snapshot['sizeLabel'] }}</span>
        </p>
        @if ($snapshot['status'] !== 'completed')
            <small>{{ $snapshot['statusLabel'] }}</small>
        @endif
    </div>

    <div class="backup-snapshot-actions">
        @if ($snapshot['canDownload'])
            <a href="{{ $snapshot['downloadUrl'] }}">
                <x-admin.icon name="download" :size="16" /> Download
            </a>
        @else
            <button type="button" disabled title="A protected backup download service is not configured">
                <x-admin.icon name="download" :size="16" /> Download
            </button>
        @endif

        <button
            class="backup-restore-row-action"
            type="button"
            data-backup-restore
            data-snapshot-id="{{ $snapshot['id'] }}"
            data-snapshot-summary="{{ $snapshot['summary'] }}"
            @disabled(! $snapshot['canRestore'])
            @if (! $snapshot['canRestore']) title="A protected restore service is not configured" @endif
        >
            Restore
        </button>
    </div>
</article>
