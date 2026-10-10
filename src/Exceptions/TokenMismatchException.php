<?php

declare(strict_types=1);

namespace Slendie\Exceptions;

use Throwable;

/**
 * Token CSRF em falta ou inválido (HTTP 419).
 */
class TokenMismatchException extends HttpException
{
    public function __construct(string $message = 'CSRF token mismatch.', ?Throwable $previous = null)
    {
        parent::__construct(419, $message, $previous);
    }
}
