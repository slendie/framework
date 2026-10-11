<?php

declare(strict_types=1);

namespace Slendie\Model\Relations;

use Slendie\Model\Collection;
use Slendie\Model\Model;

class HasMany extends HasOneOrMany
{
    public function getResults(): Collection
    {
        return $this->query->get();
    }

    /**
     * @param  iterable<Model>  $models
     */
    public function saveMany(iterable $models): Collection
    {
        $saved = [];

        foreach ($models as $model) {
            $this->save($model);
            $saved[] = $model;
        }

        return new Collection($saved);
    }
}
