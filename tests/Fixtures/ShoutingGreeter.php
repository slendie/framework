<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures;

final class ShoutingGreeter implements GreeterContract
{
    public function __construct(private GreeterContract $inner)
    {
    }

    public function greet(string $name): string
    {
        return mb_strtoupper($this->inner->greet($name));
    }
}
