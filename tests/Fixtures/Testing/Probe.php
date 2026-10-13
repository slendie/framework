<?php

declare(strict_types=1);

namespace Slendie\Tests\Fixtures\Testing;

/**
 * Estado que atravessa testes: os callbacks de fim de teste engolem excepções, por isso deixam aqui o que viram
 * e o teste seguinte (ordem garantida dentro do ficheiro) verifica.
 */
final class Probe
{
    /** @var array<string, mixed> */
    public static array $seen = [];
}
