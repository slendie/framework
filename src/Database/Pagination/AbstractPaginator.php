<?php

declare(strict_types=1);

namespace Slendie\Database\Pagination;

use ArrayAccess;
use ArrayIterator;
use Closure;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use LogicException;
use Slendie\Contracts\Arrayable;
use Traversable;

/**
 * Base dos paginadores: itens da página, URLs (com a query string actual preservada) e `links()`.
 *
 * @implements IteratorAggregate<int, mixed>
 * @implements ArrayAccess<int, mixed>
 */
abstract class AbstractPaginator implements Arrayable, ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /** @var (Closure(string): int)|null */
    protected static ?Closure $currentPageResolver = null;

    /** @var (Closure(): string)|null */
    protected static ?Closure $currentPathResolver = null;

    /** @var (Closure(): array<string, mixed>)|null */
    protected static ?Closure $queryStringResolver = null;

    protected string $path = '/';

    protected string $pageName = 'page';

    /** @var array<string, mixed> */
    protected array $query = [];

    protected ?string $fragment = null;

    /**
     * @param  list<mixed>  $items
     * @param  array{path?: string, pageName?: string, query?: array<string, mixed>, fragment?: string|null}  $options
     */
    public function __construct(protected array $items, protected int $perPage, protected int $currentPage, array $options = [])
    {
        $this->path = $options['path'] ?? $this->path;
        $this->pageName = $options['pageName'] ?? $this->pageName;
        $this->query = $options['query'] ?? [];
        $this->fragment = $options['fragment'] ?? null;

        unset($this->query[$this->pageName]);
    }

    // -------------------------------------------------------------------------
    // Resolvers (a DatabaseServiceProvider liga-os ao Request)
    // -------------------------------------------------------------------------

    /**
     * @param  Closure(string): int  $resolver
     */
    public static function currentPageResolver(Closure $resolver): void
    {
        static::$currentPageResolver = $resolver;
    }

    /**
     * @param  Closure(): string  $resolver
     */
    public static function currentPathResolver(Closure $resolver): void
    {
        static::$currentPathResolver = $resolver;
    }

    /**
     * @param  Closure(): array<string, mixed>  $resolver
     */
    public static function queryStringResolver(Closure $resolver): void
    {
        static::$queryStringResolver = $resolver;
    }

    public static function flushResolvers(): void
    {
        static::$currentPageResolver = static::$currentPathResolver = static::$queryStringResolver = null;
    }

    public static function resolveCurrentPage(string $pageName = 'page'): int
    {
        if (static::$currentPageResolver !== null) {
            return max(1, (static::$currentPageResolver)($pageName));
        }

        $page = $_GET[$pageName] ?? 1;

        return is_numeric($page) ? max(1, (int) $page) : 1;
    }

    public static function resolveCurrentPath(): string
    {
        if (static::$currentPathResolver !== null) {
            return (static::$currentPathResolver)();
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url(is_string($uri) ? $uri : '/', PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolveQueryString(): array
    {
        return static::$queryStringResolver !== null ? (static::$queryStringResolver)() : $_GET;
    }

    // -------------------------------------------------------------------------
    // Itens
    // -------------------------------------------------------------------------

    /**
     * @return list<mixed>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function isNotEmpty(): bool
    {
        return $this->items !== [];
    }

    /** Número (1-based) do primeiro item da página; null se vazia. */
    public function firstItem(): ?int
    {
        return $this->items === [] ? null : ($this->currentPage - 1) * $this->perPage + 1;
    }

    /** Número (1-based) do último item da página; null se vazia. */
    public function lastItem(): ?int
    {
        return $this->items === [] ? null : (($this->currentPage - 1) * $this->perPage) + count($this->items);
    }

    abstract public function hasMorePages(): bool;

    public function onFirstPage(): bool
    {
        return $this->currentPage <= 1;
    }

    // -------------------------------------------------------------------------
    // URLs
    // -------------------------------------------------------------------------

    public function url(int $page): string
    {
        $query = [...$this->query, $this->pageName => max(1, $page)];

        return $this->path.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986).($this->fragment !== null ? '#'.$this->fragment : '');
    }

    public function nextPageUrl(): ?string
    {
        return $this->hasMorePages() ? $this->url($this->currentPage + 1) : null;
    }

    public function previousPageUrl(): ?string
    {
        return $this->currentPage > 1 ? $this->url($this->currentPage - 1) : null;
    }

    public function withPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Acrescenta parâmetros a todos os URLs (filtros, ordenação...).
     *
     * @param  array<string, mixed>  $query
     */
    public function appends(array $query): static
    {
        foreach ($query as $key => $value) {
            if ($key !== $this->pageName) {
                $this->query[$key] = $value;
            }
        }

        return $this;
    }

    public function fragment(?string $fragment): static
    {
        $this->fragment = $fragment;

        return $this;
    }

    public function getPageName(): string
    {
        return $this->pageName;
    }

    /**
     * Renderiza os links com uma view Blade (`pagination::default`, `pagination::simple` ou a tua).
     */
    public function links(string $view = 'pagination::default'): string
    {
        /** @var \Slendie\View\Factory $factory */
        $factory = app('view');

        return $factory->render($view, ['paginator' => $this]);
    }

    // -------------------------------------------------------------------------
    // Contratos PHP
    // -------------------------------------------------------------------------

    public function count(): int
    {
        return count($this->items);
    }

    /** @return Traversable<int, mixed> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Um paginador é só de leitura.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Um paginador é só de leitura.');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  int  $flags
     */
    public function toJson(int $flags = 0): string
    {
        return (string) json_encode($this->jsonSerialize(), $flags);
    }
}
