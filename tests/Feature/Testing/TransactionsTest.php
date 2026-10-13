<?php

declare(strict_types=1);

use Slendie\Tests\Fixtures\Testing\Probe;
use Slendie\Tests\Fixtures\Testing\TransactionalTestCase;

uses(TransactionalTestCase::class);

it('DatabaseTransactions abre uma transacção em cada ligação indicada e desfá-la no fim', function (): void {
    $connection = $this->db();

    expect($connection->transactionLevel())->toBe(1);

    $connection->getPdo()->exec('create table probe (x integer)');
    $connection->getPdo()->exec('insert into probe values (1)');

    $this->beforeApplicationDestroyed(static function () use ($connection): void {
        Probe::$seen['level'] = $connection->transactionLevel();
        Probe::$seen['table'] = $connection->scalar("select count(*) from sqlite_master where name = 'probe'");
    });
});

it('...e o rollback apagou o que o teste anterior fez', function (): void {
    expect(Probe::$seen['level'])->toBe(0)
        ->and((int) Probe::$seen['table'])->toBe(0);
});
