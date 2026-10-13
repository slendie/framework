<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Testing;

use Closure;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;

/** Verifica que uma asserção falha (e com que mensagem): os testes das asserções precisam do caso negativo. */
final class Fails
{
    public static function with(Closure $callback, string ...$messageContains): void
    {
        try {
            $callback();
        } catch (AssertionFailedError $e) {
            foreach ($messageContains as $needle) {
                if (! str_contains($e->getMessage(), $needle)) {
                    throw new RuntimeException("A mensagem de falha não contém [{$needle}]:\n".$e->getMessage());
                }
            }

            return;
        }

        throw new RuntimeException('A asserção devia ter falhado, mas passou.');
    }
}
