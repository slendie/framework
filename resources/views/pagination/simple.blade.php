@if ($paginator->hasMorePages() || ! $paginator->onFirstPage())
<nav class="pagination" role="navigation" aria-label="Paginação">
    <ul>
        @if ($paginator->onFirstPage())
            <li class="disabled" aria-disabled="true"><span>&laquo; Anterior</span></li>
        @else
            <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev">&laquo; Anterior</a></li>
        @endif

        @if ($paginator->hasMorePages())
            <li><a href="{{ $paginator->nextPageUrl() }}" rel="next">Seguinte &raquo;</a></li>
        @else
            <li class="disabled" aria-disabled="true"><span>Seguinte &raquo;</span></li>
        @endif
    </ul>
</nav>
@endif
