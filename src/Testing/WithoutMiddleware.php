<?php

declare(strict_types=1);

namespace Slendie\Testing;

/**
 * Desliga todo o middleware em todos os testes da classe (incluindo a sessão: use com consciência).
 */
trait WithoutMiddleware
{
    protected function disableMiddlewareForAllTests(): void
    {
        $this->withoutMiddleware();
    }
}
