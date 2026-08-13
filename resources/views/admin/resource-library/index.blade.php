<x-admin.layout
    title="Resource Library"
    page-title="Resource Library"
    page-subtitle="Curated support resources"
    active-nav="resource-library"
    search-placeholder="Search sessions, helpers, resources, users..."
    :admin="$admin"
>
    <section class="resource-library-page" data-resource-library>
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('admin.dashboard') }}">COMPASS</a>
            <span aria-hidden="true">/</span>
            <strong aria-current="page">Resources</strong>
        </nav>

        <header class="resource-page-heading">
            <div>
                <h1>Resource library</h1>
                <p>Articles, videos, and exercises curated by the counseling team.</p>
            </div>

            <button
                class="admin-button admin-button-primary resource-bookmarks-button"
                type="button"
                aria-pressed="false"
                data-bookmarks-mode
            >
                <x-admin.icon name="bookmark" :size="18" />
                <span data-bookmarks-button-label>My bookmarks</span>
            </button>
        </header>

        @if ($loadFailed)
            <section class="resource-state-card" role="alert">
                <span><x-admin.icon name="alert-circle" :size="28" /></span>
                <h2>Unable to load resources</h2>
                <p>Please try again.</p>
                <a class="admin-button admin-button-secondary" href="{{ route('admin.resource-library') }}">
                    <x-admin.icon name="undo" :size="17" /> Retry
                </a>
            </section>
        @else
            <div class="resource-toolbar">
                <label class="resource-search" for="resource-library-search">
                    <x-admin.icon name="search" :size="20" />
                    <span class="sr-only">Search resources</span>
                    <input
                        id="resource-library-search"
                        type="search"
                        placeholder="Search resources..."
                        autocomplete="off"
                        data-resource-search
                    >
                </label>

                <button class="admin-button admin-button-secondary resource-filter-button" type="button" data-dialog-open="resource-filters-dialog">
                    <x-admin.icon name="filter" :size="18" />
                    Filters
                    <span class="resource-filter-count" data-resource-filter-count hidden>0</span>
                </button>
            </div>

            <div class="resource-category-row" role="group" aria-label="Filter resources by category">
                @foreach ($categories as $slug => $label)
                    <button
                        class="resource-category-chip {{ $slug === 'all' ? 'is-active' : '' }}"
                        type="button"
                        data-resource-category-chip="{{ $slug }}"
                        aria-pressed="{{ $slug === 'all' ? 'true' : 'false' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <p class="resource-results-status" data-resource-results-status aria-live="polite"></p>

            <div class="resource-grid" data-resource-grid>
                @foreach ($resources as $resource)
                    <x-admin.resource-card :resource="$resource" />
                @endforeach
            </div>

            <section class="resource-state-card resource-empty-state" data-resource-empty hidden>
                <span><x-admin.icon name="book-open" :size="28" /></span>
                <h2 data-resource-empty-title>No resources found</h2>
                <p data-resource-empty-copy>Try changing your search or filters.</p>
                <button class="admin-button admin-button-secondary" type="button" data-clear-resource-filters data-resource-empty-action>Clear filters</button>
            </section>

            <div class="admin-toast" data-resource-toast role="status" aria-live="polite" hidden>
                <span class="admin-toast-icon"><x-admin.icon name="check-circle" :size="19" /></span>
                <span data-resource-toast-message></span>
            </div>

            <x-admin.dialog
                id="resource-filters-dialog"
                title="Filter resources"
                description="Narrow the library by format, category, duration, or bookmarks."
                size="small"
            >
                <form data-resource-filter-form>
                    <div class="admin-dialog-body resource-filter-fields">
                        <label class="admin-field">
                            <span>Resource type</span>
                            <select data-resource-type-filter>
                                @foreach ($resourceTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="admin-field">
                            <span>Category</span>
                            <select data-resource-category-filter>
                                @foreach ($categories as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="admin-field">
                            <span>Duration</span>
                            <select data-resource-duration-filter>
                                <option value="all">Any duration</option>
                                <option value="5">5 minutes or less</option>
                                <option value="10">10 minutes or less</option>
                                <option value="15">15 minutes or less</option>
                            </select>
                        </label>

                        <label class="resource-filter-checkbox">
                            <input type="checkbox" data-resource-bookmarked-filter>
                            <span>Bookmarked resources only</span>
                        </label>
                    </div>
                    <footer class="admin-dialog-footer">
                        <button class="admin-button admin-button-secondary" type="button" data-clear-advanced-filters>Clear</button>
                        <button class="admin-button admin-button-primary" type="submit">Apply filters</button>
                    </footer>
                </form>
            </x-admin.dialog>
        @endif
    </section>
</x-admin.layout>
