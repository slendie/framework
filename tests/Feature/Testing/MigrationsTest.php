<?php

declare(strict_types=1);

use Slendie\Tests\Fixtures\Testing\MigratingTestCase;
use Slendie\Tests\Fixtures\Testing\Probe;

uses(MigratingTestCase::class);

it('DatabaseMigrations migra antes do teste', function (): void {
    $this->assertDatabaseCount('posts', 0);

    $connection = $this->db();

    $this->beforeApplicationDestroyed(static function () use ($connection): void {
        Probe::$seen['posts_after_reset'] = $connection->scalar("select count(*) from sqlite_master where name = 'posts'");
    });
});

it('...e reverte as migrações no fim (rollback real)', function (): void {
    expect((int) Probe::$seen['posts_after_reset'])->toBe(0);
});
