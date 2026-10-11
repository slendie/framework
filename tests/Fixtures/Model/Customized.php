<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Model;

use Slendie\Model\Model;

class Customized extends Model
{
    protected ?string $table = 'my_table';
}
