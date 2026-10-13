<?php

declare(strict_types=1);

use Slendie\Tests\Fixtures\Http\TagMiddleware;
use Slendie\Tests\Fixtures\Testing\WithoutMiddlewareTestCase;

uses(WithoutMiddlewareTestCase::class);

it('o trait WithoutMiddleware desliga o middleware em todos os testes da classe', function (): void {
    TagMiddleware::$log = [];

    $this->get('/tagged')->assertOk()->assertHeaderMissing('X-Content-Type-Options');

    expect(TagMiddleware::$log)->toBeEmpty();
});
