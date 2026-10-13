<?php

declare(strict_types=1);

use Slendie\Tests\Fixtures\Testing\SeededTestCase;

uses(SeededTestCase::class);

it('o semeador corre na primeira migração e os dados ficam para todos os testes', function (): void {
    $this->assertDatabaseCount('posts', 2)->assertDatabaseHas('posts', ['slug' => 'primeiro']);

    $this->post('/posts', ['title' => 'Terceiro'])->assertRedirect();

    $this->assertDatabaseCount('posts', 3);
});

it('e o que o teste anterior escreveu foi desfeito, mas a semente não', function (): void {
    $this->assertDatabaseCount('posts', 2);
});
