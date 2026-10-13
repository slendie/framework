<?php

declare(strict_types=1);

use Slendie\Database\DatabaseManager;
use Slendie\Testing\RefreshDatabase;
use Slendie\Tests\Fixtures\Model\Post;
use Slendie\Tests\Fixtures\Testing\Fails;
use Slendie\Tests\Fixtures\Testing\FixtureTestCase;
use Slendie\Tests\Fixtures\Testing\PostSeeder;
use Slendie\Tests\Fixtures\Testing\Probe;

uses(FixtureTestCase::class, RefreshDatabase::class);

it('RefreshDatabase: o primeiro teste escreve na base de dados', function (): void {
    Probe::$seen['connection'] = spl_object_id($this->db());

    $this->post('/posts', ['title' => 'Olá'])->assertRedirect('/target');

    $this->assertDatabaseHas('posts', ['title' => 'Olá', 'slug' => 'ola'])
        ->assertDatabaseCount('posts', 1);

    expect($this->db()->transactionLevel())->toBe(1);
});

it('RefreshDatabase: o teste seguinte não vê nada e reutiliza a ligação migrada', function (): void {
    $this->assertDatabaseEmpty('posts')
        ->assertDatabaseMissing('posts', ['title' => 'Olá']);

    expect(spl_object_id($this->db()))->toBe(Probe::$seen['connection']);
});

it('assertDatabaseHas/Missing usam null como IS NULL e mostram o conteúdo da tabela na falha', function (): void {
    Post::create(['title' => 'Com slug', 'slug' => 'com-slug']);
    Post::create(['title' => 'Sem slug']);

    $this->assertDatabaseHas('posts', ['title' => 'Sem slug', 'slug' => null])
        ->assertDatabaseMissing('posts', ['title' => 'Com slug', 'slug' => null])
        ->assertDatabaseHas('posts', ['title' => 'Com slug'], 'sqlite');

    Fails::with(fn () => $this->assertDatabaseHas('posts', ['title' => 'Nada']), 'Found 2 row(s)', 'Com slug');
    Fails::with(fn () => $this->assertDatabaseMissing('posts', ['title' => 'Sem slug']), 'no row in the table [posts]');
    Fails::with(fn () => $this->assertDatabaseCount('posts', 5), 'has 5 rows (it has 2)');
    Fails::with(fn () => $this->assertDatabaseEmpty('posts'));

    $this->db()->table('posts')->delete();

    Fails::with(fn () => $this->assertDatabaseHas('posts', ['title' => 'Nada']), 'The table is empty');
})->group('db');

it('assertModelExists/Missing', function (): void {
    $post = Post::create(['title' => 'M']);

    $this->assertModelExists($post);

    $post->forceDelete();

    $this->assertModelMissing($post);

    Fails::with(fn () => $this->assertModelExists($post));
    Fails::with(fn () => $this->assertModelMissing(Post::create(['title' => 'N'])));
});

it('assertSoftDeleted / assertNotSoftDeleted aceitam model ou tabela', function (): void {
    $kept = Post::create(['title' => 'Fica']);
    $gone = Post::create(['title' => 'Vai']);
    $gone->delete();

    $this->assertSoftDeleted($gone)
        ->assertSoftDeleted('posts', ['title' => 'Vai'])
        ->assertNotSoftDeleted($kept)
        ->assertNotSoftDeleted('posts', ['title' => 'Fica']);

    Fails::with(fn () => $this->assertSoftDeleted($kept), 'soft-deleted row');
    Fails::with(fn () => $this->assertNotSoftDeleted($gone), 'not soft-deleted');

    $this->delete('/posts/'.$kept->getKey())->assertNoContent();
    $this->assertSoftDeleted($kept);
});

it('seed() corre um seeder e rejeita classes que não o são', function (): void {
    $this->seed(PostSeeder::class)->assertDatabaseCount('posts', 2)->assertDatabaseHas('posts', ['slug' => 'segundo']);

    expect(fn () => $this->seed(stdClass::class))->toThrow(InvalidArgumentException::class, 'não é um seeder');

    // O DatabaseSeeder por omissão é o da aplicação (outros testes da suite podem definir um).
    class_exists('Database\Seeders\DatabaseSeeder') || expect(fn () => $this->seed())->toThrow(InvalidArgumentException::class, 'DatabaseSeeder');
});

it('as transacções aninhadas do código testado convivem com a do teste', function (): void {
    $this->db()->transaction(fn () => Post::create(['title' => 'Dentro']));

    $this->assertDatabaseHas('posts', ['title' => 'Dentro']);

    $this->get('/posts/count')->assertJson(['count' => 1]);
});

it('a ligação preservada não é fechada no fim do teste', function (): void {
    $db = $this->app->make(DatabaseManager::class);

    expect($this->preservedConnections)->toBe(['sqlite'])
        ->and(array_keys($db->getConnections()))->toBe(['sqlite']);
});
