<?php

declare(strict_types=1);

namespace Slendie\Database\Pagination;

/**
 * Paginador sem total: só sabe se há página seguinte (recebe `perPage + 1` itens e descarta o extra).
 */
class SimplePaginator extends AbstractPaginator
{
    protected bool $hasMore;

    /**
     * @param  list<mixed>  $items  até `perPage + 1` itens
     * @param  array{path?: string, pageName?: string, query?: array<string, mixed>, fragment?: string|null}  $options
     */
    public function __construct(array $items, int $perPage, int $currentPage, array $options = [])
    {
        $perPage = max(1, $perPage);
        $this->hasMore = count($items) > $perPage;

        parent::__construct(array_slice($items, 0, $perPage), $perPage, max(1, $currentPage), $options);
    }

    public function hasMorePages(): bool
    {
        return $this->hasMore;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'per_page' => $this->perPage,
            'current_page' => $this->currentPage,
            'from' => $this->firstItem(),
            'to' => $this->lastItem(),
            'next_page_url' => $this->nextPageUrl(),
            'prev_page_url' => $this->previousPageUrl(),
        ];
    }
}
