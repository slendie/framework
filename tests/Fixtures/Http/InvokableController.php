<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Http;

class InvokableController
{
    public function __invoke(): string
    {
        return 'invoked';
    }
}
