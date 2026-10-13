<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use Slendie\Testing\RefreshDatabase;
use Slendie\Tests\Fixtures\Testing\Fails;
use Slendie\Tests\Fixtures\Testing\FixtureTestCase;

uses(FixtureTestCase::class, RefreshDatabase::class);

it('corre comandos e verifica o código de saída', function (): void {
    $this->console('migrate:status')->assertSuccessful();
    $this->console('migrate:status')->assertOk()->assertExitCode(0)->assertNotExitCode(1);
    $this->console('db:seed', ['--class' => 'Classe\\Que\\Nao\\Existe', '--force' => true])
        ->expectsOutputToContain('does not exist')
        ->assertFailed()
        ->assertExitCode(1);
});

it('um comando desconhecido lança CommandNotFoundException', function (): void {
    expect(fn () => $this->console('nao:existe')->run())->toThrow(Slendie\Console\CommandNotFoundException::class);
});

it('expectsOutput e expectsOutputToContain', function (): void {
    $this->console('migrate:status')
        ->expectsOutputToContain('create_posts_table')
        ->doesntExpectOutputToContain('create_leads_table')
        ->assertSuccessful();

    $this->console('greet', ['name' => 'Ana'])
        ->expectsOutput('Olá, Ana!')
        ->doesntExpectOutput('Adeus')
        ->assertSuccessful();
});

it('expectsTable verifica uma tabela desenhada', function (): void {
    $this->console('migrate:status')
        ->expectsTable(['Migration', 'Batch', 'Status'], [['2026_01_01_000000_create_posts_table', '1', 'Ran']])
        ->assertSuccessful();
});

it('responde a perguntas com expectsQuestion, expectsConfirmation e expectsChoice', function (): void {
    $this->console('ask')
        ->expectsQuestion('Nome?', 'Ana')
        ->expectsQuestion('Password?', 'segredo')
        ->expectsConfirmation('Certeza?', 'no')
        ->expectsChoice('Formato?', 'csv', ['pdf', 'csv'])
        ->expectsQuestion('Nível?', 'a')
        ->expectsOutputToContain('name=Ana pass=segredo sure=false format=csv level=alto')
        ->assertSuccessful();
});

it('falha com a saída completa quando a expectativa não se cumpre', function (): void {
    Fails::with(fn () => $this->console('migrate:status')->expectsOutput('inexistente')->assertSuccessful(), 'Expected the output line [inexistente]', 'create_posts_table');
    Fails::with(fn () => $this->console('migrate:status')->expectsOutputToContain('zzz')->assertSuccessful(), 'to contain [zzz]');
    Fails::with(fn () => $this->console('migrate:status')->doesntExpectOutputToContain('posts')->assertSuccessful(), 'not to contain');
    Fails::with(fn () => $this->console('migrate:status')->doesntExpectOutput('x')->expectsTable(['A'], [['b']])->assertSuccessful(), 'given table');
    Fails::with(fn () => $this->console('migrate:status')->assertExitCode(3), 'Expected status code [3] but received [0]');
    Fails::with(fn () => $this->console('migrate:status')->assertFailed(), 'Unexpected status code');
    Fails::with(fn () => $this->console('migrate:status')->expectsQuestion('Pergunta que não existe?', 'x')->assertSuccessful(), 'to be asked');
    Fails::with(fn () => $this->console('greet', ['name' => 'Ana'])->doesntExpectOutput('Olá, Ana!')->assertSuccessful(), 'Unexpected output line');
});

it('output() devolve o texto e o comando só corre uma vez', function (): void {
    $command = $this->console('migrate:status');

    expect($command->output())->toContain('create_posts_table')
        ->and($command->run())->toBe(0);
});

it('um comando sem asserções finais corre na mesma (destrutor) e as expectativas contam', function (): void {
    expect(function (): void {
        $this->console('migrate:status')->expectsOutputToContain('isto não aparece');
        gc_collect_cycles();
    })->toThrow(AssertionFailedError::class);
});
