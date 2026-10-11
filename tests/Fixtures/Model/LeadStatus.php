<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Model;

enum LeadStatus: string
{
    case New = 'new';
    case Won = 'won';
}
