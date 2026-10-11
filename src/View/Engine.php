<?php

declare(strict_types=1);

namespace Slendie\View;

use Slendie\Exceptions\HttpException;
use Slendie\View\Compiler\BladeCompiler;
use Throwable;

/**
 * Executa o PHP compilado de uma view e devolve o HTML. Compila primeiro, se a cache estiver desactualizada.
 */
class Engine
{
    public function __construct(protected BladeCompiler $compiler)
    {
    }

    public function getCompiler(): BladeCompiler
    {
        return $this->compiler;
    }

    /**
     * @param  string  $path  ficheiro `.blade.php` de origem
     * @param  array<string, mixed>  $data
     */
    public function get(string $path, array $data, Factory $env): string
    {
        if ($this->compiler->isExpired($path)) {
            $this->compiler->compile($path);
        }

        return $this->evaluate($this->compiler->getCompiledPath($path), $path, $data, $env);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function evaluate(string $compiled, string $path, array $data, Factory $env): string
    {
        $level = ob_get_level();

        ob_start();

        try {
            (static function (string $__path, array $__data, Factory $__env): void {
                extract($__data, EXTR_SKIP);

                include $__path;
            })($compiled, $data, $env);
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $this->handleException($e, $compiled, $path);
        }

        if (ob_get_level() > $level + 1) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw new ViewException('Há uma secção ou stack por fechar (falta @endsection, @endpush ou @endprepend?)', $path);
        }

        return (string) ob_get_clean();
    }

    /**
     * O compilador preserva as linhas do template, por isso a linha do erro no PHP compilado é a do `.blade.php`.
     */
    protected function handleException(Throwable $e, string $compiled, string $path): Throwable
    {
        if ($e instanceof ViewException || $e instanceof HttpException) {
            return $e;
        }

        $line = null;
        $target = $this->normalize($compiled);

        if ($this->normalize($e->getFile()) === $target) {
            $line = $e->getLine();
        } else {
            foreach ($e->getTrace() as $frame) {
                if (isset($frame['file'], $frame['line']) && $this->normalize($frame['file']) === $target) {
                    $line = $frame['line'];

                    break;
                }
            }
        }

        return ViewException::wrap($e, $path, $line);
    }

    protected function normalize(string $path): string
    {
        return str_replace('\\', '/', realpath($path) ?: $path);
    }
}
