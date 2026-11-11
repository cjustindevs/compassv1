@php($noun = $noun ?? 'results')
@if ($paginator->total() > 0)
<nav class="ui-pagination" aria-label="Pagination">
    <p class="ui-pagination-summary">Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }} {{ $noun }}</p>
    @if ($paginator->hasPages())
        <ul class="ui-pagination-pills">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="ui-page ui-page--disabled" aria-disabled="true" aria-label="Previous page"><x-ui-icon name="chevron-left" /></span>
                @else
                    <a class="ui-page" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page"><x-ui-icon name="chevron-left" /></a>
                @endif
            </li>
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="ui-page ui-page--gap" aria-hidden="true">{{ $element }}</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span class="ui-page ui-page--active" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="ui-page" href="{{ $url }}" aria-label="Go to page {{ $page }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
            <li>
                @if ($paginator->hasMorePages())
                    <a class="ui-page" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page"><x-ui-icon name="chevron-right" /></a>
                @else
                    <span class="ui-page ui-page--disabled" aria-disabled="true" aria-label="Next page"><x-ui-icon name="chevron-right" /></span>
                @endif
            </li>
        </ul>
    @endif
</nav>
@endif
