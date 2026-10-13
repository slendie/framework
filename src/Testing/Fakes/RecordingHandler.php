<?php

declare(strict_types=1);

namespace Slendie\Testing\Fakes;

use Slendie\Foundation\Application;
use Slendie\Foundation\Exceptions\Handler;
use Slendie\Http\Request;
use Slendie\Http\Response;
use Throwable;

/**
 * Embrulha o Handler da aplicação e guarda a última excepção renderizada, para o TestResponse a mostrar
 * nas mensagens de falha (`Expected status 200 but received 500` + a excepção que a causou).
 */
final class RecordingHandler extends Handler
{
    public ?Throwable $lastException = null;

    public function __construct(Application $app, private readonly Handler $inner)
    {
        parent::__construct($app);
    }

    public function inner(): Handler
    {
        return $this->inner;
    }

    public function shouldReport(Throwable $e): bool
    {
        return $this->inner->shouldReport($e);
    }

    public function report(Throwable $e): void
    {
        $this->inner->report($e);
    }

    public function render(Request $request, Throwable $e): Response
    {
        $this->lastException = $e;

        return $this->inner->render($request, $e);
    }

    public function renderForConsole(Throwable $e): void
    {
        $this->inner->renderForConsole($e);
    }

    public function renderRaw(Throwable $e): void
    {
        $this->inner->renderRaw($e);
    }
}
