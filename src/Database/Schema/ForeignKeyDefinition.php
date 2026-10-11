<?php

declare(strict_types=1);

namespace Slendie\Database\Schema;

use InvalidArgumentException;

/**
 * Chave estrangeira fluente: `$table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete()`.
 */
class ForeignKeyDefinition extends Command
{
    public const ACTIONS = ['cascade', 'restrict', 'set null', 'no action', 'set default'];

    /** @var list<string> */
    public array $references = [];

    public ?string $on = null;

    public ?string $onDelete = null;

    public ?string $onUpdate = null;

    /**
     * @param  string|list<string>  $columns
     */
    public function references(string|array $columns): static
    {
        $this->references = array_values((array) $columns);

        return $this;
    }

    public function on(string $table): static
    {
        $this->on = $table;

        return $this;
    }

    public function onDelete(string $action): static
    {
        $this->onDelete = $this->action($action);

        return $this;
    }

    public function onUpdate(string $action): static
    {
        $this->onUpdate = $this->action($action);

        return $this;
    }

    public function cascadeOnDelete(): static
    {
        return $this->onDelete('cascade');
    }

    public function cascadeOnUpdate(): static
    {
        return $this->onUpdate('cascade');
    }

    public function restrictOnDelete(): static
    {
        return $this->onDelete('restrict');
    }

    public function restrictOnUpdate(): static
    {
        return $this->onUpdate('restrict');
    }

    public function nullOnDelete(): static
    {
        return $this->onDelete('set null');
    }

    public function noActionOnDelete(): static
    {
        return $this->onDelete('no action');
    }

    protected function action(string $action): string
    {
        $action = strtolower(trim((string) preg_replace('/\s+/', ' ', $action)));

        if (! in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException("Acção de chave estrangeira inválida [{$action}]. Usa: ".implode(', ', self::ACTIONS).'.');
        }

        return $action;
    }
}
