<?php

declare(strict_types=1);

use Slendie\Container\Container;
use Slendie\Database\Connection;
use Slendie\Database\Schema\Blueprint;
use Slendie\Database\Seeders\Seeder;
use Slendie\Tests\Fixtures\Database\DbHelpers as Db;

/*
| Seeders de teste: o log mostra a ordem e a injecção de dependências.
*/

final class SeederLog
{
    /** @var list<string> */
    public static array $entries = [];
}

class FirstSeeder extends Seeder
{
    public function run(Connection $db): void
    {
        SeederLog::$entries[] = 'first';
        $db->table('seeded')->insert(['name' => 'first']);
    }
}

class SecondSeeder extends Seeder
{
    public function run(Connection $db, string $name = 'second'): void
    {
        SeederLog::$entries[] = $name;
        $db->table('seeded')->insert(['name' => $name]);
    }
}

class RootSeeder extends Seeder
{
    public function run(): void
    {
        SeederLog::$entries[] = 'root';
        $this->call([FirstSeeder::class, SecondSeeder::class]);
        $this->call(NestedSeeder::class);
        $this->callWith(SecondSeeder::class, ['name' => 'custom']);
    }
}

class NestedSeeder extends Seeder
{
    public function run(): void
    {
        SeederLog::$entries[] = 'nested';
        $this->call(FirstSeeder::class);
    }
}

class NoRunSeeder extends Seeder
{
}

beforeEach(function (): void {
    SeederLog::$entries = [];

    $this->db = Db::live('sqlite');
    $this->db->getSchemaBuilder()->create('seeded', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    $this->container = new Container();
    $this->container->instance(Connection::class, $this->db);
    Container::setInstance($this->container);
});

it('call() encadeia seeders por ordem, com injecção de dependências no run()', function (): void {
    (new RootSeeder($this->container))->__invoke();

    expect(SeederLog::$entries)->toBe(['root', 'first', 'second', 'nested', 'first', 'custom'])
        ->and($this->db->table('seeded')->pluck('name'))->toBe(['first', 'second', 'first', 'custom']);
});

it('usa o container global se não receber nenhum', function (): void {
    (new RootSeeder())->__invoke();

    expect(array_slice(SeederLog::$entries, 0, 2))->toBe(['root', 'first']);
});

it('setContainer() troca o container e call() devolve o próprio seeder', function (): void {
    $other = new Container();
    $other->instance(Connection::class, $this->db);

    $seeder = (new FirstSeeder())->setContainer($other);

    expect($seeder->call(SecondSeeder::class))->toBe($seeder)
        ->and(SeederLog::$entries)->toBe(['second']);
});

it('recusa classes que não são seeders e seeders sem run()', function (): void {
    $seeder = new RootSeeder($this->container);

    expect(fn () => $seeder->call(stdClass::class))->toThrow(InvalidArgumentException::class, 'não é um seeder')
        ->and(fn () => $seeder->call(NoRunSeeder::class))->toThrow(LogicException::class, 'run()');
});
