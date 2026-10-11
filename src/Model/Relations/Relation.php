<?php

declare(strict_types=1);

namespace Slendie\Model\Relations;

use Slendie\Model\Builder;
use Slendie\Model\Model;

/**
 * Relação lazy entre dois models. Reencaminha os métodos para o Builder do model relacionado,
 * pelo que `$user->posts()->where('published', 1)->get()` funciona.
 */
abstract class Relation
{
    public function __construct(protected Builder $query, protected Model $parent)
    {
        $this->addConstraints();
    }

    abstract protected function addConstraints(): void;

    /**
     * O resultado da relação: um Model (ou null) em hasOne/belongsTo, uma Collection em hasMany.
     */
    abstract public function getResults(): mixed;

    public function getQuery(): Builder
    {
        return $this->query;
    }

    public function getParent(): Model
    {
        return $this->parent;
    }

    public function getRelated(): Model
    {
        return $this->query->getModel();
    }

    /**
     * @param  array<array-key, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        $result = $this->query->{$method}(...$parameters);

        return $result === $this->query ? $this : $result;
    }
}
