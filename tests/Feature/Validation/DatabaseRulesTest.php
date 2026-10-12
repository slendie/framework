<?php

declare(strict_types=1);

use Slendie\Database\DatabaseManager;
use Slendie\Foundation\Application;
use Slendie\Tests\Fixtures\Validation\ValidationHelpers as V;
use Slendie\Validation\PresenceVerifier;
use Slendie\Validation\Rule;

beforeEach(function (): void {
    $manager = new DatabaseManager(new Application(fixturePath('http')), [
        'default' => 'main',
        'connections' => ['main' => ['driver' => 'sqlite', 'database' => ':memory:']],
    ]);

    $db = $manager->connection();
    $db->statement('create table users (id integer primary key autoincrement, email varchar(100), account_id int null, deleted_at datetime null)');
    $db->statement('create table categories (id integer primary key autoincrement, name varchar(100), active int not null default 1)');
    $db->statement("insert into users (email, account_id, deleted_at) values ('ana@x.pt', 1, null), ('rui@x.pt', 2, null), ('old@x.pt', 1, '2020-01-01')");
    $db->statement("insert into categories (name, active) values ('Web', 1), ('Print', 0)");

    $this->factory = V::factory();
    $this->factory->setPresenceVerifier(new PresenceVerifier($manager));
});

it('unique:tabela,coluna', function (): void {
    $check = fn (string $email, string $rule = 'unique:users,email') => $this->factory->make(['email' => $email], ['email' => $rule]);

    expect($check('novo@x.pt')->passes())->toBeTrue()
        ->and($check('ana@x.pt')->passes())->toBeFalse()
        ->and($check('ana@x.pt')->errors()->first('email'))->toBe('O valor de e-mail já está a ser utilizado.')
        // coluna deduzida do nome do campo
        ->and($check('ana@x.pt', 'unique:users')->passes())->toBeFalse()
        ->and($check('novo@x.pt', 'unique:users')->passes())->toBeTrue();
});

it('unique ignora um id (string e fluente)', function (): void {
    $make = fn (mixed $rule) => $this->factory->make(['email' => 'ana@x.pt'], ['email' => [$rule]]);
    $string = $this->factory->make(['email' => 'ana@x.pt'], ['email' => 'unique:users,email,1,id']);

    expect($string->passes())->toBeTrue()
        ->and($this->factory->make(['email' => 'ana@x.pt'], ['email' => 'unique:users,email,2,id'])->passes())->toBeFalse()
        ->and($make(Rule::unique('users')->ignore(1))->passes())->toBeTrue()
        ->and($make(Rule::unique('users')->ignore(2))->passes())->toBeFalse()
        ->and($make(Rule::unique('users', 'email')->ignore(1, 'id'))->passes())->toBeTrue()
        ->and($make(Rule::unique('users')->ignore(new class () {
            public function getKey(): int
            {
                return 1;
            }
        }))->passes())->toBeTrue();
});

it('unique com condições extra (where, whereNot, whereNull, withoutTrashed, string)', function (): void {
    $make = fn (string $email, mixed $rule) => $this->factory->make(['email' => $email], ['email' => [$rule]])->passes();

    expect($make('ana@x.pt', Rule::unique('users')->where('account_id', 2)))->toBeTrue()
        ->and($make('ana@x.pt', Rule::unique('users')->where('account_id', 1)))->toBeFalse()
        ->and($make('ana@x.pt', Rule::unique('users')->whereNot('account_id', 1)))->toBeTrue()
        ->and($make('old@x.pt', Rule::unique('users')))->toBeFalse()
        ->and($make('old@x.pt', Rule::unique('users')->withoutTrashed()))->toBeTrue()
        ->and($make('old@x.pt', Rule::unique('users')->whereNotNull('deleted_at')))->toBeFalse()
        ->and($make('ana@x.pt', Rule::unique('users')->where(fn ($q) => $q->where('account_id', '=', 2))))->toBeTrue()
        ->and($this->factory->make(['email' => 'old@x.pt'], ['email' => 'unique:users,email,NULL,id,deleted_at,NULL'])->passes())->toBeTrue()
        ->and($this->factory->make(['email' => 'ana@x.pt'], ['email' => 'unique:users,email,NULL,id,account_id,1'])->passes())->toBeFalse()
        ->and($this->factory->make(['email' => 'ana@x.pt'], ['email' => 'unique:users,email,NULL,id,account_id,!1'])->passes())->toBeTrue()
        ->and($make('ana@x.pt', Rule::unique('users')->column('email')))->toBeFalse();
});

it('exists:tabela,coluna e Rule::exists', function (): void {
    $make = fn (mixed $value, mixed $rule) => $this->factory->make(['category' => $value], ['category' => [$rule]]);

    expect($make(1, 'exists:categories,id')->passes())->toBeTrue()
        ->and($make(99, 'exists:categories,id')->passes())->toBeFalse()
        ->and($make(99, 'exists:categories,id')->errors()->first('category'))->toBe('O valor escolhido para category é inválido.')
        ->and($make('Web', 'exists:categories,name')->passes())->toBeTrue()
        ->and($make(1, Rule::exists('categories', 'id'))->passes())->toBeTrue()
        ->and($make(2, Rule::exists('categories', 'id')->where('active', 1))->passes())->toBeFalse()
        ->and($make(1, Rule::exists('categories', 'id')->where('active', 1))->passes())->toBeTrue()
        ->and($make([1, 2], Rule::exists('categories', 'id'))->passes())->toBeTrue()
        ->and($make([1, 99], Rule::exists('categories', 'id'))->passes())->toBeFalse()
        ->and($make([1, 99], 'exists:categories,id')->passes())->toBeFalse()
        ->and($make([1, 2], 'exists:categories,id')->passes())->toBeTrue()
        ->and($make([], 'exists:categories,id')->passes())->toBeFalse()
        ->and($make(['x'], Rule::exists('categories', 'id')->column('name'))->passes())->toBeFalse();
});

it('exists com wildcard deduz a coluna do último segmento', function (): void {
    $v = $this->factory->make(['items' => [['name' => 'Web'], ['name' => 'Nada']]], ['items.*.name' => 'exists:categories']);

    expect($v->errors()->keys())->toBe(['items.1.name']);
});

it('sem base de dados registada, exists/unique explicam o que falta', function (): void {
    V::make(['a' => 'x'], ['a' => 'unique:users'])->passes();
})->throws(RuntimeException::class, 'exists/unique precisam de uma base de dados');

it('com a Application, a Factory liga ao DatabaseManager do container', function (): void {
    $app = new Application(fixturePath('http'));
    $app->useStoragePath(sys_get_temp_dir().'/slendie-test-'.bin2hex(random_bytes(6)));
    $app->bootstrapWith([
        Slendie\Foundation\Bootstrap\LoadEnvironment::class,
        Slendie\Foundation\Bootstrap\LoadConfiguration::class,
        Slendie\Foundation\Bootstrap\RegisterProviders::class,
        Slendie\Foundation\Bootstrap\BootProviders::class,
    ]);
    config(['database.default' => 'main', 'database.connections.main' => ['driver' => 'sqlite', 'database' => ':memory:']]);

    $app->make('db')->connection()->statement('create table users (id integer primary key autoincrement, email varchar(100))');
    $app->make('db')->connection()->statement("insert into users (email) values ('ana@x.pt')");

    expect(validator(['email' => 'ana@x.pt'], ['email' => 'unique:users'])->passes())->toBeFalse()
        ->and(Slendie\Support\Facades\Validator::make(['email' => 'novo@x.pt'], ['email' => 'unique:users'])->passes())->toBeTrue();
});
