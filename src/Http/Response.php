<?php

declare(strict_types=1);

namespace Slendie\Http;

use InvalidArgumentException;
use Stringable;

/**
 * Resposta HTTP. Nada sai para o cliente excepto através de send().
 */
class Response implements Stringable
{
    /** @var array<int, string> */
    public const array STATUS_TEXTS = [
        200 => 'OK', 201 => 'Created', 202 => 'Accepted', 204 => 'No Content',
        301 => 'Moved Permanently', 302 => 'Found', 303 => 'See Other', 304 => 'Not Modified',
        307 => 'Temporary Redirect', 308 => 'Permanent Redirect',
        400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden', 404 => 'Not Found',
        405 => 'Method Not Allowed', 409 => 'Conflict', 410 => 'Gone', 419 => 'Page Expired',
        422 => 'Unprocessable Content', 429 => 'Too Many Requests',
        500 => 'Internal Server Error', 502 => 'Bad Gateway', 503 => 'Service Unavailable',
    ];

    /** @var array<string, array{0: string, 1: string}> nome em minúsculas => [nome original, valor] */
    protected array $headers = [];

    /** @var list<Cookie> */
    protected array $cookies = [];

    protected string $content = '';

    protected int $statusCode = 200;

    protected bool $isHead = false;

    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(string $content = '', int $status = 200, array $headers = [])
    {
        $this->setContent($content);
        $this->setStatusCode($status);
        $this->withHeaders($headers);
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setStatusCode(int $code): static
    {
        if ($code < 100 || $code > 599) {
            throw new InvalidArgumentException("Código de estado HTTP inválido: {$code}.");
        }

        $this->statusCode = $code;

        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function header(string $name, string $value, bool $replace = true): static
    {
        $key = strtolower($name);

        if (! $replace && isset($this->headers[$key])) {
            return $this;
        }

        $this->headers[$key] = [$name, $value];

        return $this;
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): static
    {
        foreach ($headers as $name => $value) {
            $this->header($name, $value);
        }

        return $this;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)][1] ?? $default;
    }

    public function removeHeader(string $name): static
    {
        unset($this->headers[strtolower($name)]);

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        $headers = [];

        foreach ($this->headers as [$name, $value]) {
            $headers[$name] = $value;
        }

        return $headers;
    }

    /**
     * Acrescenta uma cookie; aceita um objecto Cookie ou os seus parâmetros.
     */
    public function cookie(Cookie|string $name, string $value = '', int $minutes = 0, string $path = '/', ?string $domain = null, bool $secure = false, bool $httpOnly = true, string $sameSite = 'lax'): static
    {
        $cookie = $name instanceof Cookie
            ? $name
            : Cookie::minutes($name, $value, $minutes, $path, $domain, $secure, $httpOnly, $sameSite);

        $this->cookies = [
            ...array_filter($this->cookies, static fn (Cookie $c): bool => $c->name !== $cookie->name || $c->path !== $cookie->path),
            $cookie,
        ];

        return $this;
    }

    /**
     * @return list<Cookie>
     */
    public function cookies(): array
    {
        return $this->cookies;
    }

    /**
     * Ajusta a resposta ao pedido (HEAD não leva corpo).
     */
    public function prepare(Request $request): static
    {
        $this->isHead = $request->realMethod() === HttpMethod::HEAD;

        return $this;
    }

    public function isOk(): bool
    {
        return $this->statusCode === 200;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isRedirect(?string $location = null): bool
    {
        return in_array($this->statusCode, [201, 301, 302, 303, 307, 308], true)
            && ($location === null || $location === $this->getHeader('Location'));
    }

    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    public function isServerError(): bool
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    public function isEmpty(): bool
    {
        return in_array($this->statusCode, [204, 304], true);
    }

    /**
     * Envia estado, headers, cookies e corpo. Único sítio da framework que escreve para o cliente.
     */
    public function send(): void
    {
        $this->sendHeaders();
        $this->sendContent();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    public function sendHeaders(): static
    {
        if (headers_sent()) {
            return $this;
        }

        foreach ($this->headers as [$name, $value]) {
            header($name.': '.$value, true);
        }

        foreach ($this->cookies as $cookie) {
            header('Set-Cookie: '.$cookie->toHeaderString(), false);
        }

        $text = self::STATUS_TEXTS[$this->statusCode] ?? '';
        header(trim(sprintf('HTTP/1.1 %d %s', $this->statusCode, $text)), true, $this->statusCode);

        return $this;
    }

    public function sendContent(): static
    {
        if ($this->isHead || $this->isEmpty() || ($this->statusCode >= 100 && $this->statusCode < 200)) {
            return $this;
        }

        echo $this->getContent();

        return $this;
    }

    public function __toString(): string
    {
        $text = self::STATUS_TEXTS[$this->statusCode] ?? '';
        $out = trim(sprintf('HTTP/1.1 %d %s', $this->statusCode, $text))."\r\n";

        foreach ($this->headers as [$name, $value]) {
            $out .= $name.': '.$value."\r\n";
        }

        foreach ($this->cookies as $cookie) {
            $out .= 'Set-Cookie: '.$cookie->toHeaderString()."\r\n";
        }

        return $out."\r\n".$this->getContent();
    }
}
