<?php

declare(strict_types=1);

namespace Slendie\Validation;

/**
 * Regra que precisa do Validator (mensagens traduzidas, base de dados) — recebe-o antes de validate().
 */
interface ValidatorAwareRule
{
    public function setValidator(Validator $validator): static;
}
