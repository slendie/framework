<?php

declare(strict_types=1);

namespace Slendie\Contracts;

/**
 * Objectos que o router sabe resolver a partir de um parâmetro de rota (route-model binding).
 */
interface UrlRoutable
{
    /**
     * Devolve a instância correspondente ao valor da rota, ou null se não existir (→ 404).
     */
    public function resolveRouteBinding(mixed $value, ?string $field = null): ?static;
}
