<?php

declare(strict_types=1);

use Slendie\Testing\RefreshDatabase;
use Slendie\Tests\Fixtures\Testing\Fails;
use Slendie\Tests\Fixtures\Testing\FixtureTestCase;

uses(FixtureTestCase::class, RefreshDatabase::class);

it('asserções de estado', function (): void {
    $this->get('/text')->assertOk()->assertStatus(200)->assertSuccessful();
    $this->get('/empty')->assertNoContent()->assertSuccessful();
    $this->get('/nada')->assertNotFound()->assertStatus(404);
    $this->post('/text')->assertMethodNotAllowed();
    $this->get('/boom')->assertServerError();
    $this->postJson('/fail')->assertUnprocessable();
    $this->postJson('/posts', ['title' => 'Novo'])->assertCreated();

    Fails::with(fn () => $this->get('/text')->assertStatus(201), 'Expected response status code [201] but received 200');
    Fails::with(fn () => $this->get('/text')->assertNotFound());
    Fails::with(fn () => $this->get('/text')->assertForbidden());
    Fails::with(fn () => $this->get('/text')->assertUnauthorized());
    Fails::with(fn () => $this->get('/text')->assertServerError());
    Fails::with(fn () => $this->get('/nada')->assertSuccessful());
    Fails::with(fn () => $this->get('/text')->assertNoContent());
});

it('forbidden e unauthorized', function (): void {
    $forbidden = new Slendie\Testing\TestResponse(new Slendie\Http\Response('', 403));
    $unauthorized = new Slendie\Testing\TestResponse(new Slendie\Http\Response('', 401));

    $forbidden->assertForbidden();
    $unauthorized->assertUnauthorized();
});

it('asserções de redirect', function (): void {
    $this->get('/old')->assertRedirect()->assertRedirect('/target')->assertRedirect('http://localhost/target')
        ->assertLocation('/target')->assertRedirectContains('arge')->assertRedirectToRoute('target');

    Fails::with(fn () => $this->get('/text')->assertRedirect(), 'Expected a redirect response but received 200');
    Fails::with(fn () => $this->get('/old')->assertRedirect('/outro'), 'Expected redirect location [/outro]');
    Fails::with(fn () => $this->get('/old')->assertRedirectContains('zzz'));
    Fails::with(fn () => $this->get('/text')->assertRedirectContains('zzz'));
});

it('asserções de headers e cookies', function (): void {
    $this->get('/header')->assertHeader('X-Test')->assertHeader('X-Test', 'yes')->assertHeaderMissing('X-Nope');
    $this->get('/cookie')->assertCookie('flavour')->assertCookie('flavour', 'choc')->assertPlainCookie('flavour', 'choc')->assertCookieMissing('x');

    Fails::with(fn () => $this->get('/header')->assertHeader('X-Nope'), 'Header [X-Nope] not present');
    Fails::with(fn () => $this->get('/header')->assertHeader('X-Test', 'no'), 'does not match');
    Fails::with(fn () => $this->get('/header')->assertHeaderMissing('X-Test'));
    Fails::with(fn () => $this->get('/cookie')->assertCookie('x'));
    Fails::with(fn () => $this->get('/cookie')->assertCookie('flavour', 'other'));
    Fails::with(fn () => $this->get('/cookie')->assertCookieMissing('flavour'));
});

it('asserções de corpo e escape', function (): void {
    $response = $this->get('/text');

    $response->assertSee('plain')->assertSee(['plain', 'more'])->assertSee('<b>text</b>', escape: false)
        ->assertDontSee('<b>text</b>')->assertDontSee('xyz')
        ->assertSeeInOrder(['plain', 'more'], escape: false)
        ->assertSeeText('plain text &', escape: false)
        ->assertDontSeeText('<b>', escape: false)
        ->assertContent('plain <b>text</b> & more');

    expect($response->content)->toBe('plain <b>text</b> & more')
        ->and($response->status)->toBe(200)
        ->and($response->getContent())->toBe($response->content)
        ->and($response->getStatusCode())->toBe(200)
        ->and(fn () => $response->nada)->toThrow(LogicException::class);

    Fails::with(fn () => $this->get('/text')->assertSee('absent'), 'contains [absent]', 'Response body');
    Fails::with(fn () => $this->get('/text')->assertSee('<b>'), 'contains');
    Fails::with(fn () => $this->get('/text')->assertSeeInOrder(['more', 'plain'], false), 'expected order');
    Fails::with(fn () => $this->get('/text')->assertDontSee('plain'));
    Fails::with(fn () => $this->get('/text')->assertSeeText('absent'));
    Fails::with(fn () => $this->get('/text')->assertDontSeeText('plain', false));
    Fails::with(fn () => $this->get('/text')->assertContent('x'));
});

it('a mensagem de falha mostra o excerto do corpo e a excepção', function (): void {
    Fails::with(
        fn () => $this->get('/boom')->assertOk(),
        'Expected response status code [200] but received 500',
        'The following exception occurred during the request',
        'RuntimeException: kaboom',
        'Response body',
    );

    $long = new Slendie\Testing\TestResponse(new Slendie\Http\Response(str_repeat('x', 900), 500));

    Fails::with(fn () => $long->assertOk(), 'first 500 of 900 chars');
});

it('asserções de view', function (): void {
    $this->get('/')->assertViewIs('home')->assertViewHas('title')->assertViewHas('title', 'Olá Mundo')
        ->assertViewHas('items', fn (array $items): bool => count($items) === 3)
        ->assertViewHasAll(['title', 'items' => [1, 2, 3]])
        ->assertViewMissing('nada');

    expect($this->get('/')->viewData('title'))->toBe('Olá Mundo');

    Fails::with(fn () => $this->get('/')->assertViewIs('outra'), 'Expected view [outra]');
    Fails::with(fn () => $this->get('/')->assertViewHas('nada'));
    Fails::with(fn () => $this->get('/')->assertViewHas('title', 'x'));
    Fails::with(fn () => $this->get('/')->assertViewHas('items', fn (): bool => false));
    Fails::with(fn () => $this->get('/')->assertViewMissing('title'));
    Fails::with(fn () => $this->get('/text')->assertViewIs('home'), 'not a view response');
});

it('asserções de JSON', function (): void {
    $response = $this->getJson('/json');

    $response->assertOk()
        ->assertJson(['name' => 'Ana'])
        ->assertJson(['nested' => ['x' => 1], 'users' => [['id' => 1]]])
        ->assertJson(['name' => 'Ana'], strict: true)
        ->assertJsonFragment(['name' => 'Ana'])
        ->assertJsonFragment(['email' => 'b@x.pt'])
        ->assertJsonMissing(['name' => 'Bia'])
        ->assertJsonPath('nested.y', 2)
        ->assertJsonPath('users.1.email', 'b@x.pt')
        ->assertJsonPath('tags', fn (array $tags): bool => $tags === ['a', 'b'])
        ->assertJsonCount(4)
        ->assertJsonCount(2, 'tags')
        ->assertJsonStructure(['name', 'tags', 'nested' => ['x'], 'users' => ['*' => ['id', 'email']]])
        ->assertJsonStructure(['users' => [['id']]])
        ->assertJsonIsObject();

    $this->getJson('/list')->assertJsonIsArray()->assertExactJson([1, 2, 3])->assertJsonCount(3);

    expect($response->json('nested.x'))->toBe(1)
        ->and($response->json()['name'])->toBe('Ana')
        ->and($response->decodeResponseJson()['tags'])->toBe(['a', 'b']);

    $this->getJson('/json')->assertExactJson([
        'users' => [['email' => 'a@x.pt', 'id' => 1], ['id' => 2, 'email' => 'b@x.pt']],
        'nested' => ['y' => 2, 'x' => 1], 'tags' => ['a', 'b'], 'name' => 'Ana',
    ]);

    Fails::with(fn () => $response->assertJson(['name' => 'Bia']), 'Unable to find JSON');
    Fails::with(fn () => $response->assertJson(['nested' => ['x' => 2]]));
    Fails::with(fn () => $response->assertJson(['absent' => 1]));
    Fails::with(fn () => $response->assertJson(['nested' => 'string']));
    Fails::with(fn () => $response->assertJson(['name' => 'Ana', 'nested' => ['x' => '1']], strict: true));
    Fails::with(fn () => $response->assertExactJson(['name' => 'Ana']), 'does not match exactly');
    Fails::with(fn () => $response->assertJsonFragment(['name' => 'Bia']), 'JSON fragment');
    Fails::with(fn () => $response->assertJsonMissing(['name' => 'Ana']), 'unexpected JSON fragment');
    Fails::with(fn () => $response->assertJsonPath('nested.x', 9), 'JSON path [nested.x]');
    Fails::with(fn () => $response->assertJsonPath('name', fn (): bool => false), 'callback');
    Fails::with(fn () => $response->assertJsonCount(9), 'number of items');
    Fails::with(fn () => $response->assertJsonCount(1, 'name'), 'not an array');
    Fails::with(fn () => $response->assertJsonStructure(['absent']), 'has the key [absent]');
    Fails::with(fn () => $response->assertJsonStructure(['nested' => ['z']]));
    Fails::with(fn () => $response->assertJsonStructure(['users' => ['*' => ['nome']]]));
    Fails::with(fn () => $response->assertJsonStructure(['name' => ['x']]), 'is an array or object');
    Fails::with(fn () => $response->assertJsonIsArray(), 'not an array');
    Fails::with(fn () => $this->getJson('/list')->assertJsonIsObject(), 'not an object');
    Fails::with(fn () => $this->get('/text')->assertJson([]), 'not valid JSON');
});

it('asserções de erros de validação JSON', function (): void {
    $response = $this->postJson('/fail', ['email' => 'x']);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('email')
        ->assertJsonValidationErrors(['email', 'name'])
        ->assertJsonMissingValidationErrors('other')
        ->assertInvalid(['email', 'name'])
        ->assertInvalid('name')
        ->assertValid('other');

    $this->postJson('/posts', ['title' => 'ok'])->assertCreated()->assertJsonMissingValidationErrors()->assertValid();

    Fails::with(fn () => $response->assertJsonValidationErrors('absent'), "key: 'absent'");
    Fails::with(fn () => $response->assertJsonValidationErrors(['email' => 'mensagem inventada']), 'validation error');
    Fails::with(fn () => $response->assertJsonMissingValidationErrors('email'), "key: 'email'");
    Fails::with(fn () => $response->assertJsonMissingValidationErrors(), 'unexpected validation errors');
    Fails::with(fn () => $response->assertValid(), 'unexpected validation errors');
    Fails::with(fn () => $response->assertValid('email'), '[email]');
    Fails::with(fn () => $response->assertInvalid('absent'), 'Expected a validation error for [absent]');
    Fails::with(fn () => $this->getJson('/json')->assertJsonValidationErrors('x'), 'none were found');

    $message = $response->json('errors.email.0');
    $response->assertJsonValidationErrors(['email' => $message])->assertInvalid(['email' => $message]);
});

it('asserções de sessão e erros de formulário', function (): void {
    $response = $this->from('/target')->post('/fail', ['email' => 'x']);

    $response->assertRedirect('/target')
        ->assertSessionHasErrors()
        ->assertSessionHasErrors('email')
        ->assertSessionHasErrors(['email', 'name'])
        ->assertSessionHasInput('email', 'x')
        ->assertSessionHasInput('email')
        ->assertSessionHasInput('email', fn (string $v): bool => $v === 'x')
        ->assertSessionDoesntHaveErrors('other')
        ->assertInvalid(['email', 'name'])
        ->assertValid('other')
        ->assertSessionHas('_token')
        ->assertSessionHasAll(['_token'])
        ->assertSessionMissing('nada');

    $error = $this->session('errors')['default']['email'][0];
    $response->assertSessionHasErrors(['email' => $error])->assertSessionHasErrors(['email' => 'E: '.$error], 'E: :message');

    $this->post('/flash')->assertSessionHas('status', 'saved')->assertSessionHas('status', fn (string $s): bool => $s === 'saved');
    $this->flushSession();
    $this->post('/posts', ['title' => 'ok'])->assertSessionHasNoErrors();

    Fails::with(fn () => $response->assertSessionHasErrors('absent'), 'missing the error for [absent]');
    Fails::with(fn () => $response->assertSessionHasErrors(['email' => 'outra']), 'does not contain');
    Fails::with(fn () => $response->assertSessionHasErrors([], null, 'other-bag'), 'does not contain errors');
    Fails::with(fn () => $response->assertSessionHasNoErrors(), 'unexpected errors');
    Fails::with(fn () => $response->assertSessionDoesntHaveErrors('email'), 'unexpected error for [email]');
    Fails::with(fn () => $response->assertSessionDoesntHaveErrors(), 'unexpected errors');
    Fails::with(fn () => $response->assertSessionDoesntHaveErrors(['email' => $error]), 'unexpected error');
    Fails::with(fn () => $response->assertSessionHas('nada'), 'missing expected key [nada]');
    Fails::with(fn () => $response->assertSessionHas('_token', 'x'), 'does not match');
    Fails::with(fn () => $response->assertSessionHas('_token', fn (): bool => false), 'callback');
    Fails::with(fn () => $response->assertSessionMissing('_token'), 'unexpected key');
    Fails::with(fn () => $response->assertSessionHasInput('nada'), 'old input');
    Fails::with(fn () => $response->assertSessionHasInput('email', 'y'), 'does not match');
    Fails::with(fn () => $response->assertSessionHasInput('email', fn (): bool => false), 'callback');
});

it('asserções de sessão falham de forma clara sem sessão', function (): void {
    $response = new Slendie\Testing\TestResponse(new Slendie\Http\Response('x'));

    Fails::with(fn () => $response->assertSessionHas('x'), 'No session is available');
});

it('utilitários de depuração escrevem o conteúdo', function (): void {
    ob_start();
    $this->getJson('/json')->dump()->dump('nested.x')->dump('nested')->dumpHeaders()->dumpSession();
    $this->get('/text')->dump();
    $out = (string) ob_get_clean();

    expect($out)->toContain('"name": "Ana"')
        ->and($out)->toContain('X-Content-Type-Options')
        ->and($out)->toContain('plain <b>text</b>');
});
