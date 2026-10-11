<?php

declare(strict_types=1);

namespace Slendie\Model;

use Slendie\Database\RecordNotFoundException;

/**
 * Lançada por `findOrFail()`/`firstOrFail()` e pelo route-model binding. O `Handler` converte-a em 404.
 */
class ModelNotFoundException extends RecordNotFoundException
{
    /** @var class-string<Model>|null */
    protected ?string $model = null;

    /** @var list<int|string> */
    protected array $ids = [];

    /**
     * @param  class-string  $model
     * @param  array<array-key, mixed>  $ids
     */
    public function setModel(string $model, array $ids = []): static
    {
        /** @var class-string<Model> $model */
        $this->model = $model;
        $this->ids = array_values(array_filter($ids, static fn (mixed $id): bool => is_int($id) || is_string($id)));

        $this->message = 'Nenhum resultado para o model ['.$model.']'
            .($this->ids !== [] ? ' '.implode(', ', array_map(strval(...), $this->ids)) : '').'.';

        return $this;
    }

    /** @return class-string<Model>|null */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /** @return list<int|string> */
    public function getIds(): array
    {
        return $this->ids;
    }
}
