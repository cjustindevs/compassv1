<x-admin.layout
    title="Audit Logs"
    page-title="Audit Logs"
    page-subtitle="Immutable privileged activity"
    active-nav="audit-logs"
    search-placeholder="Search sessions, helpers, resources, users..."
    :admin="$admin"
>
    @php
        $filtersApplied = $filters['search'] !== ''
            || $filters['actor'] !== 'all'
            || $filters['category'] !== 'all'
            || $filters['date'] !== 'all';
    @endphp

    <section class="audit-logs-page">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('admin.dashboard') }}">COMPASS</a>
            <span aria-hidden="true">/</span>
            <span>Admin</span>
            <span aria-hidden="true">/</span>
            <strong aria-current="page">Audit</strong>
        </nav>

        <header class="audit-page-heading">
            <h1>Audit logs</h1>
            <p>Every privileged action, immutable and searchable.</p>
        </header>

        <section class="audit-card" aria-labelledby="recent-events-title">
            <header class="audit-card-header">
                <div class="audit-card-title">
                    <h2 id="recent-events-title">Recent events</h2>
                </div>

                @unless ($loadFailed)
                    <form class="audit-toolbar" id="audit-filter-form" method="GET" action="{{ route('admin.audit-logs') }}" role="search">
                        <label class="audit-search" for="audit-search-input">
                            <x-admin.icon name="search" :size="17" />
                            <span class="sr-only">Search audit logs by actor, action, or target</span>
                            <input
                                id="audit-search-input"
                                name="q"
                                type="search"
                                value="{{ $filters['search'] }}"
                                placeholder="Search audit logs..."
                                autocomplete="off"
                            >
                            <button type="submit" aria-label="Search audit logs">Search</button>
                        </label>

                        <button class="admin-button admin-button-secondary audit-filter-button" type="button" data-dialog-open="audit-filters-dialog">
                            <x-admin.icon name="filter" :size="17" />
                            Filters
                            @if ($activeFilterCount > 0)
                                <span>{{ $activeFilterCount }}</span>
                            @endif
                        </button>
                    </form>
                @endunless
            </header>

            @if ($loadFailed)
                <div class="audit-load-state" role="alert">
                    <span><x-admin.icon name="alert-circle" :size="27" /></span>
                    <strong>Unable to load audit logs</strong>
                    <p>Please try again.</p>
                    <a class="admin-button admin-button-secondary" href="{{ route('admin.audit-logs') }}">
                        <x-admin.icon name="undo" :size="17" /> Retry
                    </a>
                </div>
            @else
                <x-admin.audit-log-table :logs="$logs" :filters-applied="$filtersApplied" />

                @if ($logs->hasPages())
                    <footer class="audit-pagination-footer">
                        <p>
                            Showing {{ number_format($logs->firstItem()) }}–{{ number_format($logs->lastItem()) }}
                            of {{ number_format($logs->total()) }} events
                        </p>

                        <nav class="audit-pagination" aria-label="Audit log pagination">
                            @if ($logs->onFirstPage())
                                <span aria-disabled="true">Previous</span>
                            @else
                                <a href="{{ $logs->previousPageUrl() }}" rel="prev">Previous</a>
                            @endif

                            @php
                                $pageStart = max(1, $logs->currentPage() - 2);
                                $pageEnd = min($logs->lastPage(), $logs->currentPage() + 2);
                            @endphp

                            @for ($page = $pageStart; $page <= $pageEnd; $page++)
                                @if ($page === $logs->currentPage())
                                    <span class="is-current" aria-current="page">{{ $page }}</span>
                                @else
                                    <a href="{{ $logs->url($page) }}">{{ $page }}</a>
                                @endif
                            @endfor

                            @if ($logs->hasMorePages())
                                <a href="{{ $logs->nextPageUrl() }}" rel="next">Next</a>
                            @else
                                <span aria-disabled="true">Next</span>
                            @endif
                        </nav>
                    </footer>
                @endif
            @endif
        </section>

        @unless ($loadFailed)
            <x-admin.dialog
                id="audit-filters-dialog"
                title="Filter audit logs"
                description="Combine actor, event category, date, and page-size filters."
                size="small"
            >
                <div class="admin-dialog-body audit-filter-fields">
                    <label class="admin-field">
                        <span>Actor type</span>
                        <select name="actor" form="audit-filter-form">
                            @foreach ($actorFilters as $value => $label)
                                <option value="{{ $value }}" @selected($filters['actor'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="admin-field">
                        <span>Event category</span>
                        <select name="category" form="audit-filter-form">
                            @foreach ($categoryFilters as $value => $label)
                                <option value="{{ $value }}" @selected($filters['category'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="admin-field">
                        <span>Date</span>
                        <select name="date" form="audit-filter-form">
                            @foreach ($dateFilters as $value => $label)
                                <option value="{{ $value }}" @selected($filters['date'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="admin-field">
                        <span>Rows per page</span>
                        <select name="per_page" form="audit-filter-form">
                            @foreach ($pageSizes as $pageSize)
                                <option value="{{ $pageSize }}" @selected($filters['perPage'] === $pageSize)>{{ $pageSize }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <footer class="admin-dialog-footer">
                    <a class="admin-button admin-button-secondary" href="{{ route('admin.audit-logs') }}">Clear filters</a>
                    <button class="admin-button admin-button-primary" type="submit" form="audit-filter-form">Apply filters</button>
                </footer>
            </x-admin.dialog>
        @endunless
    </section>
</x-admin.layout>
