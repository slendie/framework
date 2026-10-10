<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures;

final class Invokable
{
    public function __invoke(Clock $clock, string $suffix = '!'): string
    {
        return $clock->now().$suffix;
    }
}
