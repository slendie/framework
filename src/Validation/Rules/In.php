<?php

declare(strict_types=1);

namespace Slendie\Validation\Rules;

use Stringable;

/**
 * `Rule::in(['a', 'b'])` → `in:"a","b"`.
 */
class In implements Stringable
{
    protected string $rule = 'in';

    /**
     * @param  list<string>  $values
     */
    public function __construct(protected array $values)
    {
    }

    public function __toString(): string
    {
        return $this->rule.':'.implode(',', array_map(
            static fn (string $value): string => '"'.str_replace('"', '""', $value).'"',
            $this->values,
        ));
    }
}
