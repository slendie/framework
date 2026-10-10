<?php

declare(strict_types=1);

namespace Slendie\Contracts;

/**
 * Contrato do motor de views registado em 'view' (implementado na peça core-04-03).
 */
interface ViewFactory
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function make(string $view, array $data = []): Renderable;

    public function share(string $key, mixed $value = null): mixed;
}
