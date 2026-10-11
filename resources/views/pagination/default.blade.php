@if ($paginator->lastPage() > 1)
<nav class="pagination" role="navigation" aria-label="Paginação">
    <ul>
        @if ($paginator->onFirstPage())
            <li class="disabled" aria-disabled="true"><span>&laquo;</span></li>
        @else
            <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev">&laquo;</a></li>
        @endif

        @foreach ($paginator->elements() as $element)
            @if ($element === '...')
                <li class="disabled" aria-disabled="true"><span>&hellip;</span></li>
            @elseif ($element === $paginator->currentPage())
                <li class="active" aria-current="page"><span>{{ $element }}</span></li>
            @else
                <li><a href="{{ $paginator->url($element) }}">{{ $element }}</a></li>
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <li><a href="{{ $paginator->nextPageUrl() }}" rel="next">&raquo;</a></li>
        @else
            <li class="disabled" aria-disabled="true"><span>&raquo;</span></li>
        @endif
    </ul>
</nav>
@endif
