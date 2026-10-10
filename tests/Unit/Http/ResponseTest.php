<?php

declare(strict_types=1);

use Slendie\Contracts\Arrayable;
use Slendie\Contracts\Renderable;
use Slendie\Http\Cookie;
use Slendie\Http\JsonResponse;
use Slendie\Http\RedirectResponse;
use Slendie\Http\Request;
use Slendie\Http\Response;
use Slendie\Http\ViewResponse;
use Slendie\Session\ArraySessionHandler;
use Slendie\Session\Session;

it('guarda conteúdo, estado e headers (insensíveis a maiúsculas)', function (): void {
    $response = new Response('olá', 201, ['X-One' => '1']);
    $response->header('Content-Type', 'text/plain')->header('content-type', 'text/html');

    expect($response->getContent())->toBe('olá')
        ->and($response->getStatusCode())->toBe(201)
        ->and($response->isSuccessful())->toBeTrue()
        ->and($response->isOk())->toBeFalse()
        ->and($response->getHeader('x-one'))->toBe('1')
        ->and($response->headers())->toBe(['X-One' => '1', 'content-type' => 'text/html'])
        ->and($response->hasHeader('CONTENT-TYPE'))->toBeTrue()
        ->and($response->removeHeader('x-one')->hasHeader('X-One'))->toBeFalse();
});

it('header com replace=false não substitui', function (): void {
    $response = (new Response())->header('A', '1')->header('a', '2', replace: false);

    expect($response->getHeader('A'))->toBe('1');
});

it('recusa códigos de estado inválidos', function (): void {
    new Response('', 99);
})->throws(InvalidArgumentException::class);

it('cookie() aceita Cookie ou parâmetros e substitui a mesma cookie', function (): void {
    $response = (new Response())
        ->cookie('a', '1', 10)
        ->cookie(new Cookie('b', 'x y', 0, '/', 'example.com', true, true, 'strict'))
        ->cookie('a', '2', 10);

    expect($response->cookies())->toHaveCount(2);

    $header = $response->cookies()[0]->toHeaderString();

    expect($header)->toContain('b=x%20y')
        ->and($header)->toContain('Domain=example.com')
        ->and($header)->toContain('Secure')
        ->and($header)->toContain('HttpOnly')
        ->and($header)->toContain('SameSite=Strict')
        ->and($header)->not->toContain('Expires')
        ->and($response->cookies()[1]->toHeaderString())->toContain('a=2')->toContain('Max-Age=');
});

it('Cookie::forget() expira no passado e valida o nome', function (): void {
    $cookie = Cookie::forget('x');

    expect($cookie->isExpired())->toBeTrue()
        ->and($cookie->toHeaderString())->toContain('Max-Age=0');

    new Cookie('bad name');
})->throws(InvalidArgumentException::class);

it('send() escreve o corpo e HEAD não leva corpo', function (): void {
    ob_start();
    (new Response('corpo'))->send();
    $normal = ob_get_clean();

    ob_start();
    (new Response('corpo'))->prepare(Request::create('/', 'HEAD'))->send();
    $head = ob_get_clean();

    ob_start();
    (new Response('corpo', 204))->send();
    $empty = ob_get_clean();

    expect($normal)->toBe('corpo')->and($head)->toBe('')->and($empty)->toBe('');
});

it('__toString mostra estado, headers e corpo', function (): void {
    $text = (string) new Response('x', 404, ['X-A' => 'b']);

    expect($text)->toStartWith("HTTP/1.1 404 Not Found\r\n")
        ->and($text)->toContain("X-A: b\r\n")
        ->and($text)->toEndWith("\r\n\r\nx");
});

it('JsonResponse codifica com flags por defeito e expõe os dados', function (): void {
    $response = new JsonResponse(['nome' => 'Ação', 'url' => 'a/b'], 201);

    expect($response->getContent())->toBe('{"nome":"Ação","url":"a/b"}')
        ->and($response->getStatusCode())->toBe(201)
        ->and($response->getHeader('Content-Type'))->toBe('application/json')
        ->and($response->getData())->toBe(['nome' => 'Ação', 'url' => 'a/b']);

    $response->setData([1, 2]);
    expect($response->getContent())->toBe('[1,2]');

    $escaped = new JsonResponse(['u' => 'a/b', 'n' => 'ç'], 200, [], 0);
    expect($escaped->getContent())->toBe('{"u":"a\/b","n":"'.chr(92).'u00e7"}');
});

it('JsonResponse aceita Arrayable e null', function (): void {
    $arrayable = new class () implements Arrayable {
        public function toArray(): array
        {
            return ['ok' => true];
        }
    };

    expect((new JsonResponse($arrayable))->getContent())->toBe('{"ok":true}')
        ->and((new JsonResponse(null))->getContent())->toBe('{}');
});

it('RedirectResponse define Location e valida o estado', function (): void {
    $redirect = new RedirectResponse('/destino', 301);

    expect($redirect->getTargetUrl())->toBe('/destino')
        ->and($redirect->getHeader('Location'))->toBe('/destino')
        ->and($redirect->getStatusCode())->toBe(301)
        ->and($redirect->isRedirect())->toBeTrue()
        ->and($redirect->isRedirect('/destino'))->toBeTrue()
        ->and($redirect->isRedirect('/outro'))->toBeFalse()
        ->and($redirect->getContent())->toContain('/destino');
});

it('RedirectResponse recusa estados que não são redirecionamentos', function (): void {
    new RedirectResponse('/x', 200);
})->throws(InvalidArgumentException::class);

it('RedirectResponse with/withInput/withErrors gravam flash na sessão', function (): void {
    $session = new Session('s', new ArraySessionHandler());
    $session->start();

    $redirect = (new RedirectResponse('/form'))->setSession($session);
    $redirect->with('status', 'Gravado')
        ->withInput(['name' => 'Ana'])
        ->withErrors(['email' => ['Obrigatório']]);

    expect($session->get('status'))->toBe('Gravado')
        ->and($session->getOldInput('name'))->toBe('Ana')
        ->and($session->get('errors'))->toBe(['default' => ['email' => ['Obrigatório']]]);
});

it('RedirectResponse::with() sem sessão lança', function (): void {
    (new RedirectResponse('/x'))->with('a', 'b');
})->throws(RuntimeException::class);

it('ViewResponse renderiza só quando necessário', function (): void {
    $renders = 0;
    $view = new class ($renders) implements Renderable {
        public function __construct(private int &$renders)
        {
        }

        public function render(): string
        {
            $this->renders++;

            return '<h1>Olá</h1>';
        }
    };

    $response = new ViewResponse($view, 200, ['X-A' => '1']);

    expect($renders)->toBe(0)
        ->and($response->getContent())->toBe('<h1>Olá</h1>')
        ->and($response->getContent())->toBe('<h1>Olá</h1>')
        ->and($renders)->toBe(1)
        ->and($response->getHeader('Content-Type'))->toBe('text/html; charset=UTF-8');
});
