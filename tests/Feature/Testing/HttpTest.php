<?php

declare(strict_types=1);

use Slendie\Http\Middleware\VerifyCsrfToken;
use Slendie\Tests\Fixtures\Http\TagMiddleware;
use Slendie\Tests\Fixtures\Testing\FixtureTestCase;
use Slendie\Validation\ValidationException;

uses(FixtureTestCase::class);

it('arranca a aplicação do projecto em modo testing', function (): void {
    expect($this->app->environment())->toBe('testing')
        ->and($this->app->runningUnitTests())->toBeTrue()
        ->and($this->app->hasBeenBootstrapped())->toBeTrue();

    $this->get('/')
        ->assertOk()
        ->assertSee('Olá Mundo')
        ->assertViewIs('home');
});

it('suporta todos os verbos', function (): void {
    $this->post('/echo', ['a' => 1])->assertOk()->assertJson(['a' => '1']);
    $this->put('/echo', ['a' => 1])->assertOk()->assertJson(['method' => 'PUT']);
    $this->patch('/echo', ['a' => 1])->assertOk()->assertJson(['method' => 'PATCH']);
    $this->delete('/echo')->assertOk()->assertJson(['method' => 'DELETE']);
    $this->options('/echo')->assertNoContent()->assertHeader('Allow', 'GET, POST');
    $this->head('/text')->assertOk();
});

it('as variantes JSON enviam o corpo em JSON', function (): void {
    $this->postJson('/echo', ['nested' => ['k' => 'v'], 'n' => 5])->assertOk()->assertExactJson(['nested' => ['k' => 'v'], 'n' => 5]);
    $this->putJson('/echo', ['n' => 1])->assertJson(['method' => 'PUT', 'n' => 1]);
    $this->patchJson('/echo', ['n' => 1])->assertJson(['method' => 'PATCH']);
    $this->deleteJson('/echo', ['n' => 1])->assertJson(['method' => 'DELETE']);
    $this->getJson('/whoami')->assertJsonPath('accept', 'application/json');
    $this->json('POST', '/echo', ['x' => true])->assertJson(['x' => true]);
});

it('envia cabeçalhos, token, cookies e variáveis do servidor', function (): void {
    $this->withHeaders(['X-One' => '1'])->withHeader('Accept-Language', 'pt')->withToken('abc')->withCookie('jar', 'c1')
        ->withServerVariables(['HTTP_USER_AGENT' => 'Bot/1.0'])
        ->get('/whoami')
        ->assertJsonPath('auth', 'Bearer abc')
        ->assertJsonPath('cookie', 'c1')
        ->assertJsonPath('ua', 'Bot/1.0');

    $this->withToken('xyz', 'Basic')->get('/whoami')->assertJsonPath('auth', 'Basic xyz');
    $this->withCookies(['jar' => 'c2'])->get('/whoami?q=1')->assertJsonPath('cookie', 'c2')->assertJsonPath('query.q', '1');
    $this->get('/whoami', ['X-Extra' => 'e'])->assertOk();
});

it('as cookies da resposta ficam no cliente e voltam no pedido seguinte', function (): void {
    $this->get('/cookie')->assertCookie('flavour', 'choc')->assertCookieMissing('nada');
    $this->get('/whoami')->assertCookieMissing('flavour');
    expect($this->cookies)->toHaveKey('flavour', 'choc');
});

it('a sessão persiste entre pedidos e withSession prepara-a', function (): void {
    $this->get('/session')->assertJsonPath('user_id', null);

    $this->withSession(['user_id' => 7])->get('/session')->assertJsonPath('user_id', 7);
    $this->get('/session')->assertJsonPath('user_id', 7);

    expect($this->session('user_id'))->toBe(7);

    $this->flushSession();

    $this->get('/session')->assertJsonPath('user_id', null);
});

it('o flash passa do POST para o GET seguinte', function (): void {
    $this->post('/flash')->assertRedirect('/target')->assertSessionHas('status', 'saved');
    $this->followingRedirects()->get('/target')->assertOk();
});

it('from() define para onde volta o back()', function (): void {
    $this->get('/back')->assertRedirect('/');

    $this->from('/target')->get('/back')->assertRedirect('/target');
});

it('followingRedirects segue a cadeia até ao fim', function (): void {
    $this->get('/chain')->assertRedirect('/old');

    $this->followingRedirects()->get('/chain')->assertOk()->assertSee('target page');
});

it('o CSRF está desligado em testing e withCsrf() volta a ligá-lo', function (): void {
    $this->post('/echo', ['a' => 1])->assertOk();

    $this->withCsrf()->post('/echo', ['a' => 1])->assertStatus(419);

    $this->post('/echo', ['a' => 1, '_token' => $this->csrfToken()])->assertOk();
});

it('withoutMiddleware desliga todo o middleware ou só o indicado', function (): void {
    TagMiddleware::$log = [];

    $this->get('/tagged')->assertOk();
    expect(TagMiddleware::$log)->not->toBeEmpty();

    TagMiddleware::$log = [];
    $this->withoutMiddleware(TagMiddleware::class)->get('/tagged')->assertOk();
    expect(TagMiddleware::$log)->toBeEmpty();

    $this->withMiddleware(TagMiddleware::class)->get('/tagged');
    expect(TagMiddleware::$log)->not->toBeEmpty();

    TagMiddleware::$log = [];
    $this->withoutMiddleware()->get('/tagged')->assertOk()->assertHeaderMissing('X-Content-Type-Options');
    expect(TagMiddleware::$log)->toBeEmpty();

    $this->withMiddleware()->get('/tagged')->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('withoutExceptionHandling relança a excepção original', function (): void {
    $this->get('/boom')->assertServerError();

    $this->withoutExceptionHandling();

    expect(fn () => $this->get('/boom'))->toThrow(RuntimeException::class, 'kaboom');
    expect(fn () => $this->post('/fail'))->toThrow(ValidationException::class);

    $this->withExceptionHandling()->get('/boom')->assertServerError();
});

it('assertThrows verifica a classe e a mensagem', function (): void {
    $this->assertThrows(fn () => throw new LogicException('boom total'), LogicException::class, 'boom');
    $this->assertThrows(fn () => throw new LogicException('x'));
});

it('envia ficheiros falsos que chegam como UploadedFile válido', function (): void {
    $this->post('/upload', ['avatar' => Slendie\Testing\Fakes\FakeUploadedFile::create('a.pdf', 3)])
        ->assertOk()
        ->assertJson(['received' => true, 'valid' => true, 'name' => 'a.pdf', 'size' => 3072, 'mime' => 'application/pdf']);

    $this->post('/upload', ['avatar' => Slendie\Testing\Fakes\FakeUploadedFile::image('p.png', 4, 4)])
        ->assertJson(['received' => true, 'valid' => true, 'mime' => 'image/png']);

    $this->post('/upload', [])->assertJson(['received' => false]);
});

it('o CSRF dos testes só se desliga pelo flag da aplicação (a classe continua a funcionar sozinha)', function (): void {
    $middleware = new VerifyCsrfToken();
    $request = Slendie\Http\Request::create('/x', 'POST');

    expect(fn () => $middleware->handle($request, fn ($r) => new Slendie\Http\Response('ok')))
        ->toThrow(Slendie\Exceptions\TokenMismatchException::class);
});
