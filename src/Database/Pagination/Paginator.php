<?php

declare(strict_types=1);

namespace Slendie\Database\Pagination;

/**
 * Paginador com total (`COUNT(*)`): sabe o número de páginas.
 */
class Paginator extends AbstractPaginator
{
    /**
     * @param  list<mixed>  $items  os itens da página actual
     * @param  array{path?: string, pageName?: string, query?: array<string, mixed>, fragment?: string|null}  $options
     */
    public function __construct(array $items, protected int $total, int $perPage, int $currentPage, array $options = [])
    {
        parent::__construct($items, max(1, $perPage), max(1, $currentPage), $options);
    }

    public function total(): int
    {
        return $this->total;
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    /**
     * Páginas a mostrar nos links: `[1, '...', 4, 5, 6, '...', 20]`.
     *
     * @return list<int|string>
     */
    public function elements(int $onEachSide = 2): array
    {
        $last = $this->lastPage();

        if ($last <= ($onEachSide * 2) + 5) {
            return range(1, $last);
        }

        $start = max(2, $this->currentPage - $onEachSide);
        $end = min($last - 1, $this->currentPage + $onEachSide);

        $elements = [1];

        if ($start > 2) {
            $elements[] = '...';
        }

        array_push($elements, ...range($start, $end));

        if ($end < $last - 1) {
            $elements[] = '...';
        }

        $elements[] = $last;

        return $elements;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'total' => $this->total,
            'per_page' => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page' => $this->lastPage(),
            'from' => $this->firstItem(),
            'to' => $this->lastItem(),
            'next_page_url' => $this->nextPageUrl(),
            'prev_page_url' => $this->previousPageUrl(),
        ];
    }
}
