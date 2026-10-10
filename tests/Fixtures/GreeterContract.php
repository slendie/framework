<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures;

interface GreeterContract
{
    public function greet(string $name): string;
}
