<?php

declare(strict_types=1);

namespace Slendie\Foundation\Bootstrap;

use ErrorException;
use Slendie\Foundation\Application;
use Slendie\Foundation\Exceptions\Handler;
use Slendie\Http\Request;
use Throwable;

/**
 * Converte erros PHP em excepções e delega excepções não apanhadas para o Handler.
 */
final class HandleExceptions
{
    private static ?Application $app = null;

    public function bootstrap(Application $app): void
    {
        self::$app = $app;

        error_reporting(E_ALL);

        set_error_handler($this->handleError(...));
        set_exception_handler($this->handleException(...));
        register_shutdown_function($this->handleShutdown(...));

        if (! $app->runningUnitTests()) {
            ini_set('display_errors', 'Off');
        }

        $app->singletonIf(Handler::class);
    }

    /**
     * @throws ErrorException
     */
    public function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        if ((error_reporting() & $level) === 0) {
            return false;
        }

        if (in_array($level, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
            // Deprecações não interrompem; ficam para o log quando existir (peça 09).
            return true;
        }

        throw new ErrorException($message, 0, $level, $file, $line);
    }

    public function handleException(Throwable $e): void
    {
        $app = self::$app;

        if ($app === null) {
            echo $e;

            return;
        }

        try {
            /** @var Handler $handler */
            $handler = $app->make(Handler::class);
            $handler->report($e);

            if ($app->runningInConsole()) {
                $handler->renderForConsole($e);
            } else {
                $request = $app->bound('request') ? $app->make('request') : Request::capture();

                $handler->render($request instanceof Request ? $request : Request::capture(), $e)->send();
            }
        } catch (Throwable $inner) {
            // Último recurso: nem o Handler conseguiu responder.
            echo $inner;
        }
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error === null || ! in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
            return;
        }

        $this->handleException(new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
    }

    public static function flush(): void
    {
        self::$app = null;
        restore_error_handler();
        restore_exception_handler();
    }
}
