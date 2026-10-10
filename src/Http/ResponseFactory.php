<?php

declare(strict_types=1);

namespace Slendie\Http;

use LogicException;
use RuntimeException;
use Slendie\Contracts\ViewFactory;
use Slendie\Foundation\Application;
use Slendie\Routing\Redirector;

/**
 * Fábrica de respostas: `response()->json(...)`, `response()->download(...)`.
 */
class ResponseFactory
{
    public function __construct(protected Application $app, protected Redirector $redirector)
    {
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function make(string $content = '', int $status = 200, array $headers = []): Response
    {
        return new Response($content, $status, $headers);
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function noContent(int $status = 204, array $headers = []): Response
    {
        return new Response('', $status, $headers);
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function json(mixed $data = [], int $status = 200, array $headers = [], int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): JsonResponse
    {
        return new JsonResponse($data, $status, $headers, $flags);
    }

    /**
     * Renderiza uma view (motor da peça core-04-03) numa resposta.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public function view(string $view, array $data = [], int $status = 200, array $headers = []): ViewResponse
    {
        if (! $this->app->bound('view')) {
            throw new LogicException('response()->view() precisa do motor de views (peça core-04-03).');
        }

        $factory = $this->app->make('view');

        if (! $factory instanceof ViewFactory) {
            throw new LogicException('O serviço [view] tem de implementar Slendie\Contracts\ViewFactory.');
        }

        return new ViewResponse($factory->make($view, $data), $status, $headers);
    }

    public function redirect(): Redirector
    {
        return $this->redirector;
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function download(string $file, ?string $name = null, array $headers = [], string $disposition = 'attachment'): Response
    {
        if (! is_file($file) || ! is_readable($file)) {
            throw new RuntimeException("O ficheiro [{$file}] não existe ou não é legível.");
        }

        $name ??= basename($file);
        $fallback = (string) preg_replace('/[^\x20-\x7e]|[%"\\\\]/', '_', $name);

        $contents = file_get_contents($file);

        $response = new Response($contents === false ? '' : $contents, 200, $headers);
        $response->header('Content-Type', $response->getHeader('Content-Type') ?? (function_exists('mime_content_type') ? (mime_content_type($file) ?: 'application/octet-stream') : 'application/octet-stream'));
        $response->header('Content-Length', (string) filesize($file));
        $response->header('Content-Disposition', sprintf('%s; filename="%s"; filename*=UTF-8\'\'%s', $disposition, $fallback, rawurlencode($name)));

        return $response;
    }
}
