<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Database;

enum Status: string
{
    case Open = 'open';
    case Won = 'won';
}
