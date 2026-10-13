<?php

declare(strict_types=1);

use Slendie\Http\Controller;
use Slendie\Http\JsonResponse;
use Slendie\Http\RedirectResponse;
use Slendie\Http\Response;
use Slendie\Http\ViewResponse;
use Slendie\Routing\Redirector;
use Slendie\Support\Facades\Response as ResponseFacade;
use Slendie\Support\Facades\URL;
use Slendie\Tests\Fixtures\Http\HttpHelpers as H;

beforeEach(function (): void {
    $this->app = H::app();
    $this->factory = $this->app->make('response');
});

it('make, noContent e json', function (): void {
    expect($this->factory->make('x', 201, ['X-A' => '1']))->toBeInstanceOf(Response::class)
        ->and($this->factory->make('x', 201)->getStatusCode())->toBe(201)
        ->and($this->factory->noContent()->getStatusCode())->toBe(204)
        ->and($this->factory->noContent(205, ['X-B' => '2'])->getHeader('X-B'))->toBe('2')
        ->and($this->factory->json(['a' => 1], 202))->toBeInstanceOf(JsonResponse::class)
        ->and($this->factory->json(['a' => 1])->getContent())->toBe('{"a":1}');
});

it('view renderiza uma view numa ViewResponse', function (): void {
    $response = $this->factory->view('plain', ['name' => 'Ana'], 203, ['X-V' => 'v']);

    expect($response)->toBeInstanceOf(ViewResponse::class)
        ->and($response->getStatusCode())->toBe(203)
        ->and($response->getHeader('X-V'))->toBe('v')
        ->and($response->getContent())->toContain('<p>Ana</p>');
});

it('view recusa quando o motor de views não está disponível ou é de outro tipo', function (): void {
    $factory = new Slendie\Http\ResponseFactory(new Slendie\Foundation\Application(fixturePath('http')), $this->app->make(Redirector::class));

    expect(fn () => $factory->view('x'))->toThrow(LogicException::class, 'motor de views');

    $this->app->instance('view', new stdClass());

    expect(fn () => $this->factory->view('x'))->toThrow(LogicException::class, 'ViewFactory');
});

it('redirect devolve o Redirector', function (): void {
    expect($this->factory->redirect())->toBeInstanceOf(Redirector::class);
});

it('download envia o ficheiro com Content-Disposition e nome seguro', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'dl');
    file_put_contents($file, "a,b\n1,2\n");

    try {
        $response = $this->factory->download($file, 'relatório "x".csv', ['X-D' => 'd']);
        $inline = $this->factory->download($file, null, ['Content-Type' => 'text/csv'], 'inline');

        expect($response->getContent())->toBe("a,b\n1,2\n")
            ->and($response->getHeader('Content-Length'))->toBe('8')
            ->and($response->getHeader('X-D'))->toBe('d')
            ->and($response->getHeader('Content-Disposition'))->toContain('attachment; filename="relat__rio _x_.csv"', "filename*=UTF-8''relat%C3%B3rio%20%22x%22.csv")
            ->and($inline->getHeader('Content-Type'))->toBe('text/csv')
            ->and($inline->getHeader('Content-Disposition'))->toStartWith('inline; filename="'.basename($file).'"');

        expect(fn () => $this->factory->download($file.'.nao-existe'))->toThrow(RuntimeException::class, 'não existe');
    } finally {
        @unlink($file);
    }
});

it('as facades Response e URL falam com os serviços da aplicação', function (): void {
    $this->app->make(Slendie\Routing\Router::class)->get('/alvo/{id}', fn () => 'x')->name('alvo');

    expect(ResponseFacade::json(['ok' => true]))->toBeInstanceOf(JsonResponse::class)
        ->and(URL::to('/a'))->toBe('http://localhost/a')
        ->and(URL::route('alvo', ['id' => 3]))->toBe('http://localhost/alvo/3')
        ->and(URL::asset('css/a.css'))->toBe('http://localhost/css/a.css');
});

it('o Controller base dá atalhos e acumula middleware sem repetições', function (): void {
    $controller = new class () extends Controller {
        public function __construct()
        {
            $this->middleware('auth')->middleware(['auth', 'web']);
        }

        public function tryView(): Response
        {
            return $this->view('plain', [], 201);
        }

        public function tryRedirect(): Redirector|RedirectResponse
        {
            return $this->redirect('/x');
        }

        public function tryRedirector(): Redirector|RedirectResponse
        {
            return $this->redirect();
        }

        public function tryJson(): JsonResponse
        {
            return $this->json(['a' => 1], 202);
        }

        public function tryResponse(): Response
        {
            return $this->response('texto', 206);
        }
    };

    expect($controller->getMiddleware())->toBe(['auth', 'web'])
        ->and($controller->tryView()->getStatusCode())->toBe(201)
        ->and($controller->tryRedirect())->toBeInstanceOf(RedirectResponse::class)
        ->and($controller->tryRedirector())->toBeInstanceOf(Redirector::class)
        ->and($controller->tryJson()->getStatusCode())->toBe(202)
        ->and($controller->tryResponse()->getContent())->toBe('texto');
});
