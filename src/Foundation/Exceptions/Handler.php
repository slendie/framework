<?php

declare(strict_types=1);

namespace Slendie\Foundation\Exceptions;

use Slendie\Exceptions\HttpException;
use Slendie\Foundation\Application;
use Slendie\Http\JsonResponse;
use Slendie\Http\Request;
use Slendie\Http\Response;
use Slendie\Support\Env;
use Throwable;

/**
 * Regista (report) e converte em Response (render) todas as excepções não apanhadas.
 */
class Handler
{
    /**
     * Excepções que não vão para o log.
     * TODO(core-04-08): acrescentar ValidationException.
     *
     * @var list<class-string<Throwable>>
     */
    protected array $dontReport = [
        HttpException::class,
    ];

    /** @var list<array{0: class-string<Throwable>, 1: \Closure}> */
    protected array $renderers = [];

    public function __construct(protected Application $app)
    {
    }

    // -------------------------------------------------------------------------
    // Configuração (withExceptions)
    // -------------------------------------------------------------------------

    /**
     * @param  list<class-string<Throwable>>  $types
     */
    public function dontReport(array $types): static
    {
        $this->dontReport = [...$this->dontReport, ...$types];

        return $this;
    }

    /**
     * Regista um renderer próprio para um tipo de excepção: fn (Throwable $e, Request $r): ?Response.
     *
     * @param  class-string<Throwable>  $type
     */
    public function renderable(string $type, \Closure $callback): static
    {
        $this->renderers[] = [$type, $callback];

        return $this;
    }

    // -------------------------------------------------------------------------
    // Report
    // -------------------------------------------------------------------------

    public function shouldReport(Throwable $e): bool
    {
        foreach ($this->dontReport as $type) {
            if ($e instanceof $type) {
                return false;
            }
        }

        return true;
    }

    public function report(Throwable $e): void
    {
        if (! $this->shouldReport($e)) {
            return;
        }

        // TODO(core-04-09): delegar no Logger (canais, daily).
        $logFile = $this->app->storagePath('logs/slendie.log');
        $dir = dirname($logFile);

        if (! is_dir($dir) || ! is_writable($dir)) {
            return;
        }

        $context = json_encode([
            'exception' => $e::class,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';

        $entry = sprintf(
            "[%s] %s.ERROR: %s %s\n%s\n",
            date('Y-m-d H:i:s'),
            $this->app->environment(),
            $e->getMessage(),
            $context,
            $e->getTraceAsString(),
        );

        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    public function render(Request $request, Throwable $e): Response
    {
        foreach ($this->renderers as [$type, $callback]) {
            if ($e instanceof $type) {
                $response = $callback($e, $request);

                if ($response instanceof Response) {
                    return $response;
                }
            }
        }

        if ($e instanceof HttpException) {
            return $this->renderHttpException($request, $e);
        }

        return $this->app->isDebug()
            ? $this->renderDebug($request, $e)
            : $this->renderStatus($request, 500, 'Server Error');
    }

    protected function renderHttpException(Request $request, HttpException $e): Response
    {
        $status = $e->getStatusCode();
        $message = $e->getMessage() !== '' ? $e->getMessage() : (Response::STATUS_TEXTS[$status] ?? 'Error');

        return $this->renderStatus($request, $status, $message, $e->getHeaders());
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function renderStatus(Request $request, int $status, string $message, array $headers = []): Response
    {
        if ($request->expectsJson()) {
            return new JsonResponse(['message' => $message], $status, $headers);
        }

        $template = $this->findErrorTemplate((string) $status) ?? $this->findErrorTemplate('generic');

        $html = $template === null
            ? '<!doctype html><meta charset="utf-8"><title>'.$status.'</title><h1>'.$status.'</h1><p>'.htmlspecialchars($message, ENT_QUOTES, 'UTF-8').'</p>'
            : $this->renderTemplate($template, [
                'status' => $status,
                'title' => Response::STATUS_TEXTS[$status] ?? 'Error',
                'message' => $message,
            ]);

        return new Response($html, $status, ['Content-Type' => 'text/html; charset=UTF-8', ...$headers]);
    }

    protected function renderDebug(Request $request, Throwable $e): Response
    {
        if ($request->expectsJson()) {
            return new JsonResponse([
                'message' => $e->getMessage(),
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $this->traceFrames($e),
            ], 500);
        }

        $template = $this->findErrorTemplate('debug');

        if ($template === null) {
            return $this->renderStatus($request, 500, 'Server Error');
        }

        $html = $this->renderTemplate($template, [
            'class' => $e::class,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'snippet' => $this->codeSnippet($e->getFile(), $e->getLine()),
            'trace' => $this->traceFrames($e),
            'request' => $this->describeRequest($request),
            'environment' => $this->sanitizedEnvironment(),
        ]);

        return new Response($html, 500, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /** Excepção na consola: texto simples com trace. */
    public function renderForConsole(Throwable $e): void
    {
        $out = fopen('php://stderr', 'w');

        if ($out === false) {
            return;
        }

        fwrite($out, sprintf("\n  %s\n\n  %s\n\n  em %s:%d\n", $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));

        if ($this->app->isDebug()) {
            fwrite($out, "\n".$e->getTraceAsString()."\n");
        }

        fclose($out);
    }

    /**
     * Último recurso quando nem sequer existe um Request/Response (falha antes do Kernel).
     */
    public function renderRaw(Throwable $e): void
    {
        if (! headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo $this->app->isDebug()
            ? sprintf(
                '<!doctype html><meta charset="utf-8"><title>Erro</title><body style="font:14px/1.5 system-ui;padding:2rem"><h1>%s</h1><p><strong>%s</strong></p><p>%s:%d</p><pre>%s</pre></body>',
                htmlspecialchars($e::class, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8'),
                $e->getLine(),
                htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8'),
            )
            : '<!doctype html><meta charset="utf-8"><title>Erro</title><body style="font:16px system-ui;text-align:center;padding:4rem"><h1>500</h1><p>Ocorreu um erro. Tente novamente mais tarde.</p></body>';
    }

    // -------------------------------------------------------------------------
    // Auxiliares
    // -------------------------------------------------------------------------

    /**
     * Procura errors/{name}.php: primeiro na aplicação (resources/views/errors), depois na framework.
     * TODO(core-04-03): passar a usar o motor de views (.blade.php).
     */
    protected function findErrorTemplate(string $name): ?string
    {
        $candidates = [
            $this->app->resourcePath('views/errors/'.$name.'.php'),
            dirname(__DIR__, 3).'/resources/views/errors/'.$name.'.php',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function renderTemplate(string $file, array $data): string
    {
        $render = static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);

            ob_start();

            try {
                include $__file;
            } catch (Throwable $e) {
                ob_end_clean();

                throw $e;
            }

            return (string) ob_get_clean();
        };

        return $render($file, $data);
    }

    /**
     * ±10 linhas à volta da linha com erro.
     *
     * @return array<int, string> número da linha => código
     */
    protected function codeSnippet(string $file, int $line): array
    {
        if (! is_file($file) || ! is_readable($file)) {
            return [];
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return [];
        }

        $start = max(1, $line - 10);
        $snippet = [];

        foreach (array_slice($lines, $start - 1, 21, true) as $index => $code) {
            $snippet[$index + 1] = $code;
        }

        return $snippet;
    }

    /**
     * @return list<array{file: string, line: int, call: string}>
     */
    protected function traceFrames(Throwable $e): array
    {
        $frames = [];

        foreach ($e->getTrace() as $frame) {
            $frames[] = [
                'file' => $frame['file'] ?? '[internal]',
                'line' => $frame['line'] ?? 0,
                'call' => ($frame['class'] ?? '').($frame['type'] ?? '').$frame['function'].'()',
            ];
        }

        return $frames;
    }

    /**
     * @return array<string, mixed>
     */
    protected function describeRequest(Request $request): array
    {
        $headers = $request->headers();

        foreach (['authorization', 'cookie', 'x-csrf-token', 'x-xsrf-token'] as $secret) {
            if (isset($headers[$secret])) {
                $headers[$secret] = '••••';
            }
        }

        return [
            'method' => $request->method()->value,
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'headers' => $headers,
            'input' => $this->maskArray($request->except(['_token'])),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    protected function maskArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->maskArray($value);
            } elseif (is_string($key) && $this->isSensitive($key)) {
                $data[$key] = '••••';
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function sanitizedEnvironment(): array
    {
        return $this->maskArray(Env::all());
    }

    protected function isSensitive(string $key): bool
    {
        return preg_match('/KEY|SECRET|PASSWORD|PASSWD|TOKEN|CREDENTIAL/i', $key) === 1;
    }
}
