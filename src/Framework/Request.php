<?php

declare(strict_types=1);

namespace Slendie\Framework;

final class Request
{
    private string $method;
    private string $path;
    private array $query;
    private array $post;
    private array $files;
    private array $json;
    private array $headers;
    private array $input;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // Normaliza o path: garante barra inicial, remove barra final (exceto raiz)
        $this->path = $path === '/' ? '/' : mb_rtrim($path, '/');

        if ($this->path !== '/' && $this->path[0] !== '/') {
            $this->path = '/' . $this->path;
        }

        $this->query = $_GET ?? [];
        $this->json = json_decode(file_get_contents('php://input'), true) ?? [];

        $this->post = $_POST ?? [];
        $this->files = $_FILES ?? [];
        $this->headers = $this->getHeaders();
        $this->input = array_merge($this->query, $this->post);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function get(string|null $key = null, mixed $default = null)
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    public function post(string|null $key = null, mixed $default = null)
    {
        if ($key === null) {
            return $this->post;
        }
        return $this->post[$key] ?? $default;
    }

    public function input(string|null $key = null, mixed $default = null)
    {
        if ($key === null) {
            return $this->input;
        }
        return $this->input[$key] ?? $default;
    }

    public function file(string|null $key = null)
    {
        if ($key === null) {
            return $this->files;
        }
        return $this->files[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->input[$key]);
    }

    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]);
    }

    public function all(): array
    {
        return $this->input;
    }

    public function only(array|string $keys): array
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        $result = [];
        foreach ($keys as $key) {
            if (isset($this->input[$key])) {
                $result[$key] = $this->input[$key];
            }
        }
        return $result;
    }

    public function except(array|string $keys): array
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        $result = $this->input;
        foreach ($keys as $key) {
            unset($result[$key]);
        }
        return $result;
    }

    public function header(string|null $key = null, mixed $default = null)
    {
        if ($key === null) {
            return $this->headers;
        }
        $key = mb_strtolower($key);
        return $this->headers[$key] ?? $default;
    }

    public function isMethod(string $method): bool
    {
        return mb_strtoupper($this->method) === mb_strtoupper($method);
    }

    public function json(): array
    {
        return $this->json;
    }

    private function getHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (mb_strpos($key, 'HTTP_') === 0) {
                $header = str_replace('_', '-', mb_substr($key, 5));
                $headers[mb_strtolower($header)] = $value;
            }
        }
        return $headers;
    }

    /**
     * Método mágico para debug - retorna informações seguras quando o objeto é serializado
     * Isso previne erros quando o Pest tenta exibir informações sobre objetos Request durante erros
     */
    public function __debugInfo(): array
    {
        return [
            'method' => $this->method,
            'path' => $this->path,
            'query_count' => count($this->query),
            'post_count' => count($this->post),
            'files_count' => count($this->files),
            'headers_count' => count($this->headers),
        ];
    }

    /**
     * Método mágico para conversão para string - retorna string simples para evitar problemas
     * quando o Pest tenta exibir o objeto durante erros
     */
    public function __toString(): string
    {
        return sprintf('Request(%s %s)', $this->method, $this->path);
    }
}
