<?php

declare(strict_types=1);

namespace Slendie\Contracts;

/**
 * Algo que se transforma em HTML (a View da peça 03 implementa-o).
 */
interface Renderable
{
    public function render(): string;
}
