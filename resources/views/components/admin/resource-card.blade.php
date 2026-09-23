@props(['resource'])

<article
    class="resource-card"
    data-resource-card
    data-resource-id="{{ $resource['id'] }}"
    data-resource-search="{{ $resource['searchText'] }}"
    data-resource-category="{{ $resource['category'] }}"
    data-resource-type="{{ $resource['type'] }}"
    data-resource-duration="{{ $resource['durationMinutes'] ?? '' }}"
    data-resource-bookmarked="{{ $resource['bookmarked'] ? 'true' : 'false' }}"
>
    <div class="resource-card-visual resource-tone-{{ $resource['tone'] }}">
        <span class="resource-type-badge">{{ $resource['typeLabel'] }}</span>

        <button
            class="resource-bookmark {{ $resource['bookmarked'] ? 'is-bookmarked' : '' }}"
            type="button"
            aria-pressed="{{ $resource['bookmarked'] ? 'true' : 'false' }}"
            aria-label="{{ $resource['bookmarked'] ? 'Remove bookmark from' : 'Bookmark' }} {{ $resource['title'] }}"
            data-resource-bookmark
            data-save-url="{{ $resource['saveUrl'] }}"
            data-unsave-url="{{ $resource['unsaveUrl'] }}"
        >
            <x-admin.icon name="bookmark" :size="19" />
        </button>

        <span class="resource-visual-icon" aria-hidden="true">
            <x-admin.icon :name="$resource['icon']" :size="48" :stroke-width="1.55" />
        </span>
    </div>

    <div class="resource-card-body">
        <span class="resource-category-label">{{ $resource['categoryLabel'] }}</span>
        <h2>{{ $resource['title'] }}</h2>
        <footer>
            <span>{{ $resource['duration'] }}</span>
            <a href="{{ $resource['detailUrl'] }}" aria-label="Open {{ $resource['title'] }}">
                Open <span aria-hidden="true">→</span>
            </a>
        </footer>
    </div>
</article>
