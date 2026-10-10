<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures;

final class Greeter implements GreeterContract
{
    public function greet(string $name): string
    {
        return "Olá, {$name}!";
    }
}
