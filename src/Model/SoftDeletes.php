<?php

declare(strict_types=1);

namespace Slendie\Model;

/**
 * `delete()` marca `deleted_at` em vez de apagar a linha; as queries ignoram as linhas apagadas
 * (`withTrashed()`, `onlyTrashed()`, `withoutTrashed()`). Requer a coluna `deleted_at`.
 *
 * @mixin Model
 */
trait SoftDeletes
{
    protected bool $forceDeleting = false;

    public static function bootSoftDeletes(): void
    {
        static::addGlobalScope('softDeletes', static function (Builder $builder, Model $model): void {
            /** @var Model&self $model */
            $builder->whereNull($model->getQualifiedDeletedAtColumn());
        });
    }

    public function initializeSoftDeletes(): void
    {
        $this->casts[$this->getDeletedAtColumn()] ??= 'datetime';
    }

    public function getDeletedAtColumn(): string
    {
        return defined(static::class.'::DELETED_AT') ? (string) constant(static::class.'::DELETED_AT') : 'deleted_at';
    }

    public function getQualifiedDeletedAtColumn(): string
    {
        return $this->qualifyColumn($this->getDeletedAtColumn());
    }

    public function trashed(): bool
    {
        return $this->getAttribute($this->getDeletedAtColumn()) !== null;
    }

    public function forceDelete(): ?bool
    {
        $this->forceDeleting = true;

        try {
            return $this->delete();
        } finally {
            $this->forceDeleting = false;
        }
    }

    protected function performDeleteOnModel(): void
    {
        if ($this->forceDeleting) {
            $this->newModelQuery()->getQuery()->where($this->getKeyName(), '=', $this->getKey())->delete();
            $this->exists = false;

            return;
        }

        $column = $this->getDeletedAtColumn();
        $time = $this->freshTimestampString();
        $values = [$column => $time];

        if ($this->usesTimestamps()) {
            $values[$this->getUpdatedAtColumn()] = $time;
        }

        $this->newModelQuery()->getQuery()->where($this->getKeyName(), '=', $this->getKey())->update($values);

        foreach ($values as $key => $value) {
            $this->setAttribute($key, $value);
            $this->original[$key] = $this->attributes[$key];
        }
    }

    public function restore(): bool
    {
        if (! $this->fireModelEvent('restoring')) {
            return false;
        }

        $this->setAttribute($this->getDeletedAtColumn(), null);
        $restored = $this->save();

        $this->fireModelEvent('restored');

        return $restored;
    }

    public function scopeWithTrashed(Builder $builder, bool $withTrashed = true): Builder
    {
        return $withTrashed ? $builder->withoutGlobalScope('softDeletes') : $builder;
    }

    public function scopeOnlyTrashed(Builder $builder): Builder
    {
        /** @var Model&self $model */
        $model = $builder->getModel();

        return $builder->withoutGlobalScope('softDeletes')->whereNotNull($model->getQualifiedDeletedAtColumn());
    }

    public function scopeWithoutTrashed(Builder $builder): Builder
    {
        /** @var Model&self $model */
        $model = $builder->getModel();

        return $builder->withoutGlobalScope('softDeletes')->whereNull($model->getQualifiedDeletedAtColumn());
    }
}
