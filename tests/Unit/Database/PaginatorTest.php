<?php

declare(strict_types=1);

use Slendie\Database\Pagination\AbstractPaginator;
use Slendie\Database\Pagination\Paginator;
use Slendie\Database\Pagination\SimplePaginator;

afterEach(fn () => AbstractPaginator::flushResolvers());

function items(int $n): array
{
    return array_map(fn (int $i): array => ['id' => $i], range(1, $n));
}

it('calcula páginas, from/to e urls', function (): void {
    $p = new Paginator(items(15), 95, 15, 2, ['path' => '/leads', 'query' => ['q' => 'ana', 'page' => '9']]);

    expect($p->total())->toBe(95)
        ->and($p->perPage())->toBe(15)
        ->and($p->currentPage())->toBe(2)
        ->and($p->lastPage())->toBe(7)
        ->and($p->hasMorePages())->toBeTrue()
        ->and($p->onFirstPage())->toBeFalse()
        ->and($p->url(3))->toBe('/leads?q=ana&page=3')
        ->and($p->nextPageUrl())->toBe('/leads?q=ana&page=3')
        ->and($p->previousPageUrl())->toBe('/leads?q=ana&page=1')
        ->and($p->toArray())->toMatchArray(['total' => 95, 'per_page' => 15, 'current_page' => 2, 'last_page' => 7, 'from' => 16, 'to' => 30])
        ->and(count($p->toArray()['data']))->toBe(15);
});

it('última página e página vazia', function (): void {
    $last = new Paginator(items(5), 95, 15, 7, ['path' => '/x']);
    $empty = new Paginator([], 0, 15, 1, ['path' => '/x']);

    expect($last->hasMorePages())->toBeFalse()
        ->and($last->nextPageUrl())->toBeNull()
        ->and($last->toArray())->toMatchArray(['from' => 91, 'to' => 95])
        ->and($empty->lastPage())->toBe(1)
        ->and($empty->isEmpty())->toBeTrue()
        ->and($empty->toArray())->toMatchArray(['from' => null, 'to' => null, 'total' => 0, 'last_page' => 1])
        ->and($empty->previousPageUrl())->toBeNull();
});

it('appends, withPath, fragment e pageName', function (): void {
    $p = (new Paginator(items(2), 10, 2, 1, ['pageName' => 'p', 'path' => '/a']))
        ->appends(['sort' => 'name', 'p' => 99])
        ->withPath('/leads')
        ->fragment('tabela');

    expect($p->url(2))->toBe('/leads?sort=name&p=2#tabela')
        ->and($p->getPageName())->toBe('p');
});

it('é iterável, contável, indexável e serializa em JSON', function (): void {
    $p = new Paginator(items(3), 3, 15, 1, ['path' => '/x']);

    expect(iterator_to_array($p))->toBe(items(3))
        ->and(count($p))->toBe(3)
        ->and($p[1])->toBe(['id' => 2])
        ->and(isset($p[5]))->toBeFalse()
        ->and(json_decode(json_encode($p), true))->toMatchArray(['total' => 3, 'last_page' => 1])
        ->and($p->items())->toBe(items(3));

    expect(function () use ($p): void {
        $p[0] = 1;
    })->toThrow(LogicException::class);
});

it('elements() resume as páginas com reticências', function (): void {
    $at = fn (int $current, int $total): array => (new Paginator([], $total * 10, 10, $current))->elements();

    expect($at(1, 5))->toBe([1, 2, 3, 4, 5])
        ->and($at(1, 20))->toBe([1, 2, 3, '...', 20])
        ->and($at(10, 20))->toBe([1, '...', 8, 9, 10, 11, 12, '...', 20])
        ->and($at(20, 20))->toBe([1, '...', 18, 19, 20]);
});

it('SimplePaginator descarta o item a mais e não tem total', function (): void {
    $p = new SimplePaginator(items(11), 10, 2, ['path' => '/s']);

    expect(count($p))->toBe(10)
        ->and($p->hasMorePages())->toBeTrue()
        ->and($p->nextPageUrl())->toBe('/s?page=3')
        ->and($p->previousPageUrl())->toBe('/s?page=1')
        ->and($p->toArray())->not->toHaveKey('total')
        ->and($p->toArray())->toMatchArray(['per_page' => 10, 'current_page' => 2, 'from' => 11, 'to' => 20]);

    $last = new SimplePaginator(items(4), 10, 1, ['path' => '/s']);

    expect($last->hasMorePages())->toBeFalse()->and($last->nextPageUrl())->toBeNull();
});

it('os resolvers fornecem a página, o caminho e a query', function (): void {
    AbstractPaginator::currentPageResolver(fn (string $name): int => $name === 'p' ? 4 : 1);
    AbstractPaginator::currentPathResolver(fn (): string => '/leads');
    AbstractPaginator::queryStringResolver(fn (): array => ['q' => 'x']);

    expect(Paginator::resolveCurrentPage('p'))->toBe(4)
        ->and(Paginator::resolveCurrentPage())->toBe(1)
        ->and(Paginator::resolveCurrentPath())->toBe('/leads')
        ->and(Paginator::resolveQueryString())->toBe(['q' => 'x']);
});

it('sem resolvers usa $_GET e REQUEST_URI', function (): void {
    $get = $_GET;
    $uri = $_SERVER['REQUEST_URI'] ?? null;

    $_GET = ['page' => '3', 'q' => 'a'];
    $_SERVER['REQUEST_URI'] = '/leads?page=3&q=a';

    try {
        expect(Paginator::resolveCurrentPage())->toBe(3)
            ->and(Paginator::resolveCurrentPath())->toBe('/leads')
            ->and(Paginator::resolveQueryString())->toBe(['page' => '3', 'q' => 'a']);

        $_GET['page'] = 'abc';
        expect(Paginator::resolveCurrentPage())->toBe(1);
    } finally {
        $_GET = $get;
        $uri === null ? $_SERVER['REQUEST_URI'] = '/' : $_SERVER['REQUEST_URI'] = $uri;
    }
});
