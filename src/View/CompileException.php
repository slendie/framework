<?php

declare(strict_types=1);

namespace Slendie\View;

use Slendie\Exceptions\SlendieException;

/**
 * Erro ao compilar um template Blade (directiva desconhecida, expressão inválida...).
 */
class CompileException extends SlendieException
{
    public function __construct(string $message, protected ?string $viewPath = null, protected ?int $viewLine = null)
    {
        parent::__construct($message);
    }

    public function getViewPath(): ?string
    {
        return $this->viewPath;
    }

    public function getViewLine(): ?int
    {
        return $this->viewLine;
    }
}
