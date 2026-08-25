@props(['report'])

@php
    $searchText = Str::lower(implode(' ', [
        $report['title'],
        $report['categoryLabel'],
        $report['description'],
        $report['sourceLabel'],
    ]));
    $outputs = collect([
        $report['viewable'] ? 'viewable' : null,
        $report['exportable'] ? 'exportable' : null,
        $report['printable'] ? 'printable' : null,
    ])->filter()->implode(' ');
@endphp

<article
    class="report-card"
    data-report-card
    data-report-id="{{ $report['id'] }}"
    data-report-category="{{ $report['category'] }}"
    data-report-updated="{{ $report['updatedAtTimestamp'] ?? '' }}"
    data-report-outputs="{{ $outputs }}"
    data-report-search="{{ $searchText }}"
    data-report-records="{{ $report['sourceRecordCount'] }}"
>
    <header class="report-card-header">
        <span class="report-card-icon" aria-hidden="true">
            <x-admin.icon name="file-text" :size="24" />
        </span>
        <span class="report-category-badge">{{ $report['categoryLabel'] }}</span>
    </header>

    <div class="report-card-copy">
        <h2>{{ $report['title'] }}</h2>
        <p>{{ $report['description'] }}</p>
    </div>

    <footer class="report-card-footer">
        @if ($report['updatedAt'])
            <time datetime="{{ $report['updatedAtIso'] }}" title="Latest source activity: {{ $report['updatedAtTitle'] }}">
                {{ $report['updatedLabel'] }}
            </time>
        @else
            <span>{{ $report['updatedLabel'] }}</span>
        @endif

        <div class="report-card-actions" aria-label="Actions for {{ $report['title'] }}">
            <button
                type="button"
                data-report-view
                data-report-title="{{ $report['title'] }}"
                data-report-description="{{ $report['description'] }}"
                data-report-category-label="{{ Str::headline($report['category']) }}"
                data-report-source="{{ $report['sourceLabel'] }}"
                data-report-record-count="{{ $report['sourceRecordCount'] }}"
                data-report-updated-label="{{ $report['updatedLabel'] }}"
                data-report-sensitive="{{ $report['sensitive'] ? 'true' : 'false' }}"
                aria-label="View {{ $report['title'] }} catalog preview"
                title="View report preview"
            >
                <x-admin.icon name="file-text" :size="17" />
            </button>
            <button
                class="is-unavailable"
                type="button"
                data-report-unavailable-action="Export"
                data-report-title="{{ $report['title'] }}"
                aria-label="Export {{ $report['title'] }} (unavailable)"
                title="Export report — backend not configured"
            >
                <x-admin.icon name="file-spreadsheet" :size="17" />
            </button>
            <button
                class="is-unavailable"
                type="button"
                data-report-unavailable-action="Print"
                data-report-title="{{ $report['title'] }}"
                aria-label="Print {{ $report['title'] }} (unavailable)"
                title="Print report — print view not configured"
            >
                <x-admin.icon name="printer" :size="17" />
            </button>
            <button
                class="report-download-action is-unavailable"
                type="button"
                data-report-unavailable-action="Download"
                data-report-title="{{ $report['title'] }}"
                aria-label="Download {{ $report['title'] }} (unavailable)"
                title="Download report — no protected file exists"
            >
                <x-admin.icon name="download" :size="18" />
            </button>
        </div>
    </footer>
</article>
