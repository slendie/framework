<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Model;

use Slendie\Model\HasUuids;
use Slendie\Model\Model;

class Token extends Model
{
    use HasUuids;

    protected array $fillable = ['label'];
}
