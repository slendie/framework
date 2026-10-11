<?php

declare(strict_types=1);

namespace Slendie\Database\Migrations;

use LogicException;
use Slendie\Database\Connection;
use Slendie\Database\DatabaseManager;
use Slendie\Database\Schema\Builder;

/**
 * Base das migrações: `up()` aplica, `down()` reverte.
 *
 * O ficheiro devolve uma classe anónima (`return new class extends Migration {...}`); também são aceites
 * classes nomeadas (`CreateLeadsTable`). Dentro de `up()/down()` usa a facade `Schema` ou `$this->schema()`.
 */
abstract class Migration
{
    /** Ligação em que corre (null = a do Migrator / por omissão). */
    protected ?string $connection = null;

    /** Corre dentro de uma transacção quando o driver o permitir (MySQL faz commit implícito no DDL e ignora-a). */
    public bool $withinTransaction = true;

    protected ?DatabaseManager $manager = null;

    abstract public function up(): void;

    abstract public function down(): void;

    public function getConnection(): ?string
    {
        return $this->connection;
    }

    /** Devolve false para saltar a migração (p. ex. só para um driver); não fica registada. */
    public function shouldRun(): bool
    {
        return true;
    }

    public function setDatabaseManager(DatabaseManager $manager): static
    {
        $this->manager = $manager;

        return $this;
    }

    public function db(): Connection
    {
        if ($this->manager === null) {
            throw new LogicException('A migração não tem acesso à base de dados; corre-a através do Migrator.');
        }

        return $this->manager->connection($this->connection);
    }

    public function schema(): Builder
    {
        return $this->db()->getSchemaBuilder();
    }
}
