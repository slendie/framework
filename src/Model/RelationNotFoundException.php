<?php

declare(strict_types=1);

namespace Slendie\Model;

use Slendie\Exceptions\SlendieException;

/**
 * @phpstan-consistent-constructor
 */
class RelationNotFoundException extends SlendieException
{
    public static function make(Model $model, string $relation): static
    {
        return new static('A relação ['.$relation.'] não existe no model ['.$model::class.'].');
    }
}
