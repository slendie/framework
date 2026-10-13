<?php

declare(strict_types=1);

namespace Slendie\Testing;

use Slendie\Testing\Concerns\ManagesTestDatabase;

/**
 * Só a transacção (desfeita no fim do teste), sem migrar: para bases de dados já migradas (MySQL/PostgreSQL de testes).
 */
trait DatabaseTransactions
{
    use ManagesTestDatabase;
}
