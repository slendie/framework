<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Model;

use Slendie\Model\Model;
use Slendie\Model\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    protected array $fillable = ['title', 'slug'];
}
