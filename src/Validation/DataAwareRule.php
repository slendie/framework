<?php

declare(strict_types=1);

namespace Slendie\Validation;

/**
 * Regra que precisa de todos os dados em validação (recebe-os antes de validate()).
 */
interface DataAwareRule
{
    /**
     * @param  array<array-key, mixed>  $data
     */
    public function setData(array $data): static;
}
