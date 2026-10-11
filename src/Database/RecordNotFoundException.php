<?php

declare(strict_types=1);

namespace Slendie\Database;

use Slendie\Exceptions\SlendieException;

/**
 * Lançada por `firstOrFail()` quando a query não devolve linhas.
 */
class RecordNotFoundException extends SlendieException
{
    public function __construct(string $message = 'Nenhum registo encontrado.', protected ?string $table = null)
    {
        parent::__construct($message);
    }

    public function getTable(): ?string
    {
        return $this->table;
    }
}
