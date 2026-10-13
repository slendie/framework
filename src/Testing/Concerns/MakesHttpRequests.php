<?php

declare(strict_types=1);

namespace Slendie\Testing\Concerns;

use Slendie\Foundation\Exceptions\Handler;
use Slendie\Http\Request;
use Slendie\Http\Response;
use Slendie\Http\UploadedFile;
use Slendie\Testing\Fakes\FakeUploadedFile;
use Slendie\Testing\Fakes\RecordingHandler;
use Slendie\Testing\TestResponse;

/**
 * Pedidos HTTP sintéticos à aplicação, sem servidor: constroem um Request, passam-no pelo Kernel e devolvem
 * um TestResponse. O estado do "browser" (cookies, sessão, cabeçalhos) vive no teste.
 *
 * @phpstan-require-extends \Slendie\Testing\TestCase
 */
trait MakesHttpRequests
{
    /** @var array<string, string> */
    protected array $defaultHeaders = [];

    /** @var array<string, string> cookies enviadas em todos os pedidos (jar do cliente) */
    protected array $cookies = [];

    /** @var array<string, mixed> */
    protected array $serverVariables = [];

    protected bool $followRedirects = false;

    // -------------------------------------------------------------------------
    // Estado do cliente
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): static
    {
        $this->defaultHeaders = [...$this->defaultHeaders, ...$headers];

        return $this;
    }

    public function withHeader(string $name, string $value): static
    {
        $this->defaultHeaders[$name] = $value;

        return $this;
    }

    public function withToken(string $token, string $type = 'Bearer'): static
    {
        return $this->withHeader('Authorization', trim($type.' '.$token));
    }

    public function withCookie(string $name, string $value): static
    {
        $this->cookies[$name] = $value;

        return $this;
    }

    /**
     * @param  array<string, string>  $cookies
     */
    public function withCookies(array $cookies): static
    {
        $this->cookies = [...$this->cookies, ...$cookies];

        return $this;
    }

    /**
     * @param  array<string, mixed>  $server
     */
    public function withServerVariables(array $server): static
    {
        $this->serverVariables = [...$this->serverVariables, ...$server];

        return $this;
    }

    /**
     * Define de onde "vem" o utilizador: o `back()` volta a este URL.
     */
    public function from(string $url): static
    {
        $this->defaultHeaders['Referer'] = $url;

        $session = $this->startSession();
        $session->setPreviousUrl($url);
        $session->save();

        return $this;
    }

    public function followingRedirects(bool $follow = true): static
    {
        $this->followRedirects = $follow;

        return $this;
    }

    /**
     * Liga a verificação CSRF (desligada por omissão nos testes). Envie `_token` com o valor de `csrfToken()`.
     */
    public function withCsrf(): static
    {
        $this->app->forgetInstance('testing.csrf.disabled');

        return $this;
    }

    /** Token CSRF da sessão do cliente de teste (cria a sessão se for preciso). */
    public function csrfToken(): string
    {
        return $this->startSession()->token();
    }

    /**
     * Sem argumentos desliga todo o middleware; com uma classe (ou lista) só essas.
     *
     * @param  class-string|list<class-string>|null  $middleware
     */
    public function withoutMiddleware(string|array|null $middleware = null): static
    {
        if ($middleware === null) {
            $this->app->instance('testing.middleware.disabled', true);

            return $this;
        }

        $current = $this->app->bound('testing.middleware.disabled') ? $this->app->make('testing.middleware.disabled') : [];

        if ($current !== true) {
            $this->app->instance('testing.middleware.disabled', array_values(array_unique([...(is_array($current) ? $current : []), ...(array) $middleware])));
        }

        return $this;
    }

    /**
     * @param  class-string|list<class-string>|null  $middleware
     */
    public function withMiddleware(string|array|null $middleware = null): static
    {
        if ($middleware === null) {
            $this->app->forgetInstance('testing.middleware.disabled');

            return $this;
        }

        $current = $this->app->bound('testing.middleware.disabled') ? $this->app->make('testing.middleware.disabled') : [];

        if (is_array($current)) {
            $this->app->instance('testing.middleware.disabled', array_values(array_diff($current, (array) $middleware)));
        }

        return $this;
    }

    // -------------------------------------------------------------------------
    // Verbos
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, string>  $headers
     */
    public function get(string $uri, array $headers = []): TestResponse
    {
        return $this->request('GET', $uri, [], $headers);
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function getJson(string $uri, array $headers = []): TestResponse
    {
        return $this->json('GET', $uri, [], $headers);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public function post(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->request('POST', $uri, $data, $headers);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public function postJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->json('POST', $uri, $data, $headers);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public function put(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->request('PUT', $uri, $data, $headers);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public function putJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->json('PUT', $uri, $data, $headers);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public function patch(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->request('PATCH', $uri, $data, $headers);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public function patchJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->json('PATCH', $uri, $data, $headers);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public function delete(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->request('DELETE', $uri, $data, $headers);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public function deleteJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->json('DELETE', $uri, $data, $headers);
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function options(string $uri, array $headers = []): TestResponse
    {
        return $this->request('OPTIONS', $uri, [], $headers);
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function head(string $uri, array $headers = []): TestResponse
    {
        return $this->request('HEAD', $uri, [], $headers);
    }

    /**
     * Corpo JSON (`Content-Type` e `Accept: application/json`).
     *
     * @param  array<array-key, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public function json(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        $content = $data === [] ? null : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $server = $this->headersToServer([
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
            ...$headers,
        ]);

        return $this->call($method, $uri, [], [], [], $server, $content);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function request(string $method, string $uri, array $data, array $headers): TestResponse
    {
        $files = [];
        $data = $this->extractFiles($data, $files);

        return $this->call($method, $uri, $data, [], $files, $this->headersToServer($headers));
    }

    // -------------------------------------------------------------------------
    // Núcleo
    // -------------------------------------------------------------------------

    /**
     * Envia o pedido pelo Kernel da aplicação.
     *
     * @param  array<array-key, mixed>  $parameters
     * @param  array<string, string>  $cookies
     * @param  array<array-key, mixed>  $files  UploadedFile (ou o formato de $_FILES)
     * @param  array<string, mixed>  $server
     */
    public function call(string $method, string $uri, array $parameters = [], array $cookies = [], array $files = [], array $server = [], ?string $content = null): TestResponse
    {
        $this->sessionHandler(); // a aplicação tem de usar já o handler em memória do teste

        $request = Request::create(
            $uri,
            $method,
            $parameters,
            [...$this->cookies, ...$cookies],
            $this->normalizeFiles($files),
            [...$this->headersToServer($this->defaultHeaders), ...$this->serverVariables, ...$server],
            $content,
        );

        $response = $this->dispatchRequest($request);

        if ($this->followRedirects) {
            $hops = 0;

            while ($response->baseResponse->isRedirect() && $response->baseResponse->hasHeader('Location') && $hops++ < 20) {
                $response = $this->dispatchRequest(Request::create(
                    (string) $response->baseResponse->getHeader('Location'),
                    'GET',
                    [],
                    $this->cookies,
                    [],
                    [...$this->headersToServer($this->defaultHeaders), ...$this->serverVariables],
                ));
            }
        }

        return $response;
    }

    protected function dispatchRequest(Request $request): TestResponse
    {
        $handler = $this->recordingHandler();

        if ($handler !== null) {
            $handler->lastException = null;
        }

        $response = $this->app->handle($request);
        $this->app->terminate($request, $response);

        $this->updateCookiesFrom($response);

        return new TestResponse($response, $request, $this->currentSession(), $handler?->lastException);
    }

    protected function recordingHandler(): ?RecordingHandler
    {
        $handler = $this->app->bound(Handler::class) ? $this->app->make(Handler::class) : null;

        return $handler instanceof RecordingHandler ? $handler : null;
    }

    /** O "browser" guarda as cookies da resposta e esquece as que expiraram. */
    protected function updateCookiesFrom(Response $response): void
    {
        foreach ($response->cookies() as $cookie) {
            if ($cookie->isExpired() || $cookie->value === '') {
                unset($this->cookies[$cookie->name]);

                continue;
            }

            $this->cookies[$cookie->name] = $cookie->value;

            if ($cookie->name === $this->sessionCookieName()) {
                $this->sessionId = $cookie->value;
            }
        }
    }

    // -------------------------------------------------------------------------
    // Conversões
    // -------------------------------------------------------------------------

    /**
     * Cabeçalhos HTTP => variáveis $_SERVER (`X-Foo` => `HTTP_X_FOO`; CONTENT_* ficam como estão).
     *
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    protected function headersToServer(array $headers): array
    {
        $server = [];

        foreach ($headers as $name => $value) {
            $key = strtoupper(str_replace('-', '_', $name));

            $server[in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true) ? $key : 'HTTP_'.$key] = $value;
        }

        return $server;
    }

    /**
     * Tira os UploadedFile do corpo e põe-nos em `$files` (como um browser faria com multipart).
     *
     * @param  array<array-key, mixed>  $data
     * @param  array<array-key, mixed>  $files
     * @return array<array-key, mixed>
     */
    protected function extractFiles(array $data, array &$files): array
    {
        foreach ($data as $key => $value) {
            if ($value instanceof UploadedFile) {
                $files[$key] = $value;
                unset($data[$key]);
            } elseif (is_array($value)) {
                $nested = [];
                $data[$key] = $this->extractFiles($value, $nested);

                if ($nested !== []) {
                    $files[$key] = $nested;
                }

                if ($data[$key] === []) {
                    unset($data[$key]);
                }
            }
        }

        return $data;
    }

    /**
     * @param  array<array-key, mixed>  $files
     * @return array<array-key, mixed>
     */
    protected function normalizeFiles(array $files): array
    {
        return array_map(
            fn (mixed $file): mixed => match (true) {
                $file instanceof UploadedFile => FakeUploadedFile::toFilesArray($file),
                is_array($file) && ! isset($file['tmp_name']) => $this->normalizeFiles($file),
                default => $file,
            },
            $files,
        );
    }
}
