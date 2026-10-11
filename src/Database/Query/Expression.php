<?php

declare(strict_types=1);

namespace Slendie\Database\Query;

use Stringable;

/**
 * Fragmento SQL cru, inserido tal como está (`DB::raw('COUNT(*)')`).
 *
 * Nunca passes input do utilizador para aqui; para valores usa bindings (`selectRaw($sql, $bindings)`).
 */
final class Expression implements Stringable
{
    public function __construct(private readonly string|int|float $value)
    {
    }

    public function getValue(): string|int|float
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
