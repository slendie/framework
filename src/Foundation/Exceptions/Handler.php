<?php

declare(strict_types=1);

namespace Slendie\Foundation\Exceptions;

use Slendie\Foundation\Application;
use Throwable;

/**
 * Handler mínimo da peça 01. A peça 02 acrescenta render(Request, Throwable): Response,
 * página de debug completa, páginas 404/500 e JSON; a peça 09 liga o report() ao Logger.
 */
class Handler
{
    /** @var list<class-string<Throwable>> */
    protected array $dontReport = [];

    public function __construct(protected Application $app)
    {
    }

    public function report(Throwable $e): void
    {
        foreach ($this->dontReport as $type) {
            if ($e instanceof $type) {
                return;
            }
        }

        // TODO(core-04-09): Log::error($e->getMessage(), ['exception' => $e]).
        $logFile = $this->app->storagePath('logs/slendie.log');
        $dir = dirname($logFile);

        if (is_dir($dir) && is_writable($dir)) {
            $line = sprintf(
                "[%s] %s.ERROR: %s in %s:%d\n%s\n\n",
                date('Y-m-d H:i:s'),
                $this->app->environment(),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString(),
            );
            @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * @param  list<class-string<Throwable>>  $types
     */
    public function dontReport(array $types): static
    {
        $this->dontReport = [...$this->dontReport, ...$types];

        return $this;
    }

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
     * Resposta HTML mínima quando ainda não há Response (peça 02 substitui).
     */
    public function renderRaw(Throwable $e): void
    {
        if (! headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        if ($this->app->isDebug()) {
            printf(
                '<!doctype html><meta charset="utf-8"><title>Erro</title><body style="font:14px/1.5 system-ui;padding:2rem"><h1>%s</h1><p><strong>%s</strong></p><p>%s:%d</p><pre>%s</pre></body>',
                htmlspecialchars($e::class, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8'),
                $e->getLine(),
                htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8'),
            );

            return;
        }

        echo '<!doctype html><meta charset="utf-8"><title>Erro</title><body style="font:16px system-ui;text-align:center;padding:4rem"><h1>500</h1><p>Ocorreu um erro. Tente novamente mais tarde.</p></body>';
    }
}
