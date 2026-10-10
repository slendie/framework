<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Http;

use Slendie\Contracts\UrlRoutable;

class Article implements UrlRoutable
{
    public function __construct(public int $id = 0)
    {
    }

    public function resolveRouteBinding(mixed $value, ?string $field = null): ?static
    {
        return (int) $value === 42 ? new static(42) : null;
    }
}
