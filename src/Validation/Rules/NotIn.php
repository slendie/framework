<?php

declare(strict_types=1);

namespace Slendie\Validation\Rules;

/**
 * `Rule::notIn(['a', 'b'])` → `not_in:"a","b"`.
 */
class NotIn extends In
{
    protected string $rule = 'not_in';
}
