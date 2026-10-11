<?php

declare(strict_types=1);

namespace Slendie\Model\Relations;

use Slendie\Model\Builder;
use Slendie\Model\Model;

/**
 * Comportamento comum a `hasOne` e `hasMany`: a chave estrangeira está no model relacionado.
 */
abstract class HasOneOrMany extends Relation
{
    public function __construct(Builder $query, Model $parent, protected string $foreignKey, protected string $localKey)
    {
        parent::__construct($query, $parent);
    }

    protected function addConstraints(): void
    {
        $key = $this->parent->getAttribute($this->localKey);
        $column = $this->query->getModel()->qualifyColumn($this->foreignKey);

        $this->query->where($column, '=', $key)->whereNotNull($column);
    }

    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function make(array $attributes = []): Model
    {
        return $this->setForeignAttribute($this->query->getModel()->newInstance($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes = []): Model
    {
        $model = $this->make($attributes);
        $model->save();

        return $model;
    }

    public function save(Model $model): Model|false
    {
        $this->setForeignAttribute($model);

        return $model->save() ? $model : false;
    }

    protected function setForeignAttribute(Model $model): Model
    {
        return $model->setAttribute($this->foreignKey, $this->parent->getAttribute($this->localKey));
    }
}
