<?php

declare(strict_types=1);

namespace Slendie\Model\Relations;

use Slendie\Model\Model;

class HasOne extends HasOneOrMany
{
    public function getResults(): ?Model
    {
        return $this->query->first();
    }
}
