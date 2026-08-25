@if ($paginator->hasPages())
    <nav class="y-pager" role="navigation" aria-label="{{ __('Pagination') }}">
        <div class="y-pager__pages">
            @if ($paginator->onFirstPage())
                <span class="y-page is-disabled">←</span>
            @else
                <a class="y-page" href="{{ $paginator->previousPageUrl() }}" rel="prev">←</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="y-page is-dots">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="y-page is-active">{{ $page }}</span>
                        @else
                            <a class="y-page" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="y-page" href="{{ $paginator->nextPageUrl() }}" rel="next">→</a>
            @else
                <span class="y-page is-disabled">→</span>
            @endif
        </div>

        {{-- Jump to a specific page (keeps the current filters / sort). --}}
        <form method="GET" action="{{ $paginator->path() }}" class="y-pager-jump">
            @foreach (request()->except('page') as $key => $value)
                @if (is_array($value))
                    @foreach ($value as $v)<input type="hidden" name="{{ $key }}[]" value="{{ $v }}">@endforeach
                @else
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <span>{{ __('Page') }}</span>
            <input type="number" name="page" min="1" max="{{ $paginator->lastPage() }}"
                   value="{{ $paginator->currentPage() }}" class="y-pager-jump__input" aria-label="{{ __('Go to page') }}">
            <span>{{ __('of') }} {{ $paginator->lastPage() }}</span>
            <button type="submit" class="y-btn y-btn__ghost y-btn__xs">{{ __('Go') }}</button>
        </form>
    </nav>
@endif
