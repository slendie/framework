<?php

declare(strict_types=1);

use Slendie\Http\Response;
use Slendie\Tests\Fixtures\Http\HttpHelpers as H;

/*
| O formulário de contacto do skeleton (critério de aceitação da peça 08): valida e repõe os valores.
*/

require_once dirname(__DIR__, 4).'/skeleton/app/Http/Controllers/ContactController.php';

function contactCookie(Response $response): ?string
{
    foreach ($response->cookies() as $cookie) {
        if ($cookie->name === 'slendie_session') {
            return $cookie->value;
        }
    }

    return null;
}

beforeEach(function (): void {
    $this->app = H::app();
    config(['database.default' => 'main', 'database.connections.main' => ['driver' => 'sqlite', 'database' => ':memory:']]);

    (require dirname(__DIR__, 4).'/skeleton/database/migrations/2026_01_01_000000_create_leads_table.php')->up();

    // Rotas do skeleton (routes/web.php) + uma página para ver o `old()` e os erros.
    $router = H::router($this->app);
    $router->middleware('web')->group(function (Slendie\Routing\Router $router): void {
        $router->post('/contact', [App\Http\Controllers\ContactController::class, 'store'])->name('contact.store');
        $router->get('/contact', fn () => view('contact'));
        $router->get('/token', fn () => csrf_token());
    });

    $this->cookie = ['slendie_session' => contactCookie(H::send($this->app, 'GET', '/token'))];
    $this->token = H::send($this->app, 'GET', '/token', [], [], $this->cookie)->getContent();

    $this->submit = fn (array $data, array $server = []): Response => H::send(
        $this->app,
        'POST',
        '/contact',
        ['_token' => $this->token, ...$data],
        ['HTTP_REFERER' => 'http://localhost/contact', ...$server],
        $this->cookie,
    );
});

it('um contacto válido é gravado em leads e volta com mensagem de sucesso', function (): void {
    $response = ($this->submit)(['name' => 'Ana Silva', 'email' => 'ana@example.com', 'phone' => '+351 910 000 000', 'message' => 'Olá!', 'website' => '']);
    $leads = $this->app->make('db')->table('leads')->get();

    expect($response->getStatusCode())->toBe(302)
        ->and($leads)->toHaveCount(1)
        ->and($leads[0]['name'])->toBe('Ana Silva')
        ->and($leads[0]['email'])->toBe('ana@example.com')
        ->and($leads[0]['phone'])->toBe('+351 910 000 000')
        ->and($leads[0]['source'])->toBe('contact')
        ->and($leads[0]['ip'])->toBe('127.0.0.1')
        ->and($leads[0]['status'])->toBe('new');
});

it('só name e email são obrigatórios; phone e message são opcionais', function (): void {
    ($this->submit)(['name' => 'Rui', 'email' => 'rui@example.com', 'phone' => '', 'message' => '']);

    $lead = $this->app->make('db')->table('leads')->first();

    expect($lead['name'])->toBe('Rui')->and($lead['phone'])->toBeNull()->and($lead['message'])->toBeNull();
});

it('valida name, email, phone, message e repõe os valores', function (): void {
    $response = ($this->submit)([
        'name' => str_repeat('a', 121),
        'email' => 'nao-e-email',
        'phone' => str_repeat('9', 31),
        'message' => str_repeat('m', 2001),
    ]);

    $page = H::send($this->app, 'GET', '/contact', [], [], $this->cookie)->getContent();

    expect($response->getStatusCode())->toBe(302)
        ->and($this->app->make('db')->table('leads')->count())->toBe(0)
        ->and($page)
        ->toContain('<p class="error-name">O campo nome não pode ter mais de 120 caracteres.</p>')
        ->toContain('<p class="error-email">O campo e-mail tem de ser um endereço de e-mail válido.</p>')
        ->toContain('<input name="email" value="nao-e-email">');
});

it('campos obrigatórios em falta', function (): void {
    $response = ($this->submit)(['name' => '', 'email' => ''], ['HTTP_ACCEPT' => 'application/json']);

    expect($response->getStatusCode())->toBe(422)
        ->and(json_decode($response->getContent(), true)['errors'])->toBe([
            'name' => ['O campo nome é obrigatório.'],
            'email' => ['O campo e-mail é obrigatório.'],
        ]);
});

it('o honeypot (website) rejeita bots', function (): void {
    $response = ($this->submit)(['name' => 'Bot', 'email' => 'bot@example.com', 'website' => 'http://spam.example'], ['HTTP_ACCEPT' => 'application/json']);

    expect($response->getStatusCode())->toBe(422)
        ->and(json_decode($response->getContent(), true)['errors'])->toHaveKey('website')
        ->and($this->app->make('db')->table('leads')->count())->toBe(0);
});
