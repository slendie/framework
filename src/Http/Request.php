<?php

declare(strict_types=1);

namespace Slendie\Http;

use Closure;
use JsonException;
use RuntimeException;
use Slendie\Routing\Route;
use Slendie\Session\Session;
use Slendie\Support\Arr;
use Slendie\Support\Str;

class Request
{
    /** @var array<string, string>|null headers normalizados (minúsculas) */
    protected ?array $headerBag = null;

    /** @var array<string, mixed>|null */
    protected ?array $jsonData = null;

    /** @var array<string, UploadedFile|list<mixed>>|null */
    protected ?array $fileBag = null;

    protected ?Route $route = null;

    protected ?Session $session = null;

    protected ?Closure $userResolver = null;

    /** @var array<string, mixed> */
    protected array $attributes = [];

    /**
     * @param  array<array-key, mixed>  $query
     * @param  array<array-key, mixed>  $request  corpo (POST)
     * @param  array<string, string>  $cookies
     * @param  array<string, mixed>  $files  no formato de $_FILES
     * @param  array<string, mixed>  $server
     */
    final public function __construct(
        protected array $query = [],
        protected array $request = [],
        protected array $cookies = [],
        protected array $files = [],
        protected array $server = [],
        protected ?string $content = null,
    ) {
    }

    // -------------------------------------------------------------------------
    // Construção
    // -------------------------------------------------------------------------

    /** Cria o pedido a partir das superglobais. */
    public static function capture(): static
    {
        /** @var array<string, mixed> $query */
        $query = $_GET;
        /** @var array<string, mixed> $post */
        $post = $_POST;
        /** @var array<string, string> $cookies */
        $cookies = $_COOKIE;
        /** @var array<string, mixed> $files */
        $files = $_FILES;
        /** @var array<string, mixed> $server */
        $server = $_SERVER;

        return new static($query, $post, $cookies, $files, $server);
    }

    /**
     * Cria um pedido sintético (testes). Em GET os parâmetros vão para a query string; nos outros métodos para o corpo.
     *
     * @param  array<string, mixed>  $parameters
     * @param  array<string, string>  $cookies
     * @param  array<string, mixed>  $files
     * @param  array<string, mixed>  $server
     */
    public static function create(string $uri, string $method = 'GET', array $parameters = [], array $cookies = [], array $files = [], array $server = [], ?string $content = null): static
    {
        $server = array_replace([
            'SERVER_NAME' => 'localhost',
            'HTTP_HOST' => 'localhost',
            'SERVER_PORT' => 80,
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'Slendie',
        ], $server);

        $parts = parse_url(str_starts_with($uri, '/') ? 'http://localhost'.$uri : $uri) ?: [];

        if (isset($parts['host']) && ! str_starts_with($uri, '/')) {
            $server['HTTP_HOST'] = $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

            if (($parts['scheme'] ?? 'http') === 'https') {
                $server['HTTPS'] = 'on';
                $server['SERVER_PORT'] = 443;
            }
        }

        $path = '/'.ltrim($parts['path'] ?? '/', '/');
        $query = [];

        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $method = strtoupper($method);
        $body = [];

        if ($method === 'GET' || $method === 'HEAD') {
            $query = array_replace($query, $parameters);
        } else {
            $body = $parameters;
        }

        $server['REQUEST_METHOD'] = $method;
        $server['REQUEST_URI'] = $path.($query !== [] ? '?'.http_build_query($query) : '');
        $server['QUERY_STRING'] = http_build_query($query);

        return new static($query, $body, $cookies, $files, $server, $content);
    }

    // -------------------------------------------------------------------------
    // Método
    // -------------------------------------------------------------------------

    public function realMethod(): HttpMethod
    {
        $method = is_string($this->server['REQUEST_METHOD'] ?? null) ? $this->server['REQUEST_METHOD'] : 'GET';

        return HttpMethod::tryFromString($method) ?? HttpMethod::GET;
    }

    /** Método efectivo, com method spoofing (`_method` ou X-HTTP-Method-Override) em POST. */
    public function method(): HttpMethod
    {
        $real = $this->realMethod();

        if ($real !== HttpMethod::POST) {
            return $real;
        }

        $override = $this->request['_method'] ?? $this->query['_method'] ?? $this->header('X-HTTP-Method-Override');

        if (is_string($override)) {
            $spoofed = HttpMethod::tryFromString($override);

            if ($spoofed !== null && in_array($spoofed, [HttpMethod::PUT, HttpMethod::PATCH, HttpMethod::DELETE], true)) {
                return $spoofed;
            }
        }

        return $real;
    }

    public function isMethod(string|HttpMethod $method): bool
    {
        $method = $method instanceof HttpMethod ? $method : HttpMethod::tryFromString($method);

        return $method !== null && $this->method() === $method;
    }

    // -------------------------------------------------------------------------
    // URL
    // -------------------------------------------------------------------------

    /** Caminho normalizado: começa por `/`, sem trailing slash (excepto a raiz) e sem query string. */
    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        // Sem parse_url: '//a/b' seria interpretado como host 'a'.
        $path = explode('#', explode('?', is_string($uri) ? $uri : '/', 2)[0], 2)[0];

        $path = '/'.trim((string) preg_replace('#/{2,}#', '/', $path), '/');

        return $path;
    }

    /** O URI pedido, com query string (/posts?page=2). */
    public function uri(): string
    {
        $query = $this->queryString();

        return $this->path().($query !== '' ? '?'.$query : '');
    }

    public function queryString(): string
    {
        $query = $this->server['QUERY_STRING'] ?? null;

        if (is_string($query)) {
            return $query;
        }

        $uri = $this->server['REQUEST_URI'] ?? '';

        return is_string($uri) && str_contains($uri, '?') ? explode('#', explode('?', $uri, 2)[1], 2)[0] : '';
    }

    /** URL completo, com query string. */
    public function fullUrl(): string
    {
        return $this->root().$this->uri();
    }

    /** URL completo, sem query string. */
    public function url(): string
    {
        return $this->root().($this->path() === '/' ? '' : $this->path());
    }

    /** scheme://host[:port] */
    public function root(): string
    {
        return $this->scheme().'://'.$this->host();
    }

    public function scheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    public function isSecure(): bool
    {
        $https = $this->server['HTTPS'] ?? null;

        if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
            return true;
        }

        return (int) ($this->server['SERVER_PORT'] ?? 80) === 443;
    }

    /** Host com porta não-standard (localhost:8000). */
    public function host(): string
    {
        $host = $this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? 'localhost';

        return strtolower(is_string($host) ? $host : 'localhost');
    }

    public function ip(): ?string
    {
        $ip = $this->server['REMOTE_ADDR'] ?? null;

        return is_string($ip) ? $ip : null;
    }

    public function userAgent(): ?string
    {
        return $this->header('User-Agent');
    }

    // -------------------------------------------------------------------------
    // Input
    // -------------------------------------------------------------------------

    public function query(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->query : Arr::get($this->query, $key, $default);
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->request : Arr::get($this->request, $key, $default);
    }

    /** Query + corpo JSON + corpo de formulário; o corpo vence a query. */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        $input = array_replace($this->query, $this->isJson() ? $this->jsonArray() : [], $this->request);

        return $key === null ? $input : Arr::get($input, $key, $default);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        /** @var array<string, mixed> $input */
        $input = $this->input();

        return array_replace($input, $this->allFiles());
    }

    /**
     * @param  string|list<string>  ...$keys
     * @return array<string, mixed>
     */
    public function only(string|array ...$keys): array
    {
        return Arr::only($this->all(), $this->flattenKeys($keys));
    }

    /**
     * @param  string|list<string>  ...$keys
     * @return array<string, mixed>
     */
    public function except(string|array ...$keys): array
    {
        return Arr::except($this->all(), $this->flattenKeys($keys));
    }

    /**
     * @param  string|list<string>  $keys
     */
    public function has(string|array $keys): bool
    {
        $input = $this->all();

        foreach ((array) $keys as $key) {
            if (! Arr::has($input, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Existe e não está em branco.
     *
     * @param  string|list<string>  $keys
     */
    public function filled(string|array $keys): bool
    {
        foreach ((array) $keys as $key) {
            $value = $this->input($key);

            if (is_scalar($value) || $value === null ? blank($value) : false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Equivale a `! has()`.
     *
     * @param  string|list<string>  $keys
     */
    public function missing(string|array $keys): bool
    {
        return ! $this->has($keys);
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->input($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key);

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Aplica um callback a todos os valores de query, corpo e JSON (usado pelo TrimStrings).
     *
     * @param  Closure(string, mixed): mixed  $callback  recebe (chave, valor) de cada folha
     */
    public function transformInput(Closure $callback): void
    {
        $this->query = $this->mapLeaves($this->query, $callback);
        $this->request = $this->mapLeaves($this->request, $callback);

        if ($this->isJson()) {
            $this->jsonData = $this->mapLeaves($this->jsonArray(), $callback);
        }
    }

    /**
     * Junta valores ao input (corpo), por exemplo para o middleware normalizar dados.
     *
     * @param  array<string, mixed>  $input
     */
    public function merge(array $input): static
    {
        $this->request = array_replace($this->request, $input);

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  Closure(string, mixed): mixed  $callback
     * @return array<array-key, mixed>
     */
    private function mapLeaves(array $data, Closure $callback, string $prefix = ''): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            $result[$key] = is_array($value)
                ? $this->mapLeaves($value, $callback, $path)
                : $callback($path, $value);
        }

        return $result;
    }

    /**
     * @param  array<array-key, string|list<string>>  $keys
     * @return list<string>
     */
    private function flattenKeys(array $keys): array
    {
        $flat = [];

        array_walk_recursive($keys, static function (mixed $key) use (&$flat): void {
            $flat[] = (string) $key;
        });

        return $flat;
    }

    // -------------------------------------------------------------------------
    // JSON
    // -------------------------------------------------------------------------

    public function getContent(): string
    {
        if ($this->content === null) {
            $content = file_get_contents('php://input');
            $this->content = $content === false ? '' : $content;
        }

        return $this->content;
    }

    /** Corpo JSON decodificado (lazy). */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        $data = $this->jsonArray();

        return $key === null ? $data : Arr::get($data, $key, $default);
    }

    /** @return array<string, mixed> */
    private function jsonArray(): array
    {
        if ($this->jsonData !== null) {
            return $this->jsonData;
        }

        $content = $this->getContent();

        if ($content === '') {
            return $this->jsonData = [];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->jsonData = [];
        }

        /** @var array<string, mixed> $data */
        $data = is_array($decoded) ? $decoded : [];

        return $this->jsonData = $data;
    }

    public function isJson(): bool
    {
        return Str::contains((string) $this->header('Content-Type'), ['/json', '+json'], true);
    }

    public function wantsJson(): bool
    {
        return Str::contains((string) $this->header('Accept'), ['/json', '+json'], true);
    }

    public function ajax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest';
    }

    /** Accept JSON, pedido XHR ou rota sob /api. */
    public function expectsJson(): bool
    {
        return $this->wantsJson() || $this->ajax() || $this->path() === '/api' || str_starts_with($this->path(), '/api/');
    }

    public function isPrefetch(): bool
    {
        return strcasecmp((string) $this->header('X-Moz'), 'prefetch') === 0
            || strcasecmp((string) $this->header('Purpose'), 'prefetch') === 0
            || strcasecmp((string) $this->header('Sec-Purpose'), 'prefetch') === 0;
    }

    // -------------------------------------------------------------------------
    // Ficheiros
    // -------------------------------------------------------------------------

    public function file(string $key): ?UploadedFile
    {
        $file = Arr::get($this->allFiles(), $key);

        return $file instanceof UploadedFile ? $file : null;
    }

    public function hasFile(string $key): bool
    {
        $file = $this->file($key);

        return $file !== null && $file->isValid();
    }

    /** @return array<string, mixed> */
    public function allFiles(): array
    {
        return $this->fileBag ??= $this->convertFiles($this->files);
    }

    /**
     * @param  array<array-key, mixed>  $files
     * @return array<array-key, mixed>
     */
    private function convertFiles(array $files): array
    {
        $result = [];

        foreach ($files as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if (isset($value['tmp_name'], $value['name']) && ! is_array($value['name'])) {
                if ($value['error'] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $result[$key] = new UploadedFile(
                    (string) $value['tmp_name'],
                    (string) $value['name'],
                    isset($value['type']) ? (string) $value['type'] : null,
                    isset($value['size']) ? (int) $value['size'] : null,
                    (int) ($value['error'] ?? UPLOAD_ERR_OK),
                    (bool) ($value['test'] ?? false),
                );

                continue;
            }

            if (isset($value['tmp_name']) && is_array($value['tmp_name'])) {
                $result[$key] = $this->convertFiles($this->transposeFiles($value));

                continue;
            }

            $result[$key] = $this->convertFiles($value);
        }

        return $result;
    }

    /**
     * Converte o formato de $_FILES para múltiplos ficheiros (name[] => lista de ficheiros).
     *
     * @param  array<string, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function transposeFiles(array $value): array
    {
        $out = [];

        foreach (array_keys((array) $value['tmp_name']) as $index) {
            $entry = [];

            foreach (['name', 'type', 'tmp_name', 'error', 'size', 'test'] as $field) {
                if (isset($value[$field]) && is_array($value[$field]) && array_key_exists($index, $value[$field])) {
                    $entry[$field] = $value[$field][$index];
                }
            }

            $out[$index] = $entry;
        }

        return $out;
    }

    // -------------------------------------------------------------------------
    // Headers e cookies
    // -------------------------------------------------------------------------

    public function header(string $key, ?string $default = null): ?string
    {
        return $this->headers()[$this->normalizeHeader($key)] ?? $default;
    }

    /** @return array<string, string> nomes em minúsculas com hífens (content-type) */
    public function headers(): array
    {
        if ($this->headerBag !== null) {
            return $this->headerBag;
        }

        $headers = [];

        foreach ($this->server as $key => $value) {
            if (! is_scalar($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $headers[$this->normalizeHeader(substr($key, 5))] = (string) $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $headers[$this->normalizeHeader($key)] = (string) $value;
            }
        }

        return $this->headerBag = $headers;
    }

    private function normalizeHeader(string $name): string
    {
        return str_replace('_', '-', strtolower($name));
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization', '');

        if ($header !== null && preg_match('/^Bearer\s+(.+)$/i', $header, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    public function cookie(?string $key = null, ?string $default = null): mixed
    {
        return $key === null ? $this->cookies : ($this->cookies[$key] ?? $default);
    }

    /** @return array<string, string> */
    public function cookies(): array
    {
        return $this->cookies;
    }

    public function server(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->server : ($this->server[$key] ?? $default);
    }

    // -------------------------------------------------------------------------
    // Rota
    // -------------------------------------------------------------------------

    /** Sem argumentos devolve a Route que fez match; com argumento, o valor do parâmetro. */
    public function route(?string $param = null, mixed $default = null): mixed
    {
        if ($param === null) {
            return $this->route;
        }

        return $this->route?->parameter($param, $default) ?? $default;
    }

    public function setRoute(Route $route): static
    {
        $this->route = $route;

        return $this;
    }

    /** @return array<string, mixed> */
    public function routeParameters(): array
    {
        return $this->route?->parameters() ?? [];
    }

    // -------------------------------------------------------------------------
    // Sessão, utilizador, atributos
    // -------------------------------------------------------------------------

    public function session(): Session
    {
        return $this->session ?? throw new RuntimeException('A sessão não foi iniciada: usa o grupo de middleware "web" (StartSession).');
    }

    public function setSession(Session $session): static
    {
        $this->session = $session;

        return $this;
    }

    public function hasSession(): bool
    {
        return $this->session !== null;
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    public function validate(array $rules): never
    {
        // TODO(core-04-08): validação.
        throw new RuntimeException('Request::validate() fica disponível na peça core-04-08.');
    }

    public function setUserResolver(Closure $resolver): static
    {
        $this->userResolver = $resolver;

        return $this;
    }

    /** Utilizador autenticado, se algum package (auth) registou um resolver. */
    public function user(): mixed
    {
        return $this->userResolver === null ? null : ($this->userResolver)($this);
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->attributes, $key, $default);
    }

    public function setAttribute(string $key, mixed $value): static
    {
        Arr::set($this->attributes, $key, $value);

        return $this;
    }
}
