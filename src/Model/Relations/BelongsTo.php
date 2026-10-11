<?php

declare(strict_types=1);

namespace Slendie\Model\Relations;

use Slendie\Model\Builder;
use Slendie\Model\Model;

/**
 * A chave estrangeira está no model pai (`$comment->post_id`); o dono é o model relacionado.
 */
class BelongsTo extends Relation
{
    public function __construct(Builder $query, Model $child, protected string $foreignKey, protected string $ownerKey)
    {
        parent::__construct($query, $child);
    }

    protected function addConstraints(): void
    {
        $this->query->where($this->query->getModel()->qualifyColumn($this->ownerKey), '=', $this->parent->getAttribute($this->foreignKey));
    }

    public function getResults(): ?Model
    {
        if ($this->parent->getAttribute($this->foreignKey) === null) {
            return null;
        }

        return $this->query->first();
    }

    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    public function associate(Model $owner): Model
    {
        $this->parent->setAttribute($this->foreignKey, $owner->getAttribute($this->ownerKey));
        $this->syncLoadedRelations($owner);

        return $this->parent;
    }

    public function dissociate(): Model
    {
        $this->parent->setAttribute($this->foreignKey, null);
        $this->syncLoadedRelations(null);

        return $this->parent;
    }

    /**
     * Actualiza as relações já carregadas do mesmo tipo do dono (o nome da relação não é conhecido aqui).
     */
    protected function syncLoadedRelations(?Model $owner): void
    {
        foreach ($this->parent->getRelations() as $name => $value) {
            if ($value === null || ($value instanceof Model && $value::class === $this->query->getModel()::class)) {
                $this->parent->setRelation($name, $owner);
            }
        }
    }
}
