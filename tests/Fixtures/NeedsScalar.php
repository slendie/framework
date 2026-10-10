<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures;

final class NeedsScalar
{
    public function __construct(public readonly string $name)
    {
    }
}
