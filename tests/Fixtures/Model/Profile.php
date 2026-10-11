<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Model;

use Slendie\Model\Model;

class Profile extends Model
{
    protected array $guarded = [];

    protected bool $timestamps = false;
}
