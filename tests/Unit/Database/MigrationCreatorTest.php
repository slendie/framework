<?php

declare(strict_types=1);

use Slendie\Database\Migrations\MigrationCreator;
use Slendie\Tests\Fixtures\Database\MigrationHelpers as M;

afterEach(fn () => M::cleanup());

it('create_leads_table → stub create com a tabela inferida', function (): void {
    $dir = M::dir();
    $file = (new MigrationCreator())->create('create_leads_table', $dir);

    expect(basename($file))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_leads_table\.php$/')
        ->and(dirname($file))->toBe($dir);

    $code = file_get_contents($file);

    expect($code)
        ->toContain("Schema::create('leads', function (Blueprint \$table): void {")
        ->toContain("Schema::dropIfExists('leads');")
        ->toContain('$table->id();')
        ->toContain('$table->timestamps();')
        ->not->toContain('{{');

    // o ficheiro gerado é PHP válido e devolve uma migração
    expect(M::migrator([$dir])->resolve($file))->toBeInstanceOf(Slendie\Database\Migrations\Migration::class);
});

it('add_phone_to_leads_table → stub update', function (): void {
    $file = (new MigrationCreator())->create('add_phone_to_leads_table', M::dir());
    $code = file_get_contents($file);

    expect($code)->toContain("Schema::table('leads', function (Blueprint \$table): void {")
        ->and(substr_count($code, "Schema::table('leads'"))->toBe(2)
        ->and($code)->not->toContain('Schema::create');
});

it('infere a tabela de vários padrões de nome', function (string $name, ?string $table, bool $create): void {
    expect((new MigrationCreator())->guessTable($name))->toBe([$table, $create]);
})->with([
    ['create_leads_table', 'leads', true],
    ['create_lead_notes_table', 'lead_notes', true],
    ['add_phone_to_leads_table', 'leads', false],
    ['remove_phone_from_leads_table', 'leads', false],
    ['change_status_in_leads_table', 'leads', false],
    ['update_leads_table', 'leads', false],
    ['seed_default_settings', null, false],
]);

it('sem tabela inferível usa o stub em branco', function (): void {
    $code = file_get_contents((new MigrationCreator())->create('seed_default_settings', M::dir()));

    expect($code)->toContain('public function up(): void')
        ->and($code)->not->toContain('Schema::');
});

it('o nome e a tabela explícitos têm prioridade sobre a inferência', function (): void {
    $creator = new MigrationCreator();

    $created = file_get_contents($creator->create('make_things', M::dir(), 'things', true));
    $updated = file_get_contents($creator->create('create_other_table', M::dir(), 'things', false));

    expect($created)->toContain("Schema::create('things'")
        ->and($updated)->toContain("Schema::table('things'");
});

it('converte CamelCase para snake_case', function (): void {
    expect(basename((new MigrationCreator())->create('CreateLeadsTable', M::dir())))->toEndWith('_create_leads_table.php');
});

it('cria a pasta se não existir', function (): void {
    $dir = M::dir().'/nested/migrations';
    $file = (new MigrationCreator())->create('create_x_table', $dir);

    expect(is_file($file))->toBeTrue();

    @unlink($file);
    @rmdir($dir);
    @rmdir(dirname($dir));
});

it('recusa nomes duplicados', function (): void {
    $dir = M::dir();
    $creator = new MigrationCreator();
    $creator->create('create_leads_table', $dir);

    expect(fn () => $creator->create('create_leads_table', $dir))->toThrow(InvalidArgumentException::class, 'Já existe');
    // outro nome que apenas termina igual não conta como duplicado
    expect(is_file($creator->create('create_big_leads_table', $dir)))->toBeTrue();
});

it('recusa nomes e tabelas que não são identificadores (nada de código injectado no ficheiro)', function (): void {
    $creator = new MigrationCreator();

    expect(fn () => $creator->create("x'); system('ls'); //", M::dir()))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $creator->create('create_ok_table', M::dir(), "leads'); system('ls'); //", true))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $creator->create('../evil', M::dir()))->toThrow(InvalidArgumentException::class);
});

it('stub em falta lança RuntimeException', function (): void {
    expect(fn () => (new MigrationCreator(M::dir()))->create('create_x_table', M::dir()))->toThrow(RuntimeException::class, 'Stub');
});
