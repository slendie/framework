<?php

declare(strict_types=1);

namespace Slendie\Model;

use Slendie\Support\Str;

/**
 * Chave primária UUID (coluna de texto/uuid): gerada automaticamente ao criar o model.
 *
 * @mixin Model
 */
trait HasUuids
{
    public static function bootHasUuids(): void
    {
        static::creating(static function (Model $model): void {
            $key = $model->getKeyName();

            if (($model->getAttributes()[$key] ?? null) === null || $model->getAttributes()[$key] === '') {
                $model->setAttribute($key, Str::uuid());
            }
        });
    }

    public function initializeHasUuids(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }
}
