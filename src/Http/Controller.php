<?php

declare(strict_types=1);

namespace Slendie\Http;

use Slendie\Routing\Redirector;

/**
 * Base opcional dos controllers. Declara middleware próprio e dá atalhos para respostas.
 */
abstract class Controller
{
    /** @var list<string> */
    protected array $middleware = [];

    /**
     * Declara middleware deste controller (nome, alias ou classe, com parâmetros `alias:a,b`).
     * Chamar no construtor: `$this->middleware('auth');`
     *
     * @param  string|list<string>  $middleware
     */
    public function middleware(string|array $middleware): static
    {
        foreach ((array) $middleware as $item) {
            if (! in_array($item, $this->middleware, true)) {
                $this->middleware[] = $item;
            }
        }

        return $this;
    }

    /** @return list<string> */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function view(string $view, array $data = [], int $status = 200): Response
    {
        return response()->view($view, $data, $status);
    }

    protected function redirect(?string $to = null, int $status = 302): Redirector|RedirectResponse
    {
        return redirect($to, $status);
    }

    protected function json(mixed $data = [], int $status = 200): JsonResponse
    {
        return response()->json($data, $status);
    }

    protected function response(string $content = '', int $status = 200): Response
    {
        return response($content, $status);
    }
}
