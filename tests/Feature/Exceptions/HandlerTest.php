<?php

declare(strict_types=1);

use Slendie\Exceptions\HttpException;
use Slendie\Exceptions\MethodNotAllowedHttpException;
use Slendie\Exceptions\NotFoundHttpException;
use Slendie\Exceptions\TokenMismatchException;
use Slendie\Foundation\Exceptions\Handler;
use Slendie\Http\JsonResponse;
use Slendie\Http\Request;
use Slendie\Support\Env;
use Slendie\Tests\Fixtures\Http\HttpHelpers as H;

beforeEach(function (): void {
    $this->app = H::app();
    $this->handler = $this->app->make(Handler::class);
});

it('404 em HTML usa a página errors/404', function (): void {
    $response = $this->handler->render(Request::create('/x'), new NotFoundHttpException());

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getHeader('Content-Type'))->toBe('text/html; charset=UTF-8')
        ->and($response->getContent())->toContain('<h1>404</h1>')
        ->and($response->getContent())->toContain('Página não encontrada');
});

it('404 em JSON devolve {message}', function (): void {
    $response = $this->handler->render(Request::create('/api/x'), new NotFoundHttpException());

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(404)
        ->and(json_decode($response->getContent(), true))->toBe(['message' => 'Not Found']);
});

it('JSON usa a mensagem da excepção quando existe', function (): void {
    $response = $this->handler->render(
        Request::create('/x', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']),
        new HttpException(403, 'Sem permissão'),
    );

    expect($response->getStatusCode())->toBe(403)
        ->and(json_decode($response->getContent(), true))->toBe(['message' => 'Sem permissão']);
});

it('405 mantém o header Allow e 419 tem página própria', function (): void {
    $notAllowed = $this->handler->render(Request::create('/x'), new MethodNotAllowedHttpException(['GET', 'HEAD']));
    $expired = $this->handler->render(Request::create('/x'), new TokenMismatchException());

    expect($notAllowed->getStatusCode())->toBe(405)
        ->and($notAllowed->getHeader('Allow'))->toBe('GET, HEAD')
        ->and($notAllowed->getContent())->toContain('<h1>405</h1>')
        ->and($expired->getStatusCode())->toBe(419)
        ->and($expired->getContent())->toContain('A página expirou');
});

it('estados sem página própria usam errors/generic', function (): void {
    $response = $this->handler->render(Request::create('/x'), new HttpException(418, "I'm a teapot"));

    expect($response->getStatusCode())->toBe(418)
        ->and($response->getContent())->toContain('<h1>418</h1>')
        ->and($response->getContent())->toContain('I&#039;m a teapot');
});

it('em debug mostra a página com classe, mensagem, ficheiro:linha, excerto e trace', function (): void {
    $exception = new RuntimeException('Algo <b>correu</b> mal');

    $response = $this->handler->render(Request::create('/boom?x=1'), $exception);
    $html = $response->getContent();

    expect($response->getStatusCode())->toBe(500)
        ->and($html)->toContain('RuntimeException')
        ->and($html)->toContain('Algo &lt;b&gt;correu&lt;/b&gt; mal')
        ->and($html)->not->toContain('<b>correu</b>')
        ->and($html)->toContain(basename(__FILE__).':'.$exception->getLine())
        ->and($html)->toContain('class="hit"')
        ->and($html)->toContain('Stack trace')
        ->and($html)->toContain('http://localhost/boom?x=1');
});

it('em debug o JSON traz classe, ficheiro, linha e trace', function (): void {
    $response = $this->handler->render(Request::create('/api/boom'), new LogicException('falhou'));
    $data = json_decode($response->getContent(), true);

    expect($response->getStatusCode())->toBe(500)
        ->and($data['message'])->toBe('falhou')
        ->and($data['exception'])->toBe(LogicException::class)
        ->and($data['trace'])->toBeArray();
});

it('em produção esconde os detalhes', function (): void {
    config(['app.debug' => false]);

    $html = $this->handler->render(Request::create('/boom'), new RuntimeException('segredo interno'));
    $json = $this->handler->render(Request::create('/api/boom'), new RuntimeException('segredo interno'));

    expect($html->getStatusCode())->toBe(500)
        ->and($html->getContent())->toContain('<h1>500</h1>')
        ->and($html->getContent())->not->toContain('segredo interno')
        ->and($html->getContent())->not->toContain('RuntimeException')
        ->and(json_decode($json->getContent(), true))->toBe(['message' => 'Server Error']);
});

it('sanitiza o ambiente e os dados sensíveis do pedido na página de debug', function (): void {
    Env::set('APP_KEY', 'base64:SEGREDO-DA-APP');
    Env::set('DB_PASSWORD', 'palavra-passe-da-bd');
    Env::set('API_TOKEN', 'token-da-api');
    Env::set('MY_SECRET', 'mais-segredo');
    Env::set('APP_NAME', 'Visivel');

    $request = Request::create('/login', 'POST', ['email' => 'a@b.pt', 'password' => 'minha-pass'], [], [], ['HTTP_AUTHORIZATION' => 'Bearer abc', 'HTTP_COOKIE' => 'sess=zzz']);
    $html = $this->handler->render($request, H::exception())->getContent();

    foreach (['SEGREDO-DA-APP', 'palavra-passe-da-bd', 'token-da-api', 'mais-segredo', 'minha-pass', 'Bearer abc', 'sess=zzz'] as $secret) {
        expect($html)->not->toContain($secret);
    }

    expect($html)->toContain('••••')
        ->and($html)->toContain('Visivel')
        ->and($html)->toContain('a@b.pt');
});

it('renderable() permite respostas personalizadas por tipo', function (): void {
    $this->handler->renderable(DomainException::class, fn (DomainException $e) => response('custom: '.$e->getMessage(), 422));

    $response = $this->handler->render(Request::create('/x'), new DomainException('regra'));

    expect($response->getStatusCode())->toBe(422)->and($response->getContent())->toBe('custom: regra');
});

it('a aplicação pode sobrepor a página de erro em resources/views/errors', function (): void {
    $resources = sys_get_temp_dir().DIRECTORY_SEPARATOR.'slendie-res-'.bin2hex(random_bytes(4));
    mkdir($resources.'/views/errors', 0777, true);
    file_put_contents($resources.'/views/errors/404.php', '<?php echo "custom-404-".$status; ?>');

    $app = new Slendie\Foundation\Application($resources);
    $app->bootstrapWith([Slendie\Foundation\Bootstrap\LoadEnvironment::class]);
    mkdir($resources.'/resources/views/errors', 0777, true);
    rename($resources.'/views/errors/404.php', $resources.'/resources/views/errors/404.php');

    $response = (new Handler($app))->render(Request::create('/x'), new NotFoundHttpException());

    expect($response->getContent())->toBe('custom-404-404');
});

it('report() regista no log (excepto HttpException) com o formato esperado', function (): void {
    $logDir = storage_path('logs');
    mkdir($logDir, 0777, true);

    $this->handler->report(new NotFoundHttpException());
    expect(is_file($logDir.'/slendie.log'))->toBeFalse();

    $this->handler->report(new RuntimeException('falha grave'));

    $log = (string) file_get_contents($logDir.'/slendie.log');

    expect($log)->toMatch('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] testing\.ERROR: falha grave \{"exception":"RuntimeException"/')
        ->and($log)->toContain('#0');
});

it('dontReport() acrescenta tipos ignorados', function (): void {
    $this->handler->dontReport([DomainException::class]);

    expect($this->handler->shouldReport(new DomainException()))->toBeFalse()
        ->and($this->handler->shouldReport(new RuntimeException()))->toBeTrue()
        ->and($this->handler->shouldReport(new HttpException(500)))->toBeFalse();
});

it('abort() lança a excepção HTTP certa', function (): void {
    expect(fn () => abort(404))->toThrow(NotFoundHttpException::class)
        ->and(fn () => abort(419))->toThrow(TokenMismatchException::class)
        ->and(fn () => abort(403, 'Proibido'))->toThrow(HttpException::class, 'Proibido')
        ->and(fn () => abort_if(true, 401))->toThrow(HttpException::class)
        ->and(fn () => abort_unless(false, 401))->toThrow(HttpException::class)
        ->and(abort_if(false, 500))->toBeNull()
        ->and(abort_unless(true, 500))->toBeNull();

    try {
        abort(429, 'Calma', ['Retry-After' => '60']);
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(429)->and($e->getHeaders())->toBe(['Retry-After' => '60']);
    }
});
